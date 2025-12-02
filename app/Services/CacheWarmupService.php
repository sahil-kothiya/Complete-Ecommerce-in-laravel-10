<?php

namespace App\Services;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CacheWarmupService - Preloads critical cache data
 *
 * This service is responsible for warming up Redis cache
 * during off-peak hours or after cache clear operations
 */
class CacheWarmupService
{
    // DEPRECATED: Use RedisKeyManager instead
    // Kept for backward compatibility, mapped to new ec: structure
    private const HOMEPAGE_CACHE_PREFIX = 'ec:pg:home:';
    // No longer needed - use RedisKeyManager::productCard($id)

    /**
     * Warm up all homepage related caches
     */
    public function warmupHomepage(): array
    {
        $startTime = microtime(true);
        $results = [
            'success' => true,
            'operations' => [],
            'errors' => [],
        ];

        try {
            Log::info('Starting homepage cache warmup');

            // 1. Warm up categories
            $results['operations']['categories'] = $this->warmupCategories();

            // 2. Warm up banners
            $results['operations']['banners'] = $this->warmupBanners();

            // 3. Warm up featured products
            $results['operations']['featured_products'] = $this->warmupFeaturedProducts();

            // 4. Warm up category products
            $results['operations']['category_products'] = $this->warmupCategoryProducts();

            // 5. Build and cache full page
            $results['operations']['full_page'] = $this->warmupFullPage();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $results['duration_ms'] = $duration;

            Log::info("Homepage cache warmup completed in {$duration}ms", $results);
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            Log::error('Homepage cache warmup failed: '.$e->getMessage());
        }

        return $results;
    }

    /**
     * Warm up categories cache
     */
    private function warmupCategories(): array
    {
        $startTime = microtime(true);

        try {
            $key = self::HOMEPAGE_CACHE_PREFIX.'categories';
            $ttl = config('redis_cache.ttl.categories', 43200);

            // Fetch active parent categories
            $categories = Category::select(['id', 'title', 'slug', 'photo'])
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->orderBy('title', 'asc')
                ->limit(10)
                ->get();

            // Cache the data
            RedisCacheService::put($key, $categories, $ttl);

            return [
                'success' => true,
                'key' => $key,
                'count' => $categories->count(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warmup categories: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up banners cache
     */
    private function warmupBanners(): array
    {
        $startTime = microtime(true);

        try {
            $key = self::HOMEPAGE_CACHE_PREFIX.'banners';
            $ttl = config('redis_cache.ttl.banners', 21600);

            $banners = Banner::select(['id', 'title', 'slug', 'photo', 'description', 'status', 'link_type', 'link'])
                ->with(['discounts' => fn ($q) => $q->select(['discounts.id', 'discounts.title', 'discounts.type', 'discounts.value'])
                    ->with(['categories' => fn ($q2) => $q2->select(['categories.id', 'categories.title', 'categories.slug'])]),
                ])
                ->where('status', 'active')
                ->latest('id')
                ->limit(5)
                ->get();

            RedisCacheService::put($key, $banners, $ttl);

            return [
                'success' => true,
                'key' => $key,
                'count' => $banners->count(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warmup banners: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up featured products cache
     */
    private function warmupFeaturedProducts(): array
    {
        $startTime = microtime(true);

        try {
            $key = self::HOMEPAGE_CACHE_PREFIX.'products:featured';
            $ttl = config('redis_cache.ttl.featured_products', 3600);

            // Get featured product IDs (indexed query)
            $productIds = DB::table('products')
                ->select('id')
                ->where('status', 'active')
                ->where('is_featured', 1)
                ->orderBy('id', 'DESC')
                ->limit(20)
                ->pluck('id')
                ->toArray();

            // Fetch products with minimal data
            $products = Product::select([
                'id', 'title', 'slug', 'base_price', 'base_discount',
                'base_stock', 'has_variants', 'cat_id', 'condition',
            ])
                ->whereIn('id', $productIds)
                ->with([
                    'images' => fn ($q) => $q->orderBy('sort_order', 'asc')
                        ->take(3)
                        ->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                    'variants' => fn ($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock', 'status'])
                        ->with([
                            'images' => fn ($q) => $q->orderBy('sort_order', 'asc')
                                ->take(3)
                                ->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                        ]),
                ])
                ->get();

            // Transform and cache individual product cards
            $productCards = [];
            foreach ($products as $product) {
                $card = $this->transformToProductCard($product);
                $productCards[] = $card;

                // Cache individual product card
                $cardKey = RedisCacheService::makeKey('product_card', $product->id);
                RedisCacheService::put($cardKey, $card, 7200);
            }

            // Cache the collection
            RedisCacheService::put($key, $productCards, $ttl);

            return [
                'success' => true,
                'key' => $key,
                'count' => count($productCards),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warmup featured products: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up category products cache
     */
    private function warmupCategoryProducts(): array
    {
        $startTime = microtime(true);

        try {
            $key = self::HOMEPAGE_CACHE_PREFIX.'category_products';
            $ttl = config('redis_cache.ttl.category_products', 3600);

            // Get featured categories
            $categories = Category::select(['id', 'title', 'slug'])
                ->whereNull('parent_id')
                ->where('status', 'active')
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->limit(4)
                ->get();

            $categoryProducts = [];
            $totalProducts = 0;

            foreach ($categories as $category) {
                // Get product IDs for this category
                $productIds = DB::table('products')
                    ->select('id')
                    ->where('cat_id', $category->id)
                    ->where('status', 'active')
                    ->where('is_featured', 1)
                    ->orderBy('id', 'DESC')
                    ->limit(8)
                    ->pluck('id')
                    ->toArray();

                if (count($productIds) >= 4) {
                    // Fetch products
                    $products = Product::select([
                        'id', 'title', 'slug', 'base_price', 'base_discount',
                        'base_stock', 'has_variants', 'cat_id', 'condition',
                    ])
                        ->whereIn('id', $productIds)
                        ->with([
                            'images' => fn ($q) => $q->orderBy('sort_order', 'asc')
                                ->take(3)
                                ->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                            'variants' => fn ($q) => $q->where('status', 'active')
                                ->select(['id', 'product_id', 'price', 'discount', 'stock', 'status'])
                                ->where('stock', '>', 0)
                                ->limit(1)
                                ->with([
                                    'images' => fn ($q) => $q->orderBy('sort_order', 'asc')
                                        ->take(3)
                                        ->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                                ]),
                        ])
                        ->get();

                    // Transform products
                    $transformedProducts = [];
                    foreach ($products as $product) {
                        $transformedProducts[] = $this->transformToProductCard($product);
                    }

                    $categoryProducts[$category->slug] = [
                        'title' => $category->title,
                        'products' => $transformedProducts,
                    ];

                    $totalProducts += count($transformedProducts);
                }
            }

            // Cache category products
            RedisCacheService::put($key, $categoryProducts, $ttl);

            return [
                'success' => true,
                'key' => $key,
                'categories_count' => count($categoryProducts),
                'products_count' => $totalProducts,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warmup category products: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Warm up full page cache
     */
    private function warmupFullPage(): array
    {
        $startTime = microtime(true);

        try {
            $version = RedisCacheService::getVersion();
            $key = self::HOMEPAGE_CACHE_PREFIX."full_page_v{$version}";
            $ttl = 1800; // 30 minutes

            // Fetch all components
            $cacheKeys = [
                'categories' => self::HOMEPAGE_CACHE_PREFIX.'categories',
                'banners' => self::HOMEPAGE_CACHE_PREFIX.'banners',
                'featured' => self::HOMEPAGE_CACHE_PREFIX.'products:featured',
                'category_products' => self::HOMEPAGE_CACHE_PREFIX.'category_products',
            ];

            $components = RedisCacheService::mget(array_values($cacheKeys));

            // Build full page data
            $fullPageData = [
                'version' => $version,
                'generated_at' => now()->toIso8601String(),
                'categories' => $components[$cacheKeys['categories']] ?? [],
                'banners' => $components[$cacheKeys['banners']] ?? [],
                'featured_products' => $components[$cacheKeys['featured']] ?? [],
                'category_sections' => $components[$cacheKeys['category_products']] ?? [],
            ];

            // Cache full page
            RedisCacheService::put($key, $fullPageData, $ttl);

            return [
                'success' => true,
                'key' => $key,
                'version' => $version,
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to warmup full page: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Transform product to lightweight card format
     */
    private function transformToProductCard($product): array
    {
        // Determine primary image
        if ($product->has_variants && $product->variants->count() > 0) {
            $firstVariant = $product->variants->first();
            $primaryImage = $firstVariant?->images->first();
        } else {
            $primaryImage = $product->images->first();
        }

        // Build card data
        $card = [
            'id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'base_price' => $product->base_price,
            'base_discount' => $product->base_discount,
            'has_variants' => $product->has_variants,
            'condition' => $product->condition,
        ];

        // Add primary image
        if ($primaryImage) {
            $card['primary_image'] = [
                'image_path' => $primaryImage->image_path ?? $primaryImage->url ?? null,
                'thumbnail_path' => $primaryImage->thumbnail_path ?? $primaryImage->thumbnail_url ?? null,
                'alt_text' => $product->title,
            ];
        } else {
            $card['primary_image'] = null;
        }

        // Calculate stock and discount
        if ($product->has_variants && $product->variants->count() > 0) {
            $card['stock'] = $product->variants->sum('stock');
            $card['max_discount'] = $product->base_discount ?? 0;
        } else {
            $card['stock'] = $product->base_stock;
            $card['max_discount'] = $product->base_discount ?? 0;
        }

        return $card;
    }

    /**
     * Warm up product detail pages for top products
     */
    public function warmupTopProducts(int $limit = 100): array
    {
        $startTime = microtime(true);
        $results = [
            'success' => true,
            'cached_count' => 0,
            'errors' => [],
        ];

        try {
            // Get top viewed/sold products
            $productIds = DB::table('products')
                ->select('id')
                ->where('status', 'active')
                ->orderBy('id', 'DESC')
                ->limit($limit)
                ->pluck('id')
                ->toArray();

            foreach ($productIds as $productId) {
                try {
                    $product = Product::with(['images', 'variants.images'])
                        ->find($productId);

                    if ($product) {
                        $card = $this->transformToProductCard($product);
                        $key = RedisCacheService::makeKey('product_card', $productId);
                        RedisCacheService::put($key, $card, 7200);
                        $results['cached_count']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Product {$productId}: ".$e->getMessage();
                }
            }

            $results['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            Log::info("Warmed up {$results['cached_count']} product cards");
        } catch (\Exception $e) {
            $results['success'] = false;
            $results['errors'][] = $e->getMessage();
            Log::error('Failed to warmup top products: '.$e->getMessage());
        }

        return $results;
    }

    /**
     * Clear and rebuild all homepage caches
     */
    public function rebuildHomepageCache(): array
    {
        $startTime = microtime(true);

        try {
            Log::info('Clearing and rebuilding homepage cache');

            // Clear old caches
            $this->clearHomepageCaches();

            // Increment version for full page cache
            RedisCacheService::incrementVersion();

            // Warmup all caches
            $results = $this->warmupHomepage();

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $results['total_duration_ms'] = $duration;

            Log::info("Homepage cache rebuilt in {$duration}ms");

            return $results;
        } catch (\Exception $e) {
            Log::error('Failed to rebuild homepage cache: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Clear all homepage related caches
     */
    private function clearHomepageCaches(): void
    {
        $patterns = [
            self::HOMEPAGE_CACHE_PREFIX.'*',
        ];

        foreach ($patterns as $pattern) {
            RedisCacheService::deletePattern($pattern);
        }

        Log::info('Cleared all homepage caches');
    }

    /**
     * Get cache warmup status
     */
    public function getWarmupStatus(): array
    {
        $cacheKeys = [
            'full_page' => self::HOMEPAGE_CACHE_PREFIX.'full_page_v'.RedisCacheService::getVersion(),
            'categories' => self::HOMEPAGE_CACHE_PREFIX.'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX.'banners',
            'featured_products' => self::HOMEPAGE_CACHE_PREFIX.'products:featured',
            'category_products' => self::HOMEPAGE_CACHE_PREFIX.'category_products',
        ];

        $status = [];
        foreach ($cacheKeys as $name => $key) {
            $exists = RedisCacheService::has($key);
            $ttl = $exists ? RedisCacheService::ttl($key) : null;

            $status[$name] = [
                'key' => $key,
                'cached' => $exists,
                'ttl_seconds' => $ttl,
                'expires_in' => $ttl ? gmdate('H:i:s', $ttl) : null,
            ];
        }

        return $status;
    }
}
