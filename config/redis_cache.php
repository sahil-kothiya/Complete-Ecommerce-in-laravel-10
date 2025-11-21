<?php

/**
 * Centralized Redis Cache Configuration
 *
 * This is the SINGLE SOURCE OF TRUTH for all Redis caching in the application.
 * All cache behaviors, TTLs, enable/disable flags, and optimizations are managed here.
 *
 * Designed for: 10M+ products with sub-1-second homepage load times
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Master Cache Control
    |--------------------------------------------------------------------------
    |
    | Global on/off switches for different cache systems.
    | Set to false to completely disable specific cache layers.
    |
    */
    'enabled' => [
        'master' => env('REDIS_CACHE_ENABLED', true),           // Master switch - kills all caching if false
        'homepage' => env('CACHE_HOMEPAGE_ENABLED', true),      // Homepage full page cache
        'products' => env('CACHE_PRODUCTS_ENABLED', true),      // Product entity caching
        'categories' => env('CACHE_CATEGORIES_ENABLED', true),  // Category caching
        'banners' => env('CACHE_BANNERS_ENABLED', true),        // Banner caching
        'settings' => env('CACHE_SETTINGS_ENABLED', true),      // Settings caching
        'search' => env('CACHE_SEARCH_ENABLED', true),          // Search results caching
        'filters' => env('CACHE_FILTERS_ENABLED', true),        // Product filter caching
        'wishlist' => env('CACHE_WISHLIST_ENABLED', true),      // Wishlist caching
        'cart' => env('CACHE_CART_ENABLED', true),              // Cart caching
        'user' => env('CACHE_USER_ENABLED', true),              // User data caching
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (Time To Live) in Seconds
    |--------------------------------------------------------------------------
    |
    | How long different data types should be cached.
    | Optimized for 10M+ product database with high traffic.
    |
    */
    'ttl' => [
        // Full Page Caches
        'homepage_full' => env('CACHE_TTL_HOMEPAGE_FULL', 1800),        // 30 minutes
        'category_page' => env('CACHE_TTL_CATEGORY_PAGE', 1800),        // 30 minutes
        'product_page' => env('CACHE_TTL_PRODUCT_PAGE', 3600),          // 1 hour

        // Component Caches
        'categories' => env('CACHE_TTL_CATEGORIES', 43200),             // 12 hours - rarely change
        'banners' => env('CACHE_TTL_BANNERS', 21600),                   // 6 hours
        'featured_products' => env('CACHE_TTL_FEATURED_PRODUCTS', 3600), // 1 hour
        'category_products' => env('CACHE_TTL_CATEGORY_PRODUCTS', 3600), // 1 hour
        'latest_products' => env('CACHE_TTL_LATEST_PRODUCTS', 1800),    // 30 minutes
        'bestsellers' => env('CACHE_TTL_BESTSELLERS', 3600),            // 1 hour

        // Entity Caches
        'product_card' => env('CACHE_TTL_PRODUCT_CARD', 7200),          // 2 hours - lightweight
        'product_full' => env('CACHE_TTL_PRODUCT_FULL', 3600),          // 1 hour - complete data
        'product_variant' => env('CACHE_TTL_PRODUCT_VARIANT', 3600),    // 1 hour
        'category' => env('CACHE_TTL_CATEGORY', 21600),                 // 6 hours
        'brand' => env('CACHE_TTL_BRAND', 21600),                       // 6 hours

        // Settings & Metadata
        'settings' => env('CACHE_TTL_SETTINGS', 86400),                 // 24 hours
        'cache_version' => env('CACHE_TTL_VERSION', 86400),             // 24 hours
        'metadata' => env('CACHE_TTL_METADATA', 43200),                 // 12 hours

        // Search & Filters
        'search_results' => env('CACHE_TTL_SEARCH', 1800),              // 30 minutes
        'filters' => env('CACHE_TTL_FILTERS', 3600),                    // 1 hour
        'filter_options' => env('CACHE_TTL_FILTER_OPTIONS', 21600),     // 6 hours

        // User-Specific Caches
        'wishlist' => env('CACHE_TTL_WISHLIST', 1800),                  // 30 minutes
        'cart' => env('CACHE_TTL_CART', 900),                           // 15 minutes
        'user_session' => env('CACHE_TTL_USER_SESSION', 7200),          // 2 hours

        // Collections & Aggregations
        'product_ids' => env('CACHE_TTL_PRODUCT_IDS', 3600),            // 1 hour - ID arrays
        'aggregates' => env('CACHE_TTL_AGGREGATES', 3600),              // 1 hour - counts, sums
        'statistics' => env('CACHE_TTL_STATISTICS', 21600),             // 6 hours
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefixes
    |--------------------------------------------------------------------------
    |
    | Prefixes for different cache types. Makes it easy to invalidate
    | entire sections and prevents key collisions.
    |
    */
    'prefixes' => [
        // Full Pages
        'homepage' => 'pages:home',
        'category_page' => 'pages:category',
        'product_page' => 'pages:product',

        // Entities
        'product' => 'entities:product',
        'product_card' => 'entities:product:card',
        'product_light' => 'entities:product:light',
        'product_variant' => 'entities:variant',
        'category' => 'entities:category',
        'banner' => 'entities:banner',
        'brand' => 'entities:brand',

        // Collections
        'collection' => 'collections',
        'featured' => 'collections:featured',
        'latest' => 'collections:latest',
        'bestseller' => 'collections:bestseller',

        // Components
        'component' => 'components',
        'menu' => 'components:menu',
        'footer' => 'components:footer',

        // Search & Filters (aligned with SmartFilterCacheService)
        'search' => 'search',
        'filter' => 'filters',

        // User Data
        'user' => 'users',
        'wishlist' => 'users:wishlist',
        'cart' => 'users:cart',

        // Metadata
        'meta' => 'meta',
        'lock' => 'locks',
        'version' => 'meta:version',

        // Settings
        'settings' => 'settings',
    ],

    /*
    |--------------------------------------------------------------------------
    | Encoding & Compression Settings
    |--------------------------------------------------------------------------
    |
    | Configure how data is serialized and compressed for maximum performance.
    | Options:
    |   - serialize: PHP native serialize (compatible, slower)
    |   - json: JSON encoding (readable, medium speed)
    |   - msgpack: MessagePack (fastest, requires msgpack extension)
    |   - igbinary: Igbinary (fast, requires igbinary extension)
    |
    */
    'encoding' => [
        'method' => env('REDIS_ENCODING_METHOD', 'serialize'),  // serialize|json|msgpack|igbinary
        'compress' => env('REDIS_COMPRESS_ENABLED', true),      // Enable compression
        'compress_threshold' => env('REDIS_COMPRESS_THRESHOLD', 1024),  // Compress if > 1KB
        'compress_level' => env('REDIS_COMPRESS_LEVEL', 6),     // 1 (fast) to 9 (best compression)
        'compress_method' => env('REDIS_COMPRESS_METHOD', 'gzip'), // gzip|lz4|zstd
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Optimization Settings
    |--------------------------------------------------------------------------
    |
    | Fine-tune Redis performance for 10M+ products.
    |
    */
    'performance' => [
        // Chunking for large datasets
        'enable_chunking' => env('REDIS_CHUNKING_ENABLED', true),
        'chunk_size' => env('REDIS_CHUNK_SIZE', 16777216),      // 16MB chunks
        'max_data_size' => env('REDIS_MAX_DATA_SIZE', 67108864), // 64MB max

        // Batch operations
        'pipeline_enabled' => env('REDIS_PIPELINE_ENABLED', true),
        'batch_size' => env('REDIS_BATCH_SIZE', 1000),          // Keys per batch

        // Cache stampede prevention
        'lock_enabled' => env('REDIS_LOCK_ENABLED', true),
        'lock_timeout' => env('REDIS_LOCK_TIMEOUT', 10),        // Seconds
        'lock_retry_delay' => env('REDIS_LOCK_RETRY_DELAY', 100), // Milliseconds

        // Memory management
        'lazy_load' => env('REDIS_LAZY_LOAD', true),            // Load related data on demand
        'preload_critical' => env('REDIS_PRELOAD_CRITICAL', true), // Preload homepage data

        // Query optimization
        'use_scan' => env('REDIS_USE_SCAN', true),              // Use SCAN instead of KEYS
        'scan_count' => env('REDIS_SCAN_COUNT', 1000),          // Keys per SCAN iteration
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Invalidation Strategy
    |--------------------------------------------------------------------------
    |
    | Define how caches should be invalidated when data changes.
    |
    */
    'invalidation' => [
        // Version-based invalidation (fastest - atomic)
        'use_versioning' => env('REDIS_USE_VERSIONING', true),
        'version_key' => 'meta:cache:version',

        // Dependency map - what caches to clear when entities change
        'dependencies' => [
            'product' => [
                'product:{id}',
                'product:card:{id}',
                'product:light:{id}',
                'product:slug:{slug}',
            ],
            'product_featured' => [
                'page:home:*',
                'component:featured:*',
            ],
            'category' => [
                'category:{id}',
                'category:slug:{slug}',
                'page:category:{id}:*',
            ],
            'banner' => [
                'banner:*',
                'component:banners:*',
            ],
            'settings' => [
                'settings:*',
                'page:home:*',
            ],
        ],

        // Auto-invalidation on model updates
        'auto_invalidate' => env('REDIS_AUTO_INVALIDATE', true),
        'invalidate_related' => env('REDIS_INVALIDATE_RELATED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Configuration
    |--------------------------------------------------------------------------
    |
    | Configure automatic cache warming for critical data.
    |
    */
    'warming' => [
        'enabled' => env('REDIS_WARMING_ENABLED', true),
        'auto_warm' => env('REDIS_AUTO_WARM', true),            // Auto-warm after invalidation
        'warm_on_boot' => env('REDIS_WARM_ON_BOOT', false),     // Warm on app boot

        // What to warm
        'warm_items' => [
            'homepage' => env('REDIS_WARM_HOMEPAGE', true),
            'featured_products' => env('REDIS_WARM_FEATURED', true),
            'categories' => env('REDIS_WARM_CATEGORIES', true),
            'top_products' => env('REDIS_WARM_TOP_PRODUCTS', 100), // Top N products
        ],

        // Warming schedule
        'schedule' => env('REDIS_WARM_SCHEDULE', 'hourly'),     // hourly|daily|manual
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Debugging
    |--------------------------------------------------------------------------
    |
    | Track cache performance and health.
    |
    */
    'monitoring' => [
        'enabled' => env('REDIS_MONITORING_ENABLED', true),
        'log_hits' => env('REDIS_LOG_HITS', false),             // Log cache hits (verbose)
        'log_misses' => env('REDIS_LOG_MISSES', true),          // Log cache misses
        'log_slow_operations' => env('REDIS_LOG_SLOW_OPS', true),
        'slow_threshold_ms' => env('REDIS_SLOW_THRESHOLD', 100), // Log if > 100ms

        // Performance tracking
        'track_hit_rate' => env('REDIS_TRACK_HIT_RATE', true),
        'track_memory' => env('REDIS_TRACK_MEMORY', true),
        'track_keys_count' => env('REDIS_TRACK_KEYS_COUNT', true),

        // Alerts
        'alert_on_failure' => env('REDIS_ALERT_ON_FAILURE', true),
        'alert_memory_threshold' => env('REDIS_ALERT_MEMORY_MB', 1024), // 1GB
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Strategy
    |--------------------------------------------------------------------------
    |
    | What to do when Redis is unavailable.
    |
    */
    'fallback' => [
        'enabled' => env('REDIS_FALLBACK_ENABLED', true),
        'use_database' => env('REDIS_FALLBACK_DB', true),       // Query DB directly
        'use_file_cache' => env('REDIS_FALLBACK_FILE', false),  // Use file cache
        'log_fallbacks' => env('REDIS_FALLBACK_LOG', true),
        'retry_attempts' => env('REDIS_FALLBACK_RETRY', 3),
        'retry_delay_ms' => env('REDIS_FALLBACK_DELAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Special settings for different data types.
    |
    */
    'data_settings' => [
        // Homepage optimization
        'homepage' => [
            'enabled' => env('CACHE_HOMEPAGE_ENABLED', true),
            'full_page' => env('CACHE_HOMEPAGE_FULL_PAGE', true),
            'components' => env('CACHE_HOMEPAGE_COMPONENTS', true),
            'max_products' => env('CACHE_HOMEPAGE_MAX_PRODUCTS', 50),
            'include_variants' => env('CACHE_HOMEPAGE_INCLUDE_VARIANTS', false),
        ],

        // Product caching
        'products' => [
            'cache_variants' => env('CACHE_PRODUCTS_VARIANTS', true),
            'cache_images' => env('CACHE_PRODUCTS_IMAGES', true),
            'cache_reviews' => env('CACHE_PRODUCTS_REVIEWS', false), // Too dynamic
            'lightweight_mode' => env('CACHE_PRODUCTS_LIGHTWEIGHT', true),
        ],

        // Search optimization
        'search' => [
            'cache_queries' => env('CACHE_SEARCH_QUERIES', true),
            'cache_filters' => env('CACHE_SEARCH_FILTERS', true),
            'max_cached_pages' => env('CACHE_SEARCH_MAX_PAGES', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Connection Settings
    |--------------------------------------------------------------------------
    |
    | Specific Redis connection optimizations.
    |
    */
    'connection' => [
        'read_timeout' => env('REDIS_READ_TIMEOUT', 2),         // Seconds
        'timeout' => env('REDIS_TIMEOUT', 2),                   // Seconds
        'retry_interval' => env('REDIS_RETRY_INTERVAL', 100),   // Milliseconds
        'persistent' => env('REDIS_PERSISTENT', false),         // Persistent connections
        'database' => env('REDIS_CACHE_DB', 0),                 // Redis DB index
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing
    |--------------------------------------------------------------------------
    |
    | Settings for development and testing environments.
    |
    */
    'development' => [
        'disable_in_testing' => env('REDIS_DISABLE_IN_TESTS', false),
        'clear_on_deploy' => env('REDIS_CLEAR_ON_DEPLOY', true),
        'verbose_logging' => env('REDIS_VERBOSE_LOG', false),
        'debug_keys' => env('REDIS_DEBUG_KEYS', false),         // Log all key operations
    ],
];
