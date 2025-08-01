<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\ElasticsearchService;
use App\Helpers\RedisHelper;
use App\Jobs\IndexProductInElasticsearch;
use App\Jobs\RemoveProductFromElasticsearch;
use App\Jobs\ClearProductRelatedCaches;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ProductObserver
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        Log::info("Product created: {$product->id} - {$product->title}");

        // Immediate cache operations (fast)
        $this->handleImmediateCacheOperations($product);

        // Queue time-consuming operations
        if ($product->status === 'active') {
            // Index in Elasticsearch (queued)
            IndexProductInElasticsearch::dispatch($product->toSearchableArray())
                ->onQueue('search')
                ->delay(now()->addSeconds(5));
        }

        // Clear related caches (queued)
        ClearProductRelatedCaches::dispatch($product, 'created')
            ->onQueue('cache')
            ->delay(now()->addSeconds(2));
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        Log::info("Product updated: {$product->id} - {$product->title}");

        // Check what changed
        $changedFields = array_keys($product->getDirty());
        $significantChange = $this->hasSignificantChange($changedFields);

        // Immediate cache operations (fast)
        $this->handleImmediateCacheOperations($product);

        // Queue Elasticsearch operations only if significant changes
        if ($significantChange) {
            if ($product->status === 'active') {
                // Update in Elasticsearch (queued)
                IndexProductInElasticsearch::dispatch($product->toSearchableArray())
                    ->onQueue('search')
                    ->delay(now()->addSeconds(3));
            } else {
                // Remove from Elasticsearch if no longer active (queued)
                RemoveProductFromElasticsearch::dispatch($product->id)
                    ->onQueue('search')
                    ->delay(now()->addSeconds(3));
            }

            // Clear related caches only for significant changes (queued)
            ClearProductRelatedCaches::dispatch($product, 'updated', $changedFields)
                ->onQueue('cache')
                ->delay(now()->addSeconds(1));
        }
    }

    /**
     * Handle the Product "saved" event (covers both created and updated).
     */
    public function saved(Product $product): void
    {
        // This runs after created/updated, so we mainly handle immediate caching here
        Log::debug("ProductOB:Product saved: {$product->id}");

        // Update individual product cache immediately (fast operation)
        $this->updateIndividualProductCache($product);
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        Log::info("Product deleted: {$product->id} - {$product->title}");

        // Immediate cache cleanup (fast)
        $this->clearIndividualProductCache($product->id);

        // Queue time-consuming operations
        RemoveProductFromElasticsearch::dispatch($product->id)
            ->onQueue('search')
            ->delay(now()->addSeconds(1));

        ClearProductRelatedCaches::dispatch($product, 'deleted')
            ->onQueue('cache')
            ->delay(now()->addSeconds(1));
    }

    /**
     * Handle the Product "forceDeleted" event.
     */
    public function forceDeleted(Product $product): void
    {
        Log::info("Product force deleted: {$product->id}");

        $this->clearIndividualProductCache($product->id);

        // Queue cleanup operations
        RemoveProductFromElasticsearch::dispatch($product->id)
            ->onQueue('search');

        ClearProductRelatedCaches::dispatch($product, 'force_deleted')
            ->onQueue('cache');
    }

    /**
     * Handle the Product "restored" event.
     */
    public function restored(Product $product): void
    {
        Log::info("Product restored: {$product->id} - {$product->title}");

        // Update cache immediately
        $this->updateIndividualProductCache($product);

        // Queue operations
        if ($product->status === 'active') {
            IndexProductInElasticsearch::dispatch($product->toSearchableArray())
                ->onQueue('search')
                ->delay(now()->addSeconds(5));
        }

        ClearProductRelatedCaches::dispatch($product, 'restored')
            ->onQueue('cache')
            ->delay(now()->addSeconds(2));
    }

    /**
     * Handle immediate cache operations that should happen synchronously.
     */
    private function handleImmediateCacheOperations(Product $product): void
    {
        try {
            // Update individual product cache (fast operation)
            $this->updateIndividualProductCache($product);

            // Clear critical homepage caches immediately (fast operation)
            $this->clearCriticalHomepageCaches();
        } catch (\Exception $e) {
            Log::error("ProductOB:Immediate cache operations failed for product {$product->id}: " . $e->getMessage());
        }
    }

    /**
     * Update individual product cache.
     */
    private function updateIndividualProductCache(Product $product): void
    {
        try {
            $cacheKey = "cache:product:{$product->id}";
            $slugCacheKey = "cache:product:slug:{$product->slug}";

            // Cache product data with relationships
            $productData = $product->load(['images', 'cat_info', 'brand', 'discounts']);

            // Cache for 1 hour (individual products change less frequently)
            $ttl = 3600;

            RedisHelper::put($cacheKey, $productData, $ttl);
            RedisHelper::put($slugCacheKey, $productData, $ttl);

            Log::debug("ProductOB:Updated individual product cache: {$product->id}");
        } catch (\Exception $e) {
            Log::error("ProductOB:Failed to update individual product cache for {$product->id}: " . $e->getMessage());
        }
    }

    /**
     * Clear individual product cache.
     */
    private function clearIndividualProductCache(int|string $productId): void
    {
        try {
            $keys = [
                "cache:product:{$productId}",
                "cache:product:slug:*", // We'll need to clear all slug caches or find specific one
            ];

            // Clear specific product caches
            RedisHelper::forget("cache:product:{$productId}");

            // Clear slug-based cache (this might need optimization)
            $slugKeys = RedisHelper::keys("cache:product:slug:*");
            foreach ($slugKeys as $key) {
                $cachedProduct = RedisHelper::get($key);
                if ($cachedProduct && isset($cachedProduct['id']) && $cachedProduct['id'] == $productId) {
                    RedisHelper::forget($key);
                    break;
                }
            }

            Log::debug("ProductOB:Cleared individual product cache: {$productId}");
        } catch (\Exception $e) {
            Log::error("ProductOB:Failed to clear individual product cache for {$productId}: " . $e->getMessage());
        }
    }

    /**
     * Clear critical homepage caches immediately.
     */
    private function clearCriticalHomepageCaches(): void
    {
        try {
            $criticalCaches = [
                'cache:homepage:product_lists',
                // 'cache:homepage:categories', // If product count affects categories
                'cache:product_grids:recent_products',
                'cache:product_grids:max_price',
            ];

            RedisHelper::forgetMany($criticalCaches);

            Log::debug("ProductOB:Cleared critical homepage caches");
        } catch (\Exception $e) {
            Log::error("ProductOB:Failed to clear critical homepage caches: " . $e->getMessage());
        }
    }

    /**
     * Check if the product has significant changes that require cache clearing.
     */
    private function hasSignificantChange(array $changedFields): bool
    {
        $significantFields = [
            'title',
            'slug',
            'price',
            'discount',
            'status',
            'stock',
            'condition',
            'cat_id',
            'child_cat_id',
            'brand_id',
            'is_featured'
        ];

        return !empty(array_intersect($changedFields, $significantFields));
    }
}
