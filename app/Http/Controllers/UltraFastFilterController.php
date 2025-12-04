<?php

namespace App\Http\Controllers;

use App\Helpers\UrlEncryptor;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\FastFilterService;
use App\Services\IndexHealthService;
use App\Services\OptimizedFilterCacheService;
use App\Services\RedisKeyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

/**
 * Ultra Fast Filter Controller
 *
 * Leverages Redis Set-based indexes for sub-100ms responses
 * even with 10M+ products. Uses the indexes built by ProductIndexService.
 */
class UltraFastFilterController extends Controller
{
    private FastFilterService $filterService;

    private IndexHealthService $healthService;

    private OptimizedFilterCacheService $optimizedCache;

    private const CACHE_TTL = 300; // 5 minutes

    private const MAX_EXECUTION_TIME = 120; // 120 seconds max (increased for safe fallback)

    public function __construct(
        FastFilterService $filterService,
        IndexHealthService $healthService,
        OptimizedFilterCacheService $optimizedCache
    ) {
        $this->filterService = $filterService;
        $this->healthService = $healthService;
        $this->optimizedCache = $optimizedCache;
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

            // Check index health and attempt on-demand rebuild if needed
            // DISABLED: On-demand building causes race conditions
            // $this->ensureIndexesExist($categoryContext, $currentFilters);

            // Use Redis indexes for all filtering (no Elasticsearch for now)
            $result = $this->getRedisIndexResults($categoryContext, $currentFilters, $page, $perPage, $sortBy);

            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            return response()->json([
                'ok' => true,
                'f' => $result['filters'],
                'p' => $result['products'],
                'pg' => $result['pagination'],
                'm' => [
                    'tot' => $result['total'],
                    'cf' => $currentFilters,
                    'ms' => $executionTime,
                    'src' => $result['source'] ?? 'redis',
                    'ch' => $result['cached'] ?? false,
                    'sim' => $result['similar'] ?? false,
                    'msg' => $result['message'] ?? null,
                ],
            ], 200, [
                'Cache-Control' => 'no-cache, no-store, must-revalidate', // TEMP: Disable caching for debugging
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Response-Time' => $executionTime.'ms',
            ]);
        } catch (\Exception $e) {
            Log::error('Ultra Fast Filter Error: '.$e->getMessage(), [
                'filters' => $currentFilters,
                'category' => $categoryContext?->slug,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Filter processing failed',
                'ms' => round((microtime(true) - $startTime) * 1000, 2),
            ], 500);
        }
    }

    /**
     * Ensure required indexes exist before querying
     */
    private function ensureIndexesExist($category, array $filters): void
    {
        try {
            // Check if indexes are healthy overall
            if ($this->healthService->isHealthy()) {
                return; // All good, proceed normally
            }

            // Get list of missing indexes for this specific query
            $redisFilters = $this->convertFiltersForRedis($filters, $category);
            $missingIndexes = $this->healthService->getMissingIndexesForFilters($redisFilters);

            if (! empty($missingIndexes)) {
                Log::warning('Missing indexes detected, building on-demand', [
                    'missing_count' => count($missingIndexes),
                    'indexes' => $missingIndexes,
                ]);

                // Build missing indexes on-demand (should be quick for specific indexes)
                $this->healthService->buildMissingIndexes($missingIndexes);
            }

            // Trigger background rebuild for full index refresh (non-blocking)
            $this->healthService->triggerRebuildIfNeeded();
        } catch (\Exception $e) {
            Log::error('Index health check failed, proceeding with database fallback', [
                'error' => $e->getMessage(),
            ]);
            // Continue execution - will use database fallback
        }
    }

    /**
     * Use Redis Set-based indexes for ultra-fast filtering
     * OPTIMIZED FOR 10M PRODUCTS: Use FastFilterService with Redis SET operations
     * Fallback to database only if Redis indexes unavailable
     */
    private function getRedisIndexResults($category, array $filters, int $page, int $perPage, string $sortBy): array
    {
        $cacheKey = $this->generateCacheKey($category, $filters, $page, $perPage, $sortBy);

        // TEMPORARILY DISABLED: Caching disabled for debugging filter issues
        // Use shorter TTL for filter results to keep cache fresh
        // $cacheTtl = 180; // 3 minutes instead of 5

        // return Cache::remember($cacheKey, $cacheTtl, function () use ($category, $filters, $page, $perPage, $sortBy) {

        // Execute query directly without caching

        $startTime = microtime(true);

        // Log the sort parameter for debugging
        Log::info('UltraFastFilter Query', [
            'sortBy' => $sortBy,
            'filters' => $filters,
            'category' => $category?->slug,
            'page' => $page,
        ]);

        // Convert filters to FastFilterService format
        $redisFilters = $this->convertFiltersForRedis($filters, $category);

        Log::info('Converted Redis Filters', ['redis_filters' => $redisFilters]);

        // Use FastFilterService to get product IDs via Redis SET operations (5-50ms)
        $result = $this->filterService->getFilteredProductIds($redisFilters);

        $redisTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('FastFilter Redis Query Time', ['time_ms' => $redisTime, 'count' => $result['count']]);

        // Debug: Check if Redis result is 0 - verify against PostgreSQL
        if ($result['count'] === 0 || ! $result['key']) {
            Log::warning('❌ REDIS RETURNED 0 RESULTS - Checking PostgreSQL', [
                'filters_received' => $filters,
                'redis_filters' => $redisFilters,
                'redis_result' => $result,
                'category' => $category?->slug ?? 'none',
            ]);

            // Verify: Check if PostgreSQL has products for this category
            $dbCount = $this->verifyDatabaseHasProducts($category, $filters);

            Log::warning('🔍 DATABASE VERIFICATION', [
                'redis_count' => $result['count'],
                'database_count' => $dbCount,
                'mismatch' => $dbCount > 0,
            ]);

            return $this->buildSimilarProductsResponse($category, $filters, $perPage);
        }

        $total = $result['count'];

        // STRATEGY: Get a larger sample from Redis, sort in DB, then paginate
        // For sorting to work correctly, we need more products than just one page
        $sampleSize = min($total, $perPage * 10); // Get 10 pages worth for sorting
        $allSortableIds = $this->filterService->getPaginatedIds($result['key'], 0, $sampleSize);

        if (empty($allSortableIds)) {
            Log::warning('No product IDs from Redis', ['total' => $total, 'sample_size' => $sampleSize]);

            return $this->buildSimilarProductsResponse($category, $filters, $perPage);
        }

        // Apply sorting via database on the larger sample
        $sortedIds = $this->sortProductIds($allSortableIds, $sortBy);

        // Now paginate the sorted results
        $offset = ($page - 1) * $perPage;
        $productIds = array_slice($sortedIds, $offset, $perPage);

        if (empty($productIds)) {
            Log::warning('No product IDs after pagination', ['page' => $page, 'offset' => $offset]);

            return $this->buildSimilarProductsResponse($category, $filters, $perPage);
        }

        $sortTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('Sorting & Pagination', [
            'time_ms' => $sortTime - $redisTime,
            'sort' => $sortBy,
            'sample_size' => count($allSortableIds),
            'after_sort' => count($sortedIds),
            'page' => $page,
            'final_products' => count($productIds),
        ]);

        // Fetch product details (20-80ms for 12-48 products)
        $products = $this->fetchProductDetails($productIds, $filters);

        // Build filter data
        $filterData = $this->buildRedisFilterData($category, $filters);

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        Log::info('Total Query Time', ['time_ms' => $totalTime]);

        return [
            'filters' => $filterData,
            'products' => $products,
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'total' => $total,
            'source' => 'redis_indexes',
            'cached' => false,
            'similar' => false,
            'performance' => [
                'redis_ms' => $redisTime,
                'sort_ms' => $sortTime - $redisTime,
                'total_ms' => $totalTime,
            ],
        ];
        // });  // DISABLED: Caching temporarily disabled
    }

    /**
     * Get paginated product IDs using database with optimized WHERE IN approach
     * For 10M products, we can't load all IDs - use DB directly
     */
    private function getPaginatedIdsFromDatabase(?string $redisKey, int $page, int $perPage, string $sortBy): array
    {
        if (! $redisKey || ! Redis::exists($redisKey)) {
            return [];
        }

        // Strategy: Use database filtering with a reasonable subset from Redis
        // Get enough IDs to fill several pages (pre-fetch for speed)
        $prefetchSize = $perPage * 20; // Get 20 pages worth
        $skipSize = ($page - 1) * $perPage;

        // Sample enough product IDs to cover the page we need
        $requiredIds = $skipSize + $perPage + 1000; // Extra buffer for filtering

        try {
            // Use SRANDMEMBER to get a large random sample
            // This is O(1) for Redis and doesn't load the entire set
            $sampleSize = min($requiredIds * 2, 50000); // Cap at 50K to avoid memory issues
            $sampleIds = Redis::srandmember($redisKey, $sampleSize);

            if (! is_array($sampleIds)) {
                $sampleIds = $sampleIds ? [$sampleIds] : [];
            }

            if (empty($sampleIds)) {
                Log::warning('No sample IDs retrieved from Redis', ['key' => $redisKey]);

                return [];
            }

            $productIds = array_map('intval', $sampleIds);
        } catch (\Exception $e) {
            Log::error('Failed to sample IDs from Redis', [
                'key' => $redisKey,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        // Subquery for cheapest active variant per product (discounted + original price)
        $variantPriceSub = DB::table('product_variants as pv')
            ->selectRaw('pv.product_id')
            ->selectRaw('MIN(pv.price) as min_variant_original_price')
            ->selectRaw('MIN(pv.price * (1 - COALESCE(pv.discount, 0) / 100.0)) as min_variant_discounted_price')
            ->where('pv.status', 'active')
            ->groupBy('pv.product_id');

        // Now use database to sort and paginate
        $query = Product::query()
            ->whereIn('products.id', $productIds)
            ->where('products.status', 'active')
            ->leftJoinSub($variantPriceSub, 'variant_prices', function ($join) {
                $join->on('variant_prices.product_id', '=', 'products.id');
            })
            ->where(function ($q) {
                $q->where(function ($nonVariant) {
                    $nonVariant->where('products.has_variants', false)
                               ->whereNotNull('products.base_price');
                })->orWhere(function ($variant) {
                    $variant->where('products.has_variants', true)
                            ->whereNotNull('variant_prices.min_variant_original_price');
                });
            })
            ->select('products.id');

        $effectivePriceExpr = 'COALESCE(
            CASE WHEN products.has_variants THEN variant_prices.min_variant_discounted_price END,
            products.base_price * (1 - COALESCE(products.base_discount, 0) / 100.0),
            0
        )';

        switch ($sortBy) {
            case 'price_low_high':
                $query->orderByRaw($effectivePriceExpr.' asc')
                      ->orderByDesc('products.id');
                break;
            case 'price_high_low':
                $query->orderByRaw($effectivePriceExpr.' desc')
                      ->orderByDesc('products.id');
                break;
            case 'rating_high_low':
                $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                      ->orderByDesc('product_ratings_cache.average_rating')
                      ->orderByDesc('products.id');
                break;
            case 'name_a_z':
                $query->orderBy('products.title', 'asc');
                break;
            case 'name_z_a':
                $query->orderBy('products.title', 'desc');
                break;
            case 'latest':
            default:
                $query->orderByDesc('products.id');
                break;
        }

        // Apply pagination
        return $query->skip(($page - 1) * $perPage)
                     ->take($perPage)
                     ->pluck('id')
                     ->toArray();
    }

    /**
     * Get recent products when no filters applied
     */
    private function getRecentProductsResult(int $page, int $perPage): array
    {
        $query = Product::where('status', 'active')
            ->orderByDesc('id');

        $total = min(10000, $query->count()); // Limit to 10K for performance

        $productIds = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->pluck('id')
            ->toArray();

        $products = $this->fetchProductDetails($productIds);

        return [
            'filters' => $this->buildRedisFilterData(null, []),
            'products' => $products,
            'pagination' => $this->buildPagination($page, $perPage, $total),
            'total' => $total,
            'source' => 'database',
            'cached' => false,
        ];
    }

    /**
     * Get filter counts using Redis Set intersections
     */
    private function buildRedisFilterData($category, array $currentFilters): array
    {
        // Get base product set for the category using new RedisKeyManager structure
        $baseProductIds = $category ?
            Redis::smembers(RedisKeyManager::indexCategory($category->id)) :
            null;

        return [
            'br' => $this->getRedisFilterCounts('brand', $baseProductIds, $currentFilters),
            'pr' => $this->getRedisFilterPriceRange($baseProductIds, $currentFilters),
            'rt' => $this->getRedisFilterCounts('rating', $baseProductIds, $currentFilters),
            'dc' => $this->getRedisFilterCounts('discount', $baseProductIds, $currentFilters),
            'av' => $this->getAvailabilityStats($baseProductIds, $currentFilters),
            'sc' => $this->getSubCategories($category),
            'so' => $this->getSortOptions(),
            'af' => array_filter($currentFilters, fn ($v) => ! empty($v) && $v !== 'latest'),
        ];
    }

    /**
     * Get filter counts using Redis Set intersections
     */
    private function getRedisFilterCounts(string $filterType, ?array $baseProductIds, array $currentFilters): array
    {
        $results = [];
        // Use new ec:idx: pattern instead of index:
        $pattern = $filterType === 'brand' ? 'ec:idx:br:*' : "ec:idx:{$filterType}:*";
        $keys = Redis::keys($pattern);

        if (empty($keys)) {
            if ($filterType === 'brand') {
                return $this->buildBrandCountsFromDatabase($currentFilters);
            }

            return $results;
        }

        if ($filterType === 'brand') {
            $slugCounts = [];
            foreach ($keys as $key) {
                // Extract slug from ec:idx:br:{slug} pattern
                $slug = str_replace('ec:idx:br:', '', $key);
                if ($slug === '') {
                    continue;
                }
                $slugCounts[$slug] = Redis::scard($key);
            }

            if (empty($slugCounts)) {
                return $results;
            }

            $brandLookup = $this->getBrandSlugTitleMap();

            foreach ($slugCounts as $slug => $count) {
                if ($count <= 0) {
                    continue;
                }

                $title = $brandLookup[$slug] ?? Str::of($slug)->replace('-', ' ')->title();

                $results[] = [
                    't' => $title,
                    's' => $slug,
                    'cnt' => $count,
                    'sel' => in_array($slug, $currentFilters['brands'] ?? []),
                ];
            }

            // Keep list manageable and sorted by count desc
            usort($results, fn ($a, $b) => $b['cnt'] <=> $a['cnt']);

            return array_slice($results, 0, 50);
        }

        foreach ($keys as $key) {
            // Extract value from ec:idx:{type}:{value} pattern
            $prefix = $filterType === 'rating' ? 'ec:idx:rating:' : "ec:idx:{$filterType}:";
            $keyValue = str_replace($prefix, '', $key);
            if ($keyValue === '') {
                continue;
            }

            $count = Redis::scard($key);
            if ($count > 0) {
                $results[] = [
                    'v' => $keyValue,
                    'cnt' => $count,
                    'sel' => in_array($keyValue, $currentFilters[$filterType.'s'] ?? []),
                ];
            }
        }

        return array_slice($results, 0, 50);
    }

    /**
     * Fetch optimized product details for display
     */
    private function fetchProductDetails(array $productIds, array $filters = []): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Batch fetch all ratings for these products
        $ratingsMap = DB::table('product_ratings_cache')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'average_rating', 'total_reviews'])
            ->keyBy('product_id')
            ->map(fn ($r) => [
                'a' => round($r->average_rating, 1),
                't' => (int) $r->total_reviews,
            ])
            ->toArray();

        // Use optimized query similar to the original but for specific IDs
        $products = Product::whereIn('id', $productIds)
            ->with([
                'brand:id,title,slug',
                'images' => function ($q) {
                    // Keep ordering stable, but allow slice later per product
                    $q->orderByDesc('is_primary')
                      ->orderBy('sort_order')
                      ->orderBy('id');
                },
            ])
            ->select([
                'id', 'title', 'slug', 'base_price', 'base_discount',
                'base_stock', 'condition', 'has_variants', 'brand_id',
            ])
            ->get();

        // Maintain order of input IDs
        $orderedProducts = [];
        foreach ($productIds as $id) {
            $product = $products->firstWhere('id', $id);
            if ($product) {
                $orderedProducts[] = $product;
            }
        }

        return collect($orderedProducts)->map(function ($product) use ($ratingsMap) {
            /**
             * PRICE & STOCK RESOLUTION:
             * - has_variants=false → Use product.base_price, base_discount, base_stock
             * - has_variants=true → Get cheapest variant from product_variants table
             */
            $originalPrice = 0;
            $discountedPrice = 0;
            $discount = 0;
            $stock = 0;
            $cheapestVariant = null;

            if (! $product->has_variants) {
                // Non-variant product: Use base fields from products table
                $originalPrice = $product->base_price ?? 0;
                $discount = $product->base_discount ?? 0;
                $stock = $product->base_stock ?? 0;
                $discountedPrice = $discount > 0
                    ? $originalPrice * (1 - $discount / 100)
                    : $originalPrice;
            } else {
                // Variant product: Get cheapest variant from product_variants table
                $cheapestVariant = Cache::remember(
                    RedisKeyManager::productMeta($product->id).':min_price',
                    3600,
                    function () use ($product) {
                        return DB::table('product_variants')
                            ->where('product_id', $product->id)
                            ->where('status', 'active')
                            ->orderByRaw('price * (1 - COALESCE(discount, 0) / 100.0)')
                            ->select('id', 'price', 'discount', 'stock')
                            ->first();
                    }
                );

                if (! $cheapestVariant) {
                    Log::warning('Skipping variant product without active variants', [
                        'product_id' => $product->id,
                    ]);

                    return null;
                }

                $originalPrice = $cheapestVariant->price ?? 0;
                $discount = $cheapestVariant->discount ?? 0;
                $stock = $cheapestVariant->stock ?? 0;
                $discountedPrice = $discount > 0
                    ? $originalPrice * (1 - $discount / 100)
                    : $originalPrice;
            }

            /**
             * IMAGE RESOLUTION:
             * - has_variants=false → Use product_images table
             * - has_variants=true → Use variant_images table (from cheapest variant)
             */
            $imagePaths = [];

            if (! $product->has_variants) {
                // Non-variant: Get images from product_images table
                $imagePaths = $product->images
                    ->take(2)
                    ->pluck('image_path')
                    ->filter()
                    ->toArray();
            } else {
                // Variant: Get images from variant_images table
                if ($cheapestVariant && isset($cheapestVariant->id)) {
                    $imagePaths = DB::table('variant_images')
                        ->where('product_variant_id', $cheapestVariant->id)
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->limit(2)
                        ->pluck('image_path')
                        ->filter()
                        ->toArray();
                }

                // Fallback: If variant has no images, try product_images table
                if (empty($imagePaths)) {
                    $imagePaths = $product->images
                        ->take(2)
                        ->pluck('image_path')
                        ->filter()
                        ->toArray();
                }
            }

            // Final fallback: Use placeholder if no images found
            if (empty($imagePaths)) {
                $imagePaths = ['products/no-image.png'];
                Log::info('Product has no images, using placeholder', [
                    'product_id' => $product->id,
                    'has_variants' => $product->has_variants,
                ]);
            }

            // Normalize image paths for frontend
            $normalizedImages = array_map(
                fn ($path) => $this->normalizeImagePath($path) ?? 'products/no-image.png',
                $imagePaths
            );

            // Log data quality issues
            if ($discountedPrice <= 0) {
                Log::warning('Product has zero/invalid price', [
                    'product_id' => $product->id,
                    'has_variants' => $product->has_variants,
                    'base_price' => $product->base_price,
                    'variant_found' => isset($cheapestVariant),
                    'resolved_price' => $discountedPrice,
                ]);
            }

            return [
                'id' => $product->id,
                't' => Str::limit($product->title, 60),
                's' => $product->slug,
                'pr' => [
                    'o' => round($originalPrice, 2),
                    'f' => round($discountedPrice, 2),
                    'd' => (int) $discount,
                ],
                'st' => (int) $stock,
                'c' => $product->condition,
                'hv' => (bool) $product->has_variants,
                'b' => $product->brand ? [
                    't' => $product->brand->title,
                    's' => $product->brand->slug,
                ] : null,
                'i' => array_slice($normalizedImages, 0, 2),
                'r' => $ratingsMap[$product->id] ?? ['a' => 0, 't' => 0],
            ];
        })->filter()->values()->toArray();
    }

    // Helper methods (keeping existing logic but optimized)
    private function generateCacheKey($category, array $filters, int $page, int $perPage, string $sortBy): string
    {
        $categoryKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters)."{$page}_{$perPage}_{$sortBy}");

        return RedisKeyManager::filterResults("{$categoryKey}:{$filterHash}");
    }

    private function parseCurrentFilters(Request $request): array
    {
        // Handle price_range from either price_range param or price_min/price_max combo
        $priceRange = $request->input('price_range', '');

        if (empty($priceRange)) {
            $priceMin = $request->input('price_min', '');
            $priceMax = $request->input('price_max', '');

            if ($priceMin && $priceMax) {
                $priceRange = "{$priceMin}-{$priceMax}";
            }
        }

        return [
            'brands' => array_slice($this->parseArray($request->input('brands', [])), 0, 20),
            'ratings' => array_slice($this->parseArray($request->input('ratings', [])), 0, 5),
            'discounts' => array_slice($this->parseArray($request->input('discounts', [])), 0, 10),
            'price_range' => $priceRange,
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
        if (! $path) {
            Log::info('resolveCategoryContext: No path provided');

            return null;
        }

        try {
            $decodedPath = UrlEncryptor::decodePath($path);
            $segments = array_filter(explode('/', trim($decodedPath, '/')));

            Log::info('resolveCategoryContext', [
                'encrypted_path' => $path,
                'decoded_path' => $decodedPath,
                'segments' => $segments,
            ]);

            if (empty($segments)) {
                Log::warning('resolveCategoryContext: No segments after decode');

                return null;
            }

            $category = Category::whereNull('parent_id')
                ->where('status', 'active')
                ->where('slug', $segments[0])
                ->first();

            Log::info('resolveCategoryContext: Category lookup result', [
                'slug' => $segments[0],
                'found' => $category ? true : false,
                'category_id' => $category?->id,
            ]);

            return $category;
        } catch (\Exception $e) {
            Log::error('resolveCategoryContext: Exception', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function getBrandIdsBySlug(array $slugs): array
    {
        $cacheKey = RedisKeyManager::brandsAll().':slugs:'.md5(implode(',', $slugs));

        return Cache::remember($cacheKey, 3600, function () use ($slugs) {
            return Brand::whereIn('slug', $slugs)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });
    }

    private function buildPagination(int $page, int $perPage, int $total): array
    {
        return [
            'cp' => $page,
            'lp' => max(1, ceil($total / $perPage)),
            'tot' => $total,
            'pp' => $perPage,
            'fr' => (($page - 1) * $perPage) + 1,
            'to' => min($page * $perPage, $total),
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
            'similar' => false,
        ];
    }

    private function buildSimilarProductsResponse($category, array $filters, int $perPage): array
    {
        // Strategy 1: Remove brand filter but keep category and price
        $fallbackQuery = Product::where('status', 'active');

        if ($category) {
            $fallbackQuery->where('cat_id', $category->id);
        }

        // Try with category only (no brand, no price, no other filters)
        $productIds = $fallbackQuery
            ->orderByDesc('id')
            ->limit($perPage)
            ->pluck('id')
            ->toArray();

        // Strategy 2: If category has no products, get from any category
        if (empty($productIds)) {
            Log::info('Fallback Strategy 2: Getting products from any category');
            $productIds = Product::where('status', 'active')
                ->orderByDesc('id')
                ->limit($perPage)
                ->pluck('id')
                ->toArray();
        }

        // Strategy 3: Last resort - get ANY products (even inactive if needed)
        if (empty($productIds)) {
            Log::warning('Fallback Strategy 3: Getting ANY products');
            $productIds = Product::orderByDesc('id')
                ->limit($perPage)
                ->pluck('id')
                ->toArray();
        }

        $products = $this->fetchProductDetails($productIds);
        $fallbackCount = count($products);

        // Build descriptive message based on what filters were applied
        $message = $this->buildSimilarProductMessage($category, $filters);

        Log::info('Similar Products Fallback', [
            'original_filters' => $filters,
            'category' => $category?->slug,
            'products_found' => $fallbackCount,
            'message' => $message,
        ]);

        // Only set similar=true if filters were actually applied
        $hasFilters = ! empty($message);

        $response = [
            'filters' => $this->buildRedisFilterData($category, $filters),
            'products' => $products,
            'pagination' => $this->buildPagination(1, $perPage, $fallbackCount),
            'total' => $fallbackCount,
            'source' => 'similar_fallback',
            'cached' => false,
            'similar' => $hasFilters, // Only show warning when filters applied but no results
        ];

        if ($hasFilters) {
            $response['message'] = $message;
        }

        return $response;
    }

    private function buildSimilarProductMessage($category, array $filters): string
    {
        $appliedFilters = [];

        if (! empty($filters['brands'])) {
            $appliedFilters[] = 'brand: '.implode(', ', $filters['brands']);
        }

        if (! empty($filters['price_range'])) {
            $appliedFilters[] = 'price range: $'.str_replace('-', ' - $', $filters['price_range']);
        }

        if (! empty($filters['ratings'])) {
            $appliedFilters[] = 'rating: '.min($filters['ratings']).'+';
        }

        if (! empty($filters['discounts'])) {
            $appliedFilters[] = 'discount: '.min($filters['discounts']).'%+';
        }

        // Only show message when filters are applied but no products found
        // If no filters applied, return empty string (no warning needed)
        if (empty($appliedFilters)) {
            return '';
        }

        $filterText = implode(', ', $appliedFilters);

        if ($category) {
            return "No exact matches for {$filterText} in {$category->title}. Showing similar products from this category.";
        }

        return "No exact matches for {$filterText}. Showing similar products you might like.";
    }

    private function getSortOptions(): array
    {
        return [
            ['v' => 'latest'], ['v' => 'price_low_high'], ['v' => 'price_high_low'],
            ['v' => 'rating_high_low'], ['v' => 'name_a_z'], ['v' => 'name_z_a'],
        ];
    }

    private function getSubCategories($category): array
    {
        if (! $category) {
            return [];
        }

        return Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->select(['id', 'slug', 'title'])
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 's' => $c->slug, 't' => $c->title])
            ->toArray();
    }

    private function getRedisFilterPriceRange(?array $baseProductIds, array $currentFilters): array
    {
        // Cache the price range calculation as it's expensive
        $priceData = Cache::remember(RedisKeyManager::aggregateStats('price'), 3600, function () {
            // Calculate actual min/max prices from database
            // Check both base_price (non-variant products) and variant prices

            $minPriceFromBase = DB::table('products')
                ->where('status', 'active')
                ->where('has_variants', false)
                ->whereNotNull('base_price')
                ->min('base_price');

            $maxPriceFromBase = DB::table('products')
                ->where('status', 'active')
                ->where('has_variants', false)
                ->whereNotNull('base_price')
                ->max('base_price');

            $minPriceFromVariants = DB::table('product_variants')
                ->where('status', 'active')
                ->min('price');

            $maxPriceFromVariants = DB::table('product_variants')
                ->where('status', 'active')
                ->max('price');

            $minPrice = (int) min($minPriceFromBase ?? PHP_INT_MAX, $minPriceFromVariants ?? PHP_INT_MAX);
            $maxPrice = (int) max($maxPriceFromBase ?? 0, $maxPriceFromVariants ?? 0);

            // Generate common price ranges
            $ranges = [
                ['r' => '0-100', 'l' => 'Under $100'],
                ['r' => '100-500', 'l' => '$100 - $500'],
                ['r' => '500-1000', 'l' => '$500 - $1,000'],
            ];

            return [
                'mn' => $minPrice,
                'mx' => $maxPrice,
                'ranges' => $ranges,
            ];
        });

        $current = $currentFilters['price_range'] ?? '';
        $currentMin = $priceData['mn'];
        $currentMax = $priceData['mx'];

        if (! empty($current) && str_contains($current, '-')) {
            [$currentMin, $currentMax] = explode('-', $current);
        } elseif (! empty($current) && str_contains($current, '+')) {
            $currentMin = (int) str_replace('+', '', $current);
        }

        return [
            'mn' => $priceData['mn'],
            'mx' => $priceData['mx'],
            'cmn' => (int) $currentMin,
            'cmx' => (int) $currentMax,
            'cur' => '$',
            'ranges' => $priceData['ranges'],
        ];
    }

    private function getAvailabilityStats(?array $baseProductIds, array $currentFilters): array
    {
        // Implement availability statistics
        return [
            ['cnt' => 1000, 'v' => 'in_stock', 'sel' => in_array('in_stock', $currentFilters['availability'] ?? [])],
            ['cnt' => 100, 'v' => 'out_of_stock', 'sel' => in_array('out_of_stock', $currentFilters['availability'] ?? [])],
        ];
    }

    /**
     * Convert our filters to FastFilterService format
     */
    private function convertFiltersForRedis(array $filters, $category): array
    {
        $redisFilters = [];

        if ($category) {
            $redisFilters['category_id'] = $category->id;
        }

        if (! empty($filters['brands'])) {
            $brandIds = $this->getBrandIdsBySlug($filters['brands']);
            if (! empty($brandIds)) {
                $redisFilters['brands'] = $brandIds;
            }
        }

        if (! empty($filters['price_range'])) {
            $redisFilters['price_range'] = $filters['price_range'];
        }

        if (! empty($filters['ratings'])) {
            $redisFilters['min_rating'] = min(array_map('intval', $filters['ratings']));
        }

        if (! empty($filters['discounts'])) {
            $redisFilters['min_discount'] = min(array_map('intval', $filters['discounts']));
        }

        return $redisFilters;
    }

    private function normalizeImagePath(?string $rawPath): ?string
    {
        if (empty($rawPath)) {
            return null;
        }

        $rawPath = trim($rawPath);

        // Remove any full URL prefixes
        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            $storagePos = stripos($rawPath, '/storage/');
            if ($storagePos !== false) {
                $rawPath = substr($rawPath, $storagePos + strlen('/storage/'));
            } else {
                return null;
            }
        }

        // Remove leading slashes
        $normalized = ltrim($rawPath, '/\\');

        // Remove 'storage/' prefix if present
        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        // Check if path already has directory prefix (products/, products/variants/, photos/, etc.)
        if (Str::contains($normalized, '/')) {
            return $normalized;
        }

        // Path is just a filename - add appropriate directory prefix based on naming convention
        // Database stores: "product_123.webp" or "variant_456.webp"
        // Frontend expects: "products/product_123.webp" or "products/variants/variant_456.webp"
        if (Str::startsWith($normalized, 'variant_')) {
            return 'products/variants/'.$normalized;
        } elseif (Str::startsWith($normalized, 'product_')) {
            return 'products/'.$normalized;
        }

        // Default fallback: assume it's in products folder
        return 'products/'.$normalized;
    }

    private function getBrandSlugTitleMap(): array
    {
        return Cache::remember(RedisKeyManager::brandsAll().':slug_map', 1800, function () {
            return Brand::where('status', 'active')
                ->pluck('title', 'slug')
                ->toArray();
        });
    }

    /**
     * Map dynamic price range to Redis index keys
     * Redis indexes use fixed ranges: 0-100, 100-500, 500-1000
     */
    private function mapPriceRangeToRedisKeys(float $minPrice, float $maxPrice): array
    {
        $fixedRanges = [
            ['min' => 0, 'max' => 100, 'key' => RedisKeyManager::indexPriceRange('0-100')],
            ['min' => 100, 'max' => 500, 'key' => RedisKeyManager::indexPriceRange('100-500')],
            ['min' => 500, 'max' => 1000, 'key' => RedisKeyManager::indexPriceRange('500-1000')],
        ];

        $matchingKeys = [];
        foreach ($fixedRanges as $range) {
            // Include this range if it overlaps with the requested range
            if ($range['max'] >= $minPrice && $range['min'] <= $maxPrice) {
                $matchingKeys[] = $range['key'];
            }
        }

        return $matchingKeys;
    }

    private function buildBrandCountsFromDatabase(array $currentFilters): array
    {
        $cacheKey = RedisKeyManager::filterOptions('all', 'brands');

        $brands = Cache::remember($cacheKey, 1800, function () {
            return Brand::query()
                ->where('status', 'active')
                ->withCount(['products as product_count' => function ($q) {
                    $q->where('status', 'active');
                }])
                ->orderByDesc('product_count')
                ->get(['id', 'title', 'slug'])
                ->map(function ($brand) {
                    return [
                        't' => $brand->title,
                        's' => $brand->slug,
                        'cnt' => (int) $brand->product_count,
                    ];
                })
                ->filter(fn ($brand) => $brand['cnt'] > 0)
                ->values()
                ->toArray();
        });

        return array_map(function ($brand) use ($currentFilters) {
            $brand['sel'] = in_array($brand['s'], $currentFilters['brands'] ?? []);

            return $brand;
        }, $brands);
    }

    /**
     * Parse price filter configuration from request
     * Returns null if no price filter, or array with 'type', 'min', 'max'
     */
    private function parsePriceFilterConfig(?string $priceRange): ?array
    {
        if (empty($priceRange)) {
            return null;
        }

        // Format: "100-1000" or "1000+"
        if (str_contains($priceRange, '-')) {
            [$min, $max] = explode('-', $priceRange, 2);

            return [
                'type' => 'between',
                'min' => (float) $min,
                'max' => (float) $max,
            ];
        } elseif (str_contains($priceRange, '+')) {
            $min = (float) str_replace('+', '', $priceRange);

            return [
                'type' => 'min_only',
                'min' => $min,
                'max' => PHP_INT_MAX,
            ];
        }

        return null;
    }

    /**
     * Build lateral subquery for minimum variant price
     */
    private function buildVariantPriceSubquery(?array $priceFilterConfig): string
    {
        // Lateral join to get the minimum effective price from variants for each product
        return "(
            SELECT 
                product_variants.product_id,
                MIN(product_variants.price * (1 - COALESCE(product_variants.discount, 0) / 100.0)) as min_variant_price
            FROM product_variants
            WHERE product_variants.product_id = products.id
              AND product_variants.status = 'active'
            GROUP BY product_variants.product_id
        )";
    }

    /**
     * Get effective price expression (considers variants when needed)
     */
    private function getEffectivePriceExpression(bool $requiresVariantPricing): string
    {
        if ($requiresVariantPricing) {
            // Use COALESCE to prefer variant pricing when available, fall back to base price
            return 'COALESCE(
                pv_min.min_variant_price, 
                products.base_price * (1 - COALESCE(products.base_discount, 0) / 100.0)
            )';
        }

        // Simple case: just use base price with discount
        return 'products.base_price * (1 - COALESCE(products.base_discount, 0) / 100.0)';
    }

    /**
     * Sort product IDs using database query (optimized for small result sets)
     * Only called with 12-48 IDs, so very fast even with complex sorts
     */
    /**
     * Verify if PostgreSQL database has products for the given filters
     * Used to debug Redis vs Database mismatches
     */
    private function verifyDatabaseHasProducts($category, array $filters): int
    {
        $query = Product::where('status', 'active');

        if ($category) {
            $query->where('cat_id', $category->id);
        }

        // Apply brand filter if present
        if (! empty($filters['brands'])) {
            $brandIds = $this->getBrandIdsBySlug($filters['brands']);
            if (! empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
            }
        }

        // Apply discount filter if present
        if (! empty($filters['discounts'])) {
            $minDiscount = min(array_map('intval', $filters['discounts']));
            $query->where(function ($q) use ($minDiscount) {
                $q->where(function ($nonVariant) use ($minDiscount) {
                    $nonVariant->where('has_variants', false)
                               ->where('base_discount', '>=', $minDiscount);
                })
                ->orWhereHas('variants', function ($variant) use ($minDiscount) {
                    $variant->where('status', 'active')
                            ->where('discount', '>=', $minDiscount);
                });
            });
        }

        $count = $query->count();

        Log::info('📊 DATABASE QUERY DETAILS', [
            'category_id' => $category?->id,
            'filters' => $filters,
            'count' => $count,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
        ]);

        return $count;
    }

    private function sortProductIds(array $productIds, string $sortBy): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = Product::whereIn('products.id', $productIds)
            ->where('products.status', 'active');

        switch ($sortBy) {
            case 'price_low_high':
            case 'price_high_low':
                // For price sorting, calculate effective price (base + variants)
                $query->leftJoin(
                    DB::raw("LATERAL (
                        SELECT 
                            product_variants.product_id,
                            MIN(product_variants.price * (1 - COALESCE(product_variants.discount, 0) / 100.0)) as min_variant_price
                        FROM product_variants
                        WHERE product_variants.product_id = products.id
                          AND product_variants.status = 'active'
                        GROUP BY product_variants.product_id
                    ) as pv_min"),
                    DB::raw('TRUE'),
                    DB::raw('TRUE')
                )
                ->orderBy(
                    DB::raw('COALESCE(pv_min.min_variant_price, products.base_price * (1 - COALESCE(products.base_discount, 0) / 100.0))'),
                    $sortBy === 'price_low_high' ? 'asc' : 'desc'
                );
                break;

            case 'rating_high_low':
                $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                    ->orderByDesc(DB::raw('COALESCE(product_ratings_cache.average_rating, 0)'));
                break;

            case 'name_a_z':
                $query->orderBy('products.title', 'asc');
                break;

            case 'name_z_a':
                $query->orderBy('products.title', 'desc');
                break;

            case 'latest':
            default:
                $query->orderByDesc('products.id');
                break;
        }

        return $query->pluck('products.id')->toArray();
    }
}
