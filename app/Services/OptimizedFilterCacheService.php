<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Optimized Filter Cache Service
 *
 * Handles Redis caching for product filters with optimized structure:
 * - Uses Redis HASH for filter metadata (faster than serialized data)
 * - Uses Redis SORTED SET for price-based filtering
 * - Pipeline operations for batch processing
 * - Compressed cache keys for memory efficiency
 * - Uses RedisKeyManager for consistent key naming
 *
 * Performance targets:
 * - Filter metadata: < 10ms
 * - Price range filtering: < 50ms
 * - Category + filters: < 100ms
 */
class OptimizedFilterCacheService
{
    private const META_TTL = 600; // 10 minutes
    private const RESULT_TTL = 180; // 3 minutes

    /**
     * Get filter metadata (brands, categories, price ranges) with Redis HASH
     */
    public function getFilterMetadata(string $categorySlug = 'all'): array
    {
        $key = RedisKeyManager::filterMeta($categorySlug);
        
        try {
            // Use HGETALL for faster retrieval of structured data
            $cached = Redis::hgetall($key);
            
            if (!empty($cached)) {
                Log::debug("Filter metadata cache HIT", ['key' => $key]);
                return $this->decodeMetadata($cached);
            }
            
            // Build metadata from database
            $metadata = $this->buildFilterMetadata($categorySlug);
            
            // Store in Redis HASH for fast access
            $this->storeMetadata($key, $metadata);
            
            return $metadata;
            
        } catch (\Exception $e) {
            Log::error("Filter metadata cache error", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $this->buildFilterMetadata($categorySlug);
        }
    }

    /**
     * Store filter results with optimized Redis SET operations
     */
    public function cacheFilterResults(string $cacheKey, array $productIds, int $ttl = null): bool
    {
        $ttl = $ttl ?? self::RESULT_TTL;
        
        try {
            if (empty($productIds)) {
                // Store empty result marker
                Redis::setex($cacheKey, $ttl, 'EMPTY');
                return true;
            }
            
            // Use Redis SET for large result sets
            if (count($productIds) > 100) {
                Redis::pipeline(function ($pipe) use ($cacheKey, $productIds, $ttl) {
                    $pipe->del($cacheKey);
                    
                    // Add in chunks to avoid blocking
                    foreach (array_chunk($productIds, 1000) as $chunk) {
                        $pipe->sadd($cacheKey, ...$chunk);
                    }
                    
                    $pipe->expire($cacheKey, $ttl);
                });
            } else {
                // Small sets - direct operation
                Redis::sadd($cacheKey, ...$productIds);
                Redis::expire($cacheKey, $ttl);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error("Failed to cache filter results", [
                'key' => $cacheKey,
                'count' => count($productIds),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get cached filter results
     */
    public function getCachedFilterResults(string $cacheKey): ?array
    {
        try {
            if (!Redis::exists($cacheKey)) {
                return null;
            }
            
            $value = Redis::get($cacheKey);
            
            // Check for empty marker
            if ($value === 'EMPTY') {
                return [];
            }
            
            // Get from SET
            $members = Redis::smembers($cacheKey);
            return array_map('intval', $members);
            
        } catch (\Exception $e) {
            Log::error("Failed to get cached filter results", [
                'key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Build price range index using SORTED SET
     */
    public function buildPriceRangeIndex(string $categorySlug = 'all'): void
    {
        $key = RedisKeyManager::filterPriceIndex($categorySlug);
        
        try {
            Log::info("Building price range index", ['category' => $categorySlug]);
            
            // Get products with effective prices
            $query = DB::table('products')
                ->where('products.status', 'active')
                ->select([
                    'products.id',
                    DB::raw('COALESCE(
                        (SELECT MIN(pv.price * (1 - COALESCE(pv.discount, 0) / 100.0))
                         FROM product_variants pv
                         WHERE pv.product_id = products.id
                         AND pv.status = \'active\'),
                        products.base_price * (1 - COALESCE(products.base_discount, 0) / 100.0)
                    ) as effective_price')
                ]);
            
            if ($categorySlug !== 'all') {
                $category = DB::table('categories')
                    ->where('slug', $categorySlug)
                    ->first();
                
                if ($category) {
                    $query->where('products.cat_id', $category->id);
                }
            }
            
            // Use chunking to avoid memory issues
            Redis::del($key);
            
            $query->chunk(5000, function ($products) use ($key) {
                $priceData = [];
                
                foreach ($products as $product) {
                    if ($product->effective_price > 0) {
                        $priceData[$product->effective_price] = $product->id;
                    }
                }
                
                if (!empty($priceData)) {
                    Redis::pipeline(function ($pipe) use ($key, $priceData) {
                        foreach ($priceData as $price => $productId) {
                            $pipe->zadd($key, $price, $productId);
                        }
                    });
                }
            });
            
            Redis::expire($key, 3600); // 1 hour
            
            Log::info("Price range index built", [
                'category' => $categorySlug,
                'count' => Redis::zcard($key)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Failed to build price range index", [
                'category' => $categorySlug,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get products by price range using SORTED SET
     */
    public function getProductsByPriceRange(float $minPrice, float $maxPrice, string $categorySlug = 'all'): array
    {
        $key = RedisKeyManager::filterPriceIndex($categorySlug);
        
        try {
            // Rebuild index if missing
            if (!Redis::exists($key)) {
                $this->buildPriceRangeIndex($categorySlug);
            }
            
            // Use ZRANGEBYSCORE for efficient range query
            $productIds = Redis::zrangebyscore($key, $minPrice, $maxPrice);
            
            return array_map('intval', $productIds);
            
        } catch (\Exception $e) {
            Log::error("Failed to get products by price range", [
                'category' => $categorySlug,
                'range' => "$minPrice-$maxPrice",
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Clear filter caches
     */
    public function clearFilterCache(?string $categorySlug = null): int
    {
        try {
            if ($categorySlug) {
                // Clear specific category
                $pattern = RedisKeyManager::pattern('filter', 'meta') . $categorySlug . '*';
            } else {
                // Clear all filter caches
                $pattern = RedisKeyManager::pattern('filter');
            }
            
            $keys = Redis::keys($pattern);
            
            if (!empty($keys)) {
                Redis::del(...$keys);
            }
            
            return count($keys);
            
        } catch (\Exception $e) {
            Log::error("Failed to clear filter cache", [
                'category' => $categorySlug,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Build filter metadata from database
     * OPTIMIZED for 10M+ products: Uses efficient aggregation with proper alias handling
     */
    public function buildFilterMetadata(string $categorySlug): array
    {
        $baseQuery = DB::table('products')->where('status', 'active');
        
        if ($categorySlug !== 'all') {
            $category = DB::table('categories')->where('slug', $categorySlug)->first();
            if ($category) {
                $baseQuery->where('cat_id', $category->id);
            }
        }
        
        // Get brand counts - FIXED: Use havingRaw for PostgreSQL alias compatibility
        $brands = DB::table('brands as b')
            ->join('products as p', 'p.brand_id', '=', 'b.id')
            ->where('p.status', 'active')
            ->where('b.status', 'active')
            ->select('b.id', 'b.title', 'b.slug', DB::raw('COUNT(p.id) as product_count'))
            ->groupBy('b.id', 'b.title', 'b.slug')
            ->havingRaw('COUNT(p.id) > 0')
            ->orderByRaw('COUNT(p.id) DESC')
            ->limit(50)
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'title' => $b->title,
                'slug' => $b->slug,
                'count' => (int)$b->product_count
            ])
            ->toArray();
        
        // Get price range - OPTIMIZED: Clone query to avoid affecting original
        $priceQuery = clone $baseQuery;
        $priceStats = $priceQuery->selectRaw('
            MIN(COALESCE(base_price, 0)) as min_price,
            MAX(COALESCE(base_price, 0)) as max_price
        ')->first();
        
        return [
            'brands' => $brands,
            'price_min' => (int)($priceStats->min_price ?? 0),
            'price_max' => (int)($priceStats->max_price ?? 10000),
            'price_ranges' => [
                ['min' => 0, 'max' => 100, 'label' => 'Under $100'],
                ['min' => 100, 'max' => 500, 'label' => '$100 - $500'],
                ['min' => 500, 'max' => 1000, 'label' => '$500 - $1,000'],
                ['min' => 1000, 'max' => 999999, 'label' => 'Over $1,000'],
            ]
        ];
    }

    /**
     * Store metadata in Redis HASH
     */
    private function storeMetadata(string $key, array $metadata): void
    {
        try {
            Redis::pipeline(function ($pipe) use ($key, $metadata) {
                $pipe->del($key);
                $pipe->hmset($key, [
                    'brands' => json_encode($metadata['brands']),
                    'price_min' => $metadata['price_min'],
                    'price_max' => $metadata['price_max'],
                    'price_ranges' => json_encode($metadata['price_ranges']),
                    'updated_at' => time()
                ]);
                $pipe->expire($key, self::META_TTL);
            });
        } catch (\Exception $e) {
            Log::error("Failed to store filter metadata", [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Decode metadata from Redis HASH
     */
    private function decodeMetadata(array $data): array
    {
        return [
            'brands' => json_decode($data['brands'] ?? '[]', true),
            'price_min' => (int)($data['price_min'] ?? 0),
            'price_max' => (int)($data['price_max'] ?? 10000),
            'price_ranges' => json_decode($data['price_ranges'] ?? '[]', true)
        ];
    }
}
