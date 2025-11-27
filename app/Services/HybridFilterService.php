<?php

namespace App\Services;

use App\Services\ElasticsearchService;
use App\Services\FastFilterService;
use App\Services\RedisCacheService;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid Filter Service - Elasticsearch + Redis
 *
 * Combines the speed of Elasticsearch full-text search with Redis SET-based filtering
 * for optimal performance with 10M+ products.
 *
 * Strategy:
 * 1. If text search: Use Elasticsearch (blazing fast full-text)
 * 2. If filters only: Use Redis indexes (ultra-fast SET operations)
 * 3. If both: Elasticsearch first, then Redis refinement
 * 4. Cache everything aggressively
 *
 * Target: <1 second for any filter combination with 10M products
 */
class HybridFilterService
{
    private ElasticsearchService $elasticsearch;
    private FastFilterService $redisFilter;
    private const CACHE_TTL = 1800; // 30 minutes

    public function __construct(
        ElasticsearchService $elasticsearch,
        FastFilterService $redisFilter
    ) {
        $this->elasticsearch = $elasticsearch;
        $this->redisFilter = $redisFilter;
    }

    /**
     * Get filtered products using optimal strategy
     *
     * @param array $filters Filter criteria
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return array ['products' => Collection, 'total' => int, 'method' => string, 'time_ms' => float]
     */
    public function getFilteredProducts(array $filters, int $page = 1, int $perPage = 12): array
    {
        $startTime = microtime(true);

        // Generate cache key
        $cacheKey = $this->generateCacheKey($filters, $page, $perPage);

        // Try cache first (instant response)
        $cached = RedisCacheService::get($cacheKey);
        if ($cached) {
            $cached['time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            $cached['method'] = 'cache_hit';
            Log::info('HybridFilter: Cache HIT', [
                'filters' => $filters,
                'page' => $page,
                'time_ms' => $cached['time_ms']
            ]);
            return $cached;
        }

        // Determine optimal strategy
        $hasTextSearch = !empty($filters['search']) || !empty($filters['query']);
        $hasFilters = !empty($filters['category_id']) || !empty($filters['brands']) ||
                      !empty($filters['price_range']) || !empty($filters['min_rating']);

        $result = null;

        if ($hasTextSearch && $hasFilters) {
            // Strategy 1: Elasticsearch + Redis refinement
            $result = $this->hybridSearch($filters, $page, $perPage);
            $method = 'elasticsearch_redis_hybrid';
        } elseif ($hasTextSearch) {
            // Strategy 2: Pure Elasticsearch (best for full-text)
            $result = $this->elasticsearchOnly($filters, $page, $perPage);
            $method = 'elasticsearch_only';
        } elseif ($hasFilters) {
            // Strategy 3: Pure Redis indexes (best for structured filters)
            $result = $this->redisOnly($filters, $page, $perPage);
            $method = 'redis_only';
        } else {
            // No filters - get latest products
            $result = $this->getDefaultProducts($page, $perPage);
            $method = 'default';
        }

        if (!$result) {
            return [
                'products' => collect([]),
                'total' => 0,
                'method' => 'error',
                'time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }

        $result['time_ms'] = round((microtime(true) - $startTime) * 1000, 2);
        $result['method'] = $method;

        // Cache the result
        RedisCacheService::put($cacheKey, $result, self::CACHE_TTL);

        Log::info('HybridFilter: Result', [
            'method' => $method,
            'filters' => $filters,
            'page' => $page,
            'total' => $result['total'],
            'time_ms' => $result['time_ms']
        ]);

        return $result;
    }

    /**
     * Strategy 1: Elasticsearch search + Redis filter refinement
     * Best for: "nike shoes" + price filter + brand filter
     */
    private function hybridSearch(array $filters, int $page, int $perPage): array
    {
        $query = $filters['search'] ?? $filters['query'] ?? '';

        // Step 1: Elasticsearch full-text search (no limit, get all matches)
        $esResults = $this->elasticsearch->searchProducts($query, 10000, 1);

        if (empty($esResults['hits'])) {
            return ['products' => collect([]), 'total' => 0];
        }

        // Extract product IDs from Elasticsearch results
        $esProductIds = collect($esResults['hits'])->pluck('id')->toArray();

        // Step 2: Apply Redis filters to refine results
        unset($filters['search'], $filters['query']);

        if (!empty($filters)) {
            $redisProductIds = $this->redisFilter->getFilteredProductIds($filters);

            // Intersect: Only products that match BOTH Elasticsearch AND filters
            $finalProductIds = array_intersect($esProductIds, $redisProductIds);
        } else {
            $finalProductIds = $esProductIds;
        }

        $total = count($finalProductIds);

        // Step 3: Paginate
        $offset = ($page - 1) * $perPage;
        $paginatedIds = array_slice($finalProductIds, $offset, $perPage);

        // Step 4: Fetch products maintaining Elasticsearch order
        $products = $this->fetchProductsByIds($paginatedIds, true);

        return [
            'products' => $products,
            'total' => $total,
        ];
    }

    /**
     * Strategy 2: Pure Elasticsearch search
     * Best for: Text search without filters
     */
    private function elasticsearchOnly(array $filters, int $page, int $perPage): array
    {
        $query = $filters['search'] ?? $filters['query'] ?? '';

        $esResults = $this->elasticsearch->searchProducts($query, $perPage, $page);

        if (empty($esResults['hits'])) {
            return ['products' => collect([]), 'total' => 0];
        }

        $productIds = collect($esResults['hits'])->pluck('id')->toArray();
        $products = $this->fetchProductsByIds($productIds, true);

        return [
            'products' => $products,
            'total' => $esResults['total'] ?? count($productIds),
        ];
    }

    /**
     * Strategy 3: Pure Redis index filtering
     * Best for: Structured filters (category, brand, price, rating)
     */
    private function redisOnly(array $filters, int $page, int $perPage): array
    {
        $sortBy = $this->normalizeSort($filters['sortBy'] ?? 'latest');
        $redisResult = $this->redisFilter->getFilteredProductIds($filters);

        // Backwards compatibility: older response returned a flat list of IDs
        if (is_array($redisResult) && !array_key_exists('key', $redisResult)) {
            $productIds = array_map('intval', $redisResult);
            $total = count($productIds);

            $sortedIds = $this->applySorting($productIds, $sortBy);
            $offset = ($page - 1) * $perPage;
            $paginatedIds = array_slice($sortedIds, $offset, $perPage);

            $products = $this->fetchProductsByIds($paginatedIds, false);

            return [
                'products' => $products,
                'total' => $total,
            ];
        }

        $redisKey = $redisResult['key'] ?? null;
        $total = (int) ($redisResult['count'] ?? 0);

        if (!$redisKey || $total === 0) {
            return [
                'products' => collect([]),
                'total' => 0,
            ];
        }

        $globalOffset = max(0, ($page - 1) * $perPage);
        $paginatedIds = [];

        if ($sortBy === 'latest') {
            $paginatedIds = $this->redisFilter->getPaginatedIds($redisKey, $globalOffset, $perPage);
        } else {
            $isPriceSort = in_array($sortBy, ['price_asc', 'price_desc']);
            $buffer = $isPriceSort ? $perPage * 2 : $perPage;
            $prefetchOffset = $globalOffset > $buffer ? $globalOffset - $buffer : 0;
            $fetchSize = $perPage + $buffer;

            $candidateIds = $this->redisFilter->getPaginatedIds($redisKey, $prefetchOffset, $fetchSize);
            $candidateIds = array_map('intval', $candidateIds);

            if (!empty($candidateIds)) {
                $sortedCandidateIds = $this->applySorting($candidateIds, $sortBy);
                $sliceStart = max(0, $globalOffset - $prefetchOffset);
                $paginatedIds = array_slice($sortedCandidateIds, $sliceStart, $perPage);
            }
        }

        if (empty($paginatedIds)) {
            Log::warning('HybridFilter: Redis result empty, falling back to database query', [
                'filters' => $filters,
                'page' => $page,
                'per_page' => $perPage,
            ]);
            return $this->databaseFallback($filters, $page, $perPage);
        }

        $products = $this->fetchProductsByIds($paginatedIds, false);

        return [
            'products' => $products,
            'total' => $total,
        ];
    }

    /**
     * Get default products (no filters)
     */
    private function getDefaultProducts(int $page, int $perPage): array
    {
        $cacheKey = "default_products:page:{$page}:size:{$perPage}";

        $cached = RedisCacheService::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        $query = Product::where('status', 'active')
            ->with($this->getProductRelations())
            ->orderByDesc('id');

        $total = $query->count();
        $products = $query->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        $result = [
            'products' => $products,
            'total' => $total,
        ];

        RedisCacheService::put($cacheKey, $result, 600); // 10 min cache

        return $result;
    }

    /**
     * Fetch products by IDs with all relations
     */
    private function fetchProductsByIds(array $productIds, bool $maintainOrder = false): \Illuminate\Support\Collection
    {
        if (empty($productIds)) {
            return collect([]);
        }

        $products = Product::whereIn('id', $productIds)
            ->where('status', 'active')
            ->with($this->getProductRelations())
            ->get();

        // Maintain original order if requested (for Elasticsearch relevance)
        if ($maintainOrder) {
            $products = $products->sortBy(function ($product) use ($productIds) {
                return array_search($product->id, $productIds);
            })->values();
        }

        return $products;
    }

    /**
     * Apply sorting to product IDs
     */
    private function applySorting(array $productIds, string $sortBy): array
    {
        if (empty($productIds)) {
            return [];
        }

        $query = Product::whereIn('id', $productIds)
            ->where('status', 'active')
            ->select('products.id');

        $query = $this->applyOrderClause($query, $sortBy);

        return $query->select('products.id')->pluck('products.id')->toArray();
    }

    private function normalizeSort(string $sortBy): string
    {
        return match ($sortBy) {
            'price_low_high' => 'price_asc',
            'price_high_low' => 'price_desc',
            'name_a_z' => 'title_asc',
            'name_z_a' => 'title_desc',
            'rating_high_low' => 'rating_desc',
            'price_asc', 'price_desc', 'title_asc', 'title_desc', 'rating_desc', 'latest' => $sortBy,
            default => 'latest',
        };
    }

    private function applyOrderClause(Builder $query, string $sortBy): Builder
    {
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('products.base_price', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('products.base_price', 'desc');
                break;
            case 'title_asc':
                $query->orderBy('products.title', 'asc');
                break;
            case 'title_desc':
                $query->orderBy('products.title', 'desc');
                break;
            case 'rating_desc':
                $query->leftJoin('product_ratings_cache as prc', 'products.id', '=', 'prc.product_id')
                    ->orderByDesc(DB::raw('COALESCE(prc.average_rating, 0)'));
                break;
            case 'latest':
            default:
                $query->orderByDesc('products.id');
                break;
        }

        return $query;
    }

    private function applyDatabaseFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->whereIn('products.cat_id', $filters['category_ids']);
        } elseif (!empty($filters['category_id'])) {
            $query->where('products.cat_id', $filters['category_id']);
        }

        if (!empty($filters['brands'])) {
            $query->whereIn('products.brand_id', (array) $filters['brands']);
        }

        if (!empty($filters['price_range'])) {
            $this->applyPriceRangeFilter($query, $filters['price_range']);
        }

        if (!empty($filters['min_rating'])) {
            $query->leftJoin('product_ratings_cache as prc_filter', 'products.id', '=', 'prc_filter.product_id')
                ->where('prc_filter.average_rating', '>=', (int) $filters['min_rating']);
        }

        if (!empty($filters['min_discount'])) {
            $minDiscount = (int) $filters['min_discount'];
            $query->where(function ($q) use ($minDiscount) {
                $q->where('products.base_discount', '>=', $minDiscount)
                    ->orWhere(function ($variantQ) use ($minDiscount) {
                        $variantQ->where('products.has_variants', true)
                            ->where('products.base_discount', '>=', $minDiscount * 0.5);
                    });
            });
        }
    }

    private function applyPriceRangeFilter(Builder $query, string $priceRange): void
    {
        if (str_contains($priceRange, '-')) {
            $range = array_map('floatval', explode('-', $priceRange));
            if (count($range) === 2) {
                [$minPrice, $maxPrice] = $range;
                $query->where(function ($q) use ($minPrice, $maxPrice) {
                    $q->whereBetween('products.base_price', [$minPrice, $maxPrice])
                        ->orWhere(function ($subQ) use ($minPrice, $maxPrice) {
                            $subQ->where('products.has_variants', true)
                                ->where('products.base_price', '>=', $minPrice * 0.5)
                                ->where('products.base_price', '<=', $maxPrice * 1.5);
                        });
                });
            }
        } elseif (str_contains($priceRange, '+')) {
            $minPrice = (float) str_replace('+', '', $priceRange);
            $query->where(function ($q) use ($minPrice) {
                $q->where('products.base_price', '>=', $minPrice)
                    ->orWhere(function ($subQ) use ($minPrice) {
                        $subQ->where('products.has_variants', true)
                            ->where('products.base_price', '>=', $minPrice * 0.5);
                    });
            });
        }
    }

    private function databaseFallback(array $filters, int $page, int $perPage): array
    {
        $query = Product::query()
            ->from('products')
            ->where('products.status', 'active');

        $this->applyDatabaseFilters($query, $filters);

        $countQuery = clone $query;
        $total = $countQuery->distinct()->count('products.id');

        $sortBy = $this->normalizeSort($filters['sortBy'] ?? 'latest');
        $orderedQuery = $this->applyOrderClause(clone $query, $sortBy);

        $ids = $orderedQuery->select('products.id')
            ->distinct()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->pluck('products.id')
            ->toArray();

        $products = $this->fetchProductsByIds($ids, false);

        return [
            'products' => $products,
            'total' => $total,
        ];
    }

    /**
     * Get product relations for eager loading
     */
    private function getProductRelations(): array
    {
        return [
            'images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary', 'sort_order'])
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->take(3),
            'cat_info' => fn($q) => $q->select(['id', 'title', 'slug']),
            'sub_cat_info' => fn($q) => $q->select(['id', 'title', 'slug']),
            'brand' => fn($q) => $q->select(['id', 'title', 'slug']),
            'variants' => fn($q) => $q->where('status', 'active')
                ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_variant_id', 'is_primary'])
                    ->where('is_primary', true)])
        ];
    }

    /**
     * Generate cache key for filter combination
     */
    private function generateCacheKey(array $filters, int $page, int $perPage): string
    {
        ksort($filters);
        $hash = md5(json_encode($filters));
        return "hybrid_filter:{$hash}:page:{$page}:size:{$perPage}";
    }

    /**
     * Get autocomplete suggestions (uses Elasticsearch)
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        return $this->elasticsearch->getAutocompleteSuggestions($query, $limit);
    }

    /**
     * Check if Elasticsearch is available
     */
    public function isElasticsearchAvailable(): bool
    {
        return $this->elasticsearch->isAvailable();
    }

    /**
     * Get statistics about filter performance
     */
    public function getStats(): array
    {
        $redisStats = $this->redisFilter->getStats();

        $stats = [
            'elasticsearch' => [
                'available' => $this->elasticsearch->isAvailable(),
                'index_stats' => $this->elasticsearch->getIndexStats(),
            ],
            'redis_indexes' => $redisStats,
            'cache_keys' => count(RedisCacheService::keys('hybrid_filter:*')),
        ];

        return $stats;
    }
}
