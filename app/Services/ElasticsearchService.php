<?php

namespace App\Services;

use Elasticsearch\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ElasticsearchService
{
    private Client $client;
    private string $index;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->index = config('elasticsearch.index');
    }

    /**
     * Create index with proper mapping
     */
    public function createIndex(): bool
    {
        try {
            if ($this->client->indices()->exists(['index' => $this->index])) {
                return true;
            }

            $params = [
                'index' => $this->index,
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                        'analysis' => [
                            'analyzer' => [
                                'autocomplete' => [
                                    'tokenizer' => 'autocomplete',
                                    'filter' => ['lowercase']
                                ],
                                'autocomplete_search' => [
                                    'tokenizer' => 'keyword',
                                    'filter' => ['lowercase']
                                ]
                            ],
                            'tokenizer' => [
                                'autocomplete' => [
                                    'type' => 'edge_ngram',
                                    'min_gram' => 2,
                                    'max_gram' => 10,
                                    'token_chars' => ['letter', 'digit']
                                ]
                            ]
                        ]
                    ],
                    'mappings' => [
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => [
                                'type' => 'text',
                                'analyzer' => 'autocomplete',
                                'search_analyzer' => 'autocomplete_search',
                                'fields' => [
                                    'keyword' => ['type' => 'keyword'],
                                    'raw' => ['type' => 'text', 'analyzer' => 'standard']
                                ]
                            ],
                            'slug' => ['type' => 'keyword'],
                            'price' => ['type' => 'float'],
                            'discount' => ['type' => 'integer'],
                            'stock' => ['type' => 'integer'],
                            'status' => ['type' => 'keyword'],
                            'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss']
                        ]
                    ]
                ]
            ];

            $this->client->indices()->create($params);
            Log::info('Elasticsearch index created successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Elasticsearch index: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Search products with autocomplete
     */
    public function searchProducts(string $query, int $limit = 10): array
    {
        try {
            $params = [
                'index' => $this->index,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'multi_match' => [
                                        'query' => $query,
                                        'fields' => ['title^3', 'title.raw^2'],
                                        'type' => 'bool_prefix',
                                        'fuzziness' => 'AUTO'
                                    ]
                                ]
                            ],
                            'filter' => [
                                ['term' => ['status' => 'active']]
                            ]
                        ]
                    ],
                    'sort' => [
                        ['_score' => 'desc'],
                        ['created_at' => 'desc']
                    ],
                    'size' => $limit,
                    '_source' => ['id', 'title', 'slug', 'price', 'discount']
                ]
            ];

            $response = $this->client->search($params);
            return $response['hits']['hits'] ?? [];
        } catch (\Exception $e) {
            Log::error('Elasticsearch search failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get autocomplete suggestions
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = 'autocomplete:' . md5($query) . ':' . $limit;

        // Check Redis cache first
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        $results = $this->searchProducts($query, $limit);
        $suggestions = [];

        foreach ($results as $hit) {
            $source = $hit['_source'];
            $suggestions[] = [
                'id' => $source['id'],
                'title' => $source['title'],
                'slug' => $source['slug'],
                'price' => $source['price'],
                'discount' => $source['discount'] ?? 0
            ];
        }

        // Cache for 5 minutes
        Redis::setex($cacheKey, 300, json_encode($suggestions));

        return $suggestions;
    }

    /**
     * Index a single product
     */
    public function indexProduct(array $product): bool
    {
        try {
            $params = [
                'index' => $this->index,
                'id' => $product['id'],
                'body' => $product
            ];

            $this->client->index($params);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to index product: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Bulk index products
     */
    public function bulkIndexProducts(array $products): bool
    {
        try {
            $params = ['body' => []];

            foreach ($products as $product) {
                $params['body'][] = [
                    'index' => [
                        '_index' => $this->index,
                        '_id' => $product['id']
                    ]
                ];
                $params['body'][] = $product;
            }

            $this->client->bulk($params);
            return true;
        } catch (\Exception $e) {
            Log::error('Bulk indexing failed: ' . $e->getMessage());
            return false;
        }
    }
}
