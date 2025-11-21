<?php

namespace App\Jobs;

use App\Helpers\ImageHelper;
use App\Services\RedisCacheService;
use App\Services\RedisCacheLogger;
use App\Models\{Product, Category, Banner, Settings};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WarmHomepageCacheJob
 *
 * Warms up Redis cache for homepage components in the background.
 * This job runs on server startup to ensure instant homepage loads.
 */
class WarmHomepageCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120; // 2 minutes max
    public $tries = 1; // Don't retry - cache warming is optional
    public $failOnTimeout = false;

    private const HOMEPAGE_ALL_PRODUCTS_LIMIT = 12;
    private const PRODUCTS_PER_CATEGORY_SECTION = 12;
    private const MAX_CATEGORY_SECTIONS = 4;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $startTime = microtime(true);

        try {
            if (!Config::get('redis_cache.enabled.master', false)) {
                Log::info('Cache warming skipped: Redis cache disabled');
                return;
            }

            if (!Config::get('cache_warmup.enabled', true)) {
                Log::info('Cache warming skipped: Auto warmup disabled');
                return;
            }

            // Clear the cache operations log for new warmup session
            RedisCacheLogger::clearLog();

            Log::info('🔥 Starting homepage cache warmup...');

            $ttl = Config::get('redis_cache.ttl', []);
            $warmed = [];

            // Get cache version for versioned keys
            $cacheVersion = RedisCacheService::getVersion();

            // 1. Warm Settings (critical for all pages)
            if ($this->warmSettings($ttl, $cacheVersion)) {
                $warmed[] = 'settings';
            }

            // 2. Warm Categories
            if ($this->warmCategories($ttl, $cacheVersion)) {
                $warmed[] = 'categories';
            }

            // 3. Warm Banners
            if ($this->warmBanners($ttl, $cacheVersion)) {
                $warmed[] = 'banners';
            }

            // 4. Warm Featured Products (All Products section)
            if ($this->warmFeaturedProducts($ttl, $cacheVersion)) {
                $warmed[] = 'featured_products';
            }

            // 5. Warm Category Products
            if ($this->warmCategoryProducts($ttl, $cacheVersion)) {
                $warmed[] = 'category_products';
            }

            // 6. Mark cache as warmed
            RedisCacheService::put('meta:cache:warmed', true, 3600);
            RedisCacheService::put('meta:cache:warmed_at', now()->toIso8601String(), 3600);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('✅ Homepage cache warmup completed', [
                'duration_ms' => $duration,
                'components_warmed' => $warmed,
                'timestamp' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('❌ Homepage cache warmup failed', [
                'error' => $e->getMessage(),
                'duration_ms' => $duration,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Warm settings cache
     */
    private function warmSettings(array $ttl, int $cacheVersion): bool
    {
        try {
            $key = "cache:homepage:settings_v{$cacheVersion}";

            if (RedisCacheService::has($key)) {
                return true; // Already cached
            }

            $settings = Settings::select([
                'description',
                'short_des',
                'photo',
                'address',
                'phone',
                'email',
                'logo'
            ])->first();

            if ($settings) {
                RedisCacheService::put($key, $settings, $ttl['settings'] ?? 86400);
                Cache::put($key, $settings, $ttl['settings'] ?? 86400);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to warm settings cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm categories cache
     */
    private function warmCategories(array $ttl, int $cacheVersion): bool
    {
        try {
            $key = "cache:homepage:categories_v{$cacheVersion}";

            if (RedisCacheService::has($key)) {
                return true;
            }

            $categories = Category::select(['id', 'title', 'slug', 'photo'])
                ->where('status', 'active')
                ->whereNull('parent_id')
                ->orderBy('title', 'ASC')
                ->get();

            if ($categories->isNotEmpty()) {
                RedisCacheService::put($key, $categories, $ttl['categories'] ?? 43200);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to warm categories cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm banners cache
     */
    private function warmBanners(array $ttl, int $cacheVersion): bool
    {
        try {
            $key = "cache:homepage:banners_v{$cacheVersion}";

            if (RedisCacheService::has($key)) {
                return true;
            }

            $banners = Banner::select(['id', 'title', 'slug', 'photo', 'description', 'status', 'link_type', 'link'])
                ->where('status', 'active')
                ->orderBy('created_at', 'DESC')
                ->limit(5)
                ->get();

            if ($banners->isNotEmpty()) {
                RedisCacheService::put($key, $banners, $ttl['banners'] ?? 21600);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to warm banners cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm featured products (All Products section)
     */
    private function warmFeaturedProducts(array $ttl, int $cacheVersion): bool
    {
        try {
            $key = "cache:homepage:products:featured_v{$cacheVersion}";

            if (RedisCacheService::has($key)) {
                return true;
            }

            // Get featured product IDs
            $productIds = DB::table('products')
                ->where('status', 'active')
                ->where('is_featured', 1)
                ->inRandomOrder()
                ->limit(self::HOMEPAGE_ALL_PRODUCTS_LIMIT)
                ->pluck('id')
                ->toArray();

            if (empty($productIds)) {
                return false;
            }

            // Fetch and cache individual product cards
            $products = Product::whereIn('id', $productIds)
                ->with([
                    'brand' => function ($query) {
                        $query->select(['id', 'title', 'slug']);
                    },
                    'variants' => function ($query) {
                        $query->select(['id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status'])
                            ->where('status', 'active')
                            ->where('stock', '>', 0);
                    },
                    'variants.images' => function ($query) {
                        $query->select(['id', 'product_variant_id', 'image_path', 'is_primary']);
                    },
                    'images' => function ($query) {
                        $query->select(['id', 'product_id', 'image_path', 'is_primary', 'sort_order']);
                    }
                ])
                ->get();

            $transformedProducts = [];
            foreach ($products as $product) {
                // Debug: Check if images are loaded
                if (!$product->has_variants && $product->images->count() == 0) {
                    Log::warning("Product {$product->id} has no images loaded in warmup", [
                        'has_variants' => $product->has_variants,
                        'images_loaded' => $product->relationLoaded('images'),
                        'images_count' => $product->images->count(),
                    ]);
                }

                $transformed = $this->transformProductForDisplay($product);

                // Log transformation for debugging
                RedisCacheLogger::logProductTransform(
                    $product->id,
                    $product,
                    $transformed,
                    'featured_products_warmup'
                );

                $transformedProducts[] = $transformed;

                // Cache individual product card
                $cardKey = "product:card:{$product->id}";
                RedisCacheService::put($cardKey, $transformed, $ttl['product_card'] ?? 7200);

                // Log the cache PUT operation
                RedisCacheLogger::logPut($cardKey, $transformed, $ttl['product_card'] ?? 7200, 'individual_product_card');
            }            // Cache the complete component
            RedisCacheService::put($key, $transformedProducts, $ttl['homepage_full'] ?? 1800);

            // Log the cache PUT operation
            RedisCacheLogger::logPut($key, $transformedProducts, $ttl['homepage_full'] ?? 1800, 'featured_products_collection');

            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to warm featured products cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm category products
     */
    private function warmCategoryProducts(array $ttl, int $cacheVersion): bool
    {
        try {
            $key = "cache:homepage:category_products_v{$cacheVersion}";

            if (RedisCacheService::has($key)) {
                return true;
            }

            // Get active categories
            $categories = Category::select(['id', 'title', 'slug', 'sort_order'])
                ->where('status', 'active')
                ->whereNull('parent_id')
                ->orderBy('sort_order', 'ASC')
                ->orderBy('title', 'ASC')
                ->get();

            if ($categories->isEmpty()) {
                return false;
            }

            $categoryProducts = [];
            $categoriesProcessed = 0;

            foreach ($categories as $category) {
                if ($categoriesProcessed >= self::MAX_CATEGORY_SECTIONS) {
                    break;
                }

                // Get product IDs for this category
                $productIds = DB::table('products')
                    ->where('products.status', 'active')
                    ->where('products.cat_id', $category->id)
                    ->where('products.is_featured', 1)
                    ->inRandomOrder()
                    ->limit(self::PRODUCTS_PER_CATEGORY_SECTION)
                    ->pluck('products.id')
                    ->toArray();

                if (count($productIds) < 4) {
                    continue; // Skip categories with too few products
                }

                // Fetch products
                $products = Product::whereIn('id', $productIds)
                    ->with([
                        'brand' => function ($query) {
                            $query->select(['id', 'title', 'slug']);
                        },
                        'variants' => function ($query) {
                            $query->select(['id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status'])
                                ->where('status', 'active')
                                ->where('stock', '>', 0);
                        },
                        'variants.images' => function ($query) {
                            $query->select(['id', 'product_variant_id', 'image_path', 'is_primary']);
                        },
                        'images' => function ($query) {
                            $query->select(['id', 'product_id', 'image_path', 'is_primary', 'sort_order']);
                        }
                    ])
                    ->get();

                $transformedProducts = [];
                foreach ($products as $product) {
                    $transformed = $this->transformProductForDisplay($product);
                    $transformedProducts[] = $transformed;

                    // Cache individual product card
                    RedisCacheService::put(
                        "product:card:{$product->id}",
                        $transformed,
                        $ttl['product_card'] ?? 7200
                    );
                }

                if (!empty($transformedProducts)) {
                    $categoryProducts[$category->slug] = [
                        'id' => $category->id,
                        'title' => $category->title,
                        'slug' => $category->slug,
                        'products' => $transformedProducts,
                        'count' => count($transformedProducts)
                    ];
                    $categoriesProcessed++;
                }
            }

            if (!empty($categoryProducts)) {
                RedisCacheService::put($key, $categoryProducts, $ttl['homepage_full'] ?? 1800);
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to warm category products cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Transform product for display (reused from controller)
     */
    private function transformProductForDisplay($product)
    {
        $images = [];
        $variantsData = [];
        $variantStockTotal = 0;
        $variantMaxDiscount = 0;

        if ($product->has_variants && $product->variants && $product->variants->count() > 0) {
            foreach ($product->variants as $variant) {
                if ($variant->status === 'active' && $variant->stock > 0) {
                    $variantStockTotal += $variant->stock;

                    // Build variant images array
                    $variantImageArray = [];
                    if ($variant->images && $variant->images->count() > 0) {
                        foreach ($variant->images->take(3) as $img) {
                            $imagePath = !empty($img->image_path)
                                ? ImageHelper::variantImageUrl($img->image_path)
                                : asset('images/no-image.png');

                            $variantImageArray[] = [
                                'image_path' => $imagePath,
                                'alt_text' => $product->title,
                            ];
                        }
                    }                    $variantsData[] = [
                        'id' => $variant->id,
                        'price' => (float)$variant->price,
                        'discount' => (float)($variant->discount ?? 0),
                        'stock' => (int)$variant->stock,
                        'status' => $variant->status,
                        'images' => $variantImageArray,
                    ];

                    if (($variant->discount ?? 0) > $variantMaxDiscount) {
                        $variantMaxDiscount = $variant->discount;
                    }

                    // Use first variant images if main images still empty
                    if (empty($images) && !empty($variantImageArray)) {
                        $images = $variantImageArray;
                    }
                }
            }
        }

        // For simple products or if no variant images, use product images
        if (empty($images) && $product->images && $product->images->count() > 0) {
            foreach ($product->images->take(3) as $img) {
                $imagePath = !empty($img->image_path)
                    ? ImageHelper::productImageUrl($img->image_path)
                    : asset('images/no-image.png');

                $images[] = [
                    'image_path' => $imagePath,
                    'alt_text' => $product->title,
                ];
            }
        }        // Fallback to placeholder if still no images
        if (empty($images)) {
            $images[] = [
                'image_path' => asset('images/no-image.png'),
                'alt_text' => $product->title,
            ];
        }

        // Calculate stock and max discount
        if ($product->has_variants && count($variantsData) > 0) {
            $stock = $variantStockTotal;
            $maxDiscount = $variantMaxDiscount ?: ($product->base_discount ?? 0);
        } else {
            $stock = $product->base_stock ?? 0;
            $maxDiscount = $product->base_discount ?? 0;
        }

        return (object)[
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'base_price' => (float)$product->base_price,
            'base_discount' => (float)($product->base_discount ?? 0),
            'base_stock' => (int)($product->base_stock ?? 0),
            'has_variants' => (bool)$product->has_variants,
            'cat_id' => $product->cat_id,
            'condition' => $product->condition ?? 'default',
            'stock' => $stock,
            'max_discount' => $maxDiscount,
            'images' => $images,
            'variants' => $variantsData,
            'brand' => $product->brand ? (object)[
                'id' => $product->brand->id,
                'title' => $product->brand->title,
                'slug' => $product->brand->slug,
            ] : null,
            'rating_average' => $product->rating_average ?? 0,
            'rating_count' => $product->rating_count ?? 0,
        ];
    }
}
