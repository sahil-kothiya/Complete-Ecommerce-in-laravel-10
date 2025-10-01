<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use App\Helpers\UrlEncryptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Redis;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 1800;
    private const PRODUCTS_CACHE_TTL = 300;
    private const MAX_PRICE_DEFAULT = 10000;
    private const MAX_EXECUTION_TIME = 3000;
    private const SIMILAR_PRODUCTS_COUNT = 12;
    private const BATCH_SIZE = 100;

    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);

        // try {
            set_time_limit(self::MAX_EXECUTION_TIME);

            $categoryContext = $this->resolveCategoryContext($request, $path);
            $currentFilters = $this->parseCurrentFilters($request);

            // Define $page before generating cache keys
            $page = max(1, (int) $request->input('page', 1));
            $cacheKeys = $this->generateCacheKeys($categoryContext, $currentFilters, $request, $page);
            $cachedData = $this->getCachedDataPipeline($cacheKeys);

            $filterData = $cachedData['filters'] ?? $this->buildOptimizedFilterData($categoryContext, $currentFilters);
            if (!isset($cachedData['filters'])) {
                RedisHelper::put($cacheKeys['filters'], $filterData, self::CACHE_TTL);
            }

            $productData = $cachedData['products'] ?? $this->getOptimizedFilteredProducts($categoryContext, $currentFilters, $request);
            if (!isset($cachedData['products'])) {
                RedisHelper::put($cacheKeys['products'], $productData, self::PRODUCTS_CACHE_TTL);
            }

            $similarProducts = [];
            $showSimilar = empty($productData['products']) || $productData['total'] === 0;
            if ($showSimilar) {
                $perPage = $currentFilters['show'] ?? 12;
                $similarProducts = $this->getSimilarProducts($categoryContext, $perPage);
            }

            return response()->json([
                'success' => true,
                'filters' => $filterData,
                'products' => $productData['products'],
                'pagination' => $productData['pagination'],
                'no_results' => $showSimilar,
                'similar_products' => $similarProducts,
                'meta' => [
                    'total_products' => $productData['total'],
                    'current_filters' => $currentFilters,
                    'processing_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'cache_hit' => isset($cachedData['filters'], $cachedData['products'])
                ]
            ], 200, ['Cache-Control' => 'public, max-age=' . self::PRODUCTS_CACHE_TTL]);

        // } catch (\Exception $e) {
        //     Log::error('Filter error: ' . $e->getMessage(), [
        //         'trace' => $e->getTraceAsString()
        //     ]);

        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Failed to load products',
        //         'error' => config('app.debug') ? $e->getMessage() : null
        //     ], 500);
        // }
    }

    private function getSimilarProducts($category, int $limit = 12): array
    {
        $cacheKey = 'similar_products_v7_' . ($category?->id ?? 'all') . '_' . $limit;
        
        return RedisHelper::remember($cacheKey, 600, function () use ($category, $limit) {
            $query = DB::table('products as p')
                ->select([
                    'p.id', 'p.title', 'p.slug', 'p.price', 'p.discount', 'p.stock', 
                    'p.condition', 'p.created_at', 'b.title as brand_title', 
                    'b.slug as brand_slug', 'prc.average_rating', 'prc.total_reviews',
                    DB::raw('pi.image_path as primary_image')
                ])
                ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
                ->leftJoin('product_ratings_cache as prc', 'p.id', '=', 'prc.product_id')
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('p.id', '=', 'pi.product_id')
                         ->whereRaw('pi.sort_order = (SELECT MIN(sort_order) FROM product_images WHERE product_id = p.id)');
                })
                ->where('p.status', 'active')
                ->where('p.stock', '>', 0);

            if ($category) {
                if ($category->parent_id === null) {
                    $subcategoryIds = $this->getSubcategoryIds($category->id);
                    $query->where(function ($q) use ($category, $subcategoryIds) {
                        $q->where('p.cat_id', $category->id);
                        if (!empty($subcategoryIds)) {
                            $q->orWhereIn('p.child_cat_id', $subcategoryIds);
                        }
                    });
                } else {
                    $query->where('p.child_cat_id', $category->id);
                }
            }

            $query->orderByRaw('COALESCE(prc.average_rating, 0) * (1 + p.discount / 100.0) DESC')
                  ->orderBy('p.created_at', 'DESC')
                  ->limit($limit);

            return $this->transformProductsOptimized($query->get());
        });
    }

    private function generateCacheKeys($category, array $filters, Request $request, int $page): array
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));

        return [
            'filters' => "filters_v7:{$catKey}:{$filterHash}",
            'products' => "products_v7:{$catKey}:{$filterHash}:{$page}",
            'stats' => "stats_v7:{$catKey}:{$filterHash}",
            'subcategories' => $category ? "subcategories_v7:{$category->id}" : null,
        ];
    }

    private function getCachedDataPipeline(array $cacheKeys): array
    {
        $validKeys = array_filter($cacheKeys);
        $results = Redis::pipeline(function ($pipe) use ($validKeys) {
            foreach ($validKeys as $key) {
                $pipe->get($key);
            }
        });

        $cachedData = [];
        $keyNames = array_keys($validKeys);
        foreach ($results as $index => $result) {
            if ($result) {
                $cachedData[$keyNames[$index]] = RedisHelper::deserializeData($result);
            }
        }

        return $cachedData;
    }

    private function buildOptimizedFilterData($category, array $currentFilters): array
    {
        $baseStats = $this->getAggregatedStats($category, $currentFilters);

        return [
            'brands' => $baseStats['brands'] ?? [],
            'price_range' => $baseStats['price_range'] ?? $this->getDefaultPriceRange(),
            'ratings' => $baseStats['ratings'] ?? [],
            'discounts' => $baseStats['discounts'] ?? [],
            'availability' => $baseStats['availability'] ?? [],
            'sub_categories' => $this->getSubCategories($category),
            'sort_options' => $this->getSortOptions(),
            'display_options' => $this->getDisplayOptions(),
            'applied_filters' => $this->formatAppliedFilters($currentFilters)
        ];
    }

    private function getAggregatedStats($category, array $currentFilters): array
    {
        $cacheKey = 'agg_stats_v7_' . md5(serialize([$category?->id, $currentFilters]));
        
        return RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters) {
            $baseQuery = $this->buildBaseStatsQuery($category, $currentFilters);
            
            $sql = "
                WITH filtered_products AS ({$baseQuery->toSql()}),
                price_stats AS (
                    SELECT 
                        MIN(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as min_price,
                        MAX(CASE WHEN discount > 0 THEN price * (1 - discount / 100.0) ELSE price END) as max_price
                    FROM filtered_products
                ),
                availability_stats AS (
                    SELECT 
                        COUNT(*) FILTER (WHERE stock > 0) as in_stock,
                        COUNT(*) FILTER (WHERE stock <= 0) as out_of_stock
                    FROM filtered_products
                ),
                discount_stats AS (
                    SELECT 
                        COUNT(*) FILTER (WHERE discount >= 5) as d5,
                        COUNT(*) FILTER (WHERE discount >= 10) as d10,
                        COUNT(*) FILTER (WHERE discount >= 20) as d20,
                        COUNT(*) FILTER (WHERE discount >= 30) as d30,
                        COUNT(*) FILTER (WHERE discount >= 50) as d50
                    FROM filtered_products
                ),
                brand_stats AS (
                    SELECT 
                        b.id, b.title, b.slug,
                        COUNT(DISTINCT fp.id) as product_count
                    FROM filtered_products fp
                    INNER JOIN brands b ON fp.brand_id = b.id
                    WHERE b.status = 'active'
                    GROUP BY b.id, b.title, b.slug
                    HAVING COUNT(DISTINCT fp.id) > 0
                    ORDER BY product_count DESC
                    LIMIT 50
                ),
                rating_stats AS (
                    SELECT 
                        FLOOR(prc.average_rating) as rating,
                        COUNT(*) as count
                    FROM filtered_products fp
                    INNER JOIN product_ratings_cache prc ON fp.id = prc.product_id
                    WHERE prc.average_rating >= 1
                    GROUP BY FLOOR(prc.average_rating)
                )
                SELECT 
                    json_build_object(
                        'price', (SELECT row_to_json(p) FROM price_stats p),
                        'availability', (SELECT row_to_json(a) FROM availability_stats a),
                        'discounts', (SELECT row_to_json(d) FROM discount_stats d),
                        'brands', (SELECT json_agg(row_to_json(b)) FROM brand_stats b),
                        'ratings', (SELECT json_agg(row_to_json(r)) FROM rating_stats r)
                    ) as stats
            ";

            $result = DB::selectOne($sql, $baseQuery->getBindings());
            $stats = json_decode($result->stats, true);

            return $this->formatAggregatedStats($stats, $currentFilters);
        });
    }

    private function buildBaseStatsQuery($category, array $filters)
    {
        $query = DB::table('products as p')
            ->select(['p.id', 'p.price', 'p.discount', 'p.stock', 'p.brand_id'])
            ->where('p.status', 'active');

        if ($category) {
            $this->applyCategoryFilter($query, $category);
        }

        $filtersWithoutPrice = array_diff_key($filters, ['price_range' => 1]);
        $this->applyFiltersToQuery($query, $filtersWithoutPrice);

        return $query;
    }

    private function formatAggregatedStats(array $stats, array $currentFilters): array
    {
        return [
            'price_range' => [
                'min' => (float) ($stats['price']['min_price'] ?? 0),
                'max' => (float) ($stats['price']['max_price'] ?? self::MAX_PRICE_DEFAULT),
                'current_min' => $this->getCurrentPriceMin($currentFilters),
                'current_max' => $this->getCurrentPriceMax($currentFilters),
                'currency' => '$'
            ],
            'availability' => [
                'in_stock' => [
                    'count' => (int) ($stats['availability']['in_stock'] ?? 0),
                    'label' => 'In Stock',
                    'value' => 'in_stock',
                    'selected' => in_array('in_stock', $currentFilters['availability'] ?? [])
                ],
                'out_of_stock' => [
                    'count' => (int) ($stats['availability']['out_of_stock'] ?? 0),
                    'label' => 'Out of Stock',
                    'value' => 'out_of_stock',
                    'selected' => in_array('out_of_stock', $currentFilters['availability'] ?? [])
                ]
            ],
            'discounts' => $this->formatDiscountStats($stats['discounts'] ?? [], $currentFilters),
            'brands' => $this->formatBrandStats($stats['brands'] ?? [], $currentFilters),
            'ratings' => $this->formatRatingStats($stats['ratings'] ?? [], $currentFilters)
        ];
    }

    private function getOptimizedFilteredProducts($category, array $currentFilters, Request $request)
    {
        $perPage = min((int) $request->input('show', 12), 60);
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;
        $sortBy = $currentFilters['sortBy'] ?? 'latest';

        // Cache key for this specific query
        $queryCacheKey = 'query_products_v7_' . md5(serialize([
            'category' => $category?->id,
            'filters' => $currentFilters,
            'page' => $page,
            'perPage' => $perPage
        ]));

        return RedisHelper::remember($queryCacheKey, self::PRODUCTS_CACHE_TTL, function () use ($category, $currentFilters, $perPage, $offset, $sortBy, $page) {
            $query = DB::table('products as p')
                ->select([
                    'p.id', 'p.title', 'p.slug', 'p.price', 'p.discount', 'p.stock', 
                    'p.condition', 'p.created_at', 'b.title as brand_title', 
                    'b.slug as brand_slug', 'prc.average_rating', 'prc.total_reviews',
                    DB::raw('pi.image_path as primary_image')
                ])
                ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
                ->leftJoin('product_ratings_cache as prc', 'p.id', '=', 'prc.product_id')
                ->leftJoin('product_images as pi', function ($join) {
                    $join->on('p.id', '=', 'pi.product_id')
                         ->whereRaw('pi.sort_order = (SELECT MIN(sort_order) FROM product_images WHERE product_id = p.id)');
                })
                ->where('p.status', 'active');

            if ($category) {
                $this->applyCategoryFilter($query, $category);
            }
            $this->applyFiltersToQuery($query, $currentFilters);
            $this->applySorting($query, $sortBy);

            $products = $query->offset($offset)->limit($perPage)->get();
            $totalCount = $this->getCountEstimate($category, $currentFilters);

            return [
                'products' => $this->transformProductsOptimized($products),
                'pagination' => [
                    'current_page' => $page,
                    'last_page' => max(1, ceil($totalCount / $perPage)),
                    'total' => $totalCount,
                    'per_page' => $perPage,
                    'from' => $offset + 1,
                    'to' => min($offset + count($products), $totalCount)
                ],
                'total' => $totalCount
            ];
        });
    }

    private function applyCategoryFilter($query, $category)
    {
        if ($category->parent_id === null) {
            $subcategoryIds = $this->getSubcategoryIds($category->id);
            $query->where(function ($q) use ($category, $subcategoryIds) {
                $q->where('p.cat_id', $category->id);
                if (!empty($subcategoryIds)) {
                    $q->orWhereIn('p.child_cat_id', $subcategoryIds);
                }
            });
        } else {
            $query->where('p.child_cat_id', $category->id);
        }
    }

    private function applyFiltersToQuery($query, array $filters)
    {
        if (!empty($filters['price_range']) && str_contains($filters['price_range'], '-')) {
            [$min, $max] = array_map('floatval', explode('-', $filters['price_range']));
            if ($min >= 0 && $max > $min) {
                $query->whereRaw('
                    CASE WHEN p.discount > 0 
                    THEN p.price * (1 - p.discount / 100.0) 
                    ELSE p.price END BETWEEN ? AND ?
                ', [$min, $max]);
            }
        }

        if (!empty($filters['brands'])) {
            $brandIds = $this->getBrandIdsBySlug($filters['brands']);
            if (!empty($brandIds)) {
                $query->whereIn('p.brand_id', $brandIds);
            }
        }

        if (!empty($filters['discounts'])) {
            $minDiscount = min(array_map('intval', $filters['discounts']));
            $query->where('p.discount', '>=', $minDiscount);
        }

        if (!empty($filters['availability'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['availability'] as $avail) {
                    if ($avail === 'in_stock') {
                        $q->orWhere('p.stock', '>', 0);
                    } elseif ($avail === 'out_of_stock') {
                        $q->orWhere('p.stock', '<=', 0);
                    }
                }
            });
        }

        if (!empty($filters['ratings'])) {
            $minRating = min(array_map('intval', $filters['ratings']));
            $query->where('prc.average_rating', '>=', $minRating);
        }
    }

    private function applySorting($query, string $sortBy)
    {
        $query->selectRaw('CASE WHEN p.discount > 0 THEN p.price * (1 - p.discount / 100.0) ELSE p.price END as effective_price');
        
        switch ($sortBy) {
            case 'price_low_high':
                $query->orderBy('effective_price', 'ASC');
                break;
            case 'price_high_low':
                $query->orderBy('effective_price', 'DESC');
                break;
            case 'rating_high_low':
                $query->orderByRaw('COALESCE(prc.average_rating, 0) DESC');
                break;
            case 'name_a_z':
                $query->orderBy('p.title', 'ASC');
                break;
            case 'name_z_a':
                $query->orderBy('p.title', 'DESC');
                break;
            default:
                $query->orderBy('p.created_at', 'DESC');
        }
    }

    private function transformProductsOptimized($products): array
    {
        return $products->map(function ($p) {
            $finalPrice = $p->discount > 0 
                ? $p->price * (1 - $p->discount / 100) 
                : $p->price;

            $images = $p->primary_image ? [$p->primary_image] : ['/images/default-placeholder.jpg'];

            return [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'price' => [
                    'original' => (float) $p->price,
                    'final' => round($finalPrice, 2),
                    'currency' => '$',
                    'discount_percentage' => (int) $p->discount
                ],
                'stock' => (int) $p->stock,
                'brand' => $p->brand_title ? [
                    'title' => $p->brand_title,
                    'slug' => $p->brand_slug
                ] : null,
                'images' => $images,
                'rating' => [
                    'average' => (float) ($p->average_rating ?? 0),
                    'total' => (int) ($p->total_reviews ?? 0),
                    'stars' => round($p->average_rating ?? 0)
                ],
                'badges' => $this->getProductBadges($p),
                'urls' => [
                    'detail' => "/product-detail/{$p->slug}",
                    'add_to_cart' => "/cart/add/{$p->id}",
                    'add_to_wishlist' => "/wishlist/add/{$p->id}"
                ]
            ];
        })->toArray();
    }

    private function getCountEstimate($category, array $filters): int
    {
        $cacheKey = 'count_v7_' . md5(serialize([$category?->id, $filters]));
        
        return RedisHelper::remember($cacheKey, 300, function () use ($category, $filters) {
            $query = DB::table('products as p')
                ->selectRaw('COUNT(*) as count')
                ->where('p.status', 'active');

            if ($category) {
                $this->applyCategoryFilter($query, $category);
            }
            $this->applyFiltersToQuery($query, $filters);
            
            return $query->value('count') ?? 0;
        });
    }

    private function getBrandIdsBySlug(array $slugs): array
    {
        $cacheKey = 'brand_ids_v7_' . md5(implode(',', $slugs));
        return RedisHelper::remember($cacheKey, 3600, function () use ($slugs) {
            return Brand::whereIn('slug', $slugs)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });
    }

    private function getSubcategoryIds(int $parentId): array
    {
        return RedisHelper::remember("subcats_v7_{$parentId}", 3600, function () use ($parentId) {
            return Category::where('parent_id', $parentId)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });
    }

    private function formatDiscountStats(array $stats, array $filters): array
    {
        $result = [];
        foreach ([5, 10, 20, 30, 50] as $slab) {
            $result[] = [
                'value' => $slab,
                'label' => "$slab% & above",
                'count' => (int) ($stats["d$slab"] ?? 0),
                'selected' => in_array((string) $slab, $filters['discounts'] ?? [])
            ];
        }
        return $result;
    }

    private function formatBrandStats(?array $brands, array $filters): array
    {
        if (!$brands) return [];
        
        return array_map(function ($b) use ($filters) {
            return [
                'id' => $b['id'],
                'title' => $b['title'],
                'slug' => $b['slug'],
                'count' => (int) $b['product_count'],
                'selected' => in_array($b['slug'], $filters['brands'] ?? [])
            ];
        }, $brands);
    }

    private function formatRatingStats(?array $ratings, array $filters): array
    {
        $ratingMap = [];
        if ($ratings) {
            foreach ($ratings as $r) {
                $ratingMap[(int) $r['rating']] = (int) $r['count'];
            }
        }

        $result = [];
        for ($i = 5; $i >= 1; $i--) {
            $result[] = [
                'value' => $i,
                'label' => "$i ★ & above",
                'count' => $ratingMap[$i] ?? 0,
                'selected' => in_array((string) $i, $filters['ratings'] ?? [])
            ];
        }
        return $result;
    }

    private function parseCurrentFilters(Request $request): array
    {
        return [
            'brands' => array_slice($this->parseArray($request->input('brands', [])), 0, 20),
            'ratings' => array_slice($this->parseArray($request->input('ratings', [])), 0, 5),
            'discounts' => array_slice($this->parseArray($request->input('discounts', [])), 0, 10),
            'price_range' => $request->input('price_range', ''),
            'availability' => $this->parseArray($request->input('availability', [])),
            'sortBy' => $request->input('sortBy', 'latest'),
            'show' => min((int) $request->input('show', 12), 60)
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
            Log::error('Category resolution error: ' . $e->getMessage());
            return null;
        }
    }

    private function getSubCategories($category): array
    {
        if (!$category) return [];

        return Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->select(['id', 'title', 'slug'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'selected' => false
            ])->toArray();
    }

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

    private function getDisplayOptions(): array
    {
        return [
            ['value' => 12, 'label' => 'Show 12'],
            ['value' => 24, 'label' => 'Show 24'],
            ['value' => 36, 'label' => 'Show 36']
        ];
    }

    private function getCurrentPriceMin(array $filters): float
    {
        if (empty($filters['price_range'])) return 0;
        return (float) explode('-', $filters['price_range'])[0];
    }

    private function getCurrentPriceMax(array $filters): float
    {
        if (empty($filters['price_range'])) return self::MAX_PRICE_DEFAULT;
        return (float) (explode('-', $filters['price_range'])[1] ?? self::MAX_PRICE_DEFAULT);
    }

    private function formatAppliedFilters(array $filters): array
    {
        return array_filter($filters, fn($v, $k) => !empty($v) && $k !== 'sortBy' && $k !== 'show', ARRAY_FILTER_USE_BOTH);
    }

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

    private function getDefaultPriceRange(): array
    {
        return [
            'min' => 0,
            'max' => self::MAX_PRICE_DEFAULT,
            'current_min' => 0,
            'current_max' => self::MAX_PRICE_DEFAULT,
            'currency' => '$'
        ];
    }
}