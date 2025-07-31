<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ElasticsearchService;
use App\Services\RedisCacheManager;
use App\Helpers\RedisHelper;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    private ElasticsearchService $elasticsearch;
    private RedisCacheManager $cacheManager;

    public function __construct(
        ElasticsearchService $elasticsearch,
        RedisCacheManager $cacheManager
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->cacheManager = $cacheManager;
    }

    public function created(Product $product): void
    {
        try {
            // Index in Elasticsearch if active
            if ($product->status === 'active') {
                $this->elasticsearch->indexProduct($product->toSearchableArray());
            }

            // Cache the product
            $this->cacheProduct($product);

            // Clear all product-related caches including homepage
            $this->clearAllProductRelatedCaches($product);

            Log::info("Product created and all related caches cleared", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to process product creation", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updated(Product $product): void
    {
        try {
            $wasActive = $product->getOriginal('status') === 'active';
            $isActive = $product->status === 'active';

            // Handle Elasticsearch indexing
            if ($isActive) {
                $this->elasticsearch->indexProduct($product->toSearchableArray());
            } elseif ($wasActive && !$isActive) {
                // Product was deactivated, remove from search index
                $this->deleteFromElasticsearch($product->id);
            }

            // Update cache
            $this->cacheProduct($product);

            // Always clear all product-related caches on update
            $this->clearAllProductRelatedCaches($product);

            Log::info("Product updated and all related caches cleared", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to process product update", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function saved(Product $product): void
    {
        // This is called after both create and update
        // Clear all product-related caches to ensure consistency
        try {
            $this->clearAllProductRelatedCaches($product, true);
            Log::info("Product saved - comprehensive cache clearing completed", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to clear caches after product save", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function deleted(Product $product): void
    {
        try {
            // Remove from Elasticsearch
            $this->deleteFromElasticsearch($product->id);

            // Clear product cache
            $this->clearProductCache($product->id);

            // Clear all related caches
            $this->clearAllProductRelatedCaches($product);

            Log::info("Product deleted and all caches cleared", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to process product deletion", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function forceDeleted(Product $product): void
    {
        try {
            // Remove from Elasticsearch
            $this->deleteFromElasticsearch($product->id);

            // Clear all related caches
            $this->clearProductCache($product->id);
            $this->clearAllProductRelatedCaches($product);

            Log::info("Product force deleted and all caches cleared", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to process product force deletion", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function restored(Product $product): void
    {
        try {
            // Re-index in Elasticsearch if active
            if ($product->status === 'active') {
                $this->elasticsearch->indexProduct($product->toSearchableArray());
            }

            // Cache the restored product
            $this->cacheProduct($product);

            // Clear all related caches
            $this->clearAllProductRelatedCaches($product);

            Log::info("Product restored and all caches cleared", ['product_id' => $product->id]);
        } catch (\Exception $e) {
            Log::error("Failed to process product restoration", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cache product data
     */
    private function cacheProduct(Product $product): void
    {
        try {
            // Cache using RedisCacheManager
            $this->cacheManager->put('product', $product->id, $product->toArray());

            // Also cache using RedisHelper for consistency
            $cacheKey = "product:{$product->id}";
            $ttl = config('cache_keys.ttl.product', 3600);
            RedisHelper::put($cacheKey, $product->toArray(), $ttl);
        } catch (\Exception $e) {
            Log::error("Failed to cache product", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear product-specific cache
     */
    private function clearProductCache(int|string $id): void
    {
        try {
            // Clear using RedisCacheManager
            $this->cacheManager->forget('product', $id);

            // Clear using RedisHelper pattern
            $productKeys = RedisHelper::keys("product:{$id}*");
            if (!empty($productKeys)) {
                RedisHelper::forgetMany($productKeys);
            }
        } catch (\Exception $e) {
            Log::error("Failed to clear product cache", [
                'product_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Comprehensive cache clearing for all product-related data including homepage
     */
    private function clearAllProductRelatedCaches(Product $product, bool $isFromSaved = false): void
    {
        try {
            $clearedPatterns = [];

            // Define all cache patterns that need to be cleared
            $cachePatterns = [
                // Homepage and main pages
                'cache:homepage:*',
                'page:home*',
                'home:*',

                // Product-related caches
                'product:*',
                'products:*',
                'product_*',

                // Category caches (if product belongs to categories)
                'category:*',
                'categories:*',
                'cat:*',

                // Search and filtering caches
                'search:*',
                'filter:*',
                'facet:*',

                // Featured products and recommendations
                'cache:featured:*',
                'featured:*',
                'recommended:*',
                'related:*',

                // Product listings and pagination
                'listing:*',
                'page:*',
                'pagination:*',

                // Brand caches (if product has brand)
                'brand:*',
                'brands:*',

                // Price and discount caches
                'price:*',
                'discount:*',
                'offer:*',

                // User-specific caches that might be affected
                'user:*:cart:*',
                'user:*:wishlist:*',

                // Review and rating caches
                'review:*',
                'rating:*',

                // Inventory and stock caches
                'stock:*',
                'inventory:*',

                // SEO and sitemap caches
                'sitemap:*',
                'seo:*',
                'meta:*',

                // API response caches
                'api:*',
                'response:*'
            ];

            // Clear each pattern
            foreach ($cachePatterns as $pattern) {
                try {
                    $keys = RedisHelper::keys($pattern);
                    if (!empty($keys)) {
                        RedisHelper::forgetMany($keys);
                        $clearedPatterns[] = $pattern . ' (' . count($keys) . ' keys)';
                    }
                } catch (\Exception $e) {
                    Log::warning("Failed to clear cache pattern: {$pattern}", [
                        'product_id' => $product->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Clear specific product cache
            $this->clearProductCache($product->id);

            // Clear category-specific caches if product has categories
            if ($product->cat_id) {
                $this->clearCategorySpecificCaches($product->cat_id);
            }

            if ($product->child_cat_id) {
                $this->clearCategorySpecificCaches($product->child_cat_id);
            }

            // Clear brand-specific caches if product has brand
            if ($product->brand_id) {
                $this->clearBrandSpecificCaches($product->brand_id);
            }

            // Clear homepage cache using cache manager
            $this->cacheManager->forget('page', 'home');

            // Additional comprehensive clearing for Laravel cache
            $this->clearLaravelCachePatterns();

            $logContext = [
                'product_id' => $product->id,
                'cleared_patterns' => $clearedPatterns,
                'is_from_saved_event' => $isFromSaved
            ];

            Log::info("Comprehensive product-related cache clearing completed", $logContext);
        } catch (\Exception $e) {
            Log::error("Failed to clear all product-related caches", [
                'product_id' => $product->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear category-specific caches
     */
    private function clearCategorySpecificCaches(int $categoryId): void
    {
        try {
            $categoryPatterns = [
                "category:{$categoryId}*",
                "cat:{$categoryId}*",
                "categories:*{$categoryId}*"
            ];

            foreach ($categoryPatterns as $pattern) {
                $keys = RedisHelper::keys($pattern);
                if (!empty($keys)) {
                    RedisHelper::forgetMany($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to clear category-specific caches for category: {$categoryId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear brand-specific caches
     */
    private function clearBrandSpecificCaches(int $brandId): void
    {
        try {
            $brandPatterns = [
                "brand:{$brandId}*",
                "brands:*{$brandId}*"
            ];

            foreach ($brandPatterns as $pattern) {
                $keys = RedisHelper::keys($pattern);
                if (!empty($keys)) {
                    RedisHelper::forgetMany($keys);
                }
            }
        } catch (\Exception $e) {
            Log::warning("Failed to clear brand-specific caches for brand: {$brandId}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Clear Laravel cache patterns that might not be in Redis
     */
    private function clearLaravelCachePatterns(): void
    {
        try {
            $laravelCacheKeys = [
                'homepage',
                'products',
                'categories',
                'featured_products',
                'latest_products',
                'popular_products',
                'search_results',
                'product_filters'
            ];

            foreach ($laravelCacheKeys as $key) {
                \Illuminate\Support\Facades\Cache::forget($key);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to clear Laravel cache patterns", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Delete product from Elasticsearch
     */
    private function deleteFromElasticsearch(int|string $productId): void
    {
        try {
            $client = app(Client::class);
            $client->delete([
                'index' => config('elasticsearch.index'),
                'id' => $productId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete product from Elasticsearch', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Emergency method to clear ALL product-related caches (use with caution)
     */
    public function clearAllProductCaches(): void
    {
        try {
            $patterns = [
                'product:*',
                'products:*',
                'cache:homepage:*',
                'category:*',
                'search:*',
                'cache:featured:*',
                'brand:*',
                'listing:*',
                'page:*',
                'user:*:cart:*',
                'user:*:wishlist:*',
                'api:*',
                'response:*'
            ];

            $totalCleared = 0;

            foreach ($patterns as $pattern) {
                $keys = RedisHelper::keys($pattern);
                if (!empty($keys)) {
                    RedisHelper::forgetMany($keys);
                    $totalCleared += count($keys);
                    Log::info("Cleared cache pattern: {$pattern}", ['count' => count($keys)]);
                }
            }

            // Also clear Laravel cache
            \Illuminate\Support\Facades\Cache::flush();

            Log::info("Emergency cache clearing completed", ['total_keys_cleared' => $totalCleared]);
        } catch (\Exception $e) {
            Log::error("Failed to clear all product caches", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if related caches should be cleared based on changed attributes
     * (This method is now less relevant since we clear all caches on save)
     */
    private function shouldClearRelatedCaches(Product $product): bool
    {
        // Since we're now clearing all caches on save, this always returns true
        // But keeping the logic for potential future optimizations
        $importantFields = [
            'title',
            'slug',
            'status',
            'price',
            'offer_price',
            'stock',
            'cat_id',
            'child_cat_id',
            'brand_id',
            'photo',
            'is_featured',
            'condition'
        ];

        foreach ($importantFields as $field) {
            if ($product->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
