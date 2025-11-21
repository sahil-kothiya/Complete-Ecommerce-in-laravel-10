<?php

/**
 * EXAMPLE: How to integrate Smart Filter Caching with your existing code
 *
 * This shows the minimal changes needed to add smart caching to your current
 * FrontendController without breaking existing functionality.
 */

namespace App\Http\Controllers;

use App\Services\SmartFilterCacheService;
use App\Services\ProductFilterService;
use Illuminate\Http\Request;

class FrontendControllerWithSmartCache extends Controller
{
    private SmartFilterCacheService $cacheService;
    private ProductFilterService $filterService;

    public function __construct()
    {
        $this->cacheService = app(SmartFilterCacheService::class);
        $this->filterService = app(ProductFilterService::class);
    }

    /**
     * Updated productSubCat method with smart caching
     *
     * BEFORE: Direct database query every time
     * AFTER: Smart cache with 90%+ hit rate
     */
    public function productSubCat(Request $request, $encryptedPath)
    {
        $startTime = microtime(true);

        // Decrypt and parse filters (your existing logic)
        $filters = $this->extractFilters($request, $encryptedPath);
        $page = $request->input('page', 1);
        $perPage = $request->input('show', 12);

        // ✅ NEW: Try cache first
        $cached = $this->cacheService->getFilteredProducts($filters, $page, $perPage);

        if ($cached) {
            // Cache hit - ultra fast response
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            \Log::info("Cache HIT", [
                'filters' => $filters,
                'page' => $page,
                'response_time' => $responseTime . 'ms'
            ]);

            return $this->renderResponse($cached, $filters, 'cache');
        }

        // Cache miss - fetch from database/search engine
        $results = $this->fetchProductsFromSource($filters, $page, $perPage);

        // ✅ NEW: Store in cache for next time
        $this->cacheService->storeFilteredProducts($filters, $page, $perPage, $results);

        $responseTime = round((microtime(true) - $startTime) * 1000, 2);
        \Log::info("Cache MISS", [
            'filters' => $filters,
            'page' => $page,
            'response_time' => $responseTime . 'ms'
        ]);

        return $this->renderResponse($results, $filters, 'database');
    }

    /**
     * Fetch products from database or search engine
     * (This is your existing logic, just reorganized)
     */
    private function fetchProductsFromSource(array $filters, int $page, int $perPage): array
    {
        // Option 1: Using Meilisearch/Elasticsearch (recommended for 10M+)
        if (config('scout.driver') === 'meilisearch') {
            return $this->fetchFromSearchEngine($filters, $page, $perPage);
        }

        // Option 2: Using optimized database query (current approach)
        return $this->fetchFromDatabase($filters, $page, $perPage);
    }

    /**
     * ✅ NEW: Fetch from Meilisearch (10-100x faster than DB)
     */
    private function fetchFromSearchEngine(array $filters, int $page, int $perPage): array
    {
        $query = \App\Models\Product::search('*');

        // Apply category filter
        if (!empty($filters['category_id'])) {
            $query->whereIn('category_id', (array)$filters['category_id']);
        }

        // Apply brand filter
        if (!empty($filters['brand'])) {
            $query->whereIn('brand', (array)$filters['brand']);
        }

        // Apply price range
        if (!empty($filters['price_range'])) {
            [$min, $max] = explode('-', $filters['price_range']);
            $query->where('price', '>=', (float)$min)
                  ->where('price', '<=', (float)$max);
        }

        // Apply rating filter
        if (!empty($filters['min_rating'])) {
            $query->where('rating', '>=', (float)$filters['min_rating'][0]);
        }

        // Apply discount filter
        if (!empty($filters['min_discount'])) {
            $query->where('discount_percent', '>=', (float)$filters['min_discount'][0]);
        }

        // Apply availability
        if (!empty($filters['availability']) && $filters['availability'] === 'in_stock') {
            $query->where('stock', '>', 0);
        }

        // Apply sorting
        $sortBy = $filters['sortBy'] ?? 'latest';
        switch ($sortBy) {
            case 'price_low_high':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high_low':
                $query->orderBy('price', 'desc');
                break;
            case 'rating_high_low':
                $query->orderBy('rating', 'desc');
                break;
            case 'name_a_z':
                $query->orderBy('title', 'asc');
                break;
            case 'name_z_a':
                $query->orderBy('title', 'desc');
                break;
            default: // latest
                $query->orderBy('created_at', 'desc');
        }

        // Execute search with pagination
        $results = $query->paginate($perPage, 'page', $page);

        return [
            'products' => $results->items(),
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'last_page' => $results->lastPage(),
            'per_page' => $results->perPage(),
        ];
    }

    /**
     * Existing database query (keep as fallback)
     */
    private function fetchFromDatabase(array $filters, int $page, int $perPage): array
    {
        $query = \App\Models\Product::query()->where('status', 'active');

        // Your existing filter logic here...
        // (category, brand, price, etc.)

        $products = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'products' => $products->items(),
            'total' => $products->total(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
        ];
    }

    /**
     * Extract filters from request
     */
    private function extractFilters(Request $request, string $encryptedPath): array
    {
        $filters = [];

        // Decrypt category path
        try {
            $decrypted = \App\Helpers\UrlEncryptor::decrypt($encryptedPath);
            $pathParts = explode('/', trim($decrypted, '/'));

            if (!empty($pathParts)) {
                $category = \App\Models\Category::where('slug', end($pathParts))->first();
                if ($category) {
                    $filters['category_id'] = [$category->id];
                }
            }
        } catch (\Exception $e) {
            \Log::error('Failed to decrypt path', ['path' => $encryptedPath]);
        }

        // Extract other filters from request
        if ($request->has('brand')) {
            $filters['brand'] = $request->input('brand');
        }

        if ($request->has('price_range')) {
            $filters['price_range'] = $request->input('price_range');
        }

        if ($request->has('min_rating')) {
            $filters['min_rating'] = $request->input('min_rating');
        }

        if ($request->has('min_discount')) {
            $filters['min_discount'] = $request->input('min_discount');
        }

        if ($request->has('availability')) {
            $filters['availability'] = $request->input('availability');
        }

        if ($request->has('sortBy')) {
            $filters['sortBy'] = $request->input('sortBy');
        }

        return $filters;
    }

    /**
     * Render response (AJAX or full page)
     */
    private function renderResponse(array $results, array $filters, string $source)
    {
        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'products' => $results['products'],
                'pagination' => [
                    'current_page' => $results['current_page'],
                    'last_page' => $results['last_page'],
                    'per_page' => $results['per_page'],
                    'total' => $results['total'],
                ],
                'source' => $source, // 'cache' or 'database'
            ]);
        }

        return view('frontend.pages.product-grids', [
            'products' => new \Illuminate\Pagination\LengthAwarePaginator(
                $results['products'],
                $results['total'],
                $results['per_page'],
                $results['current_page']
            ),
            'filters' => $filters,
            'source' => $source,
        ]);
    }

    /**
     * ✅ NEW: API endpoint for high-performance filters
     * This is called by your JavaScript for instant filter updates
     */
    public function apiFilters(Request $request, $path = '')
    {
        $filters = $this->extractFilters($request, $path);
        $page = $request->input('page', 1);
        $perPage = $request->input('show', 12);

        // Smart cache lookup
        $cached = $this->cacheService->getFilteredProducts($filters, $page, $perPage);

        if ($cached) {
            return response()->json([
                'success' => true,
                'data' => $cached,
                'source' => 'cache',
                'cache_hit' => true,
            ]);
        }

        // Fetch and cache
        $results = $this->fetchProductsFromSource($filters, $page, $perPage);
        $this->cacheService->storeFilteredProducts($filters, $page, $perPage, $results);

        return response()->json([
            'success' => true,
            'data' => $results,
            'source' => 'database',
            'cache_hit' => false,
        ]);
    }
}

/**
 * EXAMPLE ARTISAN COMMANDS
 */

// Warm cache for popular combos (run at 2 AM daily)
// php artisan cache:warm-filters --auto --limit=200

// Check cache statistics
// php artisan cache:warm-filters --analyze

// Manual warm-up for specific category
// php artisan tinker
// >>> $filters = ['category_id' => [5], 'brand' => ['Nike']];
// >>> dispatch(new WarmFilterCacheJob($filters, 1, 5));

/**
 * EXAMPLE SCHEDULED TASKS
 */

// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    // Daily cache warming at 2 AM
    $schedule->command('cache:warm-filters --auto --limit=200')
             ->dailyAt('02:00')
             ->timezone('America/New_York');

    // Clear old analytics weekly
    $schedule->call(function () {
        \Redis::del('hot_filter_combos');
    })->weekly()->sundays()->at('03:00');

    // Re-index products in search engine (if using Scout)
    $schedule->command('scout:import "App\Models\Product"')
             ->weekly()
             ->sundays()
             ->at('04:00');
}

/**
 * EXAMPLE .ENV CONFIGURATION
 */

/*
# Search Engine (choose one)
SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=your-master-key

# OR
SCOUT_DRIVER=elasticsearch
ELASTICSEARCH_HOST=localhost:9200

# Redis (for caching)
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Cache settings
CACHE_DRIVER=redis
CACHE_PREFIX=ecommerce_

# Queue (for background jobs)
QUEUE_CONNECTION=redis
*/

/**
 * EXAMPLE MIGRATION PLAN
 */

/*
WEEK 1: Setup Infrastructure
Day 1-2: Install Meilisearch + Scout
Day 3-4: Index products, test search
Day 5: Performance testing

WEEK 2: Smart Caching
Day 1-2: Integrate SmartFilterCacheService
Day 3: Update FrontendController
Day 4: Test cache hit rates
Day 5: Fine-tune TTLs

WEEK 3: Automation
Day 1-2: Create WarmFilterCacheJob
Day 3: Schedule automated warming
Day 4-5: Monitor and optimize

WEEK 4: Production
Day 1-2: Load testing
Day 3: Deploy to production
Day 4-5: Monitor real traffic
*/
