<?php

return [
    'default' => env('ELASTICSEARCH_CONNECTION', 'default'),

    'connections' => [
        'default' => [
            'hosts' => [
                env('ELASTICSEARCH_HOST', 'localhost:9200'),
            ],
            'retries' => 2,
        ],
    ],

    'index' => env('ELASTICSEARCH_INDEX', 'ecommerce_products'),
];
