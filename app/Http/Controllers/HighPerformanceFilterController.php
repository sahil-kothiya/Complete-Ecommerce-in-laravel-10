<?php

namespace App\Http\Controllers;

use App\Services\RedisCacheService;
use App\Helpers\UrlEncryptor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class HighPerformanceFilterController extends Controller
{
    private const CACHE_TTL = 1800;
    private const PRODUCTS_CACHE_TTL = 300;
    private const MAX_PRICE_DEFAULT = 100;
    private const MAX_EXECUTION_TIME = 3000;

    public function getFilterData(Request $request, $path = null)
    {
        $startTime = microtime(true);
        set_time_limit(self::MAX_EXECUTION_TIME);

        $categoryContext = $this->resolveCategoryContext($request, $path);
        $currentFilters = $this->parseCurrentFilters($request);
        $cacheKeys = $this->generateCacheKeys($categoryContext, $currentFilters, $request);

        $cachedData = $this->getCachedDataPipeline($cacheKeys);

        // Build filter data
        $filterData = $cachedData['filters'] ?? $this->buildOptimizedFilterData($categoryContext, $currentFilters);
        if (!isset($cachedData['filters'])) {
            RedisCacheService::put($cacheKeys['filters'], $filterData, self::CACHE_TTL);
        }

        // Build product data
        $productData = $cachedData['products'] ?? $this->getOptimizedFilteredProducts($categoryContext, $currentFilters, $request);
        $isSimilar = $productData['sim'] ?? false;
        if (!isset($cachedData['products']) && !$isSimilar) {
            RedisCacheService::put($cacheKeys['products'], $productData, self::PRODUCTS_CACHE_TTL);
        }

        // Compact response with abbreviated keys
        return response()->json([
            'ok' => true,
            'f' => $filterData, // filters
            'p' => $productData['prods'], // products
            'pg' => $productData['pg'], // pagination
            'm' => [ // meta
                'tot' => $productData['tot'],
                'cf' => $currentFilters,
                'ms' => round((microtime(true) - $startTime) * 1000, 2),
                'ch' => isset($cachedData['filters'], $cachedData['products']),
                'sim' => $isSimilar,
                'msg' => $isSimilar ? 'No products match filters. Showing similar.' : null
            ]
        ], 200, ['Cache-Control' => 'public, max-age=' . self::PRODUCTS_CACHE_TTL]);
    }

    private function generateCacheKeys($category, array $filters, Request $request): array
    {
        $catKey = $category ? $category->slug : 'all';
        $filterHash = md5(serialize($filters));
        $page = $request->input('page', 1);

        return [
            'filters' => "fv6:{$catKey}:{$filterHash}",
            'products' => "pv6:{$catKey}:{$filterHash}:{$page}",
        ];
    }

    private function getCachedDataPipeline(array $cacheKeys): array
    {
        $validKeys = array_filter($cacheKeys);

        // Use RedisCacheService::mget() which handles deserialization automatically
        return RedisCacheService::mget($validKeys);
    }

    private function buildOptimizedFilterData($category, array $currentFilters): array
    {
        $baseStats = $this->getAggregatedStats($category, $currentFilters);

        return [
            'br' => $baseStats['brands'] ?? [], // brands
            'pr' => $baseStats['price_range'] ?? $this->getDefaultPriceRange(), // price
            'rt' => $baseStats['ratings'] ?? [], // ratings
            'dc' => $baseStats['discounts'] ?? [], // discounts
            'av' => $baseStats['availability'] ?? [], // availability
            'sc' => $this->getSubCategories($category), // subcats
            'so' => $this->getSortOptions(), // sort options
            'do' => $this->getDisplayOptions(), // display options
            'af' => $this->formatAppliedFilters($currentFilters) // applied filters
        ];
    }

    private function getAggregatedStats($category, array $currentFilters): array
    {
        $cacheKey = 'ag6_' . md5(serialize([$category?->id, $currentFilters]));

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category, $currentFilters) {
            $baseQuery = $this->buildBaseStatsQuery($category, $currentFilters);
            $baseSql = $baseQuery->toSql();
            $bindings = $baseQuery->getBindings();

            $sql = "
                WITH fp AS ({$baseSql}),
                vmin AS (
                    SELECT
                        p.id as product_id,
                        MIN(v.price) FILTER (WHERE v.stock > 0) as min_orig_instock,
                        MIN(v.price * (1 - COALESCE(v.discount, 0) / 100.0)) FILTER (WHERE v.stock > 0) as min_disc_instock,
                        bool_or(v.stock > 0) as has_stock
                    FROM fp p
                    LEFT JOIN product_variants v ON p.id = v.product_id AND v.status = 'active'
                    GROUP BY p.id
                ),
                vmaxd AS (
                    SELECT
                        p.id as product_id,
                        MAX(v.discount) FILTER (WHERE v.stock > 0 AND v.status = 'active') as max_variant_disc
                    FROM fp p
                    LEFT JOIN product_variants v ON p.id = v.product_id
                    GROUP BY p.id
                ),
                ps AS (
                    SELECT
                        MIN(effective_price) as mn,
                        MAX(effective_price) as mx
                    FROM (
                        SELECT
                            CASE
                                WHEN NOT p.has_variants AND p.base_stock > 0 THEN p.base_price * (1 - COALESCE(p.base_discount, 0) / 100.0)
                                WHEN p.has_variants AND v.has_stock THEN v.min_disc_instock
                                ELSE NULL
                            END as effective_price
                        FROM fp p
                        LEFT JOIN vmin v ON p.id = v.product_id
                        WHERE (NOT p.has_variants AND p.base_stock > 0) OR (p.has_variants AND v.has_stock)
                    ) eff
                    WHERE effective_price IS NOT NULL
                ),
                avs AS (
                    SELECT
                        COUNT(*) FILTER (WHERE (NOT p.has_variants AND p.base_stock > 0) OR (p.has_variants AND COALESCE(v.has_stock, false))) as ins,
                        COUNT(*) FILTER (WHERE NOT ((NOT p.has_variants AND p.base_stock > 0) OR (p.has_variants AND COALESCE(v.has_stock, false)))) as oos
                    FROM fp p
                    LEFT JOIN vmin v ON p.id = v.product_id
                ),
                dcs AS (
                    SELECT
                        COUNT(*) FILTER (WHERE
                            ((NOT p.has_variants AND p.base_discount >= 5 AND p.base_stock > 0) OR
                             (p.has_variants AND COALESCE(vd.max_variant_disc, 0) >= 5 AND COALESCE(v.has_stock, false)))
                        ) as d5,
                        COUNT(*) FILTER (WHERE
                            ((NOT p.has_variants AND p.base_discount >= 10 AND p.base_stock > 0) OR
                             (p.has_variants AND COALESCE(vd.max_variant_disc, 0) >= 10 AND COALESCE(v.has_stock, false)))
                        ) as d10,
                        COUNT(*) FILTER (WHERE
                            ((NOT p.has_variants AND p.base_discount >= 20 AND p.base_stock > 0) OR
                             (p.has_variants AND COALESCE(vd.max_variant_disc, 0) >= 20 AND COALESCE(v.has_stock, false)))
                        ) as d20,
                        COUNT(*) FILTER (WHERE
                            ((NOT p.has_variants AND p.base_discount >= 30 AND p.base_stock > 0) OR
                             (p.has_variants AND COALESCE(vd.max_variant_disc, 0) >= 30 AND COALESCE(v.has_stock, false)))
                        ) as d30,
                        COUNT(*) FILTER (WHERE
                            ((NOT p.has_variants AND p.base_discount >= 50 AND p.base_stock > 0) OR
                             (p.has_variants AND COALESCE(vd.max_variant_disc, 0) >= 50 AND COALESCE(v.has_stock, false)))
                        ) as d50
                    FROM fp p
                    LEFT JOIN vmin v ON p.id = v.product_id
                    LEFT JOIN vmaxd vd ON p.id = vd.product_id
                ),
                brs AS (
                    SELECT
                        b.slug, b.title, COUNT(DISTINCT f.id) as cnt
                    FROM fp f
                    INNER JOIN brands b ON f.brand_id = b.id
                    LEFT JOIN vmin v ON f.id = v.product_id
                    WHERE b.status = 'active'
                    AND ((NOT f.has_variants AND f.base_stock > 0) OR (f.has_variants AND v.has_stock))
                    GROUP BY b.slug, b.title
                    HAVING COUNT(DISTINCT f.id) > 0
                    ORDER BY b.title
                    LIMIT 50
                ),
                rts AS (
                    SELECT
                        FLOOR(prc.average_rating) as rt,
                        COUNT(DISTINCT f.id) as cnt
                    FROM fp f
                    INNER JOIN product_ratings_cache prc ON f.id = prc.product_id
                    LEFT JOIN vmin v ON f.id = v.product_id
                    WHERE prc.average_rating >= 1
                    AND ((NOT f.has_variants AND f.base_stock > 0) OR (f.has_variants AND v.has_stock))
                    GROUP BY FLOOR(prc.average_rating)
                )
                SELECT
                    json_build_object(
                        'p', (SELECT row_to_json(p) FROM ps p),
                        'av', (SELECT row_to_json(a) FROM avs a),
                        'dc', (SELECT row_to_json(d) FROM dcs d),
                        'br', (SELECT json_agg(row_to_json(b)) FROM brs b),
                        'rt', (SELECT json_agg(row_to_json(r)) FROM rts r)
                    ) as stats
            ";

            $result = DB::selectOne($sql, $bindings);
            $stats = json_decode($result->stats, true);

            return $this->formatAggregatedStats($stats, $currentFilters);
        });
    }

    private function buildBaseStatsQuery($category, array $filters)
    {
        $query = DB::table('products as p')
            ->select(['p.id', 'p.base_price', 'p.base_discount', 'p.base_stock', 'p.has_variants', 'p.brand_id'])
            ->where('p.status', 'active');

        if ($category) {
            $this->applyCategoryFilter($query, $category);
        }

        if (!empty($filters['ratings'])) {
            $query->leftJoin('product_ratings_cache as prc', 'p.id', '=', 'prc.product_id');
            $query->addSelect('prc.average_rating');
        }

        // IMPORTANT: Don't apply discount filter to stats query
        // We need to show all discount counts regardless of current selection
        $filtersWithoutPriceAndDiscount = array_diff_key($filters, [
            'price_range' => 1,
            'discounts' => 1  // Added this line
        ]);

        $this->applyFiltersToQuery($query, $filtersWithoutPriceAndDiscount);

        return $query;
    }

    private function formatAggregatedStats(array $stats, array $currentFilters): array
    {
        return [
            'price_range' => [
                'mn' => (float) ($stats['p']['mn'] ?? 0),
                'mx' => (float) ($stats['p']['mx'] ?? self::MAX_PRICE_DEFAULT),
                'cmn' => $this->getCurrentPriceMin($currentFilters),
                'cmx' => $this->getCurrentPriceMax($currentFilters),
                'cur' => '$'
            ],
            'availability' => [
                ['cnt' => (int) ($stats['av']['ins'] ?? 0), 'v' => 'in_stock', 'sel' => in_array('in_stock', $currentFilters['availability'] ?? [])],
                ['cnt' => (int) ($stats['av']['oos'] ?? 0), 'v' => 'out_of_stock', 'sel' => in_array('out_of_stock', $currentFilters['availability'] ?? [])]
            ],
            'discounts' => $this->formatDiscountStats($stats['dc'] ?? [], $currentFilters),
            'brands' => $this->formatBrandStats($stats['br'] ?? [], $currentFilters),
            'ratings' => $this->formatRatingStats($stats['rt'] ?? [], $currentFilters)
        ];
    }

    private function getOptimizedFilteredProducts($category, array $currentFilters, Request $request)
    {
        $perPage = min((int) $request->input('show', 12), 48);
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * $perPage;
        $sortBy = $currentFilters['sortBy'] ?? 'latest';

        $totalCount = $this->getCountEstimate($category, $currentFilters);
        $isSimilar = false;

        if ($totalCount == 0) {
            $similarFilters = ['sortBy' => $sortBy];
            $totalCount = $this->getCountEstimate($category, $similarFilters);
            $isSimilar = true;
            $page = 1;
            $offset = 0;
            $query = $this->buildProductQuery($category);
            $this->applyFiltersToQuery($query, $similarFilters);
            $this->applySorting($query, $sortBy);
            $products = $query->offset($offset)->limit($perPage)->get();
        } else {
            $query = $this->buildProductQuery($category);
            $this->applyFiltersToQuery($query, $currentFilters);
            $this->applySorting($query, $sortBy);
            $products = $query->offset($offset)->limit($perPage)->get();
        }

        return [
            'prods' => $this->transformProductsOptimized($products),
            'pg' => [
                'cp' => $page,
                'lp' => max(1, ceil($totalCount / $perPage)),
                'tot' => $totalCount,
                'pp' => $perPage,
                'fr' => $offset + 1,
                'to' => min($offset + count($products), $totalCount)
            ],
            'tot' => $totalCount,
            'sim' => $isSimilar
        ];
    }

    private function buildProductQuery($category)
    {
        $variantImagesSub = DB::table('product_variants as pv')
            ->select([
                'pv.product_id',
                DB::raw("ARRAY_AGG(CONCAT(vi.sort_order, ':', vi.image_path) ORDER BY vi.sort_order ASC) as variant_imgs")
            ])
            ->join('variant_images as vi', function ($join) {
                $join->on('vi.product_variant_id', '=', 'pv.id')
                    ->whereNotNull('vi.image_path')
                    ->where('vi.sort_order', '<=', 2);
            })
            ->where('pv.status', 'active')
            ->groupBy('pv.product_id');

        $query = DB::table('products as p')
            ->select([
                'p.id', 'p.title', 'p.slug', 'p.base_price', 'p.base_discount',
                'p.base_stock', 'p.condition', 'p.has_variants',
                'b.title as bt', 'b.slug as bs',
                'prc.average_rating as ar', 'prc.total_reviews as tr',
                DB::raw('ARRAY_AGG(DISTINCT CONCAT(pi.sort_order, \':\', pi.image_path)) FILTER (WHERE pi.image_path IS NOT NULL) as imgs'),
                DB::raw('variant_image_data.variant_imgs as variant_imgs'),
                // ✅ FIXED: Use LATERAL join to get cheapest in-stock variant
                DB::raw('
                    COALESCE(
                        cheapest_v.min_disc_price,
                        p.base_price * (1 - COALESCE(p.base_discount, 0) / 100.0)
                    ) as effective_price
                '),
                DB::raw('
                    COALESCE(
                        cheapest_v.price,
                        p.base_price
                    ) as effective_original_price
                '),
                DB::raw('
                    COALESCE(
                        cheapest_v.discount,
                        p.base_discount
                    ) as effective_discount
                '),
                DB::raw('
                    CASE
                        WHEN p.has_variants THEN
                            COALESCE(cheapest_v.stock > 0, false)
                        ELSE (p.base_stock > 0)
                    END as is_in_stock
                ')
            ])
            ->leftJoin('brands as b', 'p.brand_id', '=', 'b.id')
            ->leftJoin('product_ratings_cache as prc', 'p.id', '=', 'prc.product_id')
            // ✅ FIXED: Properly get cheapest in-stock variant using LATERAL join
            ->leftJoinSub(
                'SELECT DISTINCT ON (product_id)
                    product_id,
                    price,
                    discount,
                    stock,
                    price * (1 - COALESCE(discount, 0) / 100.0) as min_disc_price
                FROM product_variants
                WHERE status = \'active\' AND stock > 0
                ORDER BY product_id, price * (1 - COALESCE(discount, 0) / 100.0) ASC',
                'cheapest_v',
                'cheapest_v.product_id',
                '=',
                'p.id'
            )
            ->leftJoinSub($variantImagesSub, 'variant_image_data', function ($join) {
                $join->on('variant_image_data.product_id', '=', 'p.id');
            })
            ->leftJoin('product_images as pi', function($join) {
                $join->on('p.id', '=', 'pi.product_id')
                    ->whereRaw('pi.sort_order <= 2');
            })
            ->where('p.status', 'active')
            ->groupBy([
                'p.id', 'p.title', 'p.slug', 'p.base_price', 'p.base_discount',
                'p.base_stock', 'p.condition', 'p.has_variants',
                'b.title', 'b.slug', 'prc.average_rating', 'prc.total_reviews',
                'cheapest_v.price', 'cheapest_v.discount', 'cheapest_v.stock', 'cheapest_v.min_disc_price',
                'variant_image_data.variant_imgs'
            ]);

        if ($category) {
            $this->applyCategoryFilter($query, $category);
        }

        return $query;
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
                $query->where(function($q) use ($min, $max) {
                    $q->where(function($sub) use ($min, $max) {
                        $sub->where('p.has_variants', false)
                            ->whereRaw('(p.base_price * (1 - COALESCE(p.base_discount, 0) / 100.0) BETWEEN ? AND ?)', [$min, $max]);
                    })->orWhere(function($sub) use ($min, $max) {
                        $sub->where('p.has_variants', true)
                            ->whereRaw('EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0 AND (v.price * (1 - COALESCE(v.discount, 0) / 100.0) BETWEEN ? AND ?))', [$min, $max]);
                    });
                });
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
            $query->where(function($q) use ($minDiscount) {
                $q->where(function($sub) use ($minDiscount) {
                    $sub->where('p.has_variants', false)
                        ->where('p.base_discount', '>=', $minDiscount)
                        ->where('p.base_stock', '>', 0);
                })->orWhere(function($sub) use ($minDiscount) {
                    $sub->where('p.has_variants', true)
                        ->whereRaw('EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0 AND v.discount >= ?)', [$minDiscount]);
                });
            });
        }

        if (!empty($filters['availability'])) {
            $query->where(function ($q) use ($filters) {
                foreach ($filters['availability'] as $avail) {
                    if ($avail === 'in_stock') {
                        $q->orWhere(function($sub) {
                            $sub->where(function($s) {
                                $s->where('p.has_variants', false)->where('p.base_stock', '>', 0);
                            })->orWhere(function($s) {
                                $s->where('p.has_variants', true)
                                    ->whereRaw('EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0)');
                            });
                        });
                    } elseif ($avail === 'out_of_stock') {
                        $q->orWhere(function($sub) {
                            $sub->where(function($s) {
                                $s->where('p.has_variants', false)->where('p.base_stock', '<=', 0);
                            })->orWhere(function($s) {
                                $s->where('p.has_variants', true)
                                    ->whereRaw('NOT EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0)');
                            });
                        });
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
        switch ($sortBy) {
            case 'price_low_high':
                $query->orderByRaw('
                    CASE
                        WHEN p.has_variants THEN
                            COALESCE((SELECT MIN(v.price * (1 - COALESCE(v.discount, 0) / 100.0)) FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0), p.base_price * (1 - p.base_discount / 100.0))
                        ELSE p.base_price * (1 - COALESCE(p.base_discount, 0) / 100.0)
                    END ASC
                ');
                break;
            case 'price_high_low':
                $query->orderByRaw('
                    CASE
                        WHEN p.has_variants THEN
                            COALESCE((SELECT MIN(v.price * (1 - COALESCE(v.discount, 0) / 100.0)) FROM product_variants v WHERE v.product_id = p.id AND v.status = \'active\' AND v.stock > 0), p.base_price * (1 - p.base_discount / 100.0))
                        ELSE p.base_price * (1 - COALESCE(p.base_discount, 0) / 100.0)
                    END DESC
                ');
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
                $query->orderBy('p.id', 'DESC');
        }
    }

    private function transformProductsOptimized($products): array
    {
        return $products->map(function ($p) {
            $finalPrice = (float) ($p->effective_price ?? 0);
            $origPrice = (float) ($p->effective_original_price ?? 0);
            $disc = (int) ($p->effective_discount ?? 0);
            $isInStock = (bool) $p->is_in_stock;
            $stock = $isInStock ? ($p->has_variants ? 999 : (int) $p->base_stock) : 0;

            $variantImages = $this->normalizeImageAggregate($p->variant_imgs ?? null);
            $productImages = $this->normalizeImageAggregate($p->imgs ?? null);
            $rawImages = $p->has_variants
                ? (!empty($variantImages) ? $variantImages : $productImages)
                : (!empty($productImages) ? $productImages : $variantImages);
            $images = $this->formatFrontendImagePaths($rawImages, (bool) $p->has_variants);

            return [
                'id' => $p->id,
                't' => Str::limit($p->title, 60),
                's' => $p->slug,
                'pr' => [
                    'o' => $origPrice,
                    'f' => round($finalPrice, 2),
                    'd' => $disc
                ],
                'st' => $stock,
                'c' => $p->condition,
                'b' => $p->bt ? ['t' => $p->bt, 's' => $p->bs] : null,
                'i' => $images,
                'r' => [
                    'a' => (float) ($p->ar ?? 0),
                    't' => (int) ($p->tr ?? 0)
                ]
            ];
        })->toArray();
    }

    private function normalizeImageAggregate($rawImages): array
    {
        if (empty($rawImages)) {
            return [];
        }

        $values = is_string($rawImages)
            ? array_filter(explode(',', trim($rawImages, '{}')))
            : (array) $rawImages;

        $imageData = [];
        foreach ($values as $value) {
            $value = trim($value, " \"'");
            if ($value === '') {
                continue;
            }

            $parts = explode(':', $value, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $imageData[] = [
                'so' => (int) $parts[0],
                'p' => trim($parts[1], " \"'")
            ];
        }

        if (empty($imageData)) {
            return [];
        }

        usort($imageData, fn($a, $b) => $a['so'] <=> $b['so']);

        return array_column($imageData, 'p');
    }

    private function formatFrontendImagePaths(array $filenames, bool $hasVariants): array
    {
        if (empty($filenames)) {
            return [];
        }

        $baseDir = $hasVariants ? 'products/variants/' : 'products/';

        return array_values(array_filter(array_map(function ($path) use ($baseDir) {
            if (empty($path)) {
                return null;
            }

            $normalized = ltrim($path, '/');

            if (Str::startsWith($normalized, ['http://', 'https://'])) {
                $parsedPath = parse_url($normalized, PHP_URL_PATH) ?: '';
                $normalized = ltrim($parsedPath, '/');
            }

            if (Str::startsWith($normalized, 'storage/')) {
                $normalized = substr($normalized, strlen('storage/'));
            }

            if (
                Str::startsWith($normalized, 'products/') ||
                Str::startsWith($normalized, 'photos/') ||
                Str::startsWith($normalized, 'backend/')
            ) {
                return $normalized;
            }

            if (Str::startsWith($normalized, 'variant_')) {
                return 'products/variants/' . $normalized;
            }

            if (Str::startsWith($normalized, 'product_')) {
                return 'products/' . $normalized;
            }

            return $baseDir . $normalized;
        }, $filenames)));
    }

    private function getCountEstimate($category, array $filters): int
    {
        $cacheKey = 'cnt6_' . md5(serialize([$category?->id, $filters]));

        return Cache::remember($cacheKey, 300, function () use ($category, $filters) {
            if ($this->shouldUseEstimate($filters)) {
                $estimate = DB::selectOne("
                    SELECT reltuples::BIGINT as estimate
                    FROM pg_class
                    WHERE relname = 'products'
                ")->estimate ?? 0;

                return max(1, (int) ($estimate * $this->getFilterFactor($category, $filters)));
            }

            $query = DB::table('products as p')->select('p.id')->where('p.status', 'active');
            if ($category) {
                $this->applyCategoryFilter($query, $category);
            }
            if (!empty($filters['ratings'])) {
                $query->leftJoin('product_ratings_cache as prc', 'p.id', '=', 'prc.product_id');
            }
            $this->applyFiltersToQuery($query, $filters);

            return $query->count();
        });
    }

    private function shouldUseEstimate(array $filters): bool
    {
        return empty(array_filter($filters, fn($v) => !empty($v) && $v !== 'latest'));
    }

    private function getFilterFactor($category, array $filters): float
    {
        $factor = 1.0;
        if ($category) $factor *= 0.1;
        if (!empty($filters['brands'])) $factor *= 0.2;
        if (!empty($filters['price_range'])) $factor *= 0.3;
        if (!empty($filters['ratings'])) $factor *= 0.4;
        if (!empty($filters['discounts'])) $factor *= 0.3;
        return $factor;
    }

    private function getBrandIdsBySlug(array $slugs): array
    {
        $cacheKey = 'bids6_' . md5(implode(',', $slugs));
        return Cache::remember($cacheKey, 3600, function () use ($slugs) {
            return Brand::whereIn('slug', $slugs)
                ->where('status', 'active')
                ->pluck('id')
                ->toArray();
        });
    }

    private function getSubcategoryIds(int $parentId): array
    {
        return Cache::remember("scs6_{$parentId}", 3600, function () use ($parentId) {
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
                'v' => $slab,
                'cnt' => (int) ($stats["d$slab"] ?? 0),
                'sel' => in_array((string) $slab, $filters['discounts'] ?? [])
            ];
        }
        return $result;
    }

    private function formatBrandStats(?array $brands, array $filters): array
    {
        if (!$brands) return [];

        return array_map(function ($b) use ($filters) {
            return [
                's' => $b['slug'],
                'cnt' => (int) $b['cnt'],
                'sel' => in_array($b['slug'], $filters['brands'] ?? [])
            ];
        }, $brands);
    }

    private function formatRatingStats(?array $ratings, array $filters): array
    {
        $ratingMap = [];
        if ($ratings) {
            foreach ($ratings as $r) {
                $ratingMap[(int) $r['rt']] = (int) $r['cnt'];
            }
        }

        $result = [];
        for ($i = 5; $i >= 1; $i--) {
            $result[] = [
                'v' => $i,
                'cnt' => $ratingMap[$i] ?? 0,
                'sel' => in_array((string) $i, $filters['ratings'] ?? [])
            ];
        }
        return $result;
    }

    private function parseCurrentFilters(Request $request): array
    {
        // Parse discounts from both 'discounts' and 'discount' parameters
        $discounts = [];
        if ($request->has('discounts')) {
            $discounts = $this->parseArray($request->input('discounts', []));
        } elseif ($request->has('discount')) {
            $discounts = $this->parseArray($request->input('discount', []));
        }

        return [
            'brands' => array_slice($this->parseArray($request->input('brands', [])), 0, 20),
            'ratings' => array_slice($this->parseArray($request->input('ratings', [])), 0, 5),
            'discounts' => array_slice($discounts, 0, 10),
            'price_range' => $request->input('price_range', ''),
            'availability' => $this->parseArray($request->input('availability', [])),
            'sortBy' => $request->input('sortBy', 'latest'),
            'show' => min((int) $request->input('show', 12), 48)
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
            ->select(['id', 'slug'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                's' => $c->slug,
                'sel' => false
            ])->toArray();
    }

    private function getSortOptions(): array
    {
        return [
            ['v' => 'latest'],
            ['v' => 'price_low_high'],
            ['v' => 'price_high_low'],
            ['v' => 'rating_high_low'],
            ['v' => 'name_a_z'],
            ['v' => 'name_z_a']
        ];
    }

    private function getDisplayOptions(): array
    {
        return [
            ['v' => 12],
            ['v' => 24],
            ['v' => 36]
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

    private function getDefaultPriceRange(): array
    {
        return [
            'mn' => 0,
            'mx' => self::MAX_PRICE_DEFAULT,
            'cmn' => 0,
            'cmx' => self::MAX_PRICE_DEFAULT,
            'cur' => ''
        ];
    }
}
