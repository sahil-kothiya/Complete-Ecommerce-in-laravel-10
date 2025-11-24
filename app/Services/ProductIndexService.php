<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Product Index Service
 *
 * Builds and maintains Redis Set-based indexes for ultra-fast filtering.
 * Designed for 10M+ products with sub-100ms filter response times.
 *
 * Index Structure:
 * - index:category:{id} → Set of product IDs
 * - index:brand:{id} → Set of product IDs
 * - index:price:{range} → Set of product IDs
 * - index:rating:{min} → Set of product IDs
 * - index:discount:{range} → Set of product IDs
 *
 * Memory footprint: ~200-500MB for 10M products
 * Build time: ~5-10 minutes for complete rebuild
 */
class ProductIndexService
{
    private const INDEX_TTL = 86400; // 24 hours
    private const CHUNK_SIZE = 1000; // Process 1000 products at a time

    /**
     * Build all product indexes
     */
    public function buildAllIndexes(): array
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

        $stats['categories'] = $this->buildCategoryIndex();
        $stats['brands'] = $this->buildBrandIndex();
        $stats['price_ranges'] = $this->buildPriceIndex();
        $stats['ratings'] = $this->buildRatingIndex();
        $stats['discounts'] = $this->buildDiscountIndex();

        $stats['total_products_indexed'] = Product::where('status', 'active')->count();
        $stats['build_time_seconds'] = round(microtime(true) - $startTime, 2);

        Log::info('✅ Product indexes built successfully', $stats);

        return $stats;
    }

    /**
     * Build category index
     * Index: index:category:{category_id} → Set[product_ids]
     */
    public function buildCategoryIndex(): int
    {
        $categoriesIndexed = 0;

        Category::where('status', 'active')->chunk(100, function($categories) use (&$categoriesIndexed) {
            foreach ($categories as $category) {
                $indexKey = "index:category:{$category->id}";

                // Get all products in this category (including subcategories)
                $productIds = Product::where('status', 'active')
                    ->where(function($query) use ($category) {
                        $query->where('cat_id', $category->id)
                              ->orWhere('child_cat_id', $category->id);
                    })
                    ->pluck('id')
                    ->toArray();

                if (!empty($productIds)) {
                    // Delete existing set
                    Redis::del($indexKey);

                    // Add all product IDs to set (batch operation)
                    Redis::sadd($indexKey, ...$productIds);

                    // Set expiration
                    Redis::expire($indexKey, self::INDEX_TTL);

                    $categoriesIndexed++;

                    Log::debug("Indexed category: {$category->title}", [
                        'category_id' => $category->id,
                        'product_count' => count($productIds)
                    ]);
                }
            }
        });

        Log::info("✅ Category index built: {$categoriesIndexed} categories");
        return $categoriesIndexed;
    }

    /**
     * Build brand index
     * Index: index:brand:{brand_id} → Set[product_ids]
     */
    public function buildBrandIndex(): int
    {
        $brandsIndexed = 0;

        Brand::where('status', 'active')->chunk(100, function($brands) use (&$brandsIndexed) {
            foreach ($brands as $brand) {
                $indexKey = "index:brand:{$brand->id}";

                $productIds = Product::where('status', 'active')
                    ->where('brand_id', $brand->id)
                    ->pluck('id')
                    ->toArray();

                if (!empty($productIds)) {
                    Redis::del($indexKey);
                    Redis::sadd($indexKey, ...$productIds);
                    Redis::expire($indexKey, self::INDEX_TTL);

                    $brandsIndexed++;

                    Log::debug("Indexed brand: {$brand->title}", [
                        'brand_id' => $brand->id,
                        'product_count' => count($productIds)
                    ]);
                }
            }
        });

        Log::info("✅ Brand index built: {$brandsIndexed} brands");
        return $brandsIndexed;
    }

    /**
     * Build price range indexes
     * Index: index:price:{range} → Set[product_ids]
     */
    public function buildPriceIndex(): int
    {
        $priceRanges = [
            '0-100' => [0, 100],
            '100-500' => [100, 500],
            '500-1000' => [500, 1000],
            '1000-5000' => [1000, 5000],
            '5000-10000' => [5000, 10000],
            '10000+' => [10000, 999999],
        ];

        foreach ($priceRanges as $key => $range) {
            $indexKey = "index:price:{$key}";

            // Get products with base price in range (non-variant products)
            $productIds = Product::where('status', 'active')
                ->where('has_variants', false)
                ->whereBetween('base_price', $range)
                ->pluck('id')
                ->toArray();

            // Get products with variants in this price range
            $variantProductIds = DB::table('product_variants')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('products.status', 'active')
                ->where('products.has_variants', true)
                ->where('product_variants.status', 'active')
                ->whereBetween('product_variants.price', $range)
                ->distinct()
                ->pluck('products.id')
                ->toArray();

            $allProductIds = array_unique(array_merge($productIds, $variantProductIds));

            if (!empty($allProductIds)) {
                Redis::del($indexKey);
                Redis::sadd($indexKey, ...$allProductIds);
                Redis::expire($indexKey, self::INDEX_TTL);

                Log::debug("Indexed price range: {$key}", [
                    'product_count' => count($allProductIds)
                ]);
            }
        }

        Log::info("✅ Price index built: " . count($priceRanges) . " ranges");
        return count($priceRanges);
    }

    /**
     * Build rating indexes
     * Index: index:rating:{min_rating} → Set[product_ids]
     */
    public function buildRatingIndex(): int
    {
        $ratings = [1, 2, 3, 4, 5];

        foreach ($ratings as $minRating) {
            $indexKey = "index:rating:{$minRating}";

            // Products with average rating >= minRating
            $productIds = DB::table('products')
                ->leftJoin('product_reviews', 'products.id', '=', 'product_reviews.product_id')
                ->where('products.status', 'active')
                ->where('product_reviews.status', 'active')
                ->groupBy('products.id')
                ->havingRaw('AVG(product_reviews.rate) >= ?', [$minRating])
                ->pluck('products.id')
                ->toArray();

            if (!empty($productIds)) {
                Redis::del($indexKey);
                Redis::sadd($indexKey, ...$productIds);
                Redis::expire($indexKey, self::INDEX_TTL);

                Log::debug("Indexed rating: {$minRating}+", [
                    'product_count' => count($productIds)
                ]);
            }
        }

        Log::info("✅ Rating index built: " . count($ratings) . " levels");
        return count($ratings);
    }

    /**
     * Build discount indexes
     * Index: index:discount:{range} → Set[product_ids]
     */
    public function buildDiscountIndex(): int
    {
        $discountRanges = [
            '10' => 10,
            '25' => 25,
            '50' => 50,
            '75' => 75,
        ];

        foreach ($discountRanges as $key => $minDiscount) {
            $indexKey = "index:discount:{$key}";

            // Products with base discount >= minDiscount
            $productIds = Product::where('status', 'active')
                ->where('has_variants', false)
                ->where('base_discount', '>=', $minDiscount)
                ->pluck('id')
                ->toArray();

            // Products with variant discount >= minDiscount
            $variantProductIds = DB::table('product_variants')
                ->join('products', 'product_variants.product_id', '=', 'products.id')
                ->where('products.status', 'active')
                ->where('products.has_variants', true)
                ->where('product_variants.status', 'active')
                ->where('product_variants.discount', '>=', $minDiscount)
                ->distinct()
                ->pluck('products.id')
                ->toArray();

            $allProductIds = array_unique(array_merge($productIds, $variantProductIds));

            if (!empty($allProductIds)) {
                Redis::del($indexKey);
                Redis::sadd($indexKey, ...$allProductIds);
                Redis::expire($indexKey, self::INDEX_TTL);

                Log::debug("Indexed discount: {$minDiscount}%+", [
                    'product_count' => count($allProductIds)
                ]);
            }
        }

        Log::info("✅ Discount index built: " . count($discountRanges) . " levels");
        return count($discountRanges);
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

        // Update category index
        if ($product->cat_id) {
            Redis::sadd("index:category:{$product->cat_id}", $product->id);
        }

        if ($product->child_cat_id) {
            Redis::sadd("index:category:{$product->child_cat_id}", $product->id);
        }

        // Update brand index
        if ($product->brand_id) {
            Redis::sadd("index:brand:{$product->brand_id}", $product->id);
        }

        // Update price index
        if ($product->base_price) {
            $priceRange = $this->getPriceRange((float)$product->base_price);
            if ($priceRange) {
                Redis::sadd("index:price:{$priceRange}", $product->id);
            }
        }

        // Update discount index
        if ($product->base_discount >= 10) {
            $discountRanges = ['10' => 10, '25' => 25, '50' => 50, '75' => 75];
            foreach ($discountRanges as $key => $minDiscount) {
                if ($product->base_discount >= $minDiscount) {
                    Redis::sadd("index:discount:{$key}", $product->id);
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
            'index:category:*',
            'index:brand:*',
            'index:price:*',
            'index:rating:*',
            'index:discount:*',
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
        if ($price < 100) return '0-100';
        if ($price < 500) return '100-500';
        if ($price < 1000) return '500-1000';
        if ($price < 5000) return '1000-5000';
        if ($price < 10000) return '5000-10000';
        return '10000+';
    }

    /**
     * Get index statistics
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

        // Category stats
        $categoryKeys = Redis::keys('index:category:*');
        foreach ($categoryKeys as $key) {
            $count = Redis::scard($key);
            $stats['categories'][] = [
                'key' => $key,
                'product_count' => $count
            ];
        }

        // Brand stats
        $brandKeys = Redis::keys('index:brand:*');
        foreach ($brandKeys as $key) {
            $count = Redis::scard($key);
            $stats['brands'][] = [
                'key' => $key,
                'product_count' => $count
            ];
        }

        // Price range stats
        $priceKeys = Redis::keys('index:price:*');
        foreach ($priceKeys as $key) {
            $count = Redis::scard($key);
            $stats['price_ranges'][] = [
                'key' => $key,
                'product_count' => $count
            ];
        }

        // Rating stats
        $ratingKeys = Redis::keys('index:rating:*');
        foreach ($ratingKeys as $key) {
            $count = Redis::scard($key);
            $stats['ratings'][] = [
                'key' => $key,
                'product_count' => $count
            ];
        }

        // Discount stats
        $discountKeys = Redis::keys('index:discount:*');
        foreach ($discountKeys as $key) {
            $count = Redis::scard($key);
            $stats['discounts'][] = [
                'key' => $key,
                'product_count' => $count
            ];
        }

        $stats['total_keys'] = count($categoryKeys) + count($brandKeys) +
                               count($priceKeys) + count($ratingKeys) +
                               count($discountKeys);

        // Estimate memory usage (rough estimate)
        // Each product ID = ~8 bytes, plus overhead
        $totalMemoryBytes = 0;
        foreach (Redis::keys('index:*') as $key) {
            $size = Redis::scard($key) * 10; // ~10 bytes per ID with overhead
            $totalMemoryBytes += $size;
        }
        $stats['total_memory_mb'] = round($totalMemoryBytes / 1024 / 1024, 2);

        return $stats;
    }
}
