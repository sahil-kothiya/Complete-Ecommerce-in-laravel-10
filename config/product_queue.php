<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Product Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for product-related queue operations including 
    | Elasticsearch indexing and cache management.
    |
    */

    'queues' => [
        'search' => [
            'connection' => env('PRODUCT_SEARCH_QUEUE_CONNECTION', 'redis'),
            'queue' => 'search',
            'workers' => env('PRODUCT_SEARCH_WORKERS', 3),
            'timeout' => env('PRODUCT_SEARCH_TIMEOUT', 60),
            'tries' => env('PRODUCT_SEARCH_TRIES', 3),
            'backoff' => env('PRODUCT_SEARCH_BACKOFF', 10),
            'max_jobs' => env('PRODUCT_SEARCH_MAX_JOBS', 100),
            'max_time' => env('PRODUCT_SEARCH_MAX_TIME', 3600),
        ],

        'cache' => [
            'connection' => env('PRODUCT_CACHE_QUEUE_CONNECTION', 'redis'),
            'queue' => 'cache',
            'workers' => env('PRODUCT_CACHE_WORKERS', 3),
            'timeout' => env('PRODUCT_CACHE_TIMEOUT', 120),
            'tries' => env('PRODUCT_CACHE_TRIES', 2),
            'backoff' => env('PRODUCT_CACHE_BACKOFF', 5),
            'max_jobs' => env('PRODUCT_CACHE_MAX_JOBS', 200),
            'max_time' => env('PRODUCT_CACHE_MAX_TIME', 3600),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Delays
    |--------------------------------------------------------------------------
    |
    | Delays to prevent overwhelming the system with simultaneous operations.
    |
    */
    'delays' => [
        'search_index' => [
            'created' => env('PRODUCT_INDEX_DELAY_CREATED', 5), // seconds
            'updated' => env('PRODUCT_INDEX_DELAY_UPDATED', 3),
            'restored' => env('PRODUCT_INDEX_DELAY_RESTORED', 5),
        ],

        'search_remove' => [
            'deleted' => env('PRODUCT_REMOVE_DELAY_DELETED', 1),
            'force_deleted' => env('PRODUCT_REMOVE_DELAY_FORCE_DELETED', 0),
        ],

        'cache_clear' => [
            'created' => env('PRODUCT_CACHE_DELAY_CREATED', 2),
            'updated' => env('PRODUCT_CACHE_DELAY_UPDATED', 1),
            'deleted' => env('PRODUCT_CACHE_DELAY_DELETED', 1),
            'restored' => env('PRODUCT_CACHE_DELAY_RESTORED', 2),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Strategy
    |--------------------------------------------------------------------------
    |
    | Configuration for which caches to clear based on product changes.
    |
    */
    'cache_strategy' => [
        'immediate_clear' => [
            // Caches that should be cleared immediately (synchronously)
            'individual_product',
            'homepage_critical',
        ],

        'queued_clear' => [
            // Caches that can be cleared via queue (asynchronously)
            'homepage_extended',
            'category_pages',
            'product_grids',
            'search_autocomplete',
            'pagination',
        ],

        'significant_fields' => [
            // Fields that require extensive cache clearing
            'title',
            'slug',
            'price',
            'discount',
            'status',
            'stock',
            'condition',
            'cat_id',
            'child_cat_id',
            'brand_id',
            'is_featured'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    |
    | Settings to optimize queue performance and prevent system overload.
    |
    */
    'performance' => [
        'batch_size' => env('PRODUCT_QUEUE_BATCH_SIZE', 50),
        'parallel_processing' => env('PRODUCT_QUEUE_PARALLEL', true),
        'throttle_requests' => env('PRODUCT_QUEUE_THROTTLE', true),
        'rate_limit_per_minute' => env('PRODUCT_QUEUE_RATE_LIMIT', 120),

        // Auto-scaling settings
        'auto_scale_workers' => env('PRODUCT_QUEUE_AUTO_SCALE', false),
        'scale_up_threshold' => env('PRODUCT_QUEUE_SCALE_UP_THRESHOLD', 10),
        'scale_down_threshold' => env('PRODUCT_QUEUE_SCALE_DOWN_THRESHOLD', 2),
        'max_workers' => env('PRODUCT_QUEUE_MAX_WORKERS', 10),
        'min_workers' => env('PRODUCT_QUEUE_MIN_WORKERS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Alerting
    |--------------------------------------------------------------------------
    |
    | Configuration for monitoring queue health and performance.
    |
    */
    'monitoring' => [
        'enabled' => env('PRODUCT_QUEUE_MONITORING', true),
        'log_performance' => env('PRODUCT_QUEUE_LOG_PERFORMANCE', true),
        'alert_thresholds' => [
            'queue_size_warning' => env('PRODUCT_QUEUE_SIZE_WARNING', 20),
            'queue_size_critical' => env('PRODUCT_QUEUE_SIZE_CRITICAL', 50),
            'failed_jobs_warning' => env('PRODUCT_QUEUE_FAILED_WARNING', 5),
            'job_timeout_warning' => env('PRODUCT_QUEUE_TIMEOUT_WARNING', 300), // 5 minutes
        ],

        'notifications' => [
            'slack_webhook' => env('PRODUCT_QUEUE_SLACK_WEBHOOK'),
            'email_alerts' => env('PRODUCT_QUEUE_EMAIL_ALERTS', 'admin@example.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failure Handling
    |--------------------------------------------------------------------------
    |
    | Configuration for handling failed jobs and retry strategies.
    |
    */
    'failure_handling' => [
        'auto_retry_failed' => env('PRODUCT_QUEUE_AUTO_RETRY', true),
        'retry_delay' => env('PRODUCT_QUEUE_RETRY_DELAY', 300), // 5 minutes
        'max_retries' => env('PRODUCT_QUEUE_MAX_RETRIES', 3),

        'fallback_strategies' => [
            'search' => [
                'use_database_search' => true,
                'disable_autocomplete' => false,
            ],
            'cache' => [
                'skip_cache_clearing' => false,
                'use_lazy_clearing' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development Settings
    |--------------------------------------------------------------------------
    |
    | Settings specific to development environment.
    |
    */
    'development' => [
        'sync_in_testing' => env('PRODUCT_QUEUE_SYNC_TESTING', true),
        'fake_elasticsearch' => env('PRODUCT_QUEUE_FAKE_ELASTICSEARCH', false),
        'disable_delays' => env('PRODUCT_QUEUE_DISABLE_DELAYS', false),
        'verbose_logging' => env('PRODUCT_QUEUE_VERBOSE_LOGGING', true),
    ],
];
