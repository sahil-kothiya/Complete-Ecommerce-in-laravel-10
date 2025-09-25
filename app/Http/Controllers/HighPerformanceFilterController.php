<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 1800; // 30 minutes for filter aggregations
    private const PRODUCTS_CACHE_TTL = 300; // 5 minutes for product lists
    private const MAX_PRICE_DEFAULT = 10000;
    private const BATCH_SIZE = 50; // Process in smaller batches
    private const MAX_EXECUTION_TIME = 45; // Stop processing before timeout

    /**
     * Get filter data with aggressive caching and query optimization
     */
    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);

        try {
            // Set execution time limit
            set_time_limit(self::MAX_EXECUTION_TIME);

            $categoryContext = $this->resolveCategoryContext($request, $path);
            $currentFilters = $this->parseCurrentFilters($request);

            // Generate cache keys
            $filtersCacheKey = $this->generateFiltersCacheKey($categoryContext, $currentFilters);
            $productsCacheKey = $this->generateProductsCacheKey($categoryContext, $currentFilters, $request);

            // Try to get everything from cache first
            $cachedData = RedisHelper::mget([$filtersCacheKey, $productsCacheKey]);
            $filterData = $cachedData[$filtersCacheKey];
            $productData = $cachedData[$productsCacheKey];

            // If we don't have filter data, build it with optimized queries
            if (!$filterData) {
                $filterData = $this->buildOptimizedFilterData($categoryContext, $currentFilters);
                RedisHelper::put($filtersCacheKey, $filterData, self::CACHE_TTL);
            }

            // If we don't have product data, build it
            if (!$productData) {
                $productData = $this->getOptimizedFilteredProducts($categoryContext, $currentFilters, $request);
                RedisHelper::put($productsCacheKey, $productData, self::PRODUCTS_CACHE_TTL);
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
                    'cache_hit' => $cachedData[$filtersCacheKey] && $cachedData[$productsCacheKey]
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
     * Build filter data using pre-aggregated statistics tables
     */
    private function buildOptimizedFilterData($category, array $currentFilters)
    {
        $categoryIds = $category ? $this->getDescendantIds($category) : null;

        // Use materialized view or pre-aggregated table for statistics
        $baseStats = $this->getPreAggregatedStats($categoryIds, $currentFilters);

        return [
            'price_range' => $this->buildPriceRange($baseStats, $currentFilters),
            'brands' => $this->getOptimizedBrandStats($categoryIds, $currentFilters),
            'ratings' => $this->getOptimizedRatingStats($categoryIds, $currentFilters),
            'discounts' => $this->getOptimizedDiscountStats($categoryIds, $currentFilters),
            'availability' => $this->getAvailabilityStats($baseStats),
            'categories' => $this->getSubCategories($category),
            'sort_options' => $this->getSortOptions(),
            'display_options' => $this->getDisplayOptions(),
            'applied_filters' => $this->formatAppliedFilters($currentFilters)
        ];
    }

    /**
     * Get optimized filtered products with intelligent pagination
     */
    private function getOptimizedFilteredProducts($category, array $currentFilters, Request $request)
    {
        $perPage = min((int) $request->input('show', 12), 60); // Cap at 60
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;

        // Build optimized query with proper indexing
        $query = $this->buildOptimizedQuery($category, $currentFilters);

        // Apply sorting with index hints
        $this->applyOptimizedSorting($query, $currentFilters['sortBy'] ?? 'latest', $category);

        // Use cursor-based pagination for better performance on large datasets
        $products = $this->executePaginatedQuery($query, $offset, $perPage);

        // Get total count using approximation for better performance
        $totalCount = $this->getApproximateCount($category, $currentFilters);

        // Transform products efficiently
        $productList = $this->transformProducts($products);

        // Apply fallback logic only if needed
        if (count($productList) < $perPage && $page === 1) {
            $fallbackProducts = $this->getFallbackProducts($category, $productList, $perPage - count($productList));
            $productList = array_merge($productList, $fallbackProducts);
        }

        return [
            'products' => array_slice($productList, 0, $perPage),
            'pagination' => [
                'current_page' => $page,
                'last_page' => ceil($totalCount / $perPage),
                'total' => $totalCount,
                'per_page' => $perPage,
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $totalCount)
            ],
            'total' => $totalCount
        ];
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
                'products.created_at'
            ])
            ->where('products.status', 'active');

        // Add category filtering with index hints
        if ($category) {
            $categoryIds = $this->getDescendantIds($category);
            $query->where(function ($q) use ($categoryIds) {
                $q->whereIn('cat_id', $categoryIds)
                    ->orWhereIn('child_cat_id', $categoryIds);
            });
        }

        // Apply filters efficiently
        $this->applyOptimizedFilters($query, $currentFilters);

        return $query;
    }

    /**
     * Apply filters with optimized queries
     */
    private function applyOptimizedFilters($query, array $filters)
    {
        // Brand filter with index
        if (!empty($filters['brands'])) {
            $brandIds = Cache::remember('brand_ids_' . md5(implode(',', $filters['brands'])), 3600, function () use ($filters) {
                return Brand::whereIn('slug', $filters['brands'])
                    ->where('status', 'active')
                    ->pluck('id')
                    ->toArray();
            });

            if ($brandIds) {
                $query->whereIn('brand_id', $brandIds);
            }
        }

        // Price range filter with computed column index
        if (!empty($filters['price_range']) && str_contains($filters['price_range'], '-')) {
            [$minPrice, $maxPrice] = explode('-', $filters['price_range']);
            $minPrice = (float) trim($minPrice);
            $maxPrice = (float) trim($maxPrice);

            if ($minPrice >= 0 && $maxPrice > $minPrice) {
                $query->whereRaw('
                    CASE 
                        WHEN discount > 0 THEN 
                            price * (1 - discount::decimal / 100)
                        ELSE 
                            price 
                    END BETWEEN ? AND ?
                ', [$minPrice, $maxPrice]);
            }
        }

        // Discount filter
        if (!empty($filters['discounts'])) {
            $minDiscount = min(array_map('intval', $filters['discounts']));
            $query->where('discount', '>=', $minDiscount);
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
        }

        // Rating filter - use pre-computed rating table if available
        if (!empty($filters['ratings'])) {
            $minRating = min(array_map('intval', $filters['ratings']));

            // Use pre-computed ratings table for better performance
            if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
                $query->join('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                    ->where('product_ratings_cache.average_rating', '>=', $minRating);
            } else {
                // Fallback to subquery (slower)
                $query->whereExists(function ($subQuery) use ($minRating) {
                    $subQuery->select(DB::raw(1))
                        ->from('product_reviews')
                        ->whereColumn('product_reviews.product_id', 'products.id')
                        ->groupBy('product_reviews.product_id')
                        ->havingRaw('AVG(CAST(rate AS DECIMAL(3,2))) >= ?', [$minRating]);
                });
            }
        }
    }

    /**
     * Apply optimized sorting with proper indexes
     */
    private function applyOptimizedSorting($query, string $sortBy, ?Category $category)
    {
        // Add category priority for better relevance
        $categoryPriority = '';
        if ($category) {
            $categoryPriority = "CASE WHEN cat_id = {$category->id} THEN 0 WHEN child_cat_id = {$category->id} THEN 1 ELSE 2 END,";
        }

        switch ($sortBy) {
            case 'price_low_high':
                $query->orderByRaw($categoryPriority . 'CASE WHEN discount > 0 THEN price * (1 - discount::decimal / 100) ELSE price END ASC');
                break;
            case 'price_high_low':
                $query->orderByRaw($categoryPriority . 'CASE WHEN discount > 0 THEN price * (1 - discount::decimal / 100) ELSE price END DESC');
                break;
            case 'rating_high_low':
                // Use pre-computed ratings if available
                if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
                    $query->leftJoin('product_ratings_cache', 'products.id', '=', 'product_ratings_cache.product_id')
                        ->orderByRaw($categoryPriority . 'COALESCE(product_ratings_cache.average_rating, 0) DESC');
                } else {
                    $query->orderByRaw($categoryPriority . 'created_at DESC'); // Fallback
                }
                break;
            case 'name_a_z':
                $query->orderByRaw($categoryPriority . 'title ASC');
                break;
            case 'name_z_a':
                $query->orderByRaw($categoryPriority . 'title DESC');
                break;
            case 'latest':
            default:
                $query->orderByRaw($categoryPriority . 'created_at DESC');
                break;
        }
    }

    /**
     * Execute paginated query with cursor optimization
     */
    private function executePaginatedQuery($query, int $offset, int $limit)
    {
        return $query->offset($offset)
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Get approximate count for better performance on large datasets
     */
    private function getApproximateCount($category, array $currentFilters): int
    {
        $cacheKey = 'count_' . md5(serialize([$category?->id, $currentFilters]));

        return Cache::remember($cacheKey, 300, function () use ($category, $currentFilters) {
            // Use table statistics for very large datasets
            if ($this->isLargeDataset($category)) {
                return $this->getStatisticalCount($category, $currentFilters);
            }

            // Regular count for smaller datasets
            $countQuery = $this->buildOptimizedQuery($category, $currentFilters);
            return $countQuery->count();
        });
    }

    /**
     * Get statistical count estimation for very large datasets
     */
    private function getStatisticalCount($category, array $currentFilters): int
    {
        // Use PostgreSQL's table statistics for estimation
        $baseCount = DB::selectOne("
            SELECT reltuples::BIGINT AS estimate 
            FROM pg_class 
            WHERE relname = 'products'
        ")->estimate ?? 0;

        // Apply rough filtering factors
        $factor = 1.0;

        if ($category) {
            $factor *= 0.1; // Assume category reduces by ~90%
        }

        if (!empty($currentFilters['brands'])) {
            $factor *= 0.2; // Brand filter reduces by ~80%
        }

        if (!empty($currentFilters['price_range'])) {
            $factor *= 0.3; // Price filter reduces by ~70%
        }

        return max(1, (int) ($baseCount * $factor));
    }

    /**
     * Check if dataset is large enough to need statistical estimation
     */
    private function isLargeDataset($category): bool
    {
        static $isLarge = null;

        if ($isLarge === null) {
            $count = Cache::remember('total_products_count', 3600, function () {
                return DB::table('products')->where('status', 'active')->count();
            });
            $isLarge = $count > 1000000; // 1M+ products
        }

        return $isLarge;
    }

    /**
     * Get optimized brand statistics using aggregation table
     */
    private function getOptimizedBrandStats($categoryIds, array $currentFilters): array
    {
        $cacheKey = 'brand_stats_' . md5(serialize([$categoryIds, $currentFilters]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($categoryIds, $currentFilters) {
            $query = DB::table('products')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->where('products.status', 'active')
                ->where('brands.status', 'active');

            if ($categoryIds) {
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('cat_id', $categoryIds)
                        ->orWhereIn('child_cat_id', $categoryIds);
                });
            }

            // Apply non-brand filters
            $filtersWithoutBrands = $currentFilters;
            unset($filtersWithoutBrands['brands']);
            $this->applyOptimizedFilters($query, $filtersWithoutBrands);

            return $query->select([
                'brands.id',
                'brands.title',
                'brands.slug',
                DB::raw('COUNT(products.id) as count')
            ])
                ->groupBy('brands.id', 'brands.title', 'brands.slug')
                ->having('count', '>', 0)
                ->orderBy('brands.title')
                ->limit(50) // Limit brands for performance
                ->get()
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'title' => $brand->title,
                        'slug' => $brand->slug,
                        'count' => (int) $brand->count,
                        'selected' => false
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Transform products efficiently
     */
    private function transformProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        // Get all product IDs for batch operations
        $productIds = array_column($products, 'id');

        // Batch load brands
        $brands = $this->getBrandsBatch($productIds);

        // Batch load images (limit to 2 per product for performance)
        $images = $this->getImagesBatch($productIds);

        // Batch load ratings if using cache table
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
                    'final' => $finalPrice,
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
     * Batch load brands for performance
     */
    private function getBrandsBatch(array $productIds): array
    {
        $brands = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->whereIn('products.id', $productIds)
            ->select('products.id as product_id', 'brands.title', 'brands.slug')
            ->get()
            ->keyBy('product_id')
            ->map(function ($brand) {
                return ['title' => $brand->title, 'slug' => $brand->slug];
            })
            ->toArray();

        return $brands;
    }

    /**
     * Batch load images with limit for performance
     */
    private function getImagesBatch(array $productIds): array
    {
        $images = DB::table('product_images')
            ->whereIn('product_id', $productIds)
            ->select('product_id', 'image_path')
            ->orderBy('product_id')
            ->orderBy('sort_order')
            ->limit(count($productIds) * 2) // Max 2 images per product
            ->get()
            ->groupBy('product_id')
            ->map(function ($productImages) {
                return $productImages->take(2)->pluck('image_path')->toArray();
            })
            ->toArray();

        return $images;
    }

    /**
     * Batch load ratings
     */
    private function getRatingsBatch(array $productIds): array
    {
        // Use cache table if available
        if (DB::getSchemaBuilder()->hasTable('product_ratings_cache')) {
            return DB::table('product_ratings_cache')
                ->whereIn('product_id', $productIds)
                ->select('product_id', 'average_rating as average', 'total_reviews as total')
                ->get()
                ->keyBy('product_id')
                ->toArray();
        }

        // Fallback to empty ratings for performance
        return array_fill_keys($productIds, ['average' => 0, 'total' => 0]);
    }

    // ... (keep other utility methods from original controller)

    private function parseCurrentFilters(Request $request)
    {
        // Same as original but with validation
        $filters = [];

        $filters['brands'] = array_slice((array) $request->input('brands', []), 0, 20); // Limit brands
        if (is_string($filters['brands'])) $filters['brands'] = explode(',', $filters['brands']);

        $filters['ratings'] = array_slice((array) $request->input('ratings', []), 0, 5);
        if (is_string($filters['ratings'])) $filters['ratings'] = explode(',', $filters['ratings']);

        $filters['discounts'] = array_slice((array) $request->input('discounts', []), 0, 10);
        if (is_string($filters['discounts'])) $filters['discounts'] = explode(',', $filters['discounts']);

        $filters['price_range'] = $request->input('price_range', '');
        $filters['availability'] = (array) $request->input('availability', []);
        $filters['sortBy'] = $request->input('sortBy', 'latest');
        $filters['show'] = min((int) $request->input('show', 12), 60); // Cap at 60

        return array_filter($filters, function ($value) {
            return !empty($value);
        });
    }

    private function generateFiltersCacheKey($category, array $filters): string
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        return "filters_v2:{$catKey}:{$filterHash}";
    }

    private function generateProductsCacheKey($category, array $filters, Request $request): string
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        $page = $request->input('page', 1);
        return "products_v2:{$catKey}:{$filterHash}:{$page}";
    }

    /**
     * Get pre-aggregated statistics from materialized views
     */
    private function getPreAggregatedStats($categoryIds, array $currentFilters): array
    {
        $stats = [];

        // Check if materialized views exist
        $hasBrandStats = DB::getSchemaBuilder()->hasTable('brand_product_counts');
        $hasCategoryStats = DB::getSchemaBuilder()->hasTable('category_product_counts');
        $hasRatingStats = DB::getSchemaBuilder()->hasTable('product_ratings_cache');

        // 1. Brand statistics
        if ($hasBrandStats) {
            $brandQuery = DB::table('brand_product_counts')
                ->select([
                    'brand_id as id',
                    'title',
                    'slug',
                    'product_count as count',
                    'min_price',
                    'max_price'
                ])
                ->where('product_count', '>', 0);

            if ($categoryIds) {
                $brandQuery->whereIn('brand_id', function ($subQuery) use ($categoryIds) {
                    $subQuery->select('brand_id')
                        ->from('products')
                        ->where('status', 'active')
                        ->where(function ($q) use ($categoryIds) {
                            $q->whereIn('cat_id', $categoryIds)
                                ->orWhereIn('child_cat_id', $categoryIds);
                        })
                        ->distinct();
                });
            }

            // Apply non-brand filters
            $filtersWithoutBrands = $currentFilters;
            unset($filtersWithoutBrands['brands']);
            if (!empty($filtersWithoutBrands)) {
                $brandQuery->whereIn('brand_id', function ($subQuery) use ($categoryIds, $filtersWithoutBrands) {
                    $subQuery->select('brand_id')
                        ->from('products')
                        ->where('status', 'active');
                    if ($categoryIds) {
                        $subQuery->where(function ($q) use ($categoryIds) {
                            $q->whereIn('cat_id', $categoryIds)
                                ->orWhereIn('child_cat_id', $categoryIds);
                        });
                    }
                    $this->applyOptimizedFilters($subQuery, $filtersWithoutBrands);
                });
            }

            $stats['brands'] = $brandQuery->get()->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'title' => $brand->title,
                    'slug' => $brand->slug,
                    'count' => (int) $brand->count,
                    'min_price' => (float) $brand->min_price,
                    'max_price' => (float) $brand->max_price,
                    'selected' => false
                ];
            })->toArray();
        } else {
            // Fallback to direct query (slower)
            $stats['brands'] = $this->getOptimizedBrandStats($categoryIds, $currentFilters);
        }

        // 2. Category statistics
        if ($hasCategoryStats && $categoryIds) {
            $stats['categories'] = DB::table('category_product_counts')
                ->select([
                    'category_id as id',
                    'title',
                    'slug',
                    'total_product_count as count',
                    'parent_id'
                ])
                ->whereIn('category_id', $categoryIds)
                ->where('total_product_count', '>', 0)
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'title' => $cat->title,
                        'slug' => $cat->slug,
                        'count' => (int) $cat->count,
                        'parent_id' => $cat->parent_id,
                        'selected' => false
                    ];
                })->toArray();
        } else {
            // Fallback to dynamic query
            $stats['categories'] = $this->getSubCategories($categoryIds ? Category::find($categoryIds[0]) : null);
        }

        // 3. Price range statistics
        if ($hasBrandStats || $hasCategoryStats) {
            $priceQuery = DB::table('products')
                ->select([
                    DB::raw('MIN(CASE WHEN discount > 0 THEN price * (1 - discount::decimal / 100) ELSE price END) as min_price'),
                    DB::raw('MAX(CASE WHEN discount > 0 THEN price * (1 - discount::decimal / 100) ELSE price END) as max_price')
                ])
                ->where('status', 'active');

            if ($categoryIds) {
                $priceQuery->where(function ($q) use ($categoryIds) {
                    $q->whereIn('cat_id', $categoryIds)
                        ->orWhereIn('child_cat_id', $categoryIds);
                });
            }

            $this->applyOptimizedFilters($priceQuery, $currentFilters);
            $priceStats = $priceQuery->first();

            $stats['price_range'] = [
                'min' => (float) ($priceStats->min_price ?? 0),
                'max' => (float) ($priceStats->max_price ?? self::MAX_PRICE_DEFAULT),
                'current_min' => $this->getCurrentPriceMin($currentFilters),
                'current_max' => $this->getCurrentPriceMax($currentFilters),
                'currency' => '$'
            ];
        } else {
            // Fallback to dynamic query
            $stats['price_range'] = $this->buildPriceRange(null, $currentFilters);
        }

        // 4. Rating statistics
        if ($hasRatingStats) {
            $ratingQuery = DB::table('product_ratings_cache')
                ->select([
                    DB::raw('FLOOR(average_rating) as rating'),
                    DB::raw('COUNT(*) as count')
                ])
                ->where('average_rating', '>=', 1)
                ->groupBy(DB::raw('FLOOR(average_rating)'))
                ->orderBy('rating', 'desc');

            if ($categoryIds) {
                $ratingQuery->whereIn('product_id', function ($subQuery) use ($categoryIds) {
                    $subQuery->select('id')
                        ->from('products')
                        ->where('status', 'active')
                        ->where(function ($q) use ($categoryIds) {
                            $q->whereIn('cat_id', $categoryIds)
                                ->orWhereIn('child_cat_id', $categoryIds);
                        });
                });
            }

            $ratings = $ratingQuery->get()->mapWithKeys(function ($item) {
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
        } else {
            // Fallback to dynamic query
            $stats['ratings'] = $this->getOptimizedRatingStats($categoryIds, $currentFilters);
        }

        // 5. Discount statistics
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

        if ($categoryIds) {
            $discountQuery->where(function ($q) use ($categoryIds) {
                $q->whereIn('cat_id', $categoryIds)
                    ->orWhereIn('child_cat_id', $categoryIds);
            });
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

        // 6. Availability statistics
        $availQuery = DB::table('products')
            ->select([
                DB::raw('SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_count'),
                DB::raw('SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ])
            ->where('status', 'active');

        if ($categoryIds) {
            $availQuery->where(function ($q) use ($categoryIds) {
                $q->whereIn('cat_id', $categoryIds)
                    ->orWhereIn('child_cat_id', $categoryIds);
            });
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
     * Build price禁止
     */
    private function buildPriceRange($baseStats, array $currentFilters): array
    {
        $min = $baseStats ? (float) ($baseStats['price_range']['min'] ?? 0) : 0;
        $max = $baseStats ? (float) ($baseStats['price_range']['max'] ?? self::MAX_PRICE_DEFAULT) : self::MAX_PRICE_DEFAULT;

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
    private function getOptimizedRatingStats($categoryIds, array $currentFilters): array
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

            if ($categoryIds) {
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('cat_id', $categoryIds)
                        ->orWhereIn('child_cat_id', $categoryIds);
                });
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
    private function getOptimizedDiscountStats($categoryIds, array $currentFilters): array
    {
        $slabs = [5, 10, 20, 30, 50];
        $discounts = [];
        foreach ($slabs as $slab) {
            $query = DB::table('products')
                ->selectRaw('COUNT(*) as count')
                ->where('status', 'active')
                ->where('discount', '>=', $slab);

            if ($categoryIds) {
                $query->where(function ($q) use ($categoryIds) {
                    $q->whereIn('cat_id', $categoryIds)
                        ->orWhereIn('child_cat_id', $categoryIds);
                });
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
        return [
            'in_stock' => [
                'count' => (int) ($baseStats['availability']['in_stock']['count'] ?? 0),
                'label' => 'In Stock',
                'value' => 'in_stock',
                'selected' => false
            ],
            'out_of_stock' => [
                'count' => (int) ($baseStats['availability']['out_of_stock']['count'] ?? 0),
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
                    'count' => 0, // Could join with category_product_counts for accurate counts
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
     * Get fallback products
     */
    private function getFallbackProducts($category, array $currentProducts, int $needed): array
    {
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
            ->whereNotIn('id', array_column($currentProducts, 'id'))
            ->orderBy('title', 'asc')
            ->limit($needed);

        if ($category) {
            $categoryIds = $this->getDescendantIds($category);
            $query->where(function ($q) use ($categoryIds) {
                $q->whereIn('cat_id', $categoryIds)
                    ->orWhereIn('child_cat_id', $categoryIds);
            });
        }

        return $this->transformProducts($query->get()->toArray());
    }
    /**
     * Resolve category from path or URL
     */
    private function resolveCategoryContext(Request $request, $path = null)
    {
        if ($path) {
            $segments = explode('/', trim($path, '/'));
            $currentCategory = Category::whereNull('parent_id')
                ->where('status', 'active')
                ->where('slug', $segments[0] ?? '')
                ->first();

            if (!$currentCategory) return null;

            array_shift($segments);
            foreach ($segments as $segment) {
                $child = $currentCategory->children()
                    ->where('slug', $segment)
                    ->where('status', 'active')
                    ->first();

                if (!$child) break;
                $currentCategory = $child;
            }

            return $currentCategory;
        }

        // Fallback to URL path
        $pathSegments = $request->path() !== '/' ? explode('/', trim($request->path(), '/')) : [];
        $productCatIndex = array_search('product-cat', $pathSegments);
        if ($productCatIndex !== false && isset($pathSegments[$productCatIndex + 1])) {
            $slugPath = implode('/', array_slice($pathSegments, $productCatIndex + 1));
            return $this->resolveCategoryContext($request, $slugPath);
        }

        return null;
    }

    /**
     * Generate unique cache key for Redis
     */
    private function generateCacheKey($category, array $filters)
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        return "filters:{$catKey}:{$filterHash}";
    }

    /**
     * Build optimized filter data using single query approach
     */
    private function buildFilterData($baseQuery, array $currentFilters, ?Category $category)
    {
        $aggregationQuery = clone $baseQuery;
        $filterStats = $this->getFilterStatistics($aggregationQuery, $currentFilters);

        // Add categories if needed (sub-categories under current)
        $categories = [];
        if ($category) {
            $categories = $category->children()
                ->where('status', 'active')
                ->withCount(['products' => function ($q) use ($currentFilters) {
                    $this->applyCurrentFilters($q, $currentFilters);
                }])
                ->get()
                ->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'title' => $cat->title,
                        'slug' => $cat->slug,
                        'count' => $cat->products_count,
                        'selected' => false
                    ];
                });
        }

        // Add sort/display options
        $sortOptions = [
            ['value' => 'latest', 'label' => 'Latest'],
            ['value' => 'price_low_high', 'label' => 'Price: Low to High'],
            ['value' => 'price_high_low', 'label' => 'Price: High to Low'],
            ['value' => 'rating_high_low', 'label' => 'Rating: High to Low'],
            ['value' => 'name_a_z', 'label' => 'Name: A to Z'],
            ['value' => 'name_z_a', 'label' => 'Name: Z to A']
        ];
        $displayOptions = [
            ['value' => 12, 'label' => 'Show 12'],
            ['value' => 24, 'label' => 'Show 24'],
            ['value' => 36, 'label' => 'Show 36']
        ];

        return [
            'price_range' => $filterStats['price_range'],
            'brands' => $filterStats['brands'],
            'ratings' => $filterStats['ratings'],
            'discounts' => $filterStats['discounts'],
            'categories' => $categories,
            'availability' => $filterStats['availability'],
            'sort_options' => $sortOptions,
            'display_options' => $displayOptions,
            'applied_filters' => $this->formatAppliedFilters($currentFilters)
        ];
    }

    /**
     * Get filter statistics using optimized database queries
     */
    private function getFilterStatistics($baseQuery, array $currentFilters)
    {
        $filteredQuery = clone $baseQuery;
        $this->applyCurrentFilters($filteredQuery, $currentFilters);

        // Single query for basic statistics
        $baseStats = DB::table(DB::raw("({$filteredQuery->toSql()}) as filtered_products"))
            ->mergeBindings($filteredQuery->getQuery())
            ->select([
                DB::raw('MIN(CASE WHEN discount > 0 THEN price - (price * discount / 100) ELSE price END) as min_price'),
                DB::raw('MAX(CASE WHEN discount > 0 THEN price - (price * discount / 100) ELSE price END) as max_price'),
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) as in_stock_count'),
                DB::raw('SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count')
            ])
            ->first();

        // Get brand and other statistics
        $brandStats = $this->getBrandStatistics($filteredQuery);
        $ratingStats = $this->getRatingStatistics($filteredQuery);
        $discountStats = $this->getDiscountStatistics($filteredQuery);

        return [
            'price_range' => [
                'min' => (float) ($baseStats->min_price ?? 0),
                'max' => (float) ($baseStats->max_price ?? self::MAX_PRICE_DEFAULT),
                'current_min' => $this->getCurrentPriceMin($currentFilters),
                'current_max' => $this->getCurrentPriceMax($currentFilters),
                'currency' => '$'
            ],
            'brands' => $brandStats,
            'ratings' => $ratingStats,
            'discounts' => $discountStats,
            'availability' => [
                'in_stock' => [
                    'count' => (int) $baseStats->in_stock_count,
                    'label' => 'In Stock',
                    'value' => 'in_stock',
                    'selected' => in_array('in_stock', $currentFilters['availability'] ?? [])
                ],
                'out_of_stock' => [
                    'count' => (int) $baseStats->out_of_stock_count,
                    'label' => 'Out of Stock',
                    'value' => 'out_of_stock',
                    'selected' => in_array('out_of_stock', $currentFilters['availability'] ?? [])
                ]
            ]
        ];
    }

    /**
     * Get brand statistics with product counts
     */
    private function getBrandStatistics($baseQuery)
    {
        $query = DB::table(DB::raw("({$baseQuery->toSql()}) as filtered_products"))
            ->mergeBindings($baseQuery->getQuery())
            ->leftJoin('brands', 'filtered_products.brand_id', '=', 'brands.id')
            ->select([
                'brands.id',
                'brands.title',
                'brands.slug',
                DB::raw('COUNT(filtered_products.id) as count')
            ])
            ->where('brands.status', 'active')
            ->groupBy('brands.id', 'brands.title', 'brands.slug')
            ->havingRaw('COUNT(filtered_products.id) > 0')
            ->orderBy('brands.title')
            ->get();

        return $query->map(function ($brand) {
            return [
                'id' => $brand->id,
                'title' => $brand->title,
                'slug' => $brand->slug,
                'label' => $brand->title,
                'count' => (int) $brand->count,
                'selected' => false
            ];
        })->toArray();
    }

    /**
     * Get rating statistics (buckets 1-5 stars)
     */
    private function getRatingStatistics($baseQuery)
    {
        $ratings = [];
        for ($i = 1; $i <= 5; $i++) {
            // Use a CTE to compute average ratings per product
            $count = DB::table(DB::raw("
                (
                    SELECT fp.id
                    FROM ({$baseQuery->toSql()}) as fp
                    LEFT JOIN product_reviews ON fp.id = product_reviews.product_id
                    GROUP BY fp.id
                    HAVING COALESCE(AVG(CAST(product_reviews.rate AS DECIMAL(3,2))), 0) >= {$i}
                ) as rated_products
            "))
                ->mergeBindings($baseQuery->getQuery())
                ->count();

            $ratings[] = [
                'value' => $i,
                'label' => "{$i} ★ & above",
                'count' => $count,
                'selected' => false
            ];
        }

        return array_reverse($ratings); // 5★ first
    }

    /**
     * Get discount statistics (slabs: 5%,10%,20%,30%,50%)
     */
    private function getDiscountStatistics($baseQuery)
    {
        $slabs = [5, 10, 20, 30, 50];
        $discounts = [];
        foreach ($slabs as $slab) {
            $count = DB::table(DB::raw("({$baseQuery->toSql()}) as fp"))
                ->mergeBindings($baseQuery->getQuery())
                ->where('discount', '>=', $slab)
                ->count();

            $discounts[] = [
                'value' => $slab,
                'label' => "{$slab}% & above",
                'count' => $count,
                'selected' => false
            ];
        }

        return $discounts;
    }

    /**
     * Get filtered products with pagination
     */
    private function getFilteredProducts($baseQuery, array $currentFilters, Request $request, ?Category $category)
    {
        $query = clone $baseQuery;
        $this->applyCurrentFilters($query, $currentFilters);
        $this->applySorting($query, $currentFilters['sortBy'] ?? 'latest', $category);

        $perPage = $currentFilters['show'] ?? 12;
        $page = $request->input('page', 1);

        // Primary filtered products
        $products = $query->with(['brand', 'images'])->paginate($perPage, ['*'], 'page', $page);
        $productList = collect($products->items());

        // Fallback logic if less than 12 products on the current page
        if ($productList->count() < 12) {
            // 1. Category fallback (ignore filters except category), sorted A to Z
            $categoryOnlyQuery = clone $baseQuery;
            $categoryOnlyQuery->with(['brand', 'images']);
            $categoryOnlyQuery->orderBy('title', 'asc');
            $categoryProducts = $categoryOnlyQuery->whereNotIn('id', $productList->pluck('id'))->limit(12 - $productList->count())->get();
            $productList = $productList->concat($categoryProducts);

            // 2. Related categories fallback (siblings/related under same parent or top-level, infinite sub-levels)
            if ($productList->count() < 12 && $category) {
                // Get sibling categories (related under same parent)
                $parentId = $category->parent_id;
                $similarCategories = Category::where('parent_id', $parentId)
                    ->where('id', '!=', $category->id)
                    ->where('status', 'active')
                    ->get();

                $similarDescendantIds = [];
                foreach ($similarCategories as $simCat) {
                    $similarDescendantIds = array_merge($similarDescendantIds, $this->getDescendantIds($simCat));
                }
                $similarDescendantIds = array_unique($similarDescendantIds);

                if (!empty($similarDescendantIds)) {
                    $similarQuery = Product::query()
                        ->where('status', 'active')
                        ->where(function ($q) use ($similarDescendantIds) {
                            $q->whereIn('cat_id', $similarDescendantIds)
                                ->orWhereIn('child_cat_id', $similarDescendantIds);
                        })
                        ->whereNotIn('id', $productList->pluck('id'))
                        ->with(['brand', 'images'])
                        ->orderBy('title', 'asc')
                        ->limit(12 - $productList->count());
                    $similarProducts = $similarQuery->get();
                    $productList = $productList->concat($similarProducts);
                }
            }

            // 3. Final guarantee: always at least 12 products (fill with any active products, sorted A to Z)
            if ($productList->count() < 12) {
                $fillQuery = Product::query()
                    ->where('status', 'active')
                    ->whereNotIn('id', $productList->pluck('id'))
                    ->with(['brand', 'images'])
                    ->orderBy('title', 'asc')
                    ->limit(12 - $productList->count());
                $fillProducts = $fillQuery->get();
                $productList = $productList->concat($fillProducts);
            }
        }

        // Transform products for JSON
        $productList = $productList->map(function ($product) {
            $rating = $this->getProductRating($product->id);
            $originalPrice = is_numeric($product->price) ? (float) $product->price : 0.0;
            $discount = is_numeric($product->discount) ? (int) $product->discount : 0;
            $finalPrice = $discount > 0 ? $originalPrice - ($originalPrice * $discount / 100) : $originalPrice;

            return [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => [
                    'original' => $originalPrice,
                    'final' => $finalPrice,
                    'currency' => '$',
                    'discount_percentage' => $discount
                ],
                'stock' => (int) $product->stock,
                'brand' => $product->brand ? ['title' => $product->brand->title, 'slug' => $product->brand->slug] : null,
                'images' => $product->images->pluck('image_path')->toArray(),
                'rating' => $rating ? ['average' => (float) $rating['average'], 'total' => $rating['total'], 'stars' => round($rating['average'])] : ['average' => 0, 'total' => 0, 'stars' => 0],
                'badges' => $this->getProductBadges($product),
                'urls' => [
                    'detail' => route('product-detail', $product->slug),
                    'add_to_cart' => route('add-to-cart', $product->slug),
                    'add_to_wishlist' => route('add-to-wishlist', $product->slug)
                ]
            ];
        });

        return [
            'products' => $productList->take(12)->values()->toArray(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => ceil($productList->count() / $perPage),
                'total' => $productList->count(),
                'per_page' => $perPage,
                'links' => [] // Custom pagination if needed
            ],
            'total' => $productList->count()
        ];
    }

    /**
     * Apply current filters to query
     */
    private function applyCurrentFilters($query, array $filters)
    {
        if (!empty($filters['brands'])) {
            $brandIds = Brand::whereIn('slug', $filters['brands'])
                ->where('status', 'active')
                ->pluck('id');
            $query->whereIn('brand_id', $brandIds);
        }

        if (!empty($filters['price_range']) && str_contains($filters['price_range'], '-')) {
            [$minPrice, $maxPrice] = explode('-', $filters['price_range']);
            $minPrice = (float) trim($minPrice);
            $maxPrice = (float) trim($maxPrice);

            if ($minPrice >= 0 && $maxPrice > $minPrice) {
                $query->whereRaw('
                    CASE 
                        WHEN discount > 0 THEN 
                            price - (price * discount / 100)
                        ELSE 
                            price 
                    END BETWEEN ? AND ?
                ', [$minPrice, $maxPrice]);
            }
        }

        if (!empty($filters['ratings'])) {
            $minRating = min(array_map('intval', $filters['ratings']));
            $query->whereExists(function ($subQuery) use ($minRating) {
                $subQuery->select(DB::raw(1))
                    ->from('product_reviews')
                    ->whereColumn('product_reviews.product_id', 'products.id')
                    ->groupBy('product_reviews.product_id')
                    ->havingRaw('AVG(CAST(product_reviews.rate AS DECIMAL(3,2))) >= ?', [$minRating]);
            });
        }

        if (!empty($filters['discounts'])) {
            $minDiscount = min(array_map('intval', $filters['discounts']));
            $query->where('discount', '>=', $minDiscount);
        }

        if (!empty($filters['availability'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['availability'] as $availability) {
                    if ($availability === 'in_stock') {
                        $q->orWhere('stock', '>', 0);
                    } elseif ($availability === 'out_of_stock') {
                        $q->orWhere('stock', '=', 0);
                    }
                }
            });
        }

        return $query;
    }

    /**
     * Apply sorting to query with priority for main and child categories
     */
    private function applySorting($query, string $sortBy, ?Category $category)
    {
        $catId = $category ? $category->id : null;
        $priorityRaw = $catId ? "CASE WHEN cat_id = ? THEN 0 WHEN child_cat_id = ? THEN 1 ELSE 2 END" : null;
        $priorityBindings = $catId ? [$catId, $catId] : [];

        switch ($sortBy) {
            case 'price_low_high':
                return $query->orderByRaw('
                    CASE 
                        WHEN discount > 0 THEN 
                            price - (price * discount / 100)
                        ELSE 
                            price 
                    END ASC
                ');
                break;

            case 'price_high_low':
                return $query->orderByRaw('
                    CASE 
                        WHEN discount > 0 THEN 
                            price - (price * discount / 100)
                        ELSE 
                            price 
                    END DESC
                ');
                break;

            case 'rating_high_low':
                $selectRaw = 'products.*, AVG(CAST(product_reviews.rate AS DECIMAL(3,2))) as avg_rating';
                if ($priorityRaw) {
                    $selectRaw .= ", {$priorityRaw} as priority";
                    $query->addBinding($priorityBindings, 'select');
                }
                $query->leftJoin('product_reviews', 'products.id', '=', 'product_reviews.product_id')
                    ->selectRaw($selectRaw)
                    ->groupBy('products.id');
                if ($priorityRaw) {
                    $query->orderBy('priority', 'asc');
                }
                $query->orderByDesc('avg_rating');
                break;

            case 'name_a_z':
                if ($priorityRaw) {
                    $query->orderByRaw("{$priorityRaw} ASC", $priorityBindings);
                }
                $query->orderBy('title', 'asc');
                break;

            case 'name_z_a':
                if ($priorityRaw) {
                    $query->orderByRaw("{$priorityRaw} ASC", $priorityBindings);
                }
                $query->orderBy('title', 'desc');
                break;

            case 'latest':
            default:
                if ($priorityRaw) {
                    $query->orderByRaw("{$priorityRaw} ASC", $priorityBindings);
                }
                $query->orderBy('created_at', 'desc');
                break;
        }

        return $query;
    }

    /**
     * Build base query with category filtering
     */
    private function buildBaseQuery(?Category $category)
    {
        $query = Product::query()->where('status', 'active');

        if ($category) {
            $descendantIds = $this->getDescendantIds($category);
            $query->where(function ($q) use ($descendantIds) {
                $q->whereIn('cat_id', $descendantIds)
                    ->orWhereIn('child_cat_id', $descendantIds);
            });
        }

        return $query;
    }

    private function getCurrentPriceMin(array $filters): float
    {
        if (empty($filters['price_range'])) return 0;
        return (float) explode('-', $filters['price_range'])[0];
    }

    private function getCurrentPriceMax(array $filters): float
    {
        if (empty($filters['price_range'])) return self::MAX_PRICE_DEFAULT;
        $parts = explode('-', $filters['price_range']);
        return (float) ($parts[1] ?? self::MAX_PRICE_DEFAULT);
    }

    private function formatAppliedFilters(array $filters): array
    {
        $applied = [];
        if (!empty($filters['brands'])) $applied['brands'] = $filters['brands'];
        if (!empty($filters['ratings'])) $applied['ratings'] = $filters['ratings'];
        if (!empty($filters['discounts'])) $applied['discounts'] = $filters['discounts'];
        if (!empty($filters['price_range'])) $applied['price_range'] = $filters['price_range'];
        return $applied;
    }

    private function getProductRating(int $productId): ?array
    {
        return Cache::remember("product_rating_{$productId}", 3600, function () use ($productId) {
            $rating = DB::table('product_reviews')
                ->where('product_id', $productId)
                ->selectRaw('AVG(CAST(rate AS DECIMAL(3,2))) as avg_rating, COUNT(*) as total_reviews')
                ->first();

            if (!$rating || !$rating->avg_rating) return null;

            return [
                'average' => round($rating->avg_rating, 1),
                'total' => (int) $rating->total_reviews
            ];
        });
    }

    private function getDescendantIds(Category $category): array
    {
        $ids = [$category->id];
        $children = Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return array_unique($ids);
    }

    private function getProductBadges($product)
    {
        $badges = [];
        if ($product->discount > 0) {
            $badges[] = ['text' => $product->discount . '% Off', 'class' => 'primary'];
        }
        if ($product->stock <= 0) {
            $badges[] = ['text' => 'Sold Out', 'class' => 'danger'];
        } elseif ($product->condition === 'new') {
            $badges[] = ['text' => 'New', 'class' => 'success'];
        }
        return $badges;
    }
}
