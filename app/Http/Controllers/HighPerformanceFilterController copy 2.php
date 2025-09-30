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
use Illuminate\Support\Facades\Redis;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 1800; // 30 minutes for filter aggregations
    private const PRODUCTS_CACHE_TTL = 300; // 5 minutes for product lists
    private const MAX_PRICE_DEFAULT = 10000;
    private const BATCH_SIZE = 5000;
    private const MAX_EXECUTION_TIME = 10000;

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
            $currentFilters = $this->parseCurrentFilters($request);

            // Generate cache keys
            $filtersCacheKey = $this->generateFiltersCacheKey($categoryContext, $currentFilters);
            $productsCacheKey = $this->generateProductsCacheKey($categoryContext, $currentFilters, $request);
            $statsCacheKey = 'stats_' . md5(serialize([$categoryContext?->id, $currentFilters]));
            $subcategoryCacheKey = $categoryContext ? "subcategory_ids_{$categoryContext->id}" : null;
            $brandIdsCacheKey = !empty($currentFilters['brands']) ? 'brand_ids_' . md5(implode(',', $currentFilters['brands'])) : null;

            // Pipeline all cache checks
            $cachedData = [];
            $pipelineKeys = array_filter([$filtersCacheKey, $productsCacheKey, $statsCacheKey, $subcategoryCacheKey, $brandIdsCacheKey]);
            $results = Redis::pipeline(function ($pipe) use ($pipelineKeys) {
                foreach ($pipelineKeys as $key) {
                    $pipe->get($key);
                }
            });

            // Process pipeline results
            foreach ($pipelineKeys as $index => $key) {
                $cachedData[$key] = $results[$index] ? RedisHelper::deserializeData($results[$index]) : null;
            }

            // Build filter data if not cached
            $filterData = $cachedData[$filtersCacheKey] ?? $this->buildOptimizedFilterData($categoryContext, $currentFilters);
            if (!$cachedData[$filtersCacheKey]) {
                RedisHelper::put($filtersCacheKey, $filterData, self::CACHE_TTL);
            }

            // Build product data if not cached
            $productData = $cachedData[$productsCacheKey] ?? $this->getOptimizedFilteredProducts($categoryContext, $currentFilters, $request);
            if (!$cachedData[$productsCacheKey]) {
                RedisHelper::put($productsCacheKey, $productData, self::PRODUCTS_CACHE_TTL);
            }

            // Build stats if not cached
            $statsData = $cachedData[$statsCacheKey] ?? $this->getPreAggregatedStats($categoryContext, $currentFilters);
            if (!$cachedData[$statsCacheKey]) {
                RedisHelper::put($statsCacheKey, $statsData, self::CACHE_TTL);
            }

            $response = [
                'success' => true,
                'filters' => $filterData,
                'products' => $productData['products'],
                'pagination' => $productData['pagination'],
                'meta' => [
                    'total_products' => $productData['total'],
                    'current_filters' => $currentFilters,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'cache_hit' => !empty($cachedData[$filtersCacheKey]) && !empty($cachedData[$productsCacheKey]) && !empty($cachedData[$statsCacheKey])
                ]
            ];

            return response()->json($response, 200, [
                'Cache-Control' => 'public, max-age=' . self::PRODUCTS_CACHE_TTL
            ]);
        } catch (\Exception $e) {
            Log::error('Optimized filter error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'execution_time' => microtime(true) - $startTime
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
                $query->where(function ($q) use ($category, $subcategoryIds) {
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
                try {
                    $query->join('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                        ->where('product_ratings_cache.average_rating', '>=', $minRating);
                } catch (\Exception $e) {
                    Log::error('Error joining product_ratings_cache: ' . $e->getMessage());
                    $this->applyFallbackRatingFilter($query, $minRating);
                }
            } else {
                Log::warning('product_ratings_cache table not found, using fallback rating filter');
                $this->applyFallbackRatingFilter($query, $minRating);
            }
            Log::debug('Rating Filter Applied', ['min_rating' => $minRating]);
        }
    }

    /**
     * Fallback rating filter using product_reviews
     */
    private function applyFallbackRatingFilter($query, int $minRating)
    {
        $query->whereExists(function ($subQuery) use ($minRating) {
            $subQuery->select(DB::raw(1))
                ->from('product_reviews')
                ->whereColumn('product_reviews.product_id', 'products.id')
                ->groupBy('product_reviews.product_id')
                ->havingRaw('AVG(CAST(rate AS DECIMAL(3,2))) >= ?', [$minRating]);
        });
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
                'id',
                'title',
                'slug',
                'price',
                'discount',
                'stock',
                'condition',
                'cat_id',
                'child_cat_id',
                'brand_id',
                'created_at'
            ])
            ->where('status', 'active')
            ->whereNotIn('id', $existingIds);

        if ($category) {
            if ($category->parent_id === null) {
                $subcategoryIds = $this->getSubcategoryIds($category->id);
                $query->where(function ($q) use ($category, $subcategoryIds) {
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
            ->take($needed)
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
    /**
     * Get pre-aggregated statistics with single CTE
     */
    private function getPreAggregatedStats($category, array $currentFilters): array
    {
        $cacheKey = 'stats_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, 3600, function () use ($category, $currentFilters) {
            $query = DB::table('products')
                ->selectRaw('
                MIN(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as min_price,
                MAX(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as max_price,
                SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_count,
                SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
            ')
                ->where('status', 'active');

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function ($q) use ($category, $subcategoryIds) {
                        $q->where('cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('child_cat_id', $category->id);
                }
            }

            $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['price_range' => 1]));
            $baseStats = $query->first();

            $stats = [
                'price_range' => [
                    'min' => (float) ($baseStats->min_price ?? 0),
                    'max' => (float) ($baseStats->max_price ?? self::MAX_PRICE_DEFAULT),
                    'current_min' => $this->getCurrentPriceMin($currentFilters),
                    'current_max' => $this->getCurrentPriceMax($currentFilters),
                    'currency' => '$'
                ],
                'availability' => [
                    'in_stock' => [
                        'count' => (int) ($baseStats->in_stock_count ?? 0),
                        'label' => 'In Stock',
                        'value' => 'in_stock',
                        'selected' => in_array('in_stock', $currentFilters['availability'] ?? [])
                    ],
                    'out_of_stock' => [
                        'count' => (int) ($baseStats->out_of_stock_count ?? 0),
                        'label' => 'Out of Stock',
                        'value' => 'out_of_stock',
                        'selected' => in_array('out_of_stock', $currentFilters['availability'] ?? [])
                    ]
                ]
            ];

            // Ratings and discounts as separate queries (cached individually)
            $stats['ratings'] = $this->getOptimizedRatingStats($category, $currentFilters);
            $stats['discounts'] = $this->getOptimizedDiscountStats($category, $currentFilters);

            return $stats;
        });
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
        // Log::debug('Built Query', [...]); // Comment out in prod

        $this->applyOptimizedSorting($query, $currentFilters['sortBy'] ?? 'latest');

        // Use cursor for efficient pagination on large datasets (better than skip/take for pgSQL)
        $products = $query->cursor()->skip($offset)->take($perPage)->toArray();
        // Log::debug('Raw Products', [...]); // Comment out

        $productList = $this->transformProducts($products);
        // dd($productList);
        // Log::debug('Transformed Products', [...]);

        $totalCount = $this->getApproximateCount($category, $currentFilters); // Already optimized
        // Log::debug('Total Count', [...]);

        // Fallback logic (keep as is)
        if (count($productList) < $perPage && $page === 1) {
            $fallbackProducts = $this->getFallbackProducts($category, $productList, $perPage - count($productList), $currentFilters);
            $productList = array_merge($productList, $fallbackProducts);
            // Log::debug('Fallback Products Added', [...]);
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
                $rawQuery->where(function ($q) use ($category, $subcategoryIds) {
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
     * Execute paginated query with keyset pagination
     */
    private function executePaginatedQuery($query, int $offset, int $limit, string $sortBy)
    {
        if ($sortBy === 'latest' && $offset > 0) {
            // Keyset pagination for 'created_at DESC'
            $lastId = RedisHelper::remember("last_id_{$offset}_{$limit}", 300, function () use ($query, $offset, $limit) {
                return $query->clone()
                    ->select('id')
                    ->orderBy('created_at', 'DESC')
                    ->offset($offset - 1)
                    ->limit(1)
                    ->value('id');
            });

            if ($lastId) {
                $query->where('id', '<', $lastId);
            }
        }

        return $query->orderBy('created_at', 'DESC')
            ->take($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get approximate count
     */
    /**
     * Get approximate count
     */
    private function getApproximateCount($category, array $currentFilters): int
    {
        $cacheKey = 'count_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, 300, function () use ($category, $currentFilters) {
            if ($this->isLargeDataset($category)) {
                $baseCount = DB::selectOne("
                SELECT reltuples::BIGINT AS estimate 
                FROM pg_class 
                WHERE relname = 'products'
            ")->estimate ?? 0;

                $factor = 1.0;
                if ($category) $factor *= 0.1;
                if (!empty($currentFilters['brands'])) $factor *= 0.2;
                if (!empty($currentFilters['price_range'])) $factor *= 0.3;
                if (!empty($currentFilters['ratings'])) $factor *= 0.4;
                if (!empty($currentFilters['discounts'])) $factor *= 0.3;
                if (!empty($currentFilters['availability'])) $factor *= 0.5;
                return max(1, (int) ($baseCount * $factor));
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
            $isLarge = $count > 10000;  // Lowered threshold for more aggressive approximation
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
                    $query->where(function ($q) use ($category, $subcategoryIds) {
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
            Log::debug('transformProducts: No products to transform');
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

            // Structure images for frontend lazy loading
            $primaryImage = $productImages[0] ?? '/images/default-placeholder.jpg'; // Fallback image
            $additionalImages = array_slice($productImages, 1);

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

        Log::debug('transformProducts: Transformed products', [
            'count' => count($transformed),
            'product_ids' => $productIds
        ]);

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

        $brands = [];
        foreach (array_chunk($productIds, self::BATCH_SIZE) as $chunk) {
            $chunkBrands = DB::table('products')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->whereIn('products.id', $chunk)
                ->select('products.id as product_id', 'brands.title', 'brands.slug')
                ->take(count($chunk)) // Limit to the number of product IDs in chunk
                ->get()
                ->keyBy('product_id')
                ->map(function ($brand) {
                    return ['title' => $brand->title, 'slug' => $brand->slug];
                })
                ->toArray();
            $brands = array_merge($brands, $chunkBrands);
        }

        return $brands;
    }

    /**
     * Batch load images
     */
    private function getImagesBatch(array $productIds): array
    {
        if (empty($productIds)) {
            Log::debug('getImagesBatch: Empty product IDs provided');
            return [];
        }

        $cacheKey = 'images_batch_' . md5(implode(',', $productIds));
        return RedisHelper::remember($cacheKey, 300, function () use ($productIds) {
            // Initialize result array with empty arrays for all product IDs
            $images = array_fill_keys($productIds, []);

            foreach (array_chunk($productIds, self::BATCH_SIZE) as $chunk) {
                // Check individual product image caches first
                $cacheKeys = array_map(fn($id) => "product_images_{$id}", $chunk);
                $cachedImages = RedisHelper::mget($cacheKeys);

                $missingIds = [];
                $chunkImages = [];
                foreach ($chunk as $index => $productId) {
                    $cachedData = $cachedImages[$cacheKeys[$index]] ?? null;
                    if ($cachedData !== null && !empty($cachedData)) {
                        $chunkImages[$productId] = $cachedData;
                    } else {
                        $missingIds[] = $productId;
                    }
                }

                // Fetch missing images from DB
                if (!empty($missingIds)) {
                    try {
                        if (!DB::getSchemaBuilder()->hasTable('product_images')) {
                            Log::warning('getImagesBatch: product_images table not found, returning empty images for missing IDs', [
                                'missing_ids' => $missingIds
                            ]);
                            $dbImages = array_fill_keys($missingIds, []);
                        } else {
                            $dbImages = DB::table('product_images')
                                ->whereIn('product_id', $missingIds)
                                ->select('product_id', 'image_path')
                                ->orderBy('product_id')
                                ->orderBy('sort_order')
                                ->get()
                                ->groupBy('product_id')
                                ->map(function ($productImages) {
                                    return $productImages->take(3)->pluck('image_path')->toArray();
                                })
                                ->toArray();

                            Log::debug('getImagesBatch: Fetched images from DB', [
                                'missing_ids' => $missingIds,
                                'fetched_count' => count($dbImages),
                                'db_images' => $dbImages
                            ]);

                            // Pipeline cache updates for missing images
                            Redis::pipeline(function ($pipe) use ($dbImages) {
                                foreach ($dbImages as $productId => $imagePaths) {
                                    $pipe->set("product_images_{$productId}", serialize($imagePaths));
                                    $pipe->expire("product_images_{$productId}", 300);
                                }
                            });
                        }

                        // Merge database images into chunkImages
                        foreach ($dbImages as $productId => $imagePaths) {
                            $chunkImages[$productId] = $imagePaths;
                        }
                    } catch (\Exception $e) {
                        Log::error('getImagesBatch: Error fetching images from DB', [
                            'error' => $e->getMessage(),
                            'missing_ids' => $missingIds
                        ]);
                        // Ensure missing IDs have empty arrays to avoid null issues
                        $dbImages = array_fill_keys($missingIds, []);
                        $chunkImages = array_merge($chunkImages, $dbImages);
                    }
                } else {
                    Log::debug('getImagesBatch: All images found in cache', [
                        'chunk_ids' => $chunk,
                        'cached_images' => $chunkImages
                    ]);
                }

                // Merge chunkImages into the main images array
                foreach ($chunkImages as $productId => $imagePaths) {
                    $images[$productId] = $imagePaths;
                }
            }

            Log::debug('getImagesBatch: Final images', [
                'product_ids' => $productIds,
                'image_count' => count(array_filter($images, fn($arr) => !empty($arr))),
                'images' => $images
            ]);

            return $images;
        });
    }

    /**
     * Batch load ratings
     */
    private function getRatingsBatch(array $productIds): array
    {
        if (empty($productIds)) {
            Log::debug('getRatingsBatch: Empty product IDs provided');
            return [];
        }

        $ratings = [];
        if ($this->hasProductRatingsCacheTable()) {
            try {
                foreach (array_chunk($productIds, self::BATCH_SIZE) as $chunk) {
                    $chunkRatings = DB::table('product_ratings_cache')
                        ->whereIn('product_id', $chunk)
                        ->select('product_id', 'average_rating as average', 'total_reviews as total')
                        ->take(count($chunk))
                        ->get()
                        ->keyBy('product_id')
                        ->map(function ($rating) {
                            return [
                                'average' => (float) $rating->average,
                                'total' => (int) $rating->total
                            ];
                        })
                        ->toArray();
                    $ratings = array_merge($ratings, $chunkRatings);
                }

                Log::debug('getRatingsBatch: Fetched ratings', [
                    'product_ids' => $productIds,
                    'ratings_count' => count($ratings),
                    'ratings' => $ratings
                ]);
            } catch (\Exception $e) {
                Log::error('Error querying product_ratings_cache in getRatingsBatch: ' . $e->getMessage());
            }
        } else {
            Log::debug('getRatingsBatch: product_ratings_cache table not found, using defaults');
        }

        // Fill missing ratings with defaults
        $result = array_merge(
            array_fill_keys($productIds, ['average' => 0, 'total' => 0]),
            $ratings
        );

        return $result;
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
        return RedisHelper::remember("subcategory_ids_{$parentCategoryId}", 3600, function () use ($parentCategoryId) {
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
        $cacheKey = 'rating_stats_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters) {
            $ratings = [];

            if ($this->hasProductRatingsCacheTable()) {
                try {
                    $query = DB::table('product_ratings_cache as prc')
                        ->join('products', 'products.id', '=', 'prc.product_id')
                        ->selectRaw('FLOOR(prc.average_rating) as rating, COUNT(*) as count')
                        ->where('products.status', 'active')
                        ->where('prc.average_rating', '>=', 1);

                    if ($category) {
                        if ($category->parent_id === null) {
                            $subcategoryIds = $this->getSubcategoryIds($category->id);
                            $query->where(function ($q) use ($category, $subcategoryIds) {
                                $q->where('products.cat_id', $category->id);
                                if (!empty($subcategoryIds)) {
                                    $q->orWhereIn('products.child_cat_id', $subcategoryIds);
                                }
                            });
                        } else {
                            $query->where('products.child_cat_id', $category->id);
                        }
                    }

                    $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['ratings' => 1]));
                    $ratings = $query->groupBy(DB::raw('FLOOR(prc.average_rating)'))
                        ->orderBy('rating', 'desc')
                        ->get()
                        ->mapWithKeys(function ($item) {
                            return [(int) $item->rating => (int) $item->count];
                        })->toArray();

                    Log::debug('getOptimizedRatingStats: Fetched ratings from product_ratings_cache', [
                        'category_id' => $category?->id,
                        'filters' => $currentFilters,
                        'ratings' => $ratings
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error querying product_ratings_cache: ' . $e->getMessage());
                    $ratings = $this->getFallbackRatingStats($category, $currentFilters);
                }
            } else {
                $ratings = $this->getFallbackRatingStats($category, $currentFilters);
            }

            $result = [];
            for ($i = 5; $i >= 1; $i--) {
                $result[] = [
                    'value' => $i,
                    'label' => "$i ★ & above",
                    'count' => $ratings[$i] ?? 0,
                    'selected' => in_array((string) $i, $currentFilters['ratings'] ?? [])
                ];
            }

            return $result;
        });
    }

    /**
     * Fallback method to compute rating stats from product_reviews
     */
    private function getFallbackRatingStats($category, array $currentFilters): array
    {
        $cacheKey = 'fallback_rating_stats_' . md5(serialize([$category?->id, $currentFilters]));
        return RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters) {
            $query = DB::table('product_reviews as pr')
                ->join('products', 'products.id', '=', 'pr.product_id')
                ->selectRaw('FLOOR(AVG(CAST(pr.rate AS DECIMAL(3,2)))) as rating, COUNT(DISTINCT pr.product_id) as count')
                ->where('products.status', 'active')
                ->where('pr.rate', '>=', 1);

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function ($q) use ($category, $subcategoryIds) {
                        $q->where('products.cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('products.child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('products.child_cat_id', $category->id);
                }
            }

            $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['ratings' => 1]));
            $ratings = $query->groupBy(DB::raw('FLOOR(AVG(CAST(pr.rate AS DECIMAL(3,2))))'))
                ->orderBy('rating', 'desc')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [(int) $item->rating => (int) $item->count];
                })->toArray();

            Log::debug('getFallbackRatingStats: Fetched fallback ratings', [
                'category_id' => $category?->id,
                'filters' => $currentFilters,
                'ratings' => $ratings
            ]);

            return $ratings;
        });
    }

    /**
     * Check if product_ratings_cache table exists (cached)
     */
    private function hasProductRatingsCacheTable(): bool
    {
        return RedisHelper::remember('product_ratings_cache_table_exists', 3600, function () {
            $exists = DB::getSchemaBuilder()->hasTable('product_ratings_cache');
            if (!$exists) {
                Log::warning('product_ratings_cache table not found');
            }
            return $exists;
        });
    }

    /**
     * Get optimized discount statistics
     */
    private function getOptimizedDiscountStats($category, array $currentFilters): array
    {
        $slabs = [5, 10, 20, 30, 50];
        $cacheKey = 'discount_stats_' . md5(serialize([$category?->id, $currentFilters]));

        return RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters, $slabs) {
            // Build a single query with conditional aggregation
            $query = DB::table('products')
                ->selectRaw('
                    SUM(CASE WHEN discount >= 5 THEN 1 ELSE 0 END) as count_5,
                    SUM(CASE WHEN discount >= 10 THEN 1 ELSE 0 END) as count_10,
                    SUM(CASE WHEN discount >= 20 THEN 1 ELSE 0 END) as count_20,
                    SUM(CASE WHEN discount >= 30 THEN 1 ELSE 0 END) as count_30,
                    SUM(CASE WHEN discount >= 50 THEN 1 ELSE 0 END) as count_50
                ')
                ->where('status', 'active');

            // Apply category filter
            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function ($q) use ($category, $subcategoryIds) {
                        $q->where('cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('child_cat_id', $category->id);
                }
            }

            // Apply other filters (excluding discounts)
            $this->applyOptimizedFilters($query, array_diff_key($currentFilters, ['discounts' => 1]));

            // Execute query
            $result = $query->first();

            Log::debug('getOptimizedDiscountStats: Fetched discount stats', [
                'category_id' => $category?->id,
                'filters' => $currentFilters,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'result' => (array) $result
            ]);

            // Build discount array
            $discounts = [];
            foreach ($slabs as $slab) {
                $count = (int) ($result->{"count_$slab"} ?? 0);
                $discounts[] = [
                    'value' => $slab,
                    'label' => "$slab% & above",
                    'count' => $count,
                    'selected' => in_array((string) $slab, $currentFilters['discounts'] ?? [])
                ];
            }

            return $discounts;
        });
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
