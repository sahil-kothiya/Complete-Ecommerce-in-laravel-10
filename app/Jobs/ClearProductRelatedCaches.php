<?php

namespace App\Jobs;

use App\Helpers\RedisHelper;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ClearProductRelatedCaches implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    private array $productData;
    private string $action;
    private array $changedFields;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product, string $action, array $changedFields = [])
    {
        // Only store essential product data to avoid serialization issues
        $this->productData = [
            'id' => $product->id,
            'slug' => $product->slug,
            'cat_id' => $product->cat_id,
            'child_cat_id' => $product->child_cat_id,
            'brand_id' => $product->brand_id,
            'status' => $product->status,
            'is_featured' => $product->is_featured ?? false,
        ];

        $this->action = $action;
        $this->changedFields = $changedFields;
        $this->onQueue('cache');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Clearing product-related caches for product {$this->productData['id']} (action: {$this->action})");

            $startTime = microtime(true);

            // Clear different cache types based on action and changed fields
            $this->clearHomepageCaches();
            $this->clearCategoryCaches();
            $this->clearProductGridsCaches();
            $this->clearBrandCaches();
            $this->clearSearchCaches();

            // Clear pagination caches if needed
            if ($this->shouldClearPaginationCaches()) {
                $this->clearPaginationCaches();
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Cleared product-related caches in {$duration}ms for product {$this->productData['id']}");
        } catch (\Exception $e) {
            Log::error("Error clearing product-related caches for product {$this->productData['id']}: " . $e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Clear homepage caches.
     */
    private function clearHomepageCaches(): void
    {
        $homepageCaches = [
            'cache:homepage:product_lists',
            'cache:homepage:categories',
            'cache:homepage:category_banners',
        ];

        RedisHelper::forgetMany($homepageCaches);

        // Also clear Laravel cache
        foreach ($homepageCaches as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Clear category-related caches.
     */
    private function clearCategoryCaches(): void
    {
        if (!empty($this->productData['cat_id'])) {
            $categoryCaches = [
                "cache:category:{$this->productData['cat_id']}:products",
                "cache:category:{$this->productData['cat_id']}:count",
            ];

            if (!empty($this->productData['child_cat_id'])) {
                $categoryCaches[] = "cache:subcategory:{$this->productData['child_cat_id']}:products";
                $categoryCaches[] = "cache:subcategory:{$this->productData['child_cat_id']}:count";
            }

            RedisHelper::forgetMany($categoryCaches);

            // Clear category-specific pagination caches
            $this->clearCategoryPaginationCaches();
        }
    }

    /**
     * Clear product grids caches.
     */
    private function clearProductGridsCaches(): void
    {
        $productGridsCaches = [
            'cache:product_grids:recent_products',
            'cache:product_grids:sidebar_categories',
            'cache:product_grids:sidebar_brands',
            'cache:product_grids:max_price',
        ];

        RedisHelper::forgetMany($productGridsCaches);

        // Clear complete page caches with wildcard patterns
        $patterns = [
            'cache:product_grids:complete_page:*',
            'cache:product_grids:cat_ids:*',
            'cache:product_grids:brand_ids:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = RedisHelper::keys($pattern);
            if (!empty($keys)) {
                RedisHelper::forgetMany($keys);
            }
        }
    }

    /**
     * Clear brand-related caches.
     */
    private function clearBrandCaches(): void
    {
        if (!empty($this->productData['brand_id'])) {
            $brandCaches = [
                "cache:brand:{$this->productData['brand_id']}:products",
                "cache:brand:{$this->productData['brand_id']}:count",
            ];

            RedisHelper::forgetMany($brandCaches);
        }
    }

    /**
     * Clear search-related caches.
     */
    private function clearSearchCaches(): void
    {
        // Clear autocomplete caches that might include this product
        $patterns = [
            'autocomplete:*',
            'search:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = RedisHelper::keys($pattern);
            if (!empty($keys)) {
                RedisHelper::forgetMany($keys);
            }
        }
    }

    /**
     * Check if pagination caches should be cleared.
     */
    private function shouldClearPaginationCaches(): bool
    {
        return in_array($this->action, ['created', 'deleted', 'force_deleted']) ||
            in_array('status', $this->changedFields) ||
            in_array('is_featured', $this->changedFields);
    }

    /**
     * Clear pagination caches.
     */
    private function clearPaginationCaches(): void
    {
        $patterns = [
            'cached_products_*',
            'cache:recent_products:*',
        ];

        foreach ($patterns as $pattern) {
            $keys = RedisHelper::keys($pattern);
            if (!empty($keys)) {
                RedisHelper::forgetMany($keys);
                Log::debug("Cleared " . count($keys) . " pagination cache entries for pattern: {$pattern}");
            }
        }
    }

    /**
     * Clear category-specific pagination caches.
     */
    private function clearCategoryPaginationCaches(): void
    {
        if (empty($this->productData['cat_id'])) {
            return;
        }

        $patterns = [
            "cached_products_cat{$this->productData['cat_id']}_*",
        ];

        if (!empty($this->productData['child_cat_id'])) {
            $patterns[] = "cached_products_cat{$this->productData['cat_id']}_childcat{$this->productData['child_cat_id']}_*";
        }

        foreach ($patterns as $pattern) {
            $keys = RedisHelper::keys($pattern);
            if (!empty($keys)) {
                RedisHelper::forgetMany($keys);
                Log::debug("Cleared " . count($keys) . " category pagination cache entries for pattern: {$pattern}");
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ClearProductRelatedCaches job failed for product {$this->productData['id']}: " . $exception->getMessage());

        // You might want to implement alternative cache clearing strategy or notification
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'cache-clear',
            'product-cache',
            "product:{$this->productData['id']}",
            "action:{$this->action}"
        ];
    }
}
