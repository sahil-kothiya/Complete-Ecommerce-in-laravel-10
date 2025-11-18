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

        // Immediate cache operations (fast - synchronous)
        $this->invalidateProductCaches($product);

        // Only invalidate homepage if featured product
        if ($product->status === 'active' && $product->is_featured) {
            $this->invalidateHomepageCaches();
        }

        // Queue time-consuming operations
        if ($product->status === 'active') {
            // Index in Elasticsearch (queued)
            IndexProductInElasticsearch::dispatch($product->toSearchableArray())
                ->onQueue('search')
                ->delay(now()->addSeconds(5));
        }
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

        if (!$significantChange) {
            Log::debug("Product {$product->id}: No significant changes detected");
            return;
        }

        // Immediate cache invalidation (fast - synchronous)
        $this->invalidateProductCaches($product);

        // Invalidate homepage caches if featured product changed
        if ($this->affectsHomepage($product, $changedFields)) {
            $this->invalidateHomepageCaches();
        }

        // Queue Elasticsearch operations
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
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        Log::info("Product deleted: {$product->id} - {$product->title}");

        // Immediate cache cleanup
        $this->invalidateProductCaches($product);

        if ($product->is_featured) {
            $this->invalidateHomepageCaches();
        }

        // Queue Elasticsearch cleanup
        RemoveProductFromElasticsearch::dispatch($product->id)
            ->onQueue('search')
            ->delay(now()->addSeconds(1));
    }

    /**
     * Invalidate all caches related to a specific product
     */
    private function invalidateProductCaches(Product $product): void
    {
        try {
            $keys = [
                "product:{$product->id}",
                "product:slug:{$product->slug}",
                "product:card:{$product->id}",
                "product:light:{$product->id}",
            ];

            RedisHelper::forgetMany($keys);

            Log::debug("Invalidated product caches for product: {$product->id}");
        } catch (\Exception $e) {
            Log::error("Failed to invalidate product caches for {$product->id}: " . $e->getMessage());
        }
    }

    /**
     * Invalidate homepage caches
     * Uses versioning strategy for atomic invalidation
     */
    private function invalidateHomepageCaches(): void
    {
        try {
            // Strategy 1: Increment cache version (atomic, instant invalidation)
            $newVersion = RedisHelper::incrementVersion('meta:cache:version');

            Log::info("Invalidated homepage cache by incrementing version to: {$newVersion}");

            // Strategy 2: Also clear component caches for fresh rebuild
            $componentKeys = [
                'cache:homepage:products:featured',
                'cache:homepage:category_products',
            ];

            RedisHelper::forgetMany($componentKeys);

            Log::debug("Cleared homepage component caches");
        } catch (\Exception $e) {
            Log::error("Failed to invalidate homepage caches: " . $e->getMessage());
        }
    }

    /**
     * Check if product changes affect the homepage
     */
    private function affectsHomepage(Product $product, array $changedFields): bool
    {
        // Only invalidate homepage if featured product changed
        if (!$product->is_featured) {
            return false;
        }

        $homepageFields = [
            'title', 'slug', 'base_price', 'base_discount',
            'status', 'base_stock', 'is_featured', 'cat_id',
            'condition'
        ];

        return !empty(array_intersect($changedFields, $homepageFields));
    }

    /**
     * Check if the product has significant changes
     */
    private function hasSignificantChange(array $changedFields): bool
    {
        $significantFields = [
            'title', 'slug', 'base_price', 'base_discount',
            'status', 'base_stock', 'condition', 'cat_id',
            'child_cat_id', 'brand_id', 'is_featured'
        ];

        return !empty(array_intersect($changedFields, $significantFields));
    }
}
