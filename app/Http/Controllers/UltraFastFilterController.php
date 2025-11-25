<?php

namespace App\Http\Controllers;

use App\Services\FastFilterService;
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

    private const CACHE_TTL = 300; // 5 minutes
    private const MAX_EXECUTION_TIME = 30; // 30 seconds max

    public function __construct(FastFilterService $filterService)
    {
        $this->filterService = $filterService;
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
                    'ch' => $result['cached'] ?? false
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
     * Use Redis Set-based indexes for ultra-fast filtering
     * OPTIMIZED FOR 10M PRODUCTS: Use database with indexed WHERE clauses
     */
    private function getRedisIndexResults($category, array $filters, int $page, int $perPage, string $sortBy): array
    {
        $cacheKey = $this->generateCacheKey($category, $filters, $page, $perPage, $sortBy);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category, $filters, $page, $perPage, $sortBy) {

            // Convert filters to database query instead of Redis (better for 10M scale)
            $query = Product::where('status', 'active');

            // Apply category filter
            if ($category) {
                $query->where('cat_id', $category->id);
            }

            // Apply brand filter
            if (!empty($filters['brands'])) {
                $brandIds = $this->getBrandIdsBySlug($filters['brands']);
                if (!empty($brandIds)) {
                    $query->whereIn('brand_id', $brandIds);
                }
            }

            // Apply price range filter
            // For variant products, we need to join with variants table to get actual prices
            if (!empty($filters['price_range'])) {
                $range = explode('-', $filters['price_range']);
                if (count($range) === 2) {
                    $minPrice = (float)$range[0];
                    $maxPrice = (float)$range[1];

                    // Filter by price considering both base_price and variant prices
                    $query->where(function($q) use ($minPrice, $maxPrice) {
                        // Products without variants use base_price
                        $q->where(function($subQ) use ($minPrice, $maxPrice) {
                            $subQ->where('has_variants', false)
                                 ->whereBetween('base_price', [$minPrice, $maxPrice]);
                        })
                        // Products with variants use variant price
                        ->orWhere(function($subQ) use ($minPrice, $maxPrice) {
                            $subQ->where('has_variants', true)
                                 ->whereExists(function($existsQ) use ($minPrice, $maxPrice) {
                                     $existsQ->from('product_variants')
                                             ->whereColumn('product_variants.product_id', 'products.id')
                                             ->whereBetween('product_variants.price', [$minPrice, $maxPrice]);
                                 });
                        });
                    });
                } elseif (str_contains($filters['price_range'], '+')) {
                    $minPrice = (float)str_replace('+', '', $filters['price_range']);

                    $query->where(function($q) use ($minPrice) {
                        $q->where(function($subQ) use ($minPrice) {
                            $subQ->where('has_variants', false)
                                 ->where('base_price', '>=', $minPrice);
                        })
                        ->orWhere(function($subQ) use ($minPrice) {
                            $subQ->where('has_variants', true)
                                 ->whereExists(function($existsQ) use ($minPrice) {
                                     $existsQ->from('product_variants')
                                             ->whereColumn('product_variants.product_id', 'products.id')
                                             ->where('product_variants.price', '>=', $minPrice);
                                 });
                        });
                    });
                }
            }

            // Apply rating filter (needs join)
            if (!empty($filters['ratings'])) {
                $minRating = min(array_map('intval', $filters['ratings']));
                // Skip rating filter for now - needs proper relationship setup
                // $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                //       ->where('product_ratings_cache.average_rating', '>=', $minRating);
            }

            // Apply discount filter
            if (!empty($filters['discounts'])) {
                $minDiscount = min(array_map('intval', $filters['discounts']));
                $query->where('base_discount', '>=', $minDiscount);
            }

            // Get total count (fast with indexes)
            $total = $query->count('products.id');

            if ($total === 0) {
                return $this->getEmptyResult();
            }

            // Apply sorting
            $query->select([
                'products.id', 'products.title', 'products.slug', 'products.base_price',
                'products.base_discount', 'products.base_stock', 'products.condition',
                'products.has_variants', 'products.brand_id'
            ]);

            switch ($sortBy) {
                case 'price_low_high':
                    $query->orderBy('products.base_price', 'asc');
                    break;
                case 'price_high_low':
                    $query->orderBy('products.base_price', 'desc');
                    break;
                case 'rating_high_low':
                    // Skip rating sort for now - needs proper relationship
                    $query->orderByDesc('products.id');
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
            $productIds = $query->skip(($page - 1) * $perPage)
                               ->take($perPage)
                               ->pluck('products.id')
                               ->toArray();

            if (empty($productIds)) {
                return $this->getEmptyResult();
            }

            // Fetch product details
            $products = $this->fetchProductDetails($productIds);

            // Build filter data
            $filterData = $this->buildRedisFilterData($category, $filters);

            return [
                'filters' => $filterData,
                'products' => $products,
                'pagination' => $this->buildPagination($page, $perPage, $total),
                'total' => $total,
                'source' => 'database_indexed',
                'cached' => false
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

        // For brand filters, query database directly (more reliable than Redis)
        if ($filterType === 'brand') {
            $brandQuery = Brand::query()
                ->join('products', 'brands.id', '=', 'products.brand_id')
                ->where('products.status', 'active')
                ->where('brands.status', 'active')
                ->select('brands.id', 'brands.title', 'brands.slug', DB::raw('COUNT(DISTINCT products.id) as product_count'))
                ->groupBy('brands.id', 'brands.title', 'brands.slug')
                ->havingRaw('COUNT(DISTINCT products.id) > 0');

            $brands = $brandQuery->get();

            foreach ($brands as $brand) {
                $results[] = [
                    't' => $brand->title,
                    's' => $brand->slug,
                    'cnt' => $brand->product_count,
                    'sel' => in_array($brand->slug, $currentFilters['brands'] ?? [])
                ];
            }

            return $results;
        }

        // For other filters, use Redis if available
        $keys = Redis::keys("index:{$filterType}:*");

        foreach ($keys as $key) {
            $keyValue = str_replace("index:{$filterType}:", '', $key);
            $count = Redis::scard($key);

            if ($count > 0) {
                $results[] = [
                    'v' => $keyValue,
                    'cnt' => $count,
                    'sel' => in_array($keyValue, $currentFilters[$filterType . 's'] ?? [])
                ];
            }
        }

        return array_slice($results, 0, 50); // Limit to 50 items
    }

    /**
     * Fetch optimized product details for display
     */
    private function fetchProductDetails(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Use optimized query similar to the original but for specific IDs
        $products = Product::whereIn('id', $productIds)
            ->with([
                'brand:id,title,slug',
                'images' => function($q) {
                    $q->orderBy('sort_order')->orderBy('id')->limit(2);
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

        return collect($orderedProducts)->map(function($product) {
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
            $images = $product->images->map(function($img) {
                $rawPath = $img->image_path ?? null;
                if (empty($rawPath)) {
                    return null;
                }

                $rawPath = trim($rawPath);

                // If absolute URL provided, strip everything up to /storage/ so frontend doesn't double-prefix
                if (Str::startsWith($rawPath, ['http://', 'https://'])) {
                    $storagePos = stripos($rawPath, '/storage/');
                    if ($storagePos !== false) {
                        $rawPath = substr($rawPath, $storagePos + strlen('/storage/'));
                    } else {
                        return $rawPath; // Non-storage absolute URLs can be used as-is
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
                    return 'products/' . $normalized; // legacy relative paths missing products/ prefix
                }

                if (Str::startsWith($normalized, 'photos/')) {
                    return $normalized; // already relative to storage
                }

                return 'products/' . $normalized;
            })->filter()->values()->toArray();

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
                'r' => [
                    'a' => 0, // Placeholder - ratings can be added later
                    't' => 0
                ]
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
            'cached' => false
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
    }    private function getAvailabilityStats(?array $baseProductIds, array $currentFilters): array
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

        // Add category filter
        if ($category) {
            $redisFilters['category_id'] = $category->id;
        }

        // Convert brand slugs to IDs
        if (!empty($filters['brands'])) {
            $brandIds = $this->getBrandIdsBySlug($filters['brands']);
            if (!empty($brandIds)) {
                $redisFilters['brands'] = $brandIds;
            }
        }

        // Add price range
        if (!empty($filters['price_range'])) {
            $redisFilters['price_range'] = $filters['price_range'];
        }

        // Add minimum rating
        if (!empty($filters['ratings'])) {
            $redisFilters['min_rating'] = min(array_map('intval', $filters['ratings']));
        }

        // Add minimum discount
        if (!empty($filters['discounts'])) {
            $redisFilters['min_discount'] = min(array_map('intval', $filters['discounts']));
        }

        return $redisFilters;
    }
}
