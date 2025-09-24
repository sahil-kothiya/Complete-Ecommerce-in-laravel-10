<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 300; // 5 minutes (Redis TTL)
    private const MAX_PRICE_DEFAULT = 10000;

    /**
     * Get filter data with product counts in JSON format
     * Optimized for sub-500ms response time
     */
    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);

        try {
            // Get category context from path or URL
            $categoryContext = $this->resolveCategoryContext($request, $path);

            // Build base query with category filtering
            $baseQuery = $this->buildBaseQuery($categoryContext);

            // Get current filters from request
            $currentFilters = $this->parseCurrentFilters($request);

            // Generate cache key based on category and current filters (Redis-friendly)
            $cacheKey = $this->generateCacheKey($categoryContext, $currentFilters);

            // Try to get from Redis cache first
            $filterData = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($baseQuery, $currentFilters, $categoryContext) {
                return $this->buildFilterData($baseQuery, $currentFilters, $categoryContext);
            });

            // Get filtered products (separate for pagination)
            $productData = $this->getFilteredProducts($baseQuery, $currentFilters, $request, $categoryContext);

            $response = [
                'success' => true,
                'filters' => $filterData,
                'products' => $productData['products'],
                'pagination' => $productData['pagination'],
                'meta' => [
                    'total_products' => $productData['total'],
                    'current_filters' => $currentFilters,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'cache_hit' => Cache::has($cacheKey)
                ]
            ];

            return response()->json($response, 200, [
                'Cache-Control' => 'public, max-age=' . self::CACHE_TTL,
                'Content-Type' => 'application/json'
            ]);

        } catch (\Exception $e) {
            Log::error('Filter data error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load filter data',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
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
     * Parse current filters from request
     */
    private function parseCurrentFilters(Request $request)
    {
        $filters = [];
        $filters['brands'] = $request->input('brands', []);
        if (is_string($filters['brands'])) $filters['brands'] = explode(',', $filters['brands']);

        $filters['ratings'] = $request->input('ratings', []);
        if (is_string($filters['ratings'])) $filters['ratings'] = explode(',', $filters['ratings']);

        $filters['discounts'] = $request->input('discounts', []);
        if (is_string($filters['discounts'])) $filters['discounts'] = explode(',', $filters['discounts']);

        $filters['price_range'] = $request->input('price_range', '');
        $filters['availability'] = $request->input('availability', []);
        if (is_string($filters['availability'])) $filters['availability'] = explode(',', $filters['availability']);

        $filters['sortBy'] = $request->input('sortBy', 'latest');
        $filters['show'] = (int) $request->input('show', 12);

        return array_filter($filters, function ($value) {
            return !empty($value);
        });
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