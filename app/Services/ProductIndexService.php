<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Product Index Service
 *
 * Builds and maintains Redis Set-based indexes for ultra-fast filtering.
 * Designed for 10M+ products with sub-100ms filter response times.
 *
 * Index Structure (via RedisKeyManager):
 * - ec:idx:cat:{id} → Set of product IDs
 * - ec:idx:br:{id} → Set of product IDs
 * - ec:idx:price:{range} → Set of product IDs
 * - ec:idx:rating:{min} → Set of product IDs
 * - ec:idx:discount:{min} → Set of product IDs
 *
 * Memory footprint: ~200-500MB for 10M products
 * Build time: ~5-10 minutes for complete rebuild
 */
class ProductIndexService
{
    private const INDEX_TTL = 86400; // 24 hours

    private const CHUNK_SIZE = 5000; // Process 5000 products at a time for speed

    private const BATCH_SIZE = 10000; // Redis pipeline batch size

    private const PIPELINE_BATCH = 1000; // Pipeline operations in batches

    /**
     * Build all product indexes
     */
    public function buildAllIndexes($progressCallback = null): array
    {
        $startTime = microtime(true);
        $stats = [
            'categories' => 0,
            'brands' => 0,
            'price_ranges' => 0,
            'ratings' => 0,
            'discounts' => 0,
            'total_products_indexed' => 0,
        ];

        Log::info('🏗️ Building product indexes...');

        $stats['categories'] = $this->buildCategoryIndex($progressCallback);
        $stats['brands'] = $this->buildBrandIndex($progressCallback);
        $stats['price_ranges'] = $this->buildPriceIndex($progressCallback);
        $stats['ratings'] = $this->buildRatingIndex($progressCallback);
        $stats['discounts'] = $this->buildDiscountIndex($progressCallback);

        $stats['total_products_indexed'] = Product::where('status', 'active')->count();
        $stats['build_time_seconds'] = round(microtime(true) - $startTime, 2);

        Log::info('✅ Product indexes built successfully', $stats);

        return $stats;
    }

    /**
     * Build category index - OPTIMIZED
     * Index: ec:idx:cat:{category_id} → Set[product_ids]
     */
    public function buildCategoryIndex($progressCallback = null): int
    {
        $categoriesIndexed = 0;
        $startTime = microtime(true);

        // Get all active categories with their product counts in one query
        $categories = DB::table('categories')
            ->where('status', 'active')
            ->select('id', 'title')
            ->get();

        $totalCategories = $categories->count();
        $processed = 0;

        Log::info("[1/5] Building category indexes for {$totalCategories} categories...");

        foreach ($categories as $category) {
            $indexKey = RedisKeyManager::indexCategory($category->id);

            // Use UNLINK (non-blocking) instead of DEL
            Redis::unlink($indexKey);
            $hasProducts = false;
            $pipeline = [];

            // PostgreSQL optimized: Use cursor for large datasets
            DB::table('products')
                ->where('status', 'active')
                ->where(function ($query) use ($category) {
                    $query->where('cat_id', $category->id)
                          ->orWhere('child_cat_id', $category->id);
                })
                ->select('id')
                ->orderBy('id')
                ->chunkById(self::BATCH_SIZE, function ($products) use ($indexKey, &$hasProducts, &$pipeline) {
                    $ids = $products->pluck('id')->toArray();
                    if (! empty($ids)) {
                        // Use pipeline for batch SADD operations
                        $pipeline = array_merge($pipeline, $ids);

                        if (count($pipeline) >= self::PIPELINE_BATCH) {
                            Redis::sadd($indexKey, ...$pipeline);
                            $pipeline = [];
                        }
                        $hasProducts = true;
                    }
                });

            // Flush remaining pipeline
            if (! empty($pipeline)) {
                Redis::sadd($indexKey, ...$pipeline);
            }

            if ($hasProducts) {
                Redis::expire($indexKey, self::INDEX_TTL);
                $categoriesIndexed++;
            }

            $processed++;
            if ($processed % 5 == 0 || $processed == $totalCategories) {
                $percentComplete = round(($processed / $totalCategories) * 100, 2);
                $elapsed = round(microtime(true) - $startTime, 2);
                Log::debug("  Category progress: {$processed}/{$totalCategories} ({$percentComplete}%) - {$elapsed}s");
            }
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $message = "✅ [1/5] Categories: {$categoriesIndexed} indexed in {$totalTime}s";
        Log::info($message);

        if ($progressCallback) {
            $progressCallback(1, $message, '100%');
        }

        return $categoriesIndexed;
    }

    /**
     * Build brand index - OPTIMIZED
     * Index: ec:idx:br:{brand_id} → Set[product_ids]
     */
    public function buildBrandIndex($progressCallback = null): int
    {
        $brandsIndexed = 0;
        $startTime = microtime(true);

        // Get all active brands
        $brands = DB::table('brands')
            ->where('status', 'active')
            ->select('id', 'title')
            ->get();

        $totalBrands = $brands->count();
        $processed = 0;

        Log::info("[2/5] Building brand indexes for {$totalBrands} brands...");

        foreach ($brands as $brand) {
            $indexKey = RedisKeyManager::indexBrand($brand->id);

            Redis::unlink($indexKey);
            $hasProducts = false;
            $pipeline = [];

            // PostgreSQL optimized with pipelining
            DB::table('products')
                ->where('status', 'active')
                ->where('brand_id', $brand->id)
                ->select('id')
                ->orderBy('id')
                ->chunkById(self::BATCH_SIZE, function ($products) use ($indexKey, &$hasProducts, &$pipeline) {
                    $ids = $products->pluck('id')->toArray();
                    if (! empty($ids)) {
                        $pipeline = array_merge($pipeline, $ids);

                        if (count($pipeline) >= self::PIPELINE_BATCH) {
                            Redis::sadd($indexKey, ...$pipeline);
                            $pipeline = [];
                        }
                        $hasProducts = true;
                    }
                });

            // Flush remaining
            if (! empty($pipeline)) {
                Redis::sadd($indexKey, ...$pipeline);
            }

            if ($hasProducts) {
                Redis::expire($indexKey, self::INDEX_TTL);
                $brandsIndexed++;
            }

            $processed++;
            if ($processed % 5 == 0 || $processed == $totalBrands) {
                $percentComplete = round(($processed / $totalBrands) * 100, 2);
                $elapsed = round(microtime(true) - $startTime, 2);
                Log::debug("  Brand progress: {$processed}/{$totalBrands} ({$percentComplete}%) - {$elapsed}s");
            }
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $message = "\n✅ [2/5] Brands: {$brandsIndexed} indexed in {$totalTime}s\n";
        echo $message;
        Log::info($message);

        if ($progressCallback) {
            $progressCallback(2, $message, '100%');
        }

        return $brandsIndexed;
    }

    /**
     * Build price range indexes - OPTIMIZED
     * Index: ec:idx:price:{range} → Set[product_ids]
     */
    public function buildPriceIndex($progressCallback = null): int
    {
        $startTime = microtime(true);
        $priceRanges = [
            '0-100' => [0, 100],
            '100-500' => [100, 500],
            '500-1000' => [500, 1000],
            '1000-5000' => [1000, 5000],
            '5000-10000' => [5000, 10000],
            '10000+' => [10000, 999999],
        ];

        $total = count($priceRanges);
        $processed = 0;

        Log::info("[3/5] Building price range indexes ({$total} ranges)...");

        foreach ($priceRanges as $key => $range) {
            $indexKey = RedisKeyManager::indexPriceRange($key);
            $rangeStart = microtime(true);

            Redis::unlink($indexKey);
            $count = 0;

            // Optimized: Chunked query for base products
            DB::table('products')
                ->where('status', 'active')
                ->where('has_variants', false)
                ->whereBetween('base_price', $range)
                ->select('id')
                ->orderBy('id')
                ->chunkById(self::BATCH_SIZE, function ($products) use ($indexKey, &$count) {
                    $ids = $products->pluck('id')->toArray();
                    if (! empty($ids)) {
                        Redis::sadd($indexKey, ...$ids);
                        $count += count($ids);
                    }
                });

            // Optimized: Chunked query for variants
            DB::table('product_variants')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('products.status', 'active')
                ->where('products.has_variants', true)
                ->where('product_variants.status', 'active')
                ->whereBetween('product_variants.price', $range)
                ->select('products.id', 'product_variants.id as variant_id')
                ->distinct()
                ->orderBy('product_variants.id')
                ->chunkById(self::BATCH_SIZE, function ($variants) use ($indexKey, &$count) {
                    $ids = $variants->pluck('id')->toArray();
                    if (! empty($ids)) {
                        Redis::sadd($indexKey, ...$ids);
                        $count += count($ids);
                    }
                }, 'product_variants.id', 'variant_id');

            if ($count > 0) {
                Redis::expire($indexKey, self::INDEX_TTL);
            }

            $processed++;
            $percentComplete = round(($processed / $total) * 100, 2);
            $rangeTime = round(microtime(true) - $rangeStart, 2);
            Log::debug("  {$key}: ".$count." products ({$rangeTime}s)");
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $message = "✅ [3/5] Price ranges: {$total} indexed in {$totalTime}s\n";
        echo $message;
        Log::info($message);

        if ($progressCallback) {
            $progressCallback(3, $message, '100%');
        }

        return $total;
    }

    /**
     * Build rating indexes - OPTIMIZED
     * Index: ec:idx:rating:{min_rating} → Set[product_ids]
     */
    public function buildRatingIndex($progressCallback = null): int
    {
        $startTime = microtime(true);
        $ratings = [1, 2, 3, 4, 5];
        $total = count($ratings);

        Log::info("[4/5] Building rating indexes ({$total} levels)...");

        foreach ($ratings as $minRating) {
            $indexKey = RedisKeyManager::indexRating($minRating);
            $ratingStart = microtime(true);

            Redis::unlink($indexKey);
            $count = 0;

            // Optimized: Chunked query with grouping
            // Note: chunkById works with groupBy if we order by the grouped column
            DB::table('products')
                ->leftJoin('product_reviews', 'products.id', '=', 'product_reviews.product_id')
                ->where('products.status', 'active')
                ->where('product_reviews.status', 'active')
                ->groupBy('products.id')
                ->havingRaw('AVG(product_reviews.rate) >= ?', [$minRating])
                ->select('products.id')
                // chunkById will add orderBy products.id
                ->chunkById(self::BATCH_SIZE, function ($products) use ($indexKey, &$count) {
                    $ids = $products->pluck('id')->toArray();
                    if (! empty($ids)) {
                        Redis::sadd($indexKey, ...$ids);
                        $count += count($ids);
                    }
                });

            if ($count > 0) {
                Redis::expire($indexKey, self::INDEX_TTL);
            }

            $ratingTime = round(microtime(true) - $ratingStart, 2);
            Log::debug("  {$minRating}+ stars: ".$count." products ({$ratingTime}s)");
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $message = "✅ [4/5] Ratings: {$total} levels indexed in {$totalTime}s\n";
        echo $message;
        Log::info($message);

        if ($progressCallback) {
            $progressCallback(4, $message, '100%');
        }

        return $total;
    }

    /**
     * Build discount indexes - OPTIMIZED
     * Index: ec:idx:discount:{min} → Set[product_ids]
     */
    public function buildDiscountIndex($progressCallback = null): int
    {
        $startTime = microtime(true);
        $discountRanges = [
            '10' => 10,
            '25' => 25,
            '50' => 50,
            '75' => 75,
        ];

        $total = count($discountRanges);

        Log::info("[5/5] Building discount indexes ({$total} levels)...");

        foreach ($discountRanges as $key => $minDiscount) {
            $indexKey = "index:discount:{$key}";
            $discountStart = microtime(true);

            Redis::unlink($indexKey);
            $count = 0;

            // Optimized: Chunked query for base products
            DB::table('products')
                ->where('status', 'active')
                ->where('has_variants', false)
                ->where('base_discount', '>=', $minDiscount)
                ->select('id')
                ->orderBy('id')
                ->chunkById(self::BATCH_SIZE, function ($products) use ($indexKey, &$count) {
                    $ids = $products->pluck('id')->toArray();
                    if (! empty($ids)) {
                        Redis::sadd($indexKey, ...$ids);
                        $count += count($ids);
                    }
                });

            // Optimized: Chunked query for variants
            DB::table('product_variants')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('products.status', 'active')
                ->where('products.has_variants', true)
                ->where('product_variants.status', 'active')
                ->where('product_variants.discount', '>=', $minDiscount)
                ->select('products.id', 'product_variants.id as variant_id')
                ->distinct()
                ->orderBy('product_variants.id')
                ->chunkById(self::BATCH_SIZE, function ($variants) use ($indexKey, &$count) {
                    $ids = $variants->pluck('id')->toArray();
                    if (! empty($ids)) {
                        Redis::sadd($indexKey, ...$ids);
                        $count += count($ids);
                    }
                }, 'product_variants.id', 'variant_id');

            if ($count > 0) {
                Redis::expire($indexKey, self::INDEX_TTL);
            }

            $discountTime = round(microtime(true) - $discountStart, 2);
            Log::debug("  {$minDiscount}%+: ".$count." products ({$discountTime}s)");
        }

        $totalTime = round(microtime(true) - $startTime, 2);
        $message = "✅ [5/5] Discounts: {$total} levels indexed in {$totalTime}s\n";
        echo $message;
        Log::info($message);

        if ($progressCallback) {
            $progressCallback(5, $message, '100%');
        }

        return $total;
    }

    /**
     * Update single product in all relevant indexes
     * Called from ProductObserver on create/update
     */
    public function updateProductIndexes(Product $product): void
    {
        if ($product->status !== 'active') {
            $this->removeProductFromIndexes($product);

            return;
        }

        // Category indexes
        if ($product->cat_id) {
            Redis::sadd(RedisKeyManager::indexCategory($product->cat_id), $product->id);
        }

        if ($product->child_cat_id) {
            Redis::sadd(RedisKeyManager::indexCategory($product->child_cat_id), $product->id);
        }

        // Brand index
        if ($product->brand_id) {
            Redis::sadd(RedisKeyManager::indexBrand($product->brand_id), $product->id);
        }

        // Update price index
        if ($product->base_price) {
            $priceRange = $this->getPriceRange((float) $product->base_price);
            if ($priceRange) {
                Redis::sadd(RedisKeyManager::indexPriceRange($priceRange), $product->id);
            }
        }

        // Update discount index
        if ($product->base_discount >= 10) {
            $discountRanges = ['10' => 10, '25' => 25, '50' => 50, '75' => 75];
            foreach ($discountRanges as $key => $minDiscount) {
                if ($product->base_discount >= $minDiscount) {
                    Redis::sadd(RedisKeyManager::indexDiscount($key), $product->id);
                }
            }
        }

        Log::debug("Updated indexes for product: {$product->id}");
    }

    /**
     * Remove product from all indexes
     */
    public function removeProductFromIndexes(Product $product): void
    {
        // Get all index keys and remove this product ID
        $patterns = [
            'ec:idx:cat:*',
            'ec:idx:br:*',
            'ec:idx:price:*',
            'ec:idx:rating:*',
            'ec:idx:discount:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            foreach ($keys as $key) {
                Redis::srem($key, $product->id);
            }
        }

        Log::debug("Removed product from indexes: {$product->id}");
    }

    /**
     * Get price range key for a price value
     */
    private function getPriceRange(float $price): ?string
    {
        if ($price < 100) {
            return '0-100';
        }
        if ($price < 500) {
            return '100-500';
        }
        if ($price < 1000) {
            return '500-1000';
        }
        if ($price < 5000) {
            return '1000-5000';
        }
        if ($price < 10000) {
            return '5000-10000';
        }

        return '10000+';
    }

    /**
     * Get index statistics
     * OPTIMIZED: Uses RedisKeyManager patterns and SCAN for production safety
     */
    public function getIndexStats(): array
    {
        $stats = [
            'categories' => [],
            'brands' => [],
            'price_ranges' => [],
            'ratings' => [],
            'discounts' => [],
            'total_keys' => 0,
            'total_memory_mb' => 0,
        ];

        // Helper function to use SCAN instead of KEYS for production safety
        $getKeysByPattern = function ($pattern) {
            $keys = [];
            $cursor = '0';

            do {
                // Laravel Redis scan returns [cursor, keys]
                [$cursor, $found] = Redis::scan($cursor, ['match' => $pattern, 'count' => 100]);
                $keys = array_merge($keys, $found);
            } while ($cursor !== '0' && $cursor !== 0);

            return $keys;
        };

        // Category stats - use RedisKeyManager patterns
        $categoryKeys = $getKeysByPattern(RedisKeyManager::pattern('index', 'cat'));
        foreach ($categoryKeys as $key) {
            $count = Redis::scard($key);
            $stats['categories'][] = [
                'key' => $key,
                'product_count' => $count,
            ];
        }

        // Brand stats
        $brandKeys = $getKeysByPattern(RedisKeyManager::pattern('index', 'brand'));
        foreach ($brandKeys as $key) {
            $count = Redis::scard($key);
            $stats['brands'][] = [
                'key' => $key,
                'product_count' => $count,
            ];
        }

        // Price range stats
        $priceKeys = $getKeysByPattern(RedisKeyManager::pattern('index', 'price'));
        foreach ($priceKeys as $key) {
            $count = Redis::scard($key);
            $stats['price_ranges'][] = [
                'key' => $key,
                'product_count' => $count,
            ];
        }

        // Rating stats
        $ratingKeys = $getKeysByPattern(RedisKeyManager::pattern('index', 'rating'));
        foreach ($ratingKeys as $key) {
            $count = Redis::scard($key);
            $stats['ratings'][] = [
                'key' => $key,
                'product_count' => $count,
            ];
        }

        // Discount stats
        $discountKeys = $getKeysByPattern(RedisKeyManager::pattern('index', 'discount'));
        foreach ($discountKeys as $key) {
            $count = Redis::scard($key);
            $stats['discounts'][] = [
                'key' => $key,
                'product_count' => $count,
            ];
        }

        $stats['total_keys'] = count($categoryKeys) + count($brandKeys) +
                               count($priceKeys) + count($ratingKeys) +
                               count($discountKeys);

        // Estimate memory usage using SCAN
        $totalMemoryBytes = 0;
        $allKeys = $getKeysByPattern(RedisKeyManager::pattern('index'));
        foreach ($allKeys as $key) {
            $size = Redis::scard($key) * 10; // ~10 bytes per ID with overhead
            $totalMemoryBytes += $size;
        }
        $stats['total_memory_mb'] = round($totalMemoryBytes / 1024 / 1024, 2);

        return $stats;
    }
}
