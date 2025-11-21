<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Warmup Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cache warming for homepage and critical pages.
    | This ensures the first user gets blazing fast load times.
    |
    */

    /**
     * Master switch - enables/disables all auto warmup functionality
     */
    'enabled' => env('CACHE_WARMUP_ENABLED', true),

    /**
     * When to warm the cache:
     * 
     * - 'on_serve': Warm cache when `php artisan serve` is run (recommended for dev)
     * - 'on_boot': Warm cache on every application boot (use with caution)
     * - 'manual': Only warm cache via artisan command
     */
    'mode' => env('CACHE_WARMUP_MODE', 'on_serve'),

    /**
     * How to execute warmup:
     * 
     * - 'sync': Execute warmup immediately (blocks server start ~100-200ms)
     * - 'job': Dispatch to queue (non-blocking, requires queue worker)
     */
    'execution_mode' => env('CACHE_WARMUP_EXECUTION_MODE', 'sync'),

    /**
     * Force warmup even if cache appears warm
     */
    'force' => env('CACHE_WARMUP_FORCE', false),

    /**
     * Cache warmup timeout (seconds)
     */
    'timeout' => env('CACHE_WARMUP_TIMEOUT', 120),

    /**
     * What to warm up
     */
    'components' => [
        'settings' => env('CACHE_WARMUP_SETTINGS', true),
        'categories' => env('CACHE_WARMUP_CATEGORIES', true),
        'banners' => env('CACHE_WARMUP_BANNERS', true),
        'featured_products' => env('CACHE_WARMUP_FEATURED', true),
        'category_products' => env('CACHE_WARMUP_CATEGORY_PRODUCTS', true),
    ],

    /**
     * Warmup limits
     */
    'limits' => [
        'max_products' => env('CACHE_WARMUP_MAX_PRODUCTS', 60),
        'max_categories' => env('CACHE_WARMUP_MAX_CATEGORIES', 4),
        'max_banners' => env('CACHE_WARMUP_MAX_BANNERS', 5),
    ],

    /**
     * Logging configuration
     */
    'logging' => [
        'enabled' => env('CACHE_WARMUP_LOG_ENABLED', true),
        'verbose' => env('CACHE_WARMUP_LOG_VERBOSE', false),
        'console_output' => env('CACHE_WARMUP_CONSOLE_OUTPUT', true),
    ],

    /**
     * Performance tracking
     */
    'tracking' => [
        'enabled' => env('CACHE_WARMUP_TRACKING', true),
        'store_metrics' => env('CACHE_WARMUP_STORE_METRICS', false),
    ],

    /**
     * Artisan command name for manual warmup
     */
    'command' => 'cache:warmup',

];
