<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Index Health Service
 * 
 * Monitors Redis index health and triggers rebuilds when needed.
 * Prevents filter failures by detecting missing indexes early.
 */
class IndexHealthService
{
    private const HEALTH_CHECK_CACHE_TTL = 300; // 5 minutes
    private const MIN_EXPECTED_INDEXES = 10; // Minimum expected index keys
    private const REBUILD_LOCK_TTL = 3600; // 1 hour rebuild lock
    
    /**
     * Check if Redis indexes are healthy and populated
     */
    public function isHealthy(): bool
    {
        return Cache::remember('index_health_status', self::HEALTH_CHECK_CACHE_TTL, function () {
            try {
                // Check 1: Redis connection
                if (!$this->isRedisAvailable()) {
                    Log::warning('Index Health: Redis not available');
                    return false;
                }
                
                // Check 2: Minimum number of index keys exist
                $indexCount = $this->getIndexKeyCount();
                if ($indexCount < self::MIN_EXPECTED_INDEXES) {
                    Log::warning("Index Health: Insufficient indexes", [
                        'found' => $indexCount,
                        'minimum' => self::MIN_EXPECTED_INDEXES
                    ]);
                    return false;
                }
                
                // Check 3: Critical indexes have data
                $criticalIndexes = $this->getCriticalIndexes();
                foreach ($criticalIndexes as $indexKey) {
                    if (!Redis::exists($indexKey) || Redis::scard($indexKey) === 0) {
                        Log::warning("Index Health: Critical index empty or missing", [
                            'index' => $indexKey
                        ]);
                        return false;
                    }
                }
                
                // Check 4: Index freshness (TTL check)
                if (!$this->areIndexesFresh()) {
                    Log::warning('Index Health: Indexes are stale');
                    return false;
                }
                
                return true;
                
            } catch (\Exception $e) {
                Log::error('Index Health Check Failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return false;
            }
        });
    }
    
    /**
     * Get detailed health report
     */
    public function getHealthReport(): array
    {
        $report = [
            'healthy' => false,
            'redis_available' => false,
            'total_indexes' => 0,
            'critical_indexes_status' => [],
            'missing_indexes' => [],
            'stale_indexes' => [],
            'rebuild_recommended' => false,
            'rebuild_in_progress' => false,
            'last_rebuild_time' => null,
            'estimated_rebuild_time' => '5-10 minutes'
        ];
        
        try {
            // Redis availability
            $report['redis_available'] = $this->isRedisAvailable();
            if (!$report['redis_available']) {
                $report['rebuild_recommended'] = true;
                return $report;
            }
            
            // Index counts
            $report['total_indexes'] = $this->getIndexKeyCount();
            
            // Check critical indexes
            $criticalIndexes = $this->getCriticalIndexes();
            foreach ($criticalIndexes as $indexKey) {
                $exists = Redis::exists($indexKey);
                $count = $exists ? Redis::scard($indexKey) : 0;
                
                $report['critical_indexes_status'][$indexKey] = [
                    'exists' => $exists,
                    'count' => $count,
                    'healthy' => $exists && $count > 0
                ];
                
                if (!$exists || $count === 0) {
                    $report['missing_indexes'][] = $indexKey;
                }
            }
            
            // Check for stale indexes
            $staleIndexes = $this->getStaleIndexes();
            $report['stale_indexes'] = $staleIndexes;
            
            // Determine if rebuild is needed
            $report['rebuild_recommended'] = 
                count($report['missing_indexes']) > 0 ||
                count($staleIndexes) > 0 ||
                $report['total_indexes'] < self::MIN_EXPECTED_INDEXES;
            
            // Check if rebuild is in progress
            $report['rebuild_in_progress'] = Cache::has('index_rebuild_in_progress');
            
            // Get last rebuild timestamp
            $report['last_rebuild_time'] = Cache::get('index_last_rebuild_time');
            
            // Overall health status
            $report['healthy'] = 
                $report['redis_available'] &&
                $report['total_indexes'] >= self::MIN_EXPECTED_INDEXES &&
                count($report['missing_indexes']) === 0 &&
                count($staleIndexes) === 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate health report', [
                'error' => $e->getMessage()
            ]);
            $report['error'] = $e->getMessage();
        }
        
        return $report;
    }
    
    /**
     * Trigger index rebuild if needed (non-blocking)
     */
    public function triggerRebuildIfNeeded(): bool
    {
        if (!$this->isHealthy() && !Cache::has('index_rebuild_in_progress')) {
            return $this->triggerBackgroundRebuild();
        }
        
        return false;
    }
    
    /**
     * Trigger background index rebuild
     */
    public function triggerBackgroundRebuild(): bool
    {
        // Prevent concurrent rebuilds
        if (Cache::has('index_rebuild_in_progress')) {
            Log::info('Index rebuild already in progress, skipping');
            return false;
        }
        
        try {
            // Set rebuild lock
            Cache::put('index_rebuild_in_progress', true, self::REBUILD_LOCK_TTL);
            
            Log::info('Triggering background index rebuild');
            
            // Dispatch job for async rebuild
            dispatch(new \App\Jobs\RebuildProductIndexesJob())
                ->onQueue('indexes');
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to trigger index rebuild', [
                'error' => $e->getMessage()
            ]);
            Cache::forget('index_rebuild_in_progress');
            return false;
        }
    }
    
    /**
     * Check if specific index exists and has data
     */
    public function indexExists(string $indexKey): bool
    {
        try {
            return Redis::exists($indexKey) && Redis::scard($indexKey) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Get missing indexes for a specific filter query
     */
    public function getMissingIndexesForFilters(array $filters): array
    {
        $missing = [];
        
        if (!empty($filters['category_id'])) {
            $key = "index:category:{$filters['category_id']}";
            if (!$this->indexExists($key)) {
                $missing[] = $key;
            }
        }
        
        if (!empty($filters['brands'])) {
            foreach ($filters['brands'] as $brandId) {
                $key = "index:brand:{$brandId}";
                if (!$this->indexExists($key)) {
                    $missing[] = $key;
                }
            }
        }
        
        if (!empty($filters['price_range'])) {
            $key = "index:price:{$filters['price_range']}";
            if (!$this->indexExists($key)) {
                $missing[] = $key;
            }
        }
        
        if (!empty($filters['min_rating'])) {
            $key = "index:rating:{$filters['min_rating']}";
            if (!$this->indexExists($key)) {
                $missing[] = $key;
            }
        }
        
        if (!empty($filters['min_discount'])) {
            $key = "index:discount:{$filters['min_discount']}";
            if (!$this->indexExists($key)) {
                $missing[] = $key;
            }
        }
        
        return $missing;
    }
    
    /**
     * Build specific missing indexes on-demand
     */
    public function buildMissingIndexes(array $indexKeys): void
    {
        $indexService = app(ProductIndexService::class);
        
        foreach ($indexKeys as $indexKey) {
            try {
                $this->buildSingleIndex($indexKey, $indexService);
            } catch (\Exception $e) {
                Log::error("Failed to build index: {$indexKey}", [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Build a single index key
     */
    private function buildSingleIndex(string $indexKey, ProductIndexService $indexService): void
    {
        $parts = explode(':', $indexKey);
        if (count($parts) < 3) {
            return;
        }
        
        $type = $parts[1]; // category, brand, price, etc.
        $value = $parts[2];
        
        Log::info("Building single index: {$indexKey}");
        
        switch ($type) {
            case 'category':
                $this->buildCategoryIndex((int)$value);
                break;
            case 'brand':
                $this->buildBrandIndex((int)$value);
                break;
            case 'price':
                $this->buildPriceRangeIndex($value);
                break;
            case 'rating':
                $this->buildRatingIndex((int)$value);
                break;
            case 'discount':
                $this->buildDiscountIndex((int)$value);
                break;
        }
    }
    
    /**
     * Build single category index
     */
    private function buildCategoryIndex(int $categoryId): void
    {
        $indexKey = "index:category:{$categoryId}";
        Redis::del($indexKey);
        
        DB::table('products')
            ->where('status', 'active')
            ->where(function($query) use ($categoryId) {
                $query->where('cat_id', $categoryId)
                      ->orWhere('child_cat_id', $categoryId);
            })
            ->select('id')
            ->orderBy('id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        Redis::expire($indexKey, 86400); // 24 hours
        Log::info("Built category index: {$indexKey}");
    }
    
    /**
     * Build single brand index
     */
    private function buildBrandIndex(int $brandId): void
    {
        $indexKey = "index:brand:{$brandId}";
        Redis::del($indexKey);
        
        DB::table('products')
            ->where('status', 'active')
            ->where('brand_id', $brandId)
            ->select('id')
            ->orderBy('id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        Redis::expire($indexKey, 86400);
        Log::info("Built brand index: {$indexKey}");
    }
    
    /**
     * Build single price range index
     */
    private function buildPriceRangeIndex(string $rangeKey): void
    {
        $indexKey = "index:price:{$rangeKey}";
        Redis::del($indexKey);
        
        $ranges = [
            '0-100' => [0, 100],
            '100-500' => [100, 500],
            '500-1000' => [500, 1000],
            '1000-5000' => [1000, 5000],
            '5000-10000' => [5000, 10000],
            '10000+' => [10000, 999999],
        ];
        
        if (!isset($ranges[$rangeKey])) {
            return;
        }
        
        $range = $ranges[$rangeKey];
        
        // Base products
        DB::table('products')
            ->where('status', 'active')
            ->where('has_variants', false)
            ->whereBetween('base_price', $range)
            ->select('id')
            ->orderBy('id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        // Variant products
        DB::table('product_variants')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.status', 'active')
            ->where('products.has_variants', true)
            ->where('product_variants.status', 'active')
            ->whereBetween('product_variants.price', $range)
            ->select('products.id')
            ->distinct()
            ->orderBy('products.id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        Redis::expire($indexKey, 86400);
        Log::info("Built price range index: {$indexKey}");
    }
    
    /**
     * Build single rating index
     */
    private function buildRatingIndex(int $minRating): void
    {
        $indexKey = "index:rating:{$minRating}";
        Redis::del($indexKey);
        
        DB::table('products')
            ->leftJoin('product_reviews', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.status', 'active')
            ->where('product_reviews.status', 'active')
            ->groupBy('products.id')
            ->havingRaw('AVG(product_reviews.rate) >= ?', [$minRating])
            ->select('products.id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        Redis::expire($indexKey, 86400);
        Log::info("Built rating index: {$indexKey}");
    }
    
    /**
     * Build single discount index
     */
    private function buildDiscountIndex(int $minDiscount): void
    {
        $indexKey = "index:discount:{$minDiscount}";
        Redis::del($indexKey);
        
        // Base products
        DB::table('products')
            ->where('status', 'active')
            ->where('has_variants', false)
            ->where('base_discount', '>=', $minDiscount)
            ->select('id')
            ->orderBy('id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        // Variant products
        DB::table('product_variants')
            ->join('products', 'product_variants.product_id', '=', 'products.id')
            ->where('products.status', 'active')
            ->where('products.has_variants', true)
            ->where('product_variants.status', 'active')
            ->where('product_variants.discount', '>=', $minDiscount)
            ->select('products.id')
            ->distinct()
            ->orderBy('products.id')
            ->chunkById(10000, function ($products) use ($indexKey) {
                $ids = $products->pluck('id')->toArray();
                if (!empty($ids)) {
                    Redis::sadd($indexKey, ...$ids);
                }
            });
        
        Redis::expire($indexKey, 86400);
        Log::info("Built discount index: {$indexKey}");
    }
    
    // Helper methods
    
    private function isRedisAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function getIndexKeyCount(): int
    {
        try {
            return count(Redis::keys('index:*'));
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    private function getCriticalIndexes(): array
    {
        // Return a sample of indexes that should always exist
        $critical = [];
        
        // Get first 3 categories
        $categories = DB::table('categories')
            ->where('status', 'active')
            ->limit(3)
            ->pluck('id');
        
        foreach ($categories as $catId) {
            $critical[] = "index:category:{$catId}";
        }
        
        // Get first 3 brands
        $brands = DB::table('brands')
            ->where('status', 'active')
            ->limit(3)
            ->pluck('id');
        
        foreach ($brands as $brandId) {
            $critical[] = "index:brand:{$brandId}";
        }
        
        // Critical price ranges
        $critical[] = 'index:price:0-100';
        $critical[] = 'index:price:100-500';
        
        return $critical;
    }
    
    private function areIndexesFresh(): bool
    {
        try {
            $sampleKeys = array_slice(Redis::keys('index:*'), 0, 10);
            
            foreach ($sampleKeys as $key) {
                $ttl = Redis::ttl($key);
                if ($ttl <= 0) {
                    return false; // No TTL or expired
                }
            }
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function getStaleIndexes(): array
    {
        $stale = [];
        
        try {
            $allIndexes = Redis::keys('index:*');
            
            foreach ($allIndexes as $key) {
                $ttl = Redis::ttl($key);
                if ($ttl <= 0) {
                    $stale[] = $key;
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to check for stale indexes', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $stale;
    }
}
