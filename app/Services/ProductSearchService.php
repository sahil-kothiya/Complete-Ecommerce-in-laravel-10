<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductSearchService
{
    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        $this->elasticsearch = $elasticsearch;
    }

    /**
     * Validate and sanitize input
     */
    private function validateAndSanitizeInput($input, string $type = 'string'): array|string
    {
        if (is_null($input) || (is_array($input) && empty(array_filter($input))) || (is_string($input) && trim($input) === '')) {
            Log::debug('Empty input detected for filter', ['input' => $input, 'type' => $type]);
            return $type === 'array' ? [] : '';
        }
        if ($type === 'array' && is_string($input)) {
            $input = array_filter(explode(',', $input), 'strlen');
            return array_map('trim', $input);
        }
        if ($type === 'array' && is_array($input)) {
            return array_filter(array_map('trim', $input), 'strlen');
        }
        if ($type === 'string' && is_string($input)) {
            return trim($input);
        }
        Log::warning('Invalid input type for filter', ['input' => $input, 'type' => $type]);
        return $type === 'array' ? [] : '';
    }

    /**
     * Apply sorting to query
     */
    private function applySorting($query, ?string $sortBy): void
    {
        if ($sortBy === 'price-desc') {
            $query->orderBy('price', 'desc');
        } elseif ($sortBy === 'price-asc') {
            $query->orderBy('price', 'asc');
        }
    }

    /**
     * Search products with filters and fallback to database
     */
    public function search(Request $request, int $perPage = 9, int $page = 1): array
    {
        Log::debug('Search service called with params', ['request' => $request->all()]);

        $cacheKey = $this->generateCacheKey($request, $perPage, $page);

        try {
            $cached = Redis::get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
        } catch (\Exception $e) {
            Log::warning('Redis cache error: ' . $e->getMessage());
        }

        // Build query
        $query = Product::query()->where('status', 'active');

        // Apply filters with enhanced logging
        if ($request->has('min_rating')) {
            $ratings = $this->validateAndSanitizeInput($request->min_rating, 'array');
            if (!empty($ratings)) {
                Log::debug('Applying min_rating filter', ['ratings' => $ratings]);
                $query->whereHas('getReview', function ($q) use ($ratings) {
                    $q->select(DB::raw('avg(rate) as avg_rate'))
                      ->havingRaw('avg_rate >= ?', [min($ratings)]);
                });
            } else {
                Log::debug('Skipping min_rating filter - no values');
            }
        }

        if ($request->has('min_discount')) {
            $discounts = $this->validateAndSanitizeInput($request->min_discount, 'array');
            if (!empty($discounts)) {
                Log::debug('Applying min_discount filter', ['discounts' => $discounts]);
                $query->whereIn('discount', $discounts);
            } else {
                Log::debug('Skipping min_discount filter - no values');
            }
        }

        if ($request->has('brand')) {
            $brands = $this->validateAndSanitizeInput($request->brand, 'array');
            if (!empty($brands)) {
                Log::debug('Applying brand filter', ['brands' => $brands]);
                $brandIds = Brand::whereIn('slug', $brands)->pluck('id');
                if ($brandIds->isNotEmpty()) {
                    $query->whereIn('brand_id', $brandIds);
                } else {
                    Log::warning('No matching brands found', ['brands' => $brands]);
                    $query->whereRaw('1 = 0');  // Empty results if invalid
                }
            } else {
                Log::debug('Skipping brand filter - no values');
            }
        }

        if ($request->has('category')) {
            $categories = $this->validateAndSanitizeInput($request->category, 'array');
            if (!empty($categories)) {
                Log::debug('Applying category filter', ['categories' => $categories]);
                $categoryIds = Category::whereIn('slug', $categories)->pluck('id');
                if ($categoryIds->isNotEmpty()) {
                    $query->whereIn('cat_id', $categoryIds);
                } else {
                    Log::warning('No matching categories found', ['categories' => $categories]);
                    $query->whereRaw('1 = 0');  // Empty results if invalid
                }
            } else {
                Log::debug('Skipping category filter - no values');
            }
        }

        if ($request->has('price')) {
            $priceRange = $this->validateAndSanitizeInput($request->price, 'array');
            if (count($priceRange) === 2 && is_numeric($priceRange[0]) && is_numeric($priceRange[1])) {
                Log::debug('Applying price filter', ['range' => $priceRange]);
                $query->whereBetween('price', [(float)$priceRange[0], (float)$priceRange[1]]);
            } else {
                Log::debug('Skipping invalid price range');
            }
        }

        if ($request->has('query') && !empty($request->query)) {
            $searchQuery = $this->validateAndSanitizeInput($request->query, 'string');
            Log::debug('Applying title search', ['query' => $searchQuery]);
            $query->where('title', 'ILIKE', '%' . $searchQuery . '%');
        }

        // Try Elasticsearch first
        $elasticQuery = $this->validateAndSanitizeInput($request->query ?? '', 'string');
        try {
            $elasticResults = $this->elasticsearch->searchProducts($elasticQuery, $perPage * 5);
            if (!empty($elasticResults) && isset($elasticResults[0]['_source']['id'])) {
                $productIds = array_column(array_column($elasticResults, '_source'), 'id');
                Log::debug('Elasticsearch returned product IDs', ['count' => count($productIds)]);
                $query->whereIn('id', $productIds);
                $this->applySorting($query, $request->sortBy ?? '');
                $products = $query->with(['images', 'cat_info'])
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
                throw new \Exception('No valid Elasticsearch results');
            }
        } catch (\Exception $e) {
            Log::warning('Elasticsearch search failed: ' . $e->getMessage());
            // Fallback to database search
            $this->applySorting($query, $request->sortBy ?? '');
            $products = $query->with(['images', 'cat_info'])
                ->paginate($perPage, ['*'], 'page', $page);

            $result = [
                'products' => $products->items(),
                'total' => $products->total(),
                'source' => 'database'
            ];
        }

        try {
            Redis::setex($cacheKey, 600, json_encode($result));
        } catch (\Exception $e) {
            Log::warning('Failed to set Redis cache: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Generate cache key based on request parameters
     */
    private function generateCacheKey(Request $request, int $perPage, int $page): string
    {
        $params = [
            'query' => $this->validateAndSanitizeInput($request->query, 'string'),
            'min_rating' => $this->validateAndSanitizeInput($request->min_rating, 'array'),
            'min_discount' => $this->validateAndSanitizeInput($request->min_discount, 'array'),
            'brand' => $this->validateAndSanitizeInput($request->brand, 'array'),
            'category' => $this->validateAndSanitizeInput($request->category, 'array'),
            'price' => $this->validateAndSanitizeInput($request->price, 'array'),
            'sortBy' => $this->validateAndSanitizeInput($request->sortBy, 'string'),
            'perPage' => $perPage,
            'page' => $page
        ];
        return 'search:v2:' . hash('sha256', json_encode($params));
    }

    /**
     * Get autocomplete suggestions
     */
    public function getAutocomplete(string $query, int $limit = 10): array
    {
        $query = $this->validateAndSanitizeInput($query, 'string');
        if (strlen($query) < 2) {
            return [];
        }

        try {
            return $this->elasticsearch->getAutocompleteSuggestions($query, $limit);
        } catch (\Exception $e) {
            Log::warning('Elasticsearch autocomplete failed: ' . $e->getMessage());
            return [];
        }
    }
}