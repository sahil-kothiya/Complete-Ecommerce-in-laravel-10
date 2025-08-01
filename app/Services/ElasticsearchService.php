<?php

namespace App\Services;

use Elasticsearch\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use App\Helpers\RedisHelper;

class ElasticsearchService
{
    private Client $client;
    private string $index;
    private bool $isAvailable = true;
    private array $config;

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->index = config('elasticsearch.index');
        $this->config = config('product_queues', []);
        $this->checkElasticsearchHealth();
    }

    /**
     * Create index with proper mapping.
     */
    public function createIndex(): bool
    {
        try {
            if ($this->client->indices()->exists(['index' => $this->index])) {
                Log::info("Elasticsearch index '{$this->index}' already exists");
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
                            'summary' => [
                                'type' => 'text',
                                'analyzer' => 'standard'
                            ],
                            'price' => ['type' => 'float'],
                            'discount' => ['type' => 'integer'],
                            'stock' => ['type' => 'integer'],
                            'status' => ['type' => 'keyword'],
                            'condition' => ['type' => 'keyword'],
                            'cat_id' => ['type' => 'integer'],
                            'child_cat_id' => ['type' => 'integer'],
                            'brand_id' => ['type' => 'integer'],
                            'is_featured' => ['type' => 'boolean'],
                            'created_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss'],
                            'updated_at' => ['type' => 'date', 'format' => 'yyyy-MM-dd HH:mm:ss']
                        ]
                    ]
                ]
            ];

            $response = $this->client->indices()->create($params);
            Log::info('Elasticsearch index created successfully', ['index' => $this->index]);
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to create Elasticsearch index: ' . $e->getMessage());
            $this->isAvailable = false;
            return false;
        }
    }

    /**
     * Search products with enhanced error handling and fallbacks.
     */
    public function searchProducts(string $query, int $limit = 10, int $page = 1): array
    {
        if (!$this->isAvailable) {
            return $this->fallbackSearch($query, $limit, $page);
        }

        try {
            $from = ($page - 1) * $limit;

            $params = [
                'index' => $this->index,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'multi_match' => [
                                        'query' => $query,
                                        'fields' => ['title^3', 'title.raw^2', 'summary^1'],
                                        'type' => 'best_fields',
                                        'fuzziness' => 'AUTO',
                                        'prefix_length' => 1
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
                        ['is_featured' => 'desc'],
                        ['created_at' => 'desc']
                    ],
                    'from' => $from,
                    'size' => $limit,
                    '_source' => [
                        'id',
                        'title',
                        'slug',
                        'price',
                        'discount',
                        'stock',
                        'condition',
                        'cat_id',
                        'is_featured'
                    ]
                ]
            ];

            $response = $this->client->search($params);

            return [
                'products' => $this->transformSearchResults($response['hits']['hits'] ?? []),
                'total' => $response['hits']['total']['value'] ?? 0,
                'source' => 'elasticsearch'
            ];
        } catch (\Exception $e) {
            Log::error('Elasticsearch search failed: ' . $e->getMessage());
            $this->isAvailable = false;
            return $this->fallbackSearch($query, $limit, $page);
        }
    }

    /**
     * Get autocomplete suggestions with caching and fallbacks.
     */
    public function getAutocompleteSuggestions(string $query, int $limit = 10): array
    {
        $cacheKey = 'autocomplete:' . md5($query) . ':' . $limit;

        // Check Redis cache first
        $cached = RedisHelper::get($cacheKey);
        if ($cached) {
            return $cached;
        }

        if (!$this->isAvailable) {
            return $this->fallbackAutocomplete($query, $limit);
        }

        try {
            $params = [
                'index' => $this->index,
                'body' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'match' => [
                                        'title' => [
                                            'query' => $query,
                                            'operator' => 'and'
                                        ]
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
                        ['is_featured' => 'desc']
                    ],
                    'size' => $limit,
                    '_source' => ['id', 'title', 'slug', 'price', 'discount']
                ]
            ];

            $response = $this->client->search($params);
            $suggestions = [];

            foreach ($response['hits']['hits'] ?? [] as $hit) {
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
            RedisHelper::put($cacheKey, $suggestions, 300);
            return $suggestions;
        } catch (\Exception $e) {
            Log::error('Elasticsearch autocomplete failed: ' . $e->getMessage());
            $this->isAvailable = false;
            return $this->fallbackAutocomplete($query, $limit);
        }
    }

    /**
     * Index a single product with enhanced error handling.
     */
    public function indexProduct(array $product): bool
    {
        if (!$this->isAvailable) {
            Log::warning("Elasticsearch not available, skipping product indexing for product {$product['id']}");
            return false;
        }

        try {
            // Validate required fields
            if (!isset($product['id']) || !isset($product['status'])) {
                Log::error("Invalid product data for indexing", $product);
                return false;
            }

            // Only index active products
            if ($product['status'] !== 'active') {
                Log::info("Skipping indexing of inactive product {$product['id']}");
                return $this->removeProduct($product['id']);
            }

            $params = [
                'index' => $this->index,
                'id' => $product['id'],
                'body' => $this->prepareProductForIndexing($product)
            ];

            $response = $this->client->index($params);

            if (isset($response['result']) && in_array($response['result'], ['created', 'updated'])) {
                Log::info("Successfully indexed product {$product['id']} in Elasticsearch");
                return true;
            } else {
                Log::warning("Unexpected response when indexing product {$product['id']}", $response);
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Failed to index product {$product['id']}: " . $e->getMessage());
            $this->handleIndexingError($e);
            return false;
        }
    }

    /**
     * Remove a product from the index.
     */
    public function removeProduct(int|string $productId): bool
    {
        if (!$this->isAvailable) {
            Log::warning("Elasticsearch not available, skipping product removal for product {$productId}");
            return false;
        }

        try {
            $response = $this->client->delete([
                'index' => $this->index,
                'id' => $productId
            ]);

            if (isset($response['result']) && $response['result'] === 'deleted') {
                Log::info("Successfully removed product {$productId} from Elasticsearch");
                return true;
            } elseif (isset($response['result']) && $response['result'] === 'not_found') {
                Log::info("Product {$productId} not found in Elasticsearch (already removed)");
                return true; // Consider this success
            }

            return false;
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            Log::info("Product {$productId} not found in Elasticsearch index");
            return true; // Product already doesn't exist
        } catch (\Exception $e) {
            Log::error("Failed to remove product {$productId}: " . $e->getMessage());
            $this->handleIndexingError($e);
            return false;
        }
    }

    /**
     * Bulk index products with better error handling.
     */
    public function bulkIndexProducts(array $products): bool
    {
        if (!$this->isAvailable || empty($products)) {
            return false;
        }

        try {
            $params = ['body' => []];
            $productIds = [];

            foreach ($products as $product) {
                if (!isset($product['id']) || $product['status'] !== 'active') {
                    continue;
                }

                $productIds[] = $product['id'];

                $params['body'][] = [
                    'index' => [
                        '_index' => $this->index,
                        '_id' => $product['id']
                    ]
                ];
                $params['body'][] = $this->prepareProductForIndexing($product);
            }

            if (empty($params['body'])) {
                Log::info("No valid products to bulk index");
                return true;
            }

            $response = $this->client->bulk($params);

            // Check for errors in bulk response
            if ($response['errors']) {
                $this->handleBulkIndexingErrors($response['items'], $productIds);
                return false;
            }

            Log::info("Successfully bulk indexed " . count($productIds) . " products");
            return true;
        } catch (\Exception $e) {
            Log::error('Bulk indexing failed: ' . $e->getMessage());
            $this->handleIndexingError($e);
            return false;
        }
    }

    /**
     * Check Elasticsearch health.
     */
    public function checkElasticsearchHealth(): bool
    {
        try {
            $response = $this->client->cluster()->health();
            $this->isAvailable = isset($response['status']) &&
                in_array($response['status'], ['green', 'yellow']);

            if (!$this->isAvailable) {
                Log::warning("Elasticsearch cluster health is poor", $response);
            }
        } catch (\Exception $e) {
            Log::error("Elasticsearch health check failed: " . $e->getMessage());
            $this->isAvailable = false;
        }

        return $this->isAvailable;
    }

    /**
     * Get index statistics.
     */
    public function getIndexStats(): array
    {
        try {
            if (!$this->isAvailable) {
                return ['error' => 'Elasticsearch not available'];
            }

            $stats = $this->client->indices()->stats(['index' => $this->index]);
            $indexStats = $stats['indices'][$this->index] ?? [];

            return [
                'document_count' => $indexStats['total']['docs']['count'] ?? 0,
                'store_size' => $indexStats['total']['store']['size_in_bytes'] ?? 0,
                'store_size_human' => $this->formatBytes($indexStats['total']['store']['size_in_bytes'] ?? 0),
                'health' => $this->isAvailable ? 'available' : 'unavailable',
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get index stats: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Prepare product data for indexing.
     */
    private function prepareProductForIndexing(array $product): array
    {
        return [
            'id' => $product['id'],
            'title' => $product['title'] ?? '',
            'slug' => $product['slug'] ?? '',
            'summary' => $product['summary'] ?? '',
            'price' => (float)($product['price'] ?? 0),
            'discount' => (int)($product['discount'] ?? 0),
            'stock' => (int)($product['stock'] ?? 0),
            'status' => $product['status'] ?? 'inactive',
            'condition' => $product['condition'] ?? 'default',
            'cat_id' => $product['cat_id'] ?? null,
            'child_cat_id' => $product['child_cat_id'] ?? null,
            'brand_id' => $product['brand_id'] ?? null,
            'is_featured' => (bool)($product['is_featured'] ?? false),
            'created_at' => $product['created_at'] ?? now()->format('Y-m-d H:i:s'),
            'updated_at' => $product['updated_at'] ?? now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Transform search results to consistent format.
     */
    private function transformSearchResults(array $hits): array
    {
        $products = [];

        foreach ($hits as $hit) {
            $source = $hit['_source'];
            $products[] = (object) [
                'id' => $source['id'],
                'title' => $source['title'],
                'slug' => $source['slug'],
                'price' => $source['price'],
                'discount' => $source['discount'] ?? 0,
                'stock' => $source['stock'] ?? 0,
                'condition' => $source['condition'] ?? 'default',
                'cat_id' => $source['cat_id'] ?? null,
                'is_featured' => $source['is_featured'] ?? false,
                'score' => $hit['_score'] ?? 0,
            ];
        }

        return $products;
    }

    /**
     * Fallback search using database when Elasticsearch is unavailable.
     */
    private function fallbackSearch(string $query, int $limit, int $page): array
    {
        Log::info("Using database fallback search for query: {$query}");

        try {
            $products = \App\Models\Product::where('status', 'active')
                ->where('title', 'ILIKE', "%{$query}%")
                ->orderByDesc('is_featured')
                ->orderByDesc('id')
                ->offset(($page - 1) * $limit)
                ->limit($limit)
                ->get(['id', 'title', 'slug', 'price', 'discount', 'stock', 'condition', 'cat_id', 'is_featured']);

            $total = \App\Models\Product::where('status', 'active')
                ->where('title', 'ILIKE', "%{$query}%")
                ->count();

            return [
                'products' => $products->toArray(),
                'total' => $total,
                'source' => 'database_fallback'
            ];
        } catch (\Exception $e) {
            Log::error("Database fallback search failed: " . $e->getMessage());
            return [
                'products' => [],
                'total' => 0,
                'source' => 'fallback_failed'
            ];
        }
    }

    /**
     * Fallback autocomplete using database.
     */
    private function fallbackAutocomplete(string $query, int $limit): array
    {
        try {
            $products = \App\Models\Product::where('status', 'active')
                ->where('title', 'ILIKE', "%{$query}%")
                ->orderByDesc('is_featured')
                ->limit($limit)
                ->get(['id', 'title', 'slug', 'price', 'discount']);

            return $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'discount' => $product->discount ?? 0
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::error("Database fallback autocomplete failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Handle indexing errors.
     */
    private function handleIndexingError(\Exception $e): void
    {
        // If connection error, mark as unavailable
        if (
            strpos($e->getMessage(), 'Connection') !== false ||
            strpos($e->getMessage(), 'cURL error') !== false
        ) {
            $this->isAvailable = false;
            Log::warning("Marking Elasticsearch as unavailable due to connection error");
        }
    }

    /**
     * Handle bulk indexing errors.
     */
    private function handleBulkIndexingErrors(array $items, array $productIds): void
    {
        $errorCount = 0;

        foreach ($items as $index => $item) {
            if (isset($item['index']['error'])) {
                $productId = $productIds[$index] ?? 'unknown';
                $error = $item['index']['error'];
                Log::error("Bulk indexing error for product {$productId}: " . json_encode($error));
                $errorCount++;
            }
        }

        Log::error("Bulk indexing completed with {$errorCount} errors out of " . count($items) . " items");
    }

    /**
     * Format bytes to human readable format.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Check if Elasticsearch is available.
     */
    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    /**
     * Force availability check and update status.
     */
    public function refreshAvailability(): bool
    {
        return $this->checkElasticsearchHealth();
    }
}
