<?php

namespace App\Services;

/**
 * Redis Key Manager - Single Source of Truth
 *
 * Centralized Redis key naming convention to prevent conflicts and duplicates.
 * All services MUST use this to generate Redis keys.
 *
 * Key Structure: {namespace}:{module}:{type}:{identifier}:{suffix}
 * Example: ecom:filter:meta:electronics
 * Example: ecom:index:category:5
 * Example: ecom:cache:product:123:full
 */
class RedisKeyManager
{
    // Global namespace - SHORT to save memory
    private const NAMESPACE = 'ecom';
    
    // Modules
    private const MODULE_FILTER = 'filter';
    private const MODULE_INDEX = 'index';
    private const MODULE_CACHE = 'cache';
    private const MODULE_TEMP = 'temp';
    private const MODULE_SETTINGS = 'settings';
    private const MODULE_METRICS = 'metrics';
    
    /**
     * Build a standardized Redis key
     * @param string $module One of: filter, index, cache, temp, settings, metrics
     * @param string $type Type of data: meta, results, category, brand, product, etc.
     * @param string|array $identifier Unique identifier(s)
     * @param string|null $suffix Optional suffix for variants
     * @return string Properly formatted Redis key
     */
    public static function make(string $module, string $type, $identifier = null, ?string $suffix = null): string
    {
        $parts = [self::NAMESPACE, $module, $type];
        
        if ($identifier !== null) {
            if (is_array($identifier)) {
                $parts[] = implode(':', $identifier);
            } else {
                $parts[] = $identifier;
            }
        }
        
        if ($suffix !== null) {
            $parts[] = $suffix;
        }
        
        return implode(':', $parts);
    }
    
    /**
     * Filter module keys
     */
    public static function filterMeta(string $categorySlug = 'all'): string
    {
        return self::make(self::MODULE_FILTER, 'meta', $categorySlug);
    }
    
    public static function filterResults(string $hash): string
    {
        return self::make(self::MODULE_FILTER, 'results', $hash);
    }
    
    public static function filterPriceIndex(string $categorySlug = 'all'): string
    {
        return self::make(self::MODULE_FILTER, 'price_idx', $categorySlug);
    }
    
    /**
     * Index module keys (for ProductIndexService)
     */
    public static function indexCategory(int $categoryId): string
    {
        return self::make(self::MODULE_INDEX, 'cat', $categoryId);
    }
    
    public static function indexBrand(int $brandId): string
    {
        return self::make(self::MODULE_INDEX, 'brand', $brandId);
    }
    
    public static function indexPrice(string $range): string
    {
        return self::make(self::MODULE_INDEX, 'price', $range);
    }
    
    public static function indexRating(int $rating): string
    {
        return self::make(self::MODULE_INDEX, 'rating', $rating);
    }
    
    public static function indexDiscount(int $discount): string
    {
        return self::make(self::MODULE_INDEX, 'discount', $discount);
    }
    
    /**
     * Cache module keys
     */
    public static function cacheProduct(int $productId, string $variant = 'full'): string
    {
        return self::make(self::MODULE_CACHE, 'product', $productId, $variant);
    }
    
    public static function cacheCategory(int $categoryId): string
    {
        return self::make(self::MODULE_CACHE, 'category', $categoryId);
    }
    
    public static function cacheSettings(string $type = 'global'): string
    {
        return self::make(self::MODULE_SETTINGS, $type);
    }
    
    public static function cacheHomepage(string $section = 'full'): string
    {
        return self::make(self::MODULE_CACHE, 'homepage', $section);
    }
    
    /**
     * Temp module keys (with auto-expire)
     */
    public static function tempFilter(string $hash): string
    {
        return self::make(self::MODULE_TEMP, 'filter', $hash);
    }
    
    public static function tempUnion(string $type, array $ids): string
    {
        $hash = md5(implode(',', $ids));
        return self::make(self::MODULE_TEMP, "union_{$type}", $hash);
    }
    
    /**
     * Metrics module keys
     */
    public static function metricsHit(string $tier): string
    {
        return self::make(self::MODULE_METRICS, 'hit', $tier);
    }
    
    public static function metricsMiss(): string
    {
        return self::make(self::MODULE_METRICS, 'miss');
    }
    
    /**
     * Get all keys for a module (for bulk operations)
     */
    public static function pattern(string $module, ?string $type = null): string
    {
        if ($type) {
            return self::NAMESPACE . ":{$module}:{$type}:*";
        }
        return self::NAMESPACE . ":{$module}:*";
    }
    
    /**
     * Validate if a key follows the standard
     */
    public static function isValid(string $key): bool
    {
        return str_starts_with($key, self::NAMESPACE . ':');
    }
    
    /**
     * Parse a key into components
     */
    public static function parse(string $key): ?array
    {
        if (!self::isValid($key)) {
            return null;
        }
        
        $parts = explode(':', $key);
        
        return [
            'namespace' => $parts[0] ?? null,
            'module' => $parts[1] ?? null,
            'type' => $parts[2] ?? null,
            'identifier' => $parts[3] ?? null,
            'suffix' => $parts[4] ?? null,
        ];
    }
    
    /**
     * Get namespace
     */
    public static function namespace(): string
    {
        return self::NAMESPACE;
    }
    
    /**
     * Clean old/invalid keys (migration helper)
     */
    public static function getOldPatterns(): array
    {
        return [
            'ecommerce:v1:*',      // Old verbose namespace
            'uf_*',                 // Old UltraFast keys
            'cache:homepage:*',     // Old unnamespaced cache
            'response_cache:*',     // Old response cache
        ];
    }
}
