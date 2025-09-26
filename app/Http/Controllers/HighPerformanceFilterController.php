<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use App\Helpers\UrlEncryptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 1800; // 30 minutes for filter aggregations
    private const PRODUCTS_CACHE_TTL = 300; // 5 minutes for product lists
    private const MAX_PRICE_DEFAULT = 10000;
    private const BATCH_SIZE = 50;
    private const MAX_EXECUTION_TIME = 45;

    /**
     * Get filter data with aggressive caching and query optimization
     */
    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);

        try {
            set_time_limit(self::MAX_EXECUTION_TIME);

            // Step 1: Resolve category context
            $categoryContext = $this->resolveCategoryContext($request, $path);
            if (!$categoryContext) {
                Log::warning('No valid category context resolved', ['path' => $path]);
            }
            Log::debug('Step 1: Resolved Category Context', ['category' => $categoryContext?->toArray()]);

            $currentFilters = $this->parseCurrentFilters($request);
            Log::debug('Step 2: Parsed Current Filters', $currentFilters);

            // Generate cache keys
            $filtersCacheKey = $this->generateFiltersCacheKey($categoryContext, $currentFilters);
            $productsCacheKey = $this->generateProductsCacheKey($categoryContext, $currentFilters, $request);
            Log::debug('Step 3: Generated Cache Keys', ['filters' => $filtersCacheKey, 'products' => $productsCacheKey]);

            // Try to get cached data
            $cachedData = RedisHelper::mget([$filtersCacheKey, $productsCacheKey]);
            $filterData = $cachedData[$filtersCacheKey] ?? null;
            $productData = $cachedData[$productsCacheKey] ?? null;

            // Build filter data if not cached
            if (!$filterData) {
                $filterData = $this->buildOptimizedFilterData($categoryContext, $currentFilters);
                RedisHelper::put($filtersCacheKey, $filterData, self::CACHE_TTL);
            }
            Log::debug('Step 4: Filter Data', $filterData);

            // Build product data if not cached
            if (!$productData) {
                $productData = $this->getOptimizedFilteredProducts($categoryContext, $currentFilters, $request);
                RedisHelper::put($productsCacheKey, $productData, self::PRODUCTS_CACHE_TTL);
            }
            Log::debug('Step 5: Product Data', $productData);

            $response = [
                'success' => true,
                'filters' => $filterData,
                'products' => $productData['products'],
                'pagination' => $productData['pagination'],
                'meta' => [
                    'total_products' => $productData['total'],
                    'current_filters' => $currentFilters,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'cache_hit' => !is_null($cachedData[$filtersCacheKey]) && !is_null($cachedData[$productsCacheKey])
                ]
            ];

            return response()->json($response, 200, [
                'Cache-Control' => 'public, max-age=' . self::PRODUCTS_CACHE_TTL
            ]);
        } catch (\Exception $e) {
            Log::error('Optimized filter error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'execution_time' => microtime(true) - $startTime,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load products',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Build optimized filter data for the response
     */
    private function buildOptimizedFilterData($category, array $currentFilters): array
    {
        $startTime = microtime(true);
        Log::debug('Building Optimized Filter Data', [
            'category_id' => $category?->id,
            'filters' => $currentFilters
        ]);

        // Get pre-aggregated stats (includes price range, availability, etc.)
        $baseStats = $this->getPreAggregatedStats($category, $currentFilters);

        // Build filter data
        $filterData = [
            'brands' => $this->getOptimizedBrandStats($category, $currentFilters),
            'price_range' => $this->buildPriceRange($baseStats, $currentFilters),
            'ratings' => $this->getOptimizedRatingStats($category, $currentFilters),
            'discounts' => $this->getOptimizedDiscountStats($category, $currentFilters),
            'availability' => $this->getAvailabilityStats($baseStats),
            'sub_categories' => $this->getSubCategories($category),
            'sort_options' => $this->getSortOptions(),
            'display_options' => $this->getDisplayOptions(),
            'applied_filters' => $this->formatAppliedFilters($currentFilters)
        ];

        Log::debug('Filter Data Built', [
            'data' => $filterData,
            'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);

        return $filterData;
    }

    /**
     * Build optimized query with proper indexes
     */
    private function buildOptimizedQuery($category, array $currentFilters)
    {
        $query = DB::table('products')
            ->select([
                'products.id',
                'products.title',
                'products.slug',
                'products.price',
                'products.discount',
                'products.stock',
                'products.condition',
                'products.cat_id',
                'products.child_cat_id',
                'products.brand_id',
                'products.created_at',
                'products.updated_at'
            ])
            ->where('products.status', 'active');

        // Apply category filter
        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $query->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $query->where('child_cat_id', $category->id);
            }
            Log::debug('Category Filter Applied', [
                'category_id' => $category->id,
                'is_parent' => $category->parent_id === null ? 'main' : 'child',
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);
        }

        // Apply filters
        $this->applyOptimizedFilters($query, $currentFilters);

        return $query;
    }

    /**
     * Apply filters with optimized queries
     */
    private function applyOptimizedFilters($query, array $filters)
    {
        // Price range filter (applied first to ensure precedence)
        if (!empty($filters['price_range']) && str_contains($filters['price_range'], '-')) {
            [$minPrice, $maxPrice] = explode('-', $filters['price_range']);
            $minPrice = (float) trim($minPrice);
            $maxPrice = (float) trim($maxPrice);

            if ($minPrice >= 0 && $maxPrice > $minPrice) {
                $query->whereRaw('
                    CASE 
                        WHEN discount > 0 THEN 
                            price * (1 - discount / 100.0)
                        ELSE 
                            price 
                    END BETWEEN ? AND ?
                ', [$minPrice, $maxPrice]);
                Log::debug('Price Filter Applied', [
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice,
                    'sql' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
            } else {
                Log::warning('Invalid price range', [
                    'min_price' => $minPrice,
                    'max_price' => $maxPrice
                ]);
            }
        }

        // Brand filter
        if (!empty($filters['brands'])) {
            $brandIds = RedisHelper::remember('brand_ids_' . md5(implode(',', $filters['brands'])), 3600, function () use ($filters) {
                return Brand::whereIn('slug', $filters['brands'])
                    ->where('status', 'active')
                    ->pluck('id')
                    ->toArray();
            });
            if (!empty($brandIds)) {
                $query->whereIn('brand_id', $brandIds);
                Log::debug('Brand Filter Applied', ['brand_ids' => $brandIds]);
            }
        }

        // Discount filter
        if (!empty($filters['discounts'])) {
            $minDiscount = min(array_map('intval', $filters['discounts']));
            $query->where('discount', '>=', $minDiscount);
            Log::debug('Discount Filter Applied', ['min_discount' => $minDiscount]);
        }

        // Availability filter
        if (!empty($filters['availability'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['availability'] as $availability) {
                    if ($availability === 'in_stock') {
                        $q->orWhere('stock', '>', 0);
                    } elseif ($availability === 'out_of_stock') {
                        $q->orWhere('stock', '<=', 0);
                    }
                }
            });
            Log::debug('Availability Filter Applied', ['availability' => $filters['availability']]);
        }

        // Rating filter
        if (!empty($filters['ratings'])) {
            $minRating = min(array_map('intval', $filters['ratings']));
            if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
                $query->join('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                    ->where('product_ratings_cache.average_rating', '>=', $minRating);
            } else {
                $query->whereExists(function ($subQuery) use ($minRating) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_reviews')
                        ->whereColumn('product_reviews.product_id', 'products.id')
                        ->groupBy('product_reviews.product_id')
                        ->havingRaw('AVG(CAST(rate AS DECIMAL(3,2))) >= ?', [$minRating]);
                });
            }
            Log::debug('Rating Filter Applied', ['min_rating' => $minRating]);
        }
    }

    /**
     * Get fallback products
     */
    private function getFallbackProducts($category, array $currentProducts, int $needed, array $currentFilters): array
    {
        if ($needed <= 0) {
            return [];
        }

        $existingIds = array_column($currentProducts, 'id');

        $query = DB::table('products')
            ->select([
                'id', 'title', 'slug', 'price', 'discount', 'stock', 'condition',
                'cat_id', 'child_cat_id', 'brand_id', 'created_at'
            ])
            ->where('status', 'active')
            ->whereNotIn('id', $existingIds);

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $query->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $query->where('child_cat_id', $category->id);
            }
        }

        // Apply price filter to fallback products
        if (!empty($currentFilters['price_range']) && str_contains($currentFilters['price_range'], '-')) {
            [$minPrice, $maxPrice] = explode('-', $currentFilters['price_range']);
            $minPrice = (float) trim($minPrice);
            $maxPrice = (float) trim($maxPrice);
            $query->whereRaw('
                CASE 
                    WHEN discount > 0 THEN price * (1 - discount / 100.0)
                    ELSE price 
                END BETWEEN ? AND ?
            ', [$minPrice, $maxPrice]);
            Log::debug('Price Filter Applied to Fallback Products', [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);
        }

        $products = $query->orderBy('created_at', 'desc')
            ->limit($needed)
            ->get()
            ->toArray();

        Log::debug('Fallback Products', [
            'count' => count($products),
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        return $this->transformProducts($products);
    }

    /**
     * Get pre-aggregated statistics
     */
    private function getPreAggregatedStats($category, array $currentFilters): array
    {
        $stats = [];

        // Price range statistics
        $priceQuery = DB::table('products')
            ->select([
                DB::raw('MIN(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as min_price'),
                DB::raw('MAX(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as max_price')
            ])
            ->where('status', 'active');

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $priceQuery->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $priceQuery->where('child_cat_id', $category->id);
            }
        }

        // Apply all filters except price_range to get accurate min/max
        $this->applyOptimizedFilters($priceQuery, array_diff_key($currentFilters, ['price_range' => 1]));
        $priceStats = $priceQuery->first();

        $stats['price_range'] = [
            'min' => (float) ($priceStats->min_price ?? 0),
            'max' => (float) ($priceStats->max_price ?? self::MAX_PRICE_DEFAULT),
            'current_min' => $this->getCurrentPriceMin($currentFilters),
            'current_max' => $this->getCurrentPriceMax($currentFilters),
            'currency' => '$'
        ];

        // Rating statistics
        if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
            $ratingQuery = DB::table('product_ratings_cache')
                ->join('products', 'product_ratings_cache.product_id', '=', 'products.id')
                ->select([
                    DB::raw('FLOOR(average_rating) as rating'),
                    DB::raw('COUNT(*) as count')
                ])
                ->where('products.status', 'active')
                ->where('average_rating', '>=', 1);

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $ratingQuery->where(function($q) use ($category, $subcategoryIds) {
                        $q->where('products.cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('products.child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $ratingQuery->where('products.child_cat_id', $category->id);
                }
            }

            $this->applyOptimizedFilters($ratingQuery, array_diff_key($currentFilters, ['ratings' => 1]));
            $ratings = $ratingQuery->groupBy(DB::raw('FLOOR(average_rating)'))
                ->orderBy('rating', 'desc')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [(int) $item->rating => (int) $item->count];
                })->toArray();

            $stats['ratings'] = [];
            for ($i = 5; $i >= 1; $i--) {
                $stats['ratings'][] = [
                    'value' => $i,
                    'label' => "$i ★ & above",
                    'count' => $ratings[$i] ?? 0,
                    'selected' => in_array((string) $i, $currentFilters['ratings'] ?? [])
                ];
            }
        }

        // Discount statistics
        $discountQuery = DB::table('products')
            ->select([
                DB::raw('CASE 
                    WHEN discount >= 50 THEN 50
                    WHEN discount >= 30 THEN 30
                    WHEN discount >= 20 THEN 20
                    WHEN discount >= 10 THEN 10
                    WHEN discount >= 5 THEN 5
                    ELSE 0
                END as discount_slab'),
                DB::raw('COUNT(*) as count')
            ])
            ->where('status', 'active')
            ->where('discount', '>', 0);

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $discountQuery->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $discountQuery->where('child_cat_id', $category->id);
            }
        }

        $this->applyOptimizedFilters($discountQuery, array_diff_key($currentFilters, ['discounts' => 1]));
        $discounts = $discountQuery->groupBy(DB::raw('discount_slab'))
            ->get()
            ->mapWithKeys(function ($item) {
                return [(int) $item->discount_slab => (int) $item->count];
            })->toArray();

        $slabs = [5, 10, 20, 30, 50];
        $stats['discounts'] = [];
        foreach ($slabs as $slab) {
            $stats['discounts'][] = [
                'value' => $slab,
                'label' => "$slab% & above",
                'count' => $discounts[$slab] ?? 0,
                'selected' => in_array((string) $slab, $currentFilters['discounts'] ?? [])
            ];
        }

        // Availability statistics
        $availQuery = DB::table('products')
            ->select([
                DB::raw('SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_count'),
                DB::raw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ])
            ->where('status', 'active');

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $availQuery->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $availQuery->where('child_cat_id', $category->id);
            }
        }

        $this->applyOptimizedFilters($availQuery, array_diff_key($currentFilters, ['availability' => 1]));
        $availStats = $availQuery->first();

        $stats['availability'] = [
            'in_stock' => [
                'count' => (int) ($availStats->in_stock_count ?? 0),
                'label' => 'In Stock',
                'value' => 'in_stock',
                'selected' => in_array('in_stock', $currentFilters['availability'] ?? [])
            ],
            'out_of_stock' => [
                'count' => (int) ($availStats->out_of_stock_count ?? 0),
                'label' => 'Out of Stock',
                'value' => 'out_of_stock',
                'selected' => in_array('out_of_stock', $currentFilters['availability'] ?? [])
            ]
        ];

        return $stats;
    }

    /**
     * Get optimized filtered products
     */
    private function getOptimizedFilteredProducts($category, array $currentFilters, Request $request)
    {
        $perPage = min((int) $request->input('show', 12), 60);
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        $query = $this->buildOptimizedQuery($category, $currentFilters);
        Log::debug('Built Query', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        $this->debugRawProductCount($category);

        $this->applyOptimizedSorting($query, $currentFilters['sortBy'] ?? 'latest');

        $products = $this->executePaginatedQuery($query, $offset, $perPage);
        Log::debug('Raw Products', [
            'count' => count($products),
            'first_few' => array_slice($products, 0, 3)
        ]);

        $productList = $this->transformProducts($products);
        Log::debug('Transformed Products', ['count' => count($productList)]);

        $totalCount = $this->getApproximateCount($category, $currentFilters);
        Log::debug('Total Count', ['total' => $totalCount]);

        // Apply fallback logic only if needed
        if (count($productList) < $perPage && $page === 1) {
            $fallbackProducts = $this->getFallbackProducts($category, $productList, $perPage - count($productList), $currentFilters);
            $productList = array_merge($productList, $fallbackProducts);
            Log::debug('Fallback Products Added', ['count' => count($fallbackProducts)]);
        }

        return [
            'products' => array_slice($productList, 0, $perPage),
            'pagination' => [
                'current_page' => $page,
                'last_page' => max(1, ceil($totalCount / $perPage)),
                'total' => $totalCount,
                'per_page' => $perPage,
                'from' => $offset + 1,
                'to' => min($offset + count($productList), $totalCount)
            ],
            'total' => $totalCount
        ];
    }

    /**
     * Debug raw product count
     */
    private function debugRawProductCount($category)
    {
        $rawQuery = DB::table('products')
            ->selectRaw('COUNT(*) as count')
            ->where('status', 'active');

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $rawQuery->where(function($q) use ($category, $subcategoryIds) {
                    $q->where('cat_id', $category->id);
                    if (!empty($subcategoryIds)) {
                        $q->orWhereIn('child_cat_id', $subcategoryIds);
                    }
                });
            } else {
                $rawQuery->where('child_cat_id', $category->id);
            }
        }

        $count = $rawQuery->first()->count ?? 0;
        Log::debug('Debug Raw Product Count', [
            'category_id' => $category?->id,
            'is_parent' => $category?->parent_id === null ? 'main' : 'child',
            'raw_count' => $count,
            'sql' => $rawQuery->toSql(),
            'bindings' => $rawQuery->getBindings()
        ]);
    }

    /**
     * Apply optimized sorting
     */
    private function applyOptimizedSorting($query, string $sortBy)
    {
        switch ($sortBy) {
            case 'price_low_high':
                $query->orderByRaw('CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END ASC');
                break;
            case 'price_high_low':
                $query->orderByRaw('CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END DESC');
                break;
            case 'rating_high_low':
                if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
                    $query->leftJoin('product_ratings_cache as prc', 'products.id', '=', 'prc.product_id')
                        ->orderByRaw('COALESCE(prc.average_rating, 0) DESC')
                        ->addSelect('prc.average_rating');
                } else {
                    $query->orderBy('created_at', 'DESC');
                }
                break;
            case 'name_a_z':
                $query->orderBy('title', 'ASC');
                break;
            case 'name_z_a':
                $query->orderBy('title', 'DESC');
                break;
            case 'latest':
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }
        Log::debug('Sorting Applied', ['sortBy' => $sortBy, 'sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
    }

    /**
     * Execute paginated query
     */
    private function executePaginatedQuery($query, int $offset, int $limit)
    {
        return $query->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get approximate count
     */
    private function getApproximateCount($category, array $currentFilters): int
    {
        $cacheKey = 'count_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, 300, function () use ($category, $currentFilters) {
            if ($this->isLargeDataset($category)) {
                return $this->getStatisticalCount($category, $currentFilters);
            }
            $countQuery = $this->buildOptimizedQuery($category, $currentFilters);
            return $countQuery->count();
        });
    }

    /**
     * Get statistical count estimation
     */
    private function getStatisticalCount($category, array $currentFilters): int
    {
        try {
            $baseCount = DB::selectOne("
                SELECT reltuples::BIGINT AS estimate 
                FROM pg_class 
                WHERE relname = 'products'
            ")->estimate ?? 0;

            $factor = 1.0;
            if ($category) {
                $factor *= 0.1;
            }
            if (!empty($currentFilters['brands'])) {
                $factor *= 0.2;
            }
            if (!empty($currentFilters['price_range'])) {
                $factor *= 0.3;
            }
            return max(1, (int) ($baseCount * $factor));
        } catch (\Exception $e) {
            $countQuery = $this->buildOptimizedQuery($category, $currentFilters);
            return $countQuery->count();
        }
    }

    /**
     * Check if dataset is large
     */
    private function isLargeDataset($category): bool
    {
        static $isLarge = null;
        if ($isLarge === null) {
            $count = RedisHelper::remember('total_products_count', 3600, function () {
                return DB::table('products')->where('status', 'active')->count();
            });
            $isLarge = $count > 1000000;
        }
        return $isLarge;
    }

    /**
     * Get optimized brand statistics
     */
    private function getOptimizedBrandStats($category, array $currentFilters): array
    {
        $cacheKey = 'brand_stats_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters) {
            $query = DB::table('products')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->where('products.status', 'active')
                ->where('brands.status', 'active')
                ->select([
                    'brands.id',
                    'brands.title',
                    'brands.slug',
                    DB::raw('COUNT(DISTINCT products.id) as product_count')
                ]);

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function($q) use ($category, $subcategoryIds) {
                        $q->where('products.cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('products.child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('products.child_cat_id', $category->id);
                }
            }

            $filtersWithoutBrands = $currentFilters;
            unset($filtersWithoutBrands['brands']);
            $this->applyOptimizedFilters($query, $filtersWithoutBrands);

            $brands = $query->groupBy('brands.id', 'brands.title', 'brands.slug')
                ->havingRaw('COUNT(DISTINCT products.id) > 0')
                ->orderBy('brands.title')
                ->limit(50)
                ->get()
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'title' => $brand->title,
                        'slug' => $brand->slug,
                        'count' => (int) $brand->product_count,
                        'selected' => false
                    ];
                })
                ->toArray();

            return $brands;
        });
    }

    /**
     * Transform products
     */
    private function transformProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_column($products, 'id');
        $brands = $this->getBrandsBatch($productIds);
        $images = $this->getImagesBatch($productIds);
        $ratings = $this->getRatingsBatch($productIds);

        $transformed = [];
        foreach ($products as $product) {
            $productId = $product->id;
            $brand = $brands[$productId] ?? null;
            $productImages = $images[$productId] ?? [];
            $rating = $ratings[$productId] ?? ['average' => 0, 'total' => 0];

            $originalPrice = (float) $product->price;
            $discount = (int) $product->discount;
            $finalPrice = $discount > 0 ? $originalPrice * (1 - $discount / 100) : $originalPrice;

            $transformed[] = [
                'id' => $productId,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => [
                    'original' => $originalPrice,
                    'final' => round($finalPrice, 2),
                    'currency' => '$',
                    'discount_percentage' => $discount
                ],
                'stock' => (int) $product->stock,
                'brand' => $brand,
                'images' => $productImages,
                'rating' => [
                    'average' => (float) $rating['average'],
                    'total' => (int) $rating['total'],
                    'stars' => round($rating['average'])
                ],
                'badges' => $this->getProductBadges($product),
                'urls' => [
                    'detail' => "/product-detail/{$product->slug}",
                    'add_to_cart' => "/cart/add/{$productId}",
                    'add_to_wishlist' => "/wishlist/add/{$productId}"
                ]
            ];
        }

        return $transformed;
    }

    /**
     * Batch load brands
     */
    private function getBrandsBatch(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->whereIn('products.id', $productIds)
            ->select('products.id as product_id', 'brands.title', 'brands.slug')
            ->get()
            ->keyBy('product_id')
            ->map(function ($brand) {
                return ['title' => $brand->title, 'slug' => $brand->slug];
            })
            ->toArray();
    }

    /**
     * Batch load images
     */
    private function getImagesBatch(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        return DB::table('product_images')
            ->whereIn('product_id', $productIds)
            ->select('product_id', 'image_path')
            ->orderBy('product_id')
            ->orderBy('sort_order')
            ->limit(count($productIds) * 2)
            ->get()
            ->groupBy('product_id')
            ->map(function ($productImages) {
                return $productImages->take(2)->pluck('image_path')->toArray();
            })
            ->toArray();
    }

    /**
     * Batch load ratings
     */
    private function getRatingsBatch(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
            return DB::table('product_ratings_cache')
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'average_rating as average', 'total_reviews as total')
                ->get()
                ->keyBy('product_id')
                ->map(function ($rating) {
                    return [
                        'average' => (float) $rating->average,
                        'total' => (int) $rating->total
                    ];
                })
                ->toArray();
        }

        return array_fill_keys($productIds, ['average' => 0, 'total' => 0]);
    }

    /**
     * Parse current filters
     */
    private function parseCurrentFilters(Request $request): array
    {
        $filters = [];

        $brands = $request->input('brands', []);
        if (is_string($brands)) {
            $brands = explode(',', $brands);
        }
        $filters['brands'] = array_slice(array_filter((array) $brands), 0, 20);

        $ratings = $request->input('ratings', []);
        if (is_string($ratings)) {
            $ratings = explode(',', $ratings);
        }
        $filters['ratings'] = array_slice(array_filter((array) $ratings), 0, 5);

        $discounts = $request->input('discounts', []);
        if (is_string($discounts)) {
            $discounts = explode(',', $discounts);
        }
        $filters['discounts'] = array_slice(array_filter((array) $discounts), 0, 10);

        $filters['price_range'] = $request->input('price_range', '');
        $filters['availability'] = (array) $request->input('availability', []);
        $filters['sortBy'] = $request->input('sortBy', 'latest');
        $filters['show'] = min((int) $request->input('show', 12), 60);

        return array_filter($filters, function ($value) {
            return !empty($value);
        });
    }

    /**
     * Generate filters cache key
     */
    private function generateFiltersCacheKey($category, array $filters): string
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        return "filters_v4:{$catKey}:{$filterHash}";
    }

    /**
     * Generate products cache key
     */
    private function generateProductsCacheKey($category, array $filters, Request $request): string
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        $page = $request->input('page', 1);
        return "products_v4:{$catKey}:{$filterHash}:{$page}";
    }

    /**
     * Resolve category context
     */
    private function resolveCategoryContext(Request $request, $path = null)
    {
        if ($path) {
            try {
                $decodedPath = UrlEncryptor::decodePath($path);
                Log::debug('Decoded Path', ['decoded' => $decodedPath, 'original' => $path]);

                $segments = array_filter(explode('/', trim($decodedPath, '/')));
                if (empty($segments)) {
                    Log::warning('Empty category segments', ['path' => $path]);
                    return null;
                }

                $currentCategory = Category::whereNull('parent_id')
                    ->where('status', 'active')
                    ->where('slug', $segments[0])
                    ->first();

                if (!$currentCategory) {
                    Log::warning('Main category not found', ['slug' => $segments[0]]);
                    return null;
                }

                array_shift($segments);
                foreach ($segments as $segment) {
                    $child = Category::where('parent_id', $currentCategory->id)
                        ->where('slug', $segment)
                        ->where('status', 'active')
                        ->first();
                    if (!$child) {
                        break;
                    }
                    $currentCategory = $child;
                }

                return $currentCategory;
            } catch (\Exception $e) {
                Log::error('Error resolving category context: ' . $e->getMessage(), [
                    'path' => $path,
                    'trace' => $e->getTraceAsString()
                ]);
                return null;
            }
        }

        $pathSegments = $request->path() !== '/' ? explode('/', trim($request->path(), '/')) : [];
        $productCatIndex = array_search('product-cat', $pathSegments);

        if ($productCatIndex !== false && isset($pathSegments[$productCatIndex + 1])) {
            $slugPath = implode('/', array_slice($pathSegments, $productCatIndex + 1));
            return $this->resolveCategoryContext($request, $slugPath);
        }

        return null;
    }

    /**
     * Get subcategory IDs
     */
    private function getSubcategoryIds(int $parentCategoryId): array
    {
        return RedisHelper::remember("subcategory_ids_{$parentCategoryId}", 3600, function() use ($parentCategoryId) {
            return Category::where('parent_id', $parentCategoryId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });
    }

    /**
     * Build price range
     */
    private function buildPriceRange($baseStats, array $currentFilters): array
    {
        $min = $baseStats && isset($baseStats['price_range']) ? (float) ($baseStats['price_range']['min'] ?? 0) : 0;
        $max = $baseStats && isset($baseStats['price_range']) ? (float) ($baseStats['price_range']['max'] ?? self::MAX_PRICE_DEFAULT) : self::MAX_PRICE_DEFAULT;

        return [
            'min' => $min,
            'max' => $max,
            'current_min' => $this->getCurrentPriceMin($currentFilters),
            'current_max' => $this->getCurrentPriceMax($currentFilters),
            'currency' => '$'
        ];
    }

    /**
     * Get optimized rating statistics
     */
    private function getOptimizedRatingStats($category, array $currentFilters): array
    {
        $ratings = [];
        for ($i = 1; $i <= 5; $i++) {
            $query = DB::table('products')
                ->selectRaw('COUNT(*) as count')
                ->where('status', 'active')
                ->whereExists(function ($subQuery) use ($i) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_reviews')
                        ->whereColumn('product_reviews.product_id', 'products.id')
                        ->groupBy('product_reviews.product_id')
                        ->havingRaw('AVG(CAST(rate AS DECIMAL(3,2))) >= ?', [$i]);
                });

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function($q) use ($category, $subcategoryIds) {
                        $q->where('cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('child_cat_id', $category->id);
                }
            }

            $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['ratings' => 1]));
            $count = $query->first()->count;

            $ratings[] = [
                'value' => $i,
                'label' => "$i ★ & above",
                'count' => (int) $count,
                'selected' => in_array((string) $i, $currentFilters['ratings'] ?? [])
            ];
        }

        return array_reverse($ratings);
    }

    /**
     * Get optimized discount statistics
     */
    private function getOptimizedDiscountStats($category, array $currentFilters): array
    {
        $slabs = [5, 10, 20, 30, 50];
        $discounts = [];
        
        foreach ($slabs as $slab) {
            $query = DB::table('products')
                ->selectRaw('COUNT(*) as count')
                ->where('status', 'active')
                ->where('discount', '>=', $slab);

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function($q) use ($category, $subcategoryIds) {
                        $q->where('cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('child_cat_id', $category->id);
                }
            }

            $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['discounts' => 1]));
            $count = $query->first()->count;

            $discounts[] = [
                'value' => $slab,
                'label' => "$slab% & above",
                'count' => (int) $count,
                'selected' => in_array((string) $slab, $currentFilters['discounts'] ?? [])
            ];
        }

        return $discounts;
    }

    /**
     * Get availability statistics
     */
    private function getAvailabilityStats($baseStats): array
    {
        $inStockCount = $baseStats && isset($baseStats['availability']['in_stock']['count']) 
            ? (int) $baseStats['availability']['in_stock']['count'] : 0;
        $outOfStockCount = $baseStats && isset($baseStats['availability']['out_of_stock']['count']) 
            ? (int) $baseStats['availability']['out_of_stock']['count'] : 0;

        return [
            'in_stock' => [
                'count' => $inStockCount,
                'label' => 'In Stock',
                'value' => 'in_stock',
                'selected' => false
            ],
            'out_of_stock' => [
                'count' => $outOfStockCount,
                'label' => 'Out of Stock',
                'value' => 'out_of_stock',
                'selected' => false
            ]
        ];
    }

    /**
     * Get sub-categories
     */
    private function getSubCategories($category): array
    {
        if (!$category) {
            return [];
        }

        return Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->select(['id', 'title', 'slug'])
            ->get()
            ->map(function ($cat) {
                return [
                    'id' => $cat->id,
                    'title' => $cat->title,
                    'slug' => $cat->slug,
                    'count' => 0,
                    'selected' => false
                ];
            })->toArray();
    }

    /**
     * Get sort options
     */
    private function getSortOptions(): array
    {
        return [
            ['value' => 'latest', 'label' => 'Latest'],
            ['value' => 'price_low_high', 'label' => 'Price: Low to High'],
            ['value' => 'price_high_low', 'label' => 'Price: High to Low'],
            ['value' => 'rating_high_low', 'label' => 'Rating: High to Low'],
            ['value' => 'name_a_z', 'label' => 'Name: A to Z'],
            ['value' => 'name_z_a', 'label' => 'Name: Z to A']
        ];
    }

    /**
     * Get display options
     */
    private function getDisplayOptions(): array
    {
        return [
            ['value' => 12, 'label' => 'Show 12'],
            ['value' => 24, 'label' => 'Show 24'],
            ['value' => 36, 'label' => 'Show 36']
        ];
    }

    /**
     * Get current price min
     */
    private function getCurrentPriceMin(array $filters): float
    {
        if (empty($filters['price_range'])) {
            return 0;
        }
        $parts = explode('-', $filters['price_range']);
        return (float) ($parts[0] ?? 0);
    }

    /**
     * Get current price max
     */
    private function getCurrentPriceMax(array $filters): float
    {
        if (empty($filters['price_range'])) {
            return self::MAX_PRICE_DEFAULT;
        }
        $parts = explode('-', $filters['price_range']);
        return (float) ($parts[1] ?? self::MAX_PRICE_DEFAULT);
    }

    /**
     * Format applied filters
     */
    private function formatAppliedFilters(array $filters): array
    {
        $applied = [];
        if (!empty($filters['brands'])) $applied['brands'] = $filters['brands'];
        if (!empty($filters['ratings'])) $applied['ratings'] = $filters['ratings'];
        if (!empty($filters['discounts'])) $applied['discounts'] = $filters['discounts'];
        if (!empty($filters['price_range'])) $applied['price_range'] = $filters['price_range'];
        if (!empty($filters['availability'])) $applied['availability'] = $filters['availability'];
        return $applied;
    }

    /**
     * Get product badges
     */
    private function getProductBadges($product): array
    {
        $badges = [];
        
        if ($product->discount > 0) {
            $badges[] = ['text' => $product->discount . '% Off', 'class' => 'discount'];
        }
        
        if ($product->stock <= 0) {
            $badges[] = ['text' => 'Sold Out', 'class' => 'sold-out'];
        } elseif ($product->condition === 'new') {
            $badges[] = ['text' => 'New', 'class' => 'new'];
        }
        
        return $badges;
    }
}