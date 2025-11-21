<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

/**
 * Smart Filter Cache Service
 * Handles intelligent caching for 10M+ products with filter combinations
 *
 * Strategy:
 * - Tier 1: Hot path cache (popular combos)
 * - Tier 2: Single-dimension indexes (category, brand, etc.)
 * - Tier 3: Adaptive pre-fetching based on user behavior
 */
class SmartFilterCacheService
{
    // Cache namespace structure
    private const CACHE_NAMESPACE = 'ecommerce';
    private const CACHE_VERSION = 'v1';

    // Cache prefixes - organized hierarchy
    private const PREFIX_FILTERS = 'filters';
    private const PREFIX_PRODUCTS = 'products';
    private const PREFIX_INDEXES = 'indexes';
    private const PREFIX_METRICS = 'metrics';
    private const PREFIX_ENGAGEMENT = 'engagement';
    private const PREFIX_HOT_COMBOS = 'analytics:hot_combos';

    // Cache configuration
    private const TIER1_TTL = 3600;      // 1 hour for hot combos
    private const TIER2_TTL = 21600;     // 6 hours for indexes
    private const TIER3_TTL = 900;       // 15 min for rare combos
    private const PREFETCH_TTL = 1800;   // 30 min for pre-fetched pages

    private const MAX_HOT_COMBOS = 500;  // Track top 500 filter combinations
    private const PREFETCH_PAGES = 3;    // Pre-fetch next 3 pages
    private const ENGAGEMENT_THRESHOLD = 2; // 2 page views = engaged user

    /**
     * Get cached filter results with smart tiering
     */
    public function getFilteredProducts(array $filters, int $page = 1, int $perPage = 12): ?array
    {
        $cacheKey = $this->generateCacheKey($filters, $page, $perPage);

        // Try Tier 1: Hot path cache
        if ($cached = $this->getTier1Cache($cacheKey)) {
            $this->trackHit('tier1', $cacheKey);
            $this->maybePreFetchPages($filters, $page, $perPage);
            return $cached;
        }

        // Try Tier 2: Single-dimension indexes (can be combined)
        if ($cached = $this->getTier2IndexedCache($filters, $page, $perPage)) {
            $this->trackHit('tier2', $cacheKey);
            // Promote to Tier 1 if accessed frequently
            $this->maybePromoteToTier1($cacheKey, $cached);
            return $cached;
        }

        // Cache miss - will be fetched from DB/Elasticsearch
        $this->trackMiss($cacheKey);
        return null;
    }

    /**
     * Store filtered results with appropriate tier
     */
    public function storeFilteredProducts(array $filters, int $page, int $perPage, array $data): void
    {
        $cacheKey = $this->generateCacheKey($filters, $page, $perPage);

        // Determine tier based on filter complexity and popularity
        $tier = $this->determineTier($filters, $cacheKey);

        switch ($tier) {
            case 1:
                $this->storeTier1Cache($cacheKey, $data, self::TIER1_TTL);
                break;
            case 2:
                $this->storeTier2Cache($cacheKey, $data, self::TIER2_TTL);
                break;
            default:
                $this->storeTier3Cache($cacheKey, $data, self::TIER3_TTL);
        }

        // Also store in single-dimension indexes for faster lookup
        $this->storeSingleDimensionIndexes($filters, $page, $perPage, $data);
    }

    /**
     * Pre-fetch next pages if user is engaged
     */
    private function maybePreFetchPages(array $filters, int $currentPage, int $perPage): void
    {
        if (!$this->isUserEngaged($filters)) {
            return;
        }

        // Dispatch background job to pre-fetch next pages
        $startPage = $currentPage + 1;
        $endPage = $currentPage + self::PREFETCH_PAGES;

        dispatch(function() use ($filters, $startPage, $endPage, $perPage) {
            for ($page = $startPage; $page <= $endPage; $page++) {
                $cacheKey = $this->generateCacheKey($filters, $page, $perPage);

                // Skip if already cached
                if (Cache::has($cacheKey)) {
                    continue;
                }

                // Fetch and cache in background
                // Note: This would call your ProductFilterService
                Log::info("Pre-fetching page {$page} for filters", ['filters' => $filters]);
            }
        })->afterResponse();
    }

    /**
     * Check if user is engaged (viewing multiple pages)
     */
    private function isUserEngaged(array $filters): bool
    {
        $hash = $this->generateFilterHash($filters);
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $prefix = self::PREFIX_ENGAGEMENT;

        $sessionKey = "{$namespace}:{$version}:{$prefix}:views:{$hash}";

        // Increment page view counter (expires after 5 minutes)
        $views = Cache::increment($sessionKey, 1);
        Cache::put($sessionKey, $views, 300); // 5 min TTL

        return $views >= self::ENGAGEMENT_THRESHOLD;
    }

    /**
     * Generate cache key for filter combination
     * Format: ecommerce:v1:filters:products:{hash}:page:{page}:size:{perPage}
     */
    private function generateCacheKey(array $filters, int $page, int $perPage): string
    {
        $hash = $this->generateFilterHash($filters);
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $prefix = self::PREFIX_FILTERS;
        $type = self::PREFIX_PRODUCTS;

        return "{$namespace}:{$version}:{$prefix}:{$type}:{$hash}:page:{$page}:size:{$perPage}";
    }

    /**
     * Generate consistent hash for filters
     */
    private function generateFilterHash(array $filters): string
    {
        // Sort keys for consistency
        ksort($filters);
        array_walk_recursive($filters, function(&$item) {
            if (is_array($item)) {
                sort($item);
            }
        });

        return md5(json_encode($filters));
    }

    /**
     * Determine which tier to use based on filter popularity
     */
    private function determineTier(array $filters, string $cacheKey): int
    {
        // Single filter = Tier 2 (index-based)
        $filterCount = count(array_filter($filters));
        if ($filterCount <= 1) {
            return 2;
        }

        // Check if this combo is in hot list
        if ($this->isHotCombo($cacheKey)) {
            return 1;
        }

        // Complex/rare filters = Tier 3
        return 3;
    }

    /**
     * Check if filter combo is frequently accessed
     */
    private function isHotCombo(string $cacheKey): bool
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $analyticsKey = "{$namespace}:{$version}:" . self::PREFIX_HOT_COMBOS;

        $score = Redis::zscore($analyticsKey, $cacheKey);
        return $score !== null && $score > 10; // 10+ accesses
    }

    /**
     * Track cache hit
     */
    private function trackHit(string $tier, string $cacheKey): void
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $analyticsKey = "{$namespace}:{$version}:" . self::PREFIX_HOT_COMBOS;
        $metricsPrefix = "{$namespace}:{$version}:" . self::PREFIX_METRICS;

        // Increment access counter in sorted set
        Redis::zincrby($analyticsKey, 1, $cacheKey);

        // Keep only top N combos
        Redis::zremrangebyrank($analyticsKey, 0, -(self::MAX_HOT_COMBOS + 1));

        // Metrics
        Cache::increment("{$metricsPrefix}:cache_hit:{$tier}", 1);
    }

    /**
     * Track cache miss
     */
    private function trackMiss(string $cacheKey): void
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $metricsPrefix = "{$namespace}:{$version}:" . self::PREFIX_METRICS;

        Cache::increment("{$metricsPrefix}:cache_miss", 1);
    }

    /**
     * Promote frequently accessed combos to Tier 1
     */
    private function maybePromoteToTier1(string $cacheKey, array $data): void
    {
        if ($this->isHotCombo($cacheKey)) {
            $this->storeTier1Cache($cacheKey, $data, self::TIER1_TTL);
        }
    }

    /**
     * Store single-dimension indexes for faster lookup
     * Format: ecommerce:v1:indexes:{dimension}:{value}:page:{page}
     */
    private function storeSingleDimensionIndexes(array $filters, int $page, int $perPage, array $data): void
    {
        $dimensions = ['category_id', 'brand', 'price_range', 'rating'];
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $prefix = self::PREFIX_INDEXES;

        foreach ($dimensions as $dimension) {
            if (isset($filters[$dimension]) && !empty($filters[$dimension])) {
                $value = is_array($filters[$dimension]) ? implode(',', $filters[$dimension]) : $filters[$dimension];
                $indexKey = "{$namespace}:{$version}:{$prefix}:{$dimension}:{$value}:page:{$page}";

                Cache::put($indexKey, $data['product_ids'] ?? [], self::TIER2_TTL);
            }
        }
    }

    /**
     * Try to build result from single-dimension indexes
     */
    private function getTier2IndexedCache(array $filters, int $page, int $perPage): ?array
    {
        // This is a simplified version - in production, you'd intersect multiple indexes
        // For now, return null (Tier 2 cache miss)
        return null;
    }

    private function getTier1Cache(string $cacheKey): ?array
    {
        return Cache::get($cacheKey);
    }

    private function storeTier1Cache(string $cacheKey, array $data, int $ttl): void
    {
        Cache::put($cacheKey, $data, $ttl);
    }

    private function storeTier2Cache(string $cacheKey, array $data, int $ttl): void
    {
        Cache::put($cacheKey, $data, $ttl);
    }

    private function storeTier3Cache(string $cacheKey, array $data, int $ttl): void
    {
        Cache::put($cacheKey, $data, $ttl);
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $metricsPrefix = "{$namespace}:{$version}:" . self::PREFIX_METRICS;
        $analyticsKey = "{$namespace}:{$version}:" . self::PREFIX_HOT_COMBOS;

        return [
            'tier1_hits' => Cache::get("{$metricsPrefix}:cache_hit:tier1", 0),
            'tier2_hits' => Cache::get("{$metricsPrefix}:cache_hit:tier2", 0),
            'misses' => Cache::get("{$metricsPrefix}:cache_miss", 0),
            'hot_combos_count' => Redis::zcard($analyticsKey),
            'top_combos' => Redis::zrevrange($analyticsKey, 0, 9, 'WITHSCORES'),
        ];
    }

    /**
     * Warm up cache with popular filter combinations
     * Run this as a scheduled job during off-peak hours
     */
    public function warmUpHotCombos(array $popularCombos): void
    {
        foreach ($popularCombos as $combo) {
            // Fetch and cache first 3-5 pages
            for ($page = 1; $page <= 5; $page++) {
                $cacheKey = $this->generateCacheKey($combo['filters'], $page, 12);

                if (!Cache::has($cacheKey)) {
                    // Trigger fetch (would be handled by your controller/service)
                    Log::info("Warming up cache", [
                        'combo' => $combo,
                        'page' => $page
                    ]);
                }
            }
        }
    }

    /**
     * Clear all filter cache (useful for deployments)
     */
    public function clearAllFilterCache(): int
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $pattern = "{$namespace}:{$version}:*";

        $keys = Redis::keys($pattern);
        $count = 0;

        foreach ($keys as $key) {
            Redis::del($key);
            $count++;
        }

        Log::info("Cleared filter cache", ['keys_deleted' => $count]);
        return $count;
    }

    /**
     * Clear only product filter cache (keep metrics/analytics)
     */
    public function clearProductCache(): int
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;
        $prefix = self::PREFIX_FILTERS;
        $pattern = "{$namespace}:{$version}:{$prefix}:*";

        $keys = Redis::keys($pattern);
        $count = 0;

        foreach ($keys as $key) {
            Redis::del($key);
            $count++;
        }

        Log::info("Cleared product cache", ['keys_deleted' => $count]);
        return $count;
    }

    /**
     * Get cache structure info
     */
    public function getCacheStructure(): array
    {
        $namespace = self::CACHE_NAMESPACE;
        $version = self::CACHE_VERSION;

        return [
            'namespace' => $namespace,
            'version' => $version,
            'structure' => [
                'filters' => "{$namespace}:{$version}:" . self::PREFIX_FILTERS,
                'indexes' => "{$namespace}:{$version}:" . self::PREFIX_INDEXES,
                'metrics' => "{$namespace}:{$version}:" . self::PREFIX_METRICS,
                'engagement' => "{$namespace}:{$version}:" . self::PREFIX_ENGAGEMENT,
                'analytics' => "{$namespace}:{$version}:" . self::PREFIX_HOT_COMBOS,
            ],
            'example_keys' => [
                'filter' => "{$namespace}:{$version}:filters:products:{hash}:page:1:size:12",
                'index' => "{$namespace}:{$version}:indexes:category_id:5:page:1",
                'metric' => "{$namespace}:{$version}:metrics:cache_hit:tier1",
                'engagement' => "{$namespace}:{$version}:engagement:views:{hash}",
                'analytics' => "{$namespace}:{$version}:analytics:hot_combos",
            ]
        ];
    }
}
