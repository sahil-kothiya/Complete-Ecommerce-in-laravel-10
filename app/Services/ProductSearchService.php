<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Collection;

class ProductSearchService
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Search products with fallback to database
     */
    public function search(string $query, int $perPage = 9, int $page = 1): array
    {
        $cacheKey = "search:{$query}:{$perPage}:{$page}";

        // Check Redis cache
        $cached = Redis::get($cacheKey);
        if ($cached) {
            return json_decode($cached, true);
        }

        // Try Elasticsearch first
        $elasticResults = $this->elasticsearch->searchProducts($query, $perPage * 5);

        if (!empty($elasticResults)) {
            $productIds = array_column(array_column($elasticResults, '_source'), 'id');
            $products = Product::whereIn('id', $productIds)
                ->with(['images', 'cat_info'])
                ->get()
                ->sortBy(function ($product) use ($productIds) {
                    return array_search($product->id, $productIds);
                });

            $result = [
                'products' => $products->take($perPage),
                'total' => count($elasticResults),
                'source' => 'elasticsearch'
            ];
        } else {
            // Fallback to database search
            $products = Product::where('title', 'ILIKE', "%{$query}%")
                ->where('status', 'active')
                ->with(['images', 'cat_info'])
                ->paginate($perPage, ['*'], 'page', $page);

            $result = [
                'products' => $products->items(),
                'total' => $products->total(),
                'source' => 'database'
            ];
        }

        // Cache for 2 minutes
        Redis::setex($cacheKey, 120, json_encode($result));

        return $result;
    }

    /**
     * Get autocomplete suggestions
     */
    public function getAutocomplete(string $query, int $limit = 10): array
    {
        if (strlen($query) < 2) {
            return [];
        }

        return $this->elasticsearch->getAutocompleteSuggestions($query, $limit);
    }
}
