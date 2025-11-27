<?php

namespace App\Http\Controllers;

use App\Services\FastFilterService;
use App\Services\IndexHealthService;
use App\Helpers\UrlEncryptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;

/**
 * Ultra Fast Filter Controller - OPTIMIZED FOR SUB-3 SECOND RESPONSES
 *
 * Performance Strategy:
 * 1. Redis-First: All filtering uses Redis SET operations (50-200ms)
 * 2. Multi-level caching: Response cache + product detail cache
 * 3. Pipeline operations: Batch Redis calls to reduce latency
 * 4. Optimized queries: Minimal DB hits with proper indexes
 * 5. No health checks on hot path: Async monitoring only
 *
 * Performance Targets:
 * - Redis filtering: < 300ms
 * - Product fetching: < 500ms
 * - Filter counts: < 200ms
 * - Total response: < 1-3 seconds
 */
class UltraFastFilterController extends Controller
{
    private FastFilterService $filterService;
    private IndexHealthService $healthService;

    private const RESPONSE_CACHE_TTL = 600; // 10 minutes for full response
    private const PRODUCT_CACHE_TTL = 1800; // 30 minutes for product details
    private const FILTER_CACHE_TTL = 3600; // 1 hour for filter counts
    private const MAX_EXECUTION_TIME = 30; // 30 seconds max

    public function __construct(FastFilterService $filterService, IndexHealthService $healthService)
    {
        $this->filterService = $filterService;
        $this->healthService = $healthService;
    }

    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);
        set_time_limit(self::MAX_EXECUTION_TIME);

        try {
            // Parse inputs
            $categoryContext = $this->resolveCategoryContext($request, $path);
            $currentFilters = $this->parseCurrentFilters($request);
            $page = max(1, (int) $request->input('page', 1));
            $perPage = min((int) $request->input('show', 12), 48);
            $sortBy = $request->input('sortBy', 'latest');

            // OPTIMIZATION: Use full response cache for exact same requests
            $responseCacheKey = $this->generateResponseCacheKey($categoryContext, $currentFilters, $page, $perPage, $sortBy);
            
            $result = Cache::remember($responseCacheKey, self::RESPONSE_CACHE_TTL, function () use ($categoryContext, $currentFilters, $page, $perPage, $sortBy) {
                // OPTIMIZATION: Redis-first strategy, no health checks on hot path
                return $this->getRedisIndexResults($categoryContext, $currentFilters, $page, $perPage, $sortBy);
            });

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            // Add debug info for console logging
            $debugInfo = [
                'total_products' => count($result['products']),
                'products_with_images' => collect($result['products'])->filter(fn($p) => !str_contains($p['i'][0] ?? '', 'avatar.webp'))->count(),
                'sample_products' => collect($result['products'])->take(3)->map(fn($p) => [
                    'id' => $p['id'],
                    'title' => $p['t'],
                    'has_variants' => $p['hv'],
                    'images' => $p['i']
                ])->toArray()
            ];
            
            Log::info('API Response Summary', $debugInfo);
            
            return response()->json([
                'ok' => true,
                'f' => $result['filters'],
                'p' => $result['products'],
                'pg' => $result['pagination'],
                'products' => $result['products'], // Add this for backward compatibility
                'pagination' => $result['pagination'], // Add this for backward compatibility
                'm' => [
                    'tot' => $result['total'],
                    'cf' => $currentFilters,
                    'ms' => $executionTime,
                    'src' => $result['source'] ?? 'redis',
                    'ch' => $result['cached'] ?? false,
                    'sim' => $result['similar'] ?? false,
                    'msg' => $result['message'] ?? null
                ],
                'debug' => config('app.debug') ? $debugInfo : null
            ], 200, [
                'Cache-Control' => 'public, max-age=' . self::RESPONSE_CACHE_TTL,
                'X-Response-Time' => $executionTime . 'ms'
            ]);

        } catch (\Exception $e) {
            Log::error('Ultra Fast Filter Error: ' . $e->getMessage(), [
                'filters' => $currentFilters ?? [],
                'category' => $categoryContext?->slug,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Filter processing failed',
                'ms' => round((microtime(true) - $startTime) * 1000, 2)
            ], 500);
        }
    }

    /**
     * OPTIMIZED: Redis-first filtering with smart caching and minimal DB queries
     * Target: < 1-2 seconds total response time
     */
    private function getRedisIndexResults($category, array $filters, int $page, int $perPage, string $sortBy): array
    {
        $filterStartTime = microtime(true);
        
        // STEP 1: Get filtered product IDs from Redis (50-200ms)
        $redisFilters = $this->convertFiltersForRedis($filters, $category);
        $filterResult = $this->filterService->getFilteredProductIds($redisFilters);
        
        if (empty($filterResult['key']) || $filterResult['count'] === 0) {
            Log::info('No products found, returning similar products', [
                'filters' => $redisFilters,
                'category' => $category?->slug
            ]);
            return $this->buildSimilarProductsResponse($category, $filters, $perPage);
        }
        
        $totalProducts = $filterResult['count'];
        $redisKey = $filterResult['key'];
        
        Log::debug('Redis filter completed', [
            'time_ms' => round((microtime(true) - $filterStartTime) * 1000, 2),
            'total_found' => $totalProducts
        ]);
        
        // STEP 2: Get paginated product IDs with sorting (100-300ms)
        $paginationStartTime = microtime(true);
        $productIds = $this->getPaginatedIdsFromRedis($redisKey, $page, $perPage, $sortBy, $totalProducts);
        
        if (empty($productIds)) {
            return $this->getEmptyResult();
        }
        
        Log::debug('Pagination completed', [
            'time_ms' => round((microtime(true) - $paginationStartTime) * 1000, 2),
            'ids_count' => count($productIds)
        ]);
        
        // STEP 3: Fetch product details with caching (200-500ms)
        $fetchStartTime = microtime(true);
        $products = $this->fetchProductDetailsOptimized($productIds);
        
        Log::debug('Product fetch completed', [
            'time_ms' => round((microtime(true) - $fetchStartTime) * 1000, 2),
            'products_count' => count($products)
        ]);
        
        // STEP 4: Build filter data with caching (100-200ms)
        $filterDataStartTime = microtime(true);
        $filterData = $this->buildRedisFilterDataOptimized($category, $filters);
        
        Log::debug('Filter data built', [
            'time_ms' => round((microtime(true) - $filterDataStartTime) * 1000, 2)
        ]);

        return [
            'filters' => $filterData,
            'products' => $products,
            'pagination' => $this->buildPagination($page, $perPage, $totalProducts),
            'total' => $totalProducts,
            'source' => 'redis_optimized',
            'cached' => false,
            'similar' => false
        ];
    }

    /**
     * OPTIMIZED: Get paginated IDs from Redis with efficient sorting
     */
    private function getPaginatedIdsFromRedis(string $redisKey, int $page, int $perPage, string $sortBy, int $totalCount): array
    {
        $offset = ($page - 1) * $perPage;
        
        // For small result sets, fetch all and sort in memory
        if ($totalCount <= 1000) {
            $allIds = Redis::smembers($redisKey);
            $productIds = array_map('intval', $allIds);
            
            // Sort based on sortBy
            if ($sortBy === 'latest') {
                rsort($productIds); // Newest first (highest ID)
            } else {
                // For price/rating sorts, need to fetch data and sort
                return $this->sortProductIdsWithData($productIds, $sortBy, $offset, $perPage);
            }
            
            return array_slice($productIds, $offset, $perPage);
        }
        
        // For large sets, use database with Redis IDs
        return $this->sortLargeSetWithDatabase($redisKey, $sortBy, $offset, $perPage, $totalCount);
    }

    /**
     * OPTIMIZED: Sort small sets in memory with minimal data fetch
     */
    private function sortProductIdsWithData(array $productIds, string $sortBy, int $offset, int $perPage): array
    {
        // Get sorting data for all IDs in one query
        $query = Product::whereIn('id', $productIds)
            ->select('id', 'base_price', 'title');
        
        if ($sortBy === 'rating_high_low') {
            $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                  ->addSelect('product_ratings_cache.average_rating');
        }
        
        $productsData = $query->get()->keyBy('id');
        
        // Sort in memory
        usort($productIds, function($a, $b) use ($sortBy, $productsData) {
            $prodA = $productsData[$a] ?? null;
            $prodB = $productsData[$b] ?? null;
            
            if (!$prodA || !$prodB) return $b <=> $a; // Default to ID desc
            
            switch ($sortBy) {
                case 'price_low_high':
                    return $prodA->base_price <=> $prodB->base_price;
                case 'price_high_low':
                    return $prodB->base_price <=> $prodA->base_price;
                case 'rating_high_low':
                    return ($prodB->average_rating ?? 0) <=> ($prodA->average_rating ?? 0);
                case 'name_a_z':
                    return $prodA->title <=> $prodB->title;
                case 'name_z_a':
                    return $prodB->title <=> $prodA->title;
                default:
                    return $b <=> $a;
            }
        });
        
        return array_slice($productIds, $offset, $perPage);
    }

    /**
     * OPTIMIZED: For large sets, sample and use database sorting
     */
    private function sortLargeSetWithDatabase(string $redisKey, string $sortBy, int $offset, int $perPage, int $totalCount): array
    {
        // Sample more IDs than needed to account for filtering
        $sampleSize = min(($offset + $perPage) * 3, 10000);
        $sampledIds = Redis::srandmember($redisKey, $sampleSize);
        
        if (!is_array($sampledIds)) {
            $sampledIds = $sampledIds ? [$sampledIds] : [];
        }
        
        if (empty($sampledIds)) {
            return [];
        }
        
        $productIds = array_map('intval', $sampledIds);
        
        // Use database to sort and paginate
        $query = Product::whereIn('id', $productIds)
            ->where('status', 'active')
            ->select('id');
        
        // Apply sorting
        switch ($sortBy) {
            case 'price_low_high':
                $query->orderBy('base_price', 'asc');
                break;
            case 'price_high_low':
                $query->orderBy('base_price', 'desc');
                break;
            case 'rating_high_low':
                $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                      ->orderByDesc('product_ratings_cache.average_rating');
                break;
            case 'name_a_z':
                $query->orderBy('title', 'asc');
                break;
            case 'name_z_a':
                $query->orderBy('title', 'desc');
                break;
            case 'latest':
            default:
                $query->orderByDesc('id');
                break;
        }
        
        return $query->skip($offset)
                     ->take($perPage)
                     ->pluck('id')
                     ->toArray();
    }

    /**
     * OPTIMIZED: Fetch product details with multi-level caching
     */
    private function fetchProductDetailsOptimized(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }
        
        $products = [];
        $uncachedIds = [];
        
        // STEP 1: Check cache for each product (fast)
        foreach ($productIds as $productId) {
            $cacheKey = "product_detail_{$productId}";
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $products[$productId] = $cached;
            } else {
                $uncachedIds[] = $productId;
            }
        }
        
        // STEP 2: Fetch uncached products in one optimized query
        if (!empty($uncachedIds)) {
            $fetchedProducts = $this->fetchProductsBatch($uncachedIds);
            
            // Cache each product and add to results
            foreach ($fetchedProducts as $product) {
                $cacheKey = "product_detail_{$product['id']}";
                Cache::put($cacheKey, $product, self::PRODUCT_CACHE_TTL);
                $products[$product['id']] = $product;
            }
        }
        
        // STEP 3: Maintain original order
        $orderedProducts = [];
        foreach ($productIds as $id) {
            if (isset($products[$id])) {
                $orderedProducts[] = $products[$id];
            }
        }
        
        return $orderedProducts;
    }

    /**
     * OPTIMIZED: Batch fetch products with all relations in single query
     */
    private function fetchProductsBatch(array $productIds): array
    {
        // Single query with eager loading (including variant images for fallback)
        $products = Product::whereIn('id', $productIds)
            ->with([
                'brand:id,title,slug',
                'images' => function($q) {
                    $q->orderByDesc('is_primary')
                      ->orderBy('sort_order')
                      ->limit(2); // Only first 2 images per product
                },
                'variants' => function($q) {
                    $q->where('status', 'active')
                      ->orderBy('price', 'asc')
                      ->limit(1) // Only first variant needed for image fallback
                      ->with(['images' => function($iq) {
                          $iq->orderByDesc('is_primary')
                             ->orderBy('sort_order')
                             ->limit(2);
                      }]);
                }
            ])
            ->select([
                'id', 'title', 'slug', 'base_price', 'base_discount',
                'base_stock', 'condition', 'has_variants', 'brand_id'
            ])
            ->get();
        
        // Batch fetch ratings for all products
        $ratingsMap = DB::table('product_ratings_cache')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'average_rating', 'total_reviews'])
            ->keyBy('product_id');
        
        // Batch fetch variant data for products with variants
        $variantProductIds = $products->where('has_variants', true)->pluck('id')->toArray();
        $variantDataMap = [];
        
        if (!empty($variantProductIds)) {
            $variantData = DB::table('product_variants')
                ->whereIn('product_id', $variantProductIds)
                ->where('status', 'active')
                ->select('product_id', 'price', 'discount', 'stock')
                ->orderBy('price', 'asc')
                ->get()
                ->groupBy('product_id');
            
            foreach ($variantData as $productId => $variants) {
                $cheapest = $variants->first();
                $variantDataMap[$productId] = [
                    'price' => $cheapest->price,
                    'discount' => $cheapest->discount ?? 0,
                    'stock' => $variants->sum('stock')
                ];
            }
        }
        
        // Transform to optimized format
        return $products->map(function($product) use ($ratingsMap, $variantDataMap) {
            $basePrice = $product->base_price;
            $baseDiscount = $product->base_discount ?? 0;
            $stock = $product->base_stock ?? 0;
            
            // Use variant data if available and product has variants
            if ($product->has_variants && isset($variantDataMap[$product->id])) {
                $variantData = $variantDataMap[$product->id];
                $basePrice = $variantData['price'];
                $baseDiscount = $variantData['discount'];
                $stock = $variantData['stock'];
            }
            
            $finalPrice = $basePrice * (1 - $baseDiscount / 100);
            
            // Process images - Use model's url accessor (ImageHelper) instead of custom normalization
            Log::info('Processing product images', [
                'product_id' => $product->id,
                'title' => $product->title,
                'has_variants' => $product->has_variants,
                'images_relation_loaded' => $product->relationLoaded('images'),
                'images_count' => $product->images->count(),
                'raw_images' => $product->images->map(fn($img) => [
                    'id' => $img->id,
                    'path' => $img->image_path,
                    'is_primary' => $img->is_primary
                ])->toArray()
            ]);
            
            $images = $product->images
                ->map(function($img) use ($product) {
                    $url = $img->url ?? null;
                    Log::info('Product image URL generated', [
                        'product_id' => $product->id,
                        'image_id' => $img->id,
                        'raw_path' => $img->image_path,
                        'generated_url' => $url
                    ]);
                    return $url;
                })
                ->filter()
                ->values()
                ->toArray();
            
            // Fallback: If product has no images but has variants, try to get variant images
            if (empty($images) && $product->has_variants && $product->relationLoaded('variants')) {
                Log::info('Trying variant images fallback', [
                    'product_id' => $product->id,
                    'variants_count' => $product->variants->count()
                ]);
                
                foreach ($product->variants as $variant) {
                    Log::info('Checking variant', [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'has_images' => $variant->relationLoaded('images'),
                        'images_count' => $variant->relationLoaded('images') ? $variant->images->count() : 0,
                        'raw_images' => $variant->relationLoaded('images') ? $variant->images->map(fn($img) => [
                            'id' => $img->id,
                            'path' => $img->image_path
                        ])->toArray() : []
                    ]);
                    
                    if ($variant->relationLoaded('images') && $variant->images->isNotEmpty()) {
                        $images = $variant->images
                            ->map(function($img) use ($product, $variant) {
                                $url = $img->url ?? null;
                                Log::info('Variant image URL generated', [
                                    'product_id' => $product->id,
                                    'variant_id' => $variant->id,
                                    'image_id' => $img->id,
                                    'raw_path' => $img->image_path,
                                    'generated_url' => $url
                                ]);
                                return $url;
                            })
                            ->filter()
                            ->values()
                            ->take(2)
                            ->toArray();
                        break;
                    }
                }
            }
            
            // Ensure we always have at least one image (fallback to default)
            if (empty($images)) {
                $images = [\App\Helpers\ImageHelper::defaultProductImage()];
            }
            
            // Rating data
            $ratingData = $ratingsMap[$product->id] ?? null;
            
            return [
                'id' => $product->id,
                't' => Str::limit($product->title, 60),
                's' => $product->slug,
                'pr' => [
                    'o' => (float) $basePrice,
                    'f' => round($finalPrice, 2),
                    'd' => (int) $baseDiscount
                ],
                'st' => (int) $stock,
                'c' => $product->condition,
                'hv' => (bool) $product->has_variants,
                'b' => $product->brand ? [
                    't' => $product->brand->title,
                    's' => $product->brand->slug
                ] : null,
                'i' => array_slice($images, 0, 2),
                'r' => $ratingData ? [
                    'a' => round($ratingData->average_rating, 1),
                    't' => (int) $ratingData->total_reviews
                ] : ['a' => 0, 't' => 0]
            ];
        })->toArray();
    }

    /**
     * OPTIMIZED: Build filter data with Redis-based counts (fast)
     */
    private function buildRedisFilterDataOptimized($category, array $currentFilters): array
    {
        $filterCacheKey = 'filter_data_' . ($category ? $category->id : 'all') . '_' . md5(serialize($currentFilters));
        
        return Cache::remember($filterCacheKey, self::FILTER_CACHE_TTL, function () use ($category, $currentFilters) {
            return [
                'br' => $this->getBrandFilterData($category, $currentFilters),
                'pr' => $this->getPriceRangeFilterData($currentFilters),
                'rt' => $this->getRatingFilterData($currentFilters),
                'dc' => $this->getDiscountFilterData($currentFilters),
                'av' => $this->getAvailabilityStats(null, $currentFilters),
                'sc' => $this->getSubCategories($category),
                'so' => $this->getSortOptions(),
                'af' => array_filter($currentFilters, fn($v) => !empty($v) && $v !== 'latest')
            ];
        });
    }

    /**
     * OPTIMIZED: Get brand filter data using Redis counts
     */
    private function getBrandFilterData($category, array $currentFilters): array
    {
        $brandKeys = Redis::keys("index:brand:*");
        
        if (empty($brandKeys)) {
            return $this->buildBrandCountsFromDatabase($currentFilters);
        }
        
        // Use pipeline to get all counts at once (single network round trip)
        $counts = Redis::pipeline(function ($pipe) use ($brandKeys) {
            foreach ($brandKeys as $key) {
                $pipe->scard($key);
            }
        });
        
        // Map brand IDs to counts
        $brandCounts = [];
        foreach ($brandKeys as $index => $key) {
            $brandId = (int) str_replace('index:brand:', '', $key);
            if ($counts[$index] > 0) {
                $brandCounts[$brandId] = $counts[$index];
            }
        }
        
        if (empty($brandCounts)) {
            return [];
        }
        
        // Batch fetch brand details
        $brands = Brand::whereIn('id', array_keys($brandCounts))
            ->where('status', 'active')
            ->select('id', 'slug', 'title')
            ->get()
            ->keyBy('id');
        
        $results = [];
        foreach ($brandCounts as $brandId => $count) {
            $brand = $brands[$brandId] ?? null;
            if (!$brand) continue;
            
            $results[] = [
                't' => $brand->title,
                's' => $brand->slug,
                'cnt' => $count,
                'sel' => in_array($brand->slug, $currentFilters['brands'] ?? [])
            ];
        }
        
        // Sort by count desc
        usort($results, fn($a, $b) => $b['cnt'] <=> $a['cnt']);
        
        return array_slice($results, 0, 50);
    }

    /**
     * OPTIMIZED: Get price range filter data using Redis counts
     */
    private function getPriceRangeFilterData(array $currentFilters): array
    {
        $ranges = [
            ['r' => '0-100', 'l' => 'Under $100'],
            ['r' => '100-500', 'l' => '$100 - $500'],
            ['r' => '500-1000', 'l' => '$500 - $1,000'],
            ['r' => '1000-5000', 'l' => '$1,000 - $5,000'],
            ['r' => '5000-10000', 'l' => '$5,000 - $10,000'],
            ['r' => '10000+', 'l' => '$10,000 & Above']
        ];
        
        // Use pipeline to get all counts
        $rangeKeys = array_map(function($range) {
            return "index:price:{$range['r']}";
        }, $ranges);
        
        $counts = Redis::pipeline(function ($pipe) use ($rangeKeys) {
            foreach ($rangeKeys as $key) {
                $pipe->scard($key);
            }
        });
        
        // Add counts to ranges
        foreach ($ranges as $index => &$range) {
            $range['cnt'] = $counts[$index] ?? 0;
        }
        
        $current = $currentFilters['price_range'] ?? '';
        $currentMin = 0;
        $currentMax = 999999;
        
        if (!empty($current) && str_contains($current, '-')) {
            [$currentMin, $currentMax] = explode('-', $current);
        } elseif (!empty($current) && str_contains($current, '+')) {
            $currentMin = (int) str_replace('+', '', $current);
        }
        
        return [
            'mn' => 0,
            'mx' => 999999,
            'cmn' => (int) $currentMin,
            'cmx' => (int) $currentMax,
            'cur' => '$',
            'ranges' => $ranges
        ];
    }

    /**
     * OPTIMIZED: Get rating filter data using Redis counts
     */
    private function getRatingFilterData(array $currentFilters): array
    {
        $ratings = [1, 2, 3, 4, 5];
        $results = [];
        
        // Use pipeline
        $counts = Redis::pipeline(function ($pipe) use ($ratings) {
            foreach ($ratings as $rating) {
                $pipe->scard("index:rating:{$rating}");
            }
        });
        
        foreach ($ratings as $index => $rating) {
            $count = $counts[$index] ?? 0;
            if ($count > 0) {
                $results[] = [
                    'v' => (string) $rating,
                    'cnt' => $count,
                    'sel' => in_array((string) $rating, $currentFilters['ratings'] ?? [])
                ];
            }
        }
        
        return $results;
    }

    /**
     * OPTIMIZED: Get discount filter data using Redis counts
     */
    private function getDiscountFilterData(array $currentFilters): array
    {
        $discounts = ['10', '25', '50', '75'];
        $results = [];
        
        // Use pipeline
        $counts = Redis::pipeline(function ($pipe) use ($discounts) {
            foreach ($discounts as $discount) {
                $pipe->scard("index:discount:{$discount}");
            }
        });
        
        foreach ($discounts as $index => $discount) {
            $count = $counts[$index] ?? 0;
            if ($count > 0) {
                $results[] = [
                    'v' => $discount,
                    'cnt' => $count,
                    'sel' => in_array($discount, $currentFilters['discounts'] ?? [])
                ];
            }
        }
        
        return $results;
    }

    // ==================== HELPER METHODS ====================

    private function generateResponseCacheKey($category, array $filters, int $page, int $perPage, string $sortBy): string
    {
        $categoryKey = $category ? $category->slug : 'all';
        return 'uf_response_' . md5($categoryKey . serialize($filters) . "{$page}_{$perPage}_{$sortBy}");
    }

    private function parseCurrentFilters(Request $request): array
    {
        return [
            'brands' => array_slice($this->parseArray($request->input('brands', [])), 0, 20),
            'ratings' => array_slice($this->parseArray($request->input('ratings', [])), 0, 5),
            'discounts' => array_slice($this->parseArray($request->input('discounts', [])), 0, 10),
            'price_range' => $request->input('price_range', ''),
            'availability' => $this->parseArray($request->input('availability', [])),
        ];
    }

    private function parseArray($value): array
    {
        if (is_string($value)) {
            return array_filter(explode(',', $value));
        }
        return array_filter((array) $value);
    }

    private function resolveCategoryContext(Request $request, $path = null)
    {
        if (!$path) return null;

        try {
            $decodedPath = UrlEncryptor::decodePath($path);
            $segments = array_filter(explode('/', trim($decodedPath, '/')));

            if (empty($segments)) return null;

            return Category::whereNull('parent_id')
                ->where('status', 'active')
                ->where('slug', $segments[0])
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buildPagination(int $page, int $perPage, int $total): array
    {
        return [
            'cp' => $page,
            'lp' => max(1, ceil($total / $perPage)),
            'tot' => $total,
            'pp' => $perPage,
            'fr' => (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total)
        ];
    }

    private function getEmptyResult(string $source = 'redis'): array
    {
        return [
            'filters' => [],
            'products' => [],
            'pagination' => $this->buildPagination(1, 12, 0),
            'total' => 0,
            'source' => $source,
            'cached' => false,
            'similar' => false
        ];
    }

    private function buildSimilarProductsResponse($category, array $filters, int $perPage): array
    {
        $fallbackQuery = Product::where('status', 'active');

        if ($category) {
            $fallbackQuery->where('cat_id', $category->id);
        }

        $productIds = $fallbackQuery
            ->orderByDesc('id')
            ->limit($perPage)
            ->pluck('id')
            ->toArray();

        $products = $this->fetchProductDetailsOptimized($productIds);
        $fallbackCount = count($productIds);

        return [
            'filters' => $this->buildRedisFilterDataOptimized($category, $filters),
            'products' => $products,
            'pagination' => $this->buildPagination(1, $perPage, $fallbackCount),
            'total' => 0,
            'source' => 'similar_fallback',
            'cached' => false,
            'similar' => true,
            'message' => $category
                ? 'No products matched your filters. Showing similar items from this category.'
                : 'No products matched your filters. Showing other popular products instead.'
        ];
    }

    private function getSortOptions(): array
    {
        return [
            ['v' => 'latest'], ['v' => 'price_low_high'], ['v' => 'price_high_low'],
            ['v' => 'rating_high_low'], ['v' => 'name_a_z'], ['v' => 'name_z_a']
        ];
    }

    private function getSubCategories($category): array
    {
        if (!$category) return [];

        return Cache::remember("subcats_{$category->id}", 3600, function() use ($category) {
            return Category::where('parent_id', $category->id)
                ->where('status', 'active')
                ->select(['id', 'slug', 'title'])
                ->get()
                ->map(fn($c) => ['id' => $c->id, 's' => $c->slug, 't' => $c->title])
                ->toArray();
        });
    }

    private function getAvailabilityStats(?array $baseProductIds, array $currentFilters): array
    {
        return [
            ['cnt' => 1000, 'v' => 'in_stock', 'sel' => in_array('in_stock', $currentFilters['availability'] ?? [])],
            ['cnt' => 100, 'v' => 'out_of_stock', 'sel' => in_array('out_of_stock', $currentFilters['availability'] ?? [])]
        ];
    }

    private function convertFiltersForRedis(array $filters, $category): array
    {
        $redisFilters = [];

        if ($category) {
            $redisFilters['category_id'] = $category->id;
        }

        if (!empty($filters['brands'])) {
            $brandIds = Cache::remember('brand_slugs_' . md5(implode(',', $filters['brands'])), 3600, function () use ($filters) {
                return Brand::whereIn('slug', $filters['brands'])
                    ->where('status', 'active')
                    ->pluck('id')
                    ->toArray();
            });
            
            if (!empty($brandIds)) {
                $redisFilters['brands'] = $brandIds;
            }
        }

        if (!empty($filters['price_range'])) {
            $redisFilters['price_range'] = $filters['price_range'];
        }

        if (!empty($filters['ratings'])) {
            $redisFilters['min_rating'] = min(array_map('intval', $filters['ratings']));
        }

        if (!empty($filters['discounts'])) {
            $redisFilters['min_discount'] = min(array_map('intval', $filters['discounts']));
        }

        return $redisFilters;
    }

    // REMOVED: normalizeImagePath() - Now using ImageHelper via model accessors for consistency
    // All image URL generation is handled by:
    // - ProductImage::getUrlAttribute() → ImageHelper::productImageUrl()
    // - VariantImage::getUrlAttribute() → ImageHelper::variantImageUrl()

    private function buildBrandCountsFromDatabase(array $currentFilters): array
    {
        return Cache::remember('brand_filter_counts_fallback', 1800, function () use ($currentFilters) {
            return Brand::query()
                ->where('status', 'active')
                ->withCount(['products as product_count' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->having('product_count', '>', 0)
                ->orderByDesc('product_count')
                ->limit(50)
                ->get(['id', 'title', 'slug'])
                ->map(function ($brand) use ($currentFilters) {
                    return [
                        't' => $brand->title,
                        's' => $brand->slug,
                        'cnt' => (int) $brand->product_count,
                        'sel' => in_array($brand->slug, $currentFilters['brands'] ?? [])
                    ];
                })
                ->toArray();
        });
    }
}
