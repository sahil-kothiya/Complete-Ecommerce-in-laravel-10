<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live) Configuration
    |--------------------------------------------------------------------------
    |
    | Define how long different types of data should be cached.
    | Times are in seconds.
    |
    */
    'ttl' => [
        'settings' => env('CACHE_TTL_SETTINGS', 86400), // 24 hours
        'categories' => env('CACHE_TTL_CATEGORIES', 43200), // 12 hours
        'banners' => env('CACHE_TTL_BANNERS', 21600), // 6 hours
        'product_lists' => env('CACHE_TTL_PRODUCTS', 3600), // 1 hour
        'wishlist' => env('CACHE_TTL_WISHLIST', 1800), // 30 minutes
        'cart' => env('CACHE_TTL_CART', 900), // 15 minutes
        'user_data' => env('CACHE_TTL_USER_DATA', 1800), // 30 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefixes for different types of cached data to avoid key collisions
    | and make cache management easier.
    |
    */
    'prefixes' => [
        'homepage' => 'cache:homepage:',
        'user' => 'user:',
        'product' => 'product:',
        'category' => 'category:',
        'search' => 'search:',
    ],

    /*
    |--------------------------------------------------------------------------
    | Large Data Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for handling large datasets that might exceed Redis limits.
    |
    */
    'large_data' => [
        'compression_threshold' => env('CACHE_COMPRESSION_THRESHOLD', 1024), // 1KB
        'max_size' => env('CACHE_MAX_SIZE', 67108864), // 64MB
        'chunk_size' => env('CACHE_CHUNK_SIZE', 16777216), // 16MB
        'enable_compression' => env('CACHE_ENABLE_COMPRESSION', true),
        'compression_level' => env('CACHE_COMPRESSION_LEVEL', 6), // 1-9
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize cache performance and memory usage.
    |
    */
    'performance' => [
        'batch_operations' => env('CACHE_BATCH_OPERATIONS', true),
        'pipeline_enabled' => env('CACHE_PIPELINE_ENABLED', true),
        'lazy_loading' => env('CACHE_LAZY_LOADING', true),
        'preload_critical' => env('CACHE_PRELOAD_CRITICAL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Product Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Special configuration for product caching since it tends to be large.
    |
    */
    'products' => [
        'limit_per_page' => env('CACHE_PRODUCTS_LIMIT', 60),
        'image_limit' => env('CACHE_PRODUCTS_IMAGE_LIMIT', 2),
        'chunk_size' => env('CACHE_PRODUCTS_CHUNK_SIZE', 20),
        'use_pagination' => env('CACHE_PRODUCTS_USE_PAGINATION', true),
        'large_dataset_threshold' => env('CACHE_PRODUCTS_LARGE_THRESHOLD', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Monitoring
    |--------------------------------------------------------------------------
    |
    | Settings for monitoring cache performance and health.
    |
    */
    'monitoring' => [
        'log_large_data' => env('CACHE_LOG_LARGE_DATA', true),
        'log_failures' => env('CACHE_LOG_FAILURES', true),
        'log_performance' => env('CACHE_LOG_PERFORMANCE', false),
        'performance_threshold_ms' => env('CACHE_PERFORMANCE_THRESHOLD', 1000), // 1 second
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-warming Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cache warming for critical data.
    |
    */
    'auto_warm' => [
        'enabled' => env('CACHE_AUTO_WARM_ENABLED', false),
        'schedule' => env('CACHE_AUTO_WARM_SCHEDULE', '0 */6 * * *'), // Every 6 hours
        'critical_caches' => [
            'settings',
            'categories',
            'banners',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Rules
    |--------------------------------------------------------------------------
    |
    | Define which caches should be invalidated when certain models are updated.
    |
    */
    'invalidation' => [
        'Product' => [
            'cache:homepage:product_lists',
            'cache:homepage:category_banners',
        ],
        'Category' => [
            'cache:homepage:categories',
            'cache:homepage:category_banners',
        ],
        'Banner' => [
            'cache:homepage:banners',
        ],
        'Settings' => [
            'cache:homepage:settings',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection Settings
    |--------------------------------------------------------------------------
    |
    | Specific Redis connection settings for caching.
    |
    */
    'redis' => [
        'connection' => env('CACHE_REDIS_CONNECTION', 'default'),
        'serializer' => env('CACHE_REDIS_SERIALIZER', 'php'), // php, igbinary, json
        'compression' => env('CACHE_REDIS_COMPRESSION', 'gzip'), // gzip, lz4, none
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | What to do when Redis is unavailable.
    |
    */
    'fallback' => [
        'use_file_cache' => env('CACHE_FALLBACK_FILE', true),
        'use_database' => env('CACHE_FALLBACK_DATABASE', true),
        'log_fallbacks' => env('CACHE_FALLBACK_LOG', true),
    ],
];
