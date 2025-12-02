<?php

namespace App\Examples;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REDIS KEY MANAGER - PRACTICAL EXAMPLES
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Copy-paste ready examples for common Redis caching scenarios.
 * All examples use the new unified architecture.
 */

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\RedisCacheService;
use App\Services\RedisKeyManager;
use Illuminate\Support\Facades\Redis;

class RedisExamples
{
    /**
     * Example 1: Cache product card for listings
     * Use this for: Category pages, search results, homepage
     */
    public function cacheProductCard(int $productId)
    {
        $key = RedisKeyManager::productCard($productId);

        return RedisCacheService::remember($key, 7200, function () use ($productId) {
            $product = Product::find($productId);

            return [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => $product->base_price,
                'discount' => $product->base_discount,
                'image' => $product->primaryImage?->image_path,
                'stock' => $product->base_stock,
                'rating' => $product->average_rating,
                'is_featured' => $product->is_featured,
            ];
        });
    }

    /**
     * Example 2: Cache full product for detail page
     * Use this for: Product detail page
     */
    public function cacheProductFull(int $productId)
    {
        $key = RedisKeyManager::productFull($productId);

        return RedisCacheService::remember($key, 3600, function () use ($productId) {
            return Product::with([
                'images',
                'cat_info',
                'brand',
                'variantTypeSelections.variantType',
            ])->find($productId);
        });
    }

    /**
     * Example 3: Cache variant card
     * Use this for: Variant selectors, quick displays
     */
    public function cacheVariantCard(int $variantId)
    {
        $key = RedisKeyManager::variantCard($variantId);

        return RedisCacheService::remember($key, 7200, function () use ($variantId) {
            $variant = ProductVariant::find($variantId);

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'discount' => $variant->discount,
                'discounted_price' => $variant->discounted_price,
                'stock' => $variant->stock,
                'display_name' => $variant->display_name,
                'image' => $variant->primaryImage?->image_path,
                'status' => $variant->status,
            ];
        });
    }

    /**
     * Example 4: Cache all variants for a product
     * Use this for: Product detail page, variant selection
     */
    public function cacheProductVariants(int $productId)
    {
        // First, cache the list of variant IDs
        $variantsKey = RedisKeyManager::variantsActive($productId);

        $variantIds = RedisCacheService::remember($variantsKey, 1800, function () use ($productId) {
            return ProductVariant::where('product_id', $productId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });

        // Then load individual variant cards (they have their own cache)
        $variants = [];
        foreach ($variantIds as $variantId) {
            $variants[] = $this->cacheVariantCard($variantId);
        }

        return $variants;
    }

    /**
     * Example 5: Batch cache multiple product cards
     * Use this for: Category listings, search results
     */
    public function batchCacheProductCards(array $productIds)
    {
        // Generate all keys
        $keys = array_map(
            fn ($id) => RedisKeyManager::productCard($id),
            $productIds
        );

        // Batch get from Redis
        $cached = RedisCacheService::mget($keys);

        // Find missing IDs
        $missingIds = [];
        foreach ($productIds as $index => $id) {
            if ($cached[$keys[$index]] === null) {
                $missingIds[] = $id;
            }
        }

        // Fetch missing from database
        if (! empty($missingIds)) {
            $products = Product::whereIn('id', $missingIds)->get();

            $toCache = [];
            foreach ($products as $product) {
                $key = RedisKeyManager::productCard($product->id);
                $toCache[$key] = [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'price' => $product->base_price,
                    'discount' => $product->base_discount,
                    'image' => $product->primaryImage?->image_path,
                    'stock' => $product->base_stock,
                    'rating' => $product->average_rating,
                ];
            }

            // Batch cache missing items
            if (! empty($toCache)) {
                RedisCacheService::mset($toCache, 7200);
                $cached = array_merge($cached, $toCache);
            }
        }

        // Return in original order
        return array_map(fn ($key) => $cached[$key], $keys);
    }

    /**
     * Example 6: Fast filtering with index sets
     * Use this for: Multi-filter product search
     */
    public function filterProductsWithIndexes(int $categoryId, int $brandId, string $priceRange)
    {
        $catKey = RedisKeyManager::indexCategory($categoryId);
        $brandKey = RedisKeyManager::indexBrand($brandId);
        $priceKey = RedisKeyManager::indexPriceRange($priceRange);

        // Redis SET intersection = ultra-fast
        $productIds = Redis::sinter($catKey, $brandKey, $priceKey);

        if (empty($productIds)) {
            // Indexes not built yet, fall back to database
            return Product::where('cat_id', $categoryId)
                ->where('brand_id', $brandId)
                ->whereBetween('base_price', explode('-', $priceRange))
                ->pluck('id')
                ->toArray();
        }

        return $productIds;
    }

    /**
     * Example 7: Build category index
     * Use this for: Background job, cache warmup
     */
    public function buildCategoryIndex(int $categoryId)
    {
        $key = RedisKeyManager::indexCategory($categoryId);

        // Get all product IDs in category
        $productIds = Product::where('cat_id', $categoryId)
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // Store as SET
        Redis::del($key);
        if (! empty($productIds)) {
            Redis::sadd($key, ...$productIds);
            Redis::expire($key, 7200); // 2 hours TTL
        }

        return count($productIds);
    }

    /**
     * Example 8: Cache category with products
     * Use this for: Category page
     */
    public function cacheCategoryPage(int $categoryId, int $page = 1)
    {
        $key = RedisKeyManager::pageCategory($categoryId, $page);

        return RedisCacheService::remember($key, 1800, function () use ($categoryId, $page) {
            $category = Category::find($categoryId);

            $products = Product::where('cat_id', $categoryId)
                ->where('status', 'active')
                ->paginate(12, ['*'], 'page', $page);

            return [
                'category' => $category,
                'products' => $products->items(),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'total' => $products->total(),
                ],
            ];
        });
    }

    /**
     * Example 9: Versioned homepage cache
     * Use this for: Homepage with instant invalidation
     */
    public function cacheHomepage()
    {
        $version = RedisCacheService::getVersion();
        $key = RedisKeyManager::pageHome($version);

        return RedisCacheService::remember($key, 1800, function () {
            return [
                'banners' => $this->getBanners(),
                'featured' => $this->getFeaturedProducts(),
                'new_arrivals' => $this->getNewArrivals(),
                'bestsellers' => $this->getBestsellers(),
            ];
        });
    }

    /**
     * Invalidate homepage (and all versioned caches)
     */
    public function invalidateHomepage()
    {
        // Simply increment version
        // Old caches become unreachable instantly
        $newVersion = RedisCacheService::incrementVersion();

        return "Homepage invalidated. New version: {$newVersion}";
    }

    /**
     * Example 10: Search with caching
     * Use this for: Search results page
     */
    public function cacheSearchResults(string $query, int $page = 1)
    {
        $key = RedisKeyManager::searchQuery($query, $page);

        return RedisCacheService::remember($key, 1800, function () use ($query, $page) {
            $results = Product::search($query)
                ->where('status', 'active')
                ->paginate(12, ['*'], 'page', $page);

            return [
                'query' => $query,
                'products' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'last_page' => $results->lastPage(),
                    'total' => $results->total(),
                ],
            ];
        });
    }

    /**
     * Example 11: User cart caching
     * Use this for: Cart operations
     */
    public function cacheUserCart(int $userId)
    {
        $key = RedisKeyManager::userCart($userId);

        return RedisCacheService::remember($key, 900, function () use ($userId) {
            return \App\Models\Cart::where('user_id', $userId)
                ->with('product', 'variant')
                ->get();
        });
    }

    /**
     * Invalidate user cart after update
     */
    public function invalidateUserCart(int $userId)
    {
        $key = RedisKeyManager::userCart($userId);
        RedisCacheService::forget($key);
    }

    /**
     * Example 12: User wishlist caching
     * Use this for: Wishlist operations
     */
    public function cacheUserWishlist(int $userId)
    {
        $key = RedisKeyManager::userWishlist($userId);

        return RedisCacheService::remember($key, 1800, function () use ($userId) {
            return \App\Models\Wishlist::where('user_id', $userId)
                ->with('product')
                ->get();
        });
    }

    /**
     * Example 13: Recently viewed products
     * Use this for: User's browsing history
     */
    public function addToRecentlyViewed(int $userId, int $productId)
    {
        $key = RedisKeyManager::userRecent($userId);

        // Get current list
        $recent = RedisCacheService::get($key) ?? [];

        // Remove if already exists (to move to front)
        $recent = array_filter($recent, fn ($id) => $id !== $productId);

        // Add to front
        array_unshift($recent, $productId);

        // Keep only last 20
        $recent = array_slice($recent, 0, 20);

        // Save back
        RedisCacheService::put($key, $recent, 7200);

        return $recent;
    }

    /**
     * Example 14: Distributed locking for critical operations
     * Use this for: Preventing race conditions
     */
    public function rebuildIndexesWithLock()
    {
        $lockKey = RedisKeyManager::tempLock('rebuild-indexes');

        if (RedisCacheService::lock($lockKey, 300)) {
            try {
                // Only one process executes this
                $this->buildAllIndexes();

                return 'Indexes rebuilt successfully';
            } finally {
                RedisCacheService::unlock($lockKey);
            }
        } else {
            return 'Rebuild already in progress';
        }
    }

    /**
     * Example 15: Cache invalidation on product update
     * Use this in: ProductObserver
     */
    public function invalidateProductCaches(int $productId)
    {
        // Invalidate all caches for this product
        $pattern = RedisKeyManager::patternProduct($productId);
        RedisCacheService::forgetPattern($pattern);

        // Also invalidate variant caches
        $variantsKey = RedisKeyManager::variantsForProduct($productId);
        $variantIds = RedisCacheService::get($variantsKey);

        if ($variantIds) {
            foreach ($variantIds as $variantId) {
                // Invalidate variant full, card, images, etc.
                RedisCacheService::forget(RedisKeyManager::variantFull($variantId));
                RedisCacheService::forget(RedisKeyManager::variantCard($variantId));
                RedisCacheService::forget(RedisKeyManager::variantImages($variantId));
            }
        }

        // Invalidate category page
        $product = Product::find($productId);
        if ($product && $product->cat_id) {
            $categoryPattern = RedisKeyManager::patternCategory($product->cat_id);
            RedisCacheService::forgetPattern($categoryPattern);
        }
    }

    // Helper methods for homepage example
    private function getBanners()
    {
        $key = RedisKeyManager::componentBanners();

        return RedisCacheService::remember($key, 21600, function () {
            return \App\Models\Banner::where('status', 'active')->get();
        });
    }

    private function getFeaturedProducts()
    {
        $key = RedisKeyManager::componentFeatured();

        return RedisCacheService::remember($key, 3600, function () {
            $productIds = Product::where('is_featured', true)
                ->where('status', 'active')
                ->limit(12)
                ->pluck('id')
                ->toArray();

            return $this->batchCacheProductCards($productIds);
        });
    }

    private function getNewArrivals()
    {
        $key = RedisKeyManager::componentNewArrivals();

        return RedisCacheService::remember($key, 1800, function () {
            $productIds = Product::where('status', 'active')
                ->latest()
                ->limit(8)
                ->pluck('id')
                ->toArray();

            return $this->batchCacheProductCards($productIds);
        });
    }

    private function getBestsellers()
    {
        $key = RedisKeyManager::componentDeals();

        return RedisCacheService::remember($key, 3600, function () {
            // Assuming you have a best_sellers logic
            $productIds = Product::where('status', 'active')
                ->orderByDesc('sales_count')
                ->limit(8)
                ->pluck('id')
                ->toArray();

            return $this->batchCacheProductCards($productIds);
        });
    }

    private function buildAllIndexes()
    {
        $categories = Category::all();
        foreach ($categories as $category) {
            $this->buildCategoryIndex($category->id);
        }
    }
}
