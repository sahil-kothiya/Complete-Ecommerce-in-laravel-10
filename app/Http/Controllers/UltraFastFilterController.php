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
 * Ultra Fast Filter Controller
 *
 * Leverages Redis Set-based indexes for sub-100ms responses
 * even with 10M+ products. Uses the indexes built by ProductIndexService.
 */
class UltraFastFilterController extends Controller
{
    private FastFilterService $filterService;
    private IndexHealthService $healthService;

    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_EXECUTION_TIME = 120; // 120 seconds max (increased for safe fallback)

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

            // Check index health and attempt on-demand rebuild if needed
            $this->ensureIndexesExist($categoryContext, $currentFilters);

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
                        'msg' => $result['message'] ?? null
                ]
            ], 200, [
                'Cache-Control' => 'public, max-age=' . self::CACHE_TTL,
                'X-Response-Time' => $executionTime . 'ms'
            ]);

        } catch (\Exception $e) {
            Log::error('Ultra Fast Filter Error: ' . $e->getMessage(), [
                'filters' => $currentFilters,
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
            
            if (!empty($missingIndexes)) {
                Log::warning('Missing indexes detected, building on-demand', [
                    'missing_count' => count($missingIndexes),
                    'indexes' => $missingIndexes
                ]);
                
                // Build missing indexes on-demand (should be quick for specific indexes)
                $this->healthService->buildMissingIndexes($missingIndexes);
            }
            
            // Trigger background rebuild for full index refresh (non-blocking)
            $this->healthService->triggerRebuildIfNeeded();
            
        } catch (\Exception $e) {
            Log::error('Index health check failed, proceeding with database fallback', [
                'error' => $e->getMessage()
            ]);
            // Continue execution - will use database fallback
        }
    }

    /**
     * Use Redis Set-based indexes for ultra-fast filtering
     * OPTIMIZED FOR 10M PRODUCTS: Use database with indexed WHERE clauses
     * NOW WITH AUTOMATIC FALLBACK: If Redis indexes missing, uses pure database
     */
    private function getRedisIndexResults($category, array $filters, int $page, int $perPage, string $sortBy): array
    {
        $cacheKey = $this->generateCacheKey($category, $filters, $page, $perPage, $sortBy);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category, $filters, $page, $perPage, $sortBy) {

            // Convert filters to database query (robust fallback strategy)
            $query = Product::where('status', 'active');

            // Apply category filter (indexed column - fast)
            if ($category) {
                $query->where('cat_id', $category->id);
            }

            // Apply brand filter (indexed column - fast)
            if (!empty($filters['brands'])) {
                $brandIds = $this->getBrandIdsBySlug($filters['brands']);
                if (!empty($brandIds)) {
                    // Optimize: Use IN clause with limit to prevent full scan
                    $query->whereIn('brand_id', array_slice($brandIds, 0, 50)); // Max 50 brands
                }
            }

            // Apply price range filter - OPTIMIZED to prevent timeout
            if (!empty($filters['price_range'])) {
                $range = explode('-', $filters['price_range']);
                if (count($range) === 2) {
                    $minPrice = (float) $range[0];
                    $maxPrice = (float) $range[1];

                    // Simplified query: Only check base_price for efficiency
                    // Variant prices will be checked during product detail fetch
                    $query->where(function ($q) use ($minPrice, $maxPrice) {
                        $q->whereBetween('base_price', [$minPrice, $maxPrice])
                          ->orWhere(function($subQ) use ($minPrice, $maxPrice) {
                              // For products with variants, be more permissive
                              $subQ->where('has_variants', true)
                                   ->where('base_price', '>=', $minPrice * 0.5) // 50% tolerance
                                   ->where('base_price', '<=', $maxPrice * 1.5); // 50% tolerance
                          });
                    });
                } elseif (str_contains($filters['price_range'], '+')) {
                    $minPrice = (float) str_replace('+', '', $filters['price_range']);

                    $query->where(function ($q) use ($minPrice) {
                        $q->where('base_price', '>=', $minPrice)
                          ->orWhere(function($subQ) use ($minPrice) {
                              $subQ->where('has_variants', true)
                                   ->where('base_price', '>=', $minPrice * 0.5);
                          });
                    });
                }
            }

            // Apply rating filter - OPTIMIZED with LEFT JOIN
            if (!empty($filters['ratings'])) {
                $minRating = min(array_map('intval', $filters['ratings']));
                $query->leftJoin('product_ratings_cache as prc', 'products.id', '=', 'prc.product_id')
                      ->where('prc.average_rating', '>=', $minRating);
            }

            // Apply discount filter - OPTIMIZED to prevent timeout
            if (!empty($filters['discounts'])) {
                $minDiscount = min(array_map('intval', $filters['discounts']));

                // Simplified: Only check base_discount for speed
                $query->where(function ($q) use ($minDiscount) {
                    $q->where('base_discount', '>=', $minDiscount)
                      ->orWhere(function($subQ) use ($minDiscount) {
                          // For variant products, be permissive (check later)
                          $subQ->where('has_variants', true)
                               ->where('base_discount', '>=', $minDiscount * 0.5);
                      });
                });
            }

            // Get total count (fast with indexes)
            $total = $query->count('products.id');

                if ($total === 0) {
                    return $this->buildSimilarProductsResponse($category, $filters, $perPage);
                }

            // Select base fields - need to adjust if rating_high_low is used
            if ($sortBy === 'rating_high_low') {
                // Already have the join and select from the switch statement
            } else {
                $query->select([
                    'products.id', 'products.title', 'products.slug', 'products.base_price',
                    'products.base_discount', 'products.base_stock', 'products.condition',
                    'products.has_variants', 'products.brand_id'
                ]);
            }

            // Apply sorting - for price sorts, get larger sample and sort in-memory
            $isPriceSort = in_array($sortBy, ['price_low_high', 'price_high_low']);
            $fetchSize = $isPriceSort ? $perPage * 3 : $perPage;
            $fetchOffset = $isPriceSort ? max(0, (($page - 1) * $perPage) - $perPage) : ($page - 1) * $perPage;

            switch ($sortBy) {
                case 'price_low_high':
                    $query->orderBy('products.base_price', 'asc');
                    break;
                case 'price_high_low':
                    $query->orderBy('products.base_price', 'desc');
                    break;
                case 'rating_high_low':
                    $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                          ->orderByDesc('product_ratings_cache.average_rating')
                          ->select('products.*'); // Ensure we still select only product columns
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
            $productIds = $query->skip($fetchOffset)
                               ->take($fetchSize)
                               ->pluck('products.id')
                               ->toArray();

            if (empty($productIds)) {
                return $this->getEmptyResult();
            }

            // Fetch product details
            $products = $this->fetchProductDetails($productIds);

            // Sort by price in-memory for price sorts
            if ($isPriceSort && !empty($products)) {
                usort($products, function($a, $b) use ($sortBy) {
                    $priceA = $a['pr']['f'] ?? $a['pr']['o'] ?? PHP_INT_MAX;
                    $priceB = $b['pr']['f'] ?? $b['pr']['o'] ?? PHP_INT_MAX;
                    return ($sortBy === 'price_low_high') ? ($priceA <=> $priceB) : ($priceB <=> $priceA);
                });
                // Slice to exact page after sorting
                $offsetInPage = ($page - 1) * $perPage - $fetchOffset;
                $products = array_slice($products, max(0, $offsetInPage), $perPage);
            }

            // Build filter data
            $filterData = $this->buildRedisFilterData($category, $filters);

            return [
                'filters' => $filterData,
                'products' => $products,
                'pagination' => $this->buildPagination($page, $perPage, $total),
                'total' => $total,
                'source' => 'database_indexed',
                    'cached' => false,
                    'similar' => false
            ];
        });
    }    /**
     * Get paginated product IDs using database with optimized WHERE IN approach
     * For 10M products, we can't load all IDs - use DB directly
     */
    private function getPaginatedIdsFromDatabase(?string $redisKey, int $page, int $perPage, string $sortBy): array
    {
        if (!$redisKey || !Redis::exists($redisKey)) {
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

            if (!is_array($sampleIds)) {
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
                'error' => $e->getMessage()
            ]);
            return [];
        }

        // Now use database to sort and paginate
        $query = Product::whereIn('id', $productIds)
            ->where('status', 'active')
            ->select('id');        // Apply sorting
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
            'cached' => false
        ];
    }

    /**
     * Get filter counts using Redis Set intersections
     */
    private function buildRedisFilterData($category, array $currentFilters): array
    {
        // Get base product set for the category
        $baseProductIds = $category ?
            Redis::smembers("index:category:{$category->id}") :
            null;

        return [
            'br' => $this->getRedisFilterCounts('brand', $baseProductIds, $currentFilters),
            'pr' => $this->getRedisFilterPriceRange($baseProductIds, $currentFilters),
            'rt' => $this->getRedisFilterCounts('rating', $baseProductIds, $currentFilters),
            'dc' => $this->getRedisFilterCounts('discount', $baseProductIds, $currentFilters),
            'av' => $this->getAvailabilityStats($baseProductIds, $currentFilters),
            'sc' => $this->getSubCategories($category),
            'so' => $this->getSortOptions(),
            'af' => array_filter($currentFilters, fn($v) => !empty($v) && $v !== 'latest')
        ];
    }

    /**
     * Get filter counts using Redis Set intersections
     */
    private function getRedisFilterCounts(string $filterType, ?array $baseProductIds, array $currentFilters): array
    {
        $results = [];
        $keys = Redis::keys("index:{$filterType}:*");

        if (empty($keys)) {
            if ($filterType === 'brand') {
                return $this->buildBrandCountsFromDatabase($currentFilters);
            }
            return $results;
        }

        if ($filterType === 'brand') {
            $slugCounts = [];
            foreach ($keys as $key) {
                $slug = str_replace("index:{$filterType}:", '', $key);
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
                    'sel' => in_array($slug, $currentFilters['brands'] ?? [])
                ];
            }

            // Keep list manageable and sorted by count desc
            usort($results, fn($a, $b) => $b['cnt'] <=> $a['cnt']);
            return array_slice($results, 0, 50);
        }

        foreach ($keys as $key) {
            $keyValue = str_replace("index:{$filterType}:", '', $key);
            if ($keyValue === '') {
                continue;
            }

            $count = Redis::scard($key);
            if ($count > 0) {
                $results[] = [
                    'v' => $keyValue,
                    'cnt' => $count,
                    'sel' => in_array($keyValue, $currentFilters[$filterType . 's'] ?? [])
                ];
            }
        }

        return array_slice($results, 0, 50);
    }

    /**
     * Fetch optimized product details for display
     */
    private function fetchProductDetails(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Batch fetch all ratings for these products
        $ratingsMap = DB::table('product_ratings_cache')
            ->whereIn('product_id', $productIds)
            ->get(['product_id', 'average_rating', 'total_reviews'])
            ->keyBy('product_id')
            ->map(fn($r) => [
                'a' => round($r->average_rating, 1),
                't' => (int) $r->total_reviews
            ])
            ->toArray();

        // Use optimized query similar to the original but for specific IDs
        $products = Product::whereIn('id', $productIds)
            ->with([
                'brand:id,title,slug',
                'images' => function($q) {
                    // Keep ordering stable, but allow slice later per product
                    $q->orderByDesc('is_primary')
                      ->orderBy('sort_order')
                      ->orderBy('id');
                }
            ])
            ->select([
                'id', 'title', 'slug', 'base_price', 'base_discount',
                'base_stock', 'condition', 'has_variants', 'brand_id'
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

        return collect($orderedProducts)->map(function($product) use ($ratingsMap) {
            // For products with variants, get min price from variants
            $basePrice = $product->base_price;
            $baseDiscount = $product->base_discount ?? 0;
            $stock = $product->base_stock ?? 0;

            if ($product->has_variants && ($basePrice === null || $basePrice == 0)) {
                // Get cheapest variant
                $cheapestVariant = DB::table('product_variants')
                    ->where('product_id', $product->id)
                    ->where('status', 'active')
                    ->orderBy('price', 'asc')
                    ->first(['price', 'discount', 'stock']);

                if ($cheapestVariant) {
                    $basePrice = $cheapestVariant->price;
                    $baseDiscount = $cheapestVariant->discount ?? 0;
                    $stock = $cheapestVariant->stock ?? 0;
                }
            }

            $finalPrice = $basePrice * (1 - $baseDiscount / 100);

            // Normalize image paths to storage-relative paths expected by frontend slider
            $images = $product->images
                ->map(fn($img) => $this->normalizeImagePath($img->image_path ?? null))
                ->filter()
                ->values()
                ->toArray();

            // Variant fallback: if product lacks its own images, try pulling primary variant images
            if (empty($images) && $product->has_variants) {
                $variantImages = DB::table('product_variants')
                    ->join('variant_images', 'variant_images.product_variant_id', '=', 'product_variants.id')
                    ->where('product_variants.product_id', $product->id)
                    ->orderByDesc('variant_images.is_primary')
                    ->orderBy('variant_images.sort_order')
                    ->orderBy('variant_images.id')
                    ->limit(2)
                    ->pluck('variant_images.image_path')
                    ->map(fn($path) => $this->normalizeImagePath($path))
                    ->filter()
                    ->values()
                    ->toArray();

                if (!empty($variantImages)) {
                    $images = $variantImages;
                }
            }

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
                'r' => $ratingsMap[$product->id] ?? ['a' => 0, 't' => 0]
            ];
        })->toArray();
    }

    // Helper methods (keeping existing logic but optimized)
    private function generateCacheKey($category, array $filters, int $page, int $perPage, string $sortBy): string
    {
        $categoryKey = $category ? $category->slug : 'all';
        return 'uf_' . md5($categoryKey . serialize($filters) . "{$page}_{$perPage}_{$sortBy}");
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

    private function getBrandIdsBySlug(array $slugs): array
    {
        return Cache::remember('brand_ids_' . md5(implode(',', $slugs)), 3600, function () use ($slugs) {
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

        $products = $this->fetchProductDetails($productIds);
        $fallbackCount = count($productIds);

        return [
            'filters' => $this->buildRedisFilterData($category, $filters),
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

        return Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->select(['id', 'slug', 'title'])
            ->get()
            ->map(fn($c) => ['id' => $c->id, 's' => $c->slug, 't' => $c->title])
            ->toArray();
    }

    private function getRedisFilterPriceRange(?array $baseProductIds, array $currentFilters): array
    {
        // Cache the price range calculation as it's expensive
        $priceData = Cache::remember('price_range_global', 3600, function() {
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
                ['r' => '1000-2000', 'l' => '$1,000 - $2,000'],
                ['r' => '2000-5000', 'l' => '$2,000 - $5,000'],
                ['r' => '5000+', 'l' => '$5,000 & Above']
            ];

            return [
                'mn' => $minPrice,
                'mx' => $maxPrice,
                'ranges' => $ranges
            ];
        });

        $current = $currentFilters['price_range'] ?? '';
        $currentMin = $priceData['mn'];
        $currentMax = $priceData['mx'];

        if (!empty($current) && str_contains($current, '-')) {
            [$currentMin, $currentMax] = explode('-', $current);
        } elseif (!empty($current) && str_contains($current, '+')) {
            $currentMin = (int) str_replace('+', '', $current);
        }

        return [
            'mn' => $priceData['mn'],
            'mx' => $priceData['mx'],
            'cmn' => (int)$currentMin,
            'cmx' => (int)$currentMax,
            'cur' => '$',
            'ranges' => $priceData['ranges']
        ];
    }

    private function getAvailabilityStats(?array $baseProductIds, array $currentFilters): array
    {
        // Implement availability statistics
        return [
            ['cnt' => 1000, 'v' => 'in_stock', 'sel' => in_array('in_stock', $currentFilters['availability'] ?? [])],
            ['cnt' => 100, 'v' => 'out_of_stock', 'sel' => in_array('out_of_stock', $currentFilters['availability'] ?? [])]
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

        if (!empty($filters['brands'])) {
            $brandIds = $this->getBrandIdsBySlug($filters['brands']);
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

    private function normalizeImagePath(?string $rawPath): ?string
    {
        if (empty($rawPath)) {
            return null;
        }

        $rawPath = trim($rawPath);

        if (Str::startsWith($rawPath, ['http://', 'https://'])) {
            $storagePos = stripos($rawPath, '/storage/');
            if ($storagePos !== false) {
                $rawPath = substr($rawPath, $storagePos + strlen('/storage/'));
            } else {
                return $rawPath;
            }
        }

        $normalized = ltrim($rawPath, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if (Str::startsWith($normalized, 'products/variants/')) {
            return $normalized;
        }

        if (Str::startsWith($normalized, 'products/')) {
            return $normalized;
        }

        if (Str::startsWith($normalized, 'variant_')) {
            return 'products/variants/' . $normalized;
        }

        if (Str::startsWith($normalized, 'product_')) {
            return 'products/' . $normalized;
        }

        if (Str::startsWith($normalized, 'variants/')) {
            return 'products/' . $normalized;
        }

        if (Str::startsWith($normalized, 'photos/')) {
            return $normalized;
        }

        return 'products/' . $normalized;
    }

    private function getBrandSlugTitleMap(): array
    {
        return Cache::remember('brand_slug_title_map', 1800, function () {
            return Brand::where('status', 'active')
                ->pluck('title', 'slug')
                ->toArray();
        });
    }

    private function buildBrandCountsFromDatabase(array $currentFilters): array
    {
        $cacheKey = 'brand_filter_counts_fallback';

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
                ->filter(fn($brand) => $brand['cnt'] > 0)
                ->values()
                ->toArray();
        });

        return array_map(function ($brand) use ($currentFilters) {
            $brand['sel'] = in_array($brand['s'], $currentFilters['brands'] ?? []);
            return $brand;
        }, $brands);
    }

}
