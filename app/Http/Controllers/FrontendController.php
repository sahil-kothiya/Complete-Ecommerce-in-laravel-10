<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use App\Models\Banner;
use App\Models\Product;
use App\Models\Category;
use App\Models\PostTag;
use App\Models\PostCategory;
use App\Models\Post;
use App\Models\Cart;
use App\Models\Brand;
use App\Models\Settings;
use App\Services\ProductSearchService;
use App\Services\RedisCacheManager;
use App\User;
use Auth;
use Session;
use Newsletter;
use DB;
use Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FrontendController extends Controller
{
    private ProductSearchService $searchService;

    public function __construct(ProductSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    // Homepage cache prefix
    private const HOMEPAGE_CACHE_PREFIX = 'cache:homepage:';

    // Product grids cache prefix
    private const PRODUCT_GRIDS_CACHE_PREFIX = 'cache:product_grids:';

    /**
     * TTL configuration cache
     */
    private static ?array $ttlConfig = null;

    /**
     * Enhanced product grids with Redis caching
     */
    public function productGrids(Request $request)
    {
        $ttl = $this->getTtlConfig();

        // Generate cache key based on request parameters
        $cacheKey = $this->generateProductGridsCacheKey($request);

        // Try to get cached data first
        $cachedData = RedisHelper::get($cacheKey);

        if ($cachedData) {
            Log::info("Product grids served from cache: {$cacheKey}");
            return view('frontend.pages.product-grids', $cachedData);
        }

        // If not cached, fetch fresh data
        $data = $this->fetchProductGridsData($request, $ttl);

        // Cache the data
        $this->cacheProductGridsData($cacheKey, $data, $ttl['product_lists']);

        return view('frontend.pages.product-grids', $data);
    }

    /**
     * Generate cache key for product grids based on request parameters
     */
    private function generateProductGridsCacheKey(Request $request): string
    {
        $params = [
            'category' => $request->get('category', ''),
            'brand' => $request->get('brand', ''),
            'sortBy' => $request->get('sortBy', ''),
            'price' => $request->get('price', ''),
            'show' => $request->get('show', '9'),
            'page' => $request->get('page', '1')
        ];

        // Remove empty parameters
        $params = array_filter($params, function ($value) {
            return !empty($value);
        });

        // Create a hash of parameters for cache key
        $paramHash = md5(serialize($params));

        return self::PRODUCT_GRIDS_CACHE_PREFIX . 'page:' . $paramHash;
    }

    /**
     * Fetch fresh product grids data
     */
    private function fetchProductGridsData(Request $request, array $ttl): array
    {
        // Get recent products (cached separately)
        $recent_products = $this->getRecentProducts($ttl['product_lists']);

        // Get sidebar data (cached separately)
        $sidebarData = $this->getSidebarData($ttl);

        // Build products query
        $products = $this->buildProductsQuery($request);

        return array_merge([
            'products' => $products,
            'recent_products' => $recent_products
        ], $sidebarData);
    }

    /**
     * Build products query with filters
     */
    private function buildProductsQuery(Request $request)
    {
        $products = Product::query();

        // Category filter
        if (!empty($request->get('category'))) {
            $slug = explode(',', $request->get('category'));
            $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            $products->whereIn('cat_id', $cat_ids);
        }

        // Brand filter
        if (!empty($request->get('brand'))) {
            $slugs = explode(',', $request->get('brand'));
            $brand_ids = Brand::select('id')->whereIn('slug', $slugs)->pluck('id')->toArray();
            $products->whereIn('brand_id', $brand_ids);
        }

        // Sorting
        if (!empty($request->get('sortBy'))) {
            switch ($request->get('sortBy')) {
                case 'title':
                    $products->orderBy('title', 'ASC');
                    break;
                case 'price':
                    $products->orderBy('price', 'ASC');
                    break;
                case 'category':
                    $products->join('categories', 'products.cat_id', '=', 'categories.id')
                        ->orderBy('categories.title', 'ASC')
                        ->select('products.*');
                    break;
                case 'brand':
                    $products->join('brands', 'products.brand_id', '=', 'brands.id')
                        ->orderBy('brands.title', 'ASC')
                        ->select('products.*');
                    break;
            }
        }

        // Price filter
        if (!empty($request->get('price'))) {
            $price = explode('-', $request->get('price'));
            if (count($price) === 2 && is_numeric($price[0]) && is_numeric($price[1])) {
                $products->whereBetween('price', [$price[0], $price[1]]);
            }
        }

        // Pagination
        $perPage = !empty($request->get('show')) ? (int)$request->get('show') : 9;

        return $products->where('status', 'active')
            ->with([
                'images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary']),
                'cat_info' => fn($q) => $q->select(['id', 'title'])
            ])
            ->select([
                'id',
                'title',
                'slug',
                'price',
                'discount',
                'stock',
                'condition',
                'cat_id',
                'brand_id',
                'size',
                'summary'
            ])
            ->paginate($perPage);
    }

    /**
     * Get recent products with caching
     */
    private function getRecentProducts(int $ttl)
    {
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products';

        return Cache::remember($cacheKey, $ttl, function () use ($cacheKey, $ttl) {
            $products = Product::where('status', 'active')
                ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])])
                ->select(['id', 'title', 'slug', 'price', 'discount'])
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            // Store in Redis
            RedisHelper::put($cacheKey, $products, $ttl);

            return $products;
        });
    }

    /**
     * Get sidebar data (categories, brands, max price) with caching
     */
    private function getSidebarData(array $ttl): array
    {
        $cacheKeys = [
            'categories' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories',
            'brands' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands',
            'max_price' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price'
        ];

        // Try to get all sidebar data from Redis
        $cachedData = RedisHelper::mget(array_values($cacheKeys));

        $data = [];

        // Categories
        $data['categories'] = $cachedData[$cacheKeys['categories']]
            ?? $this->getSidebarCategories($cacheKeys['categories'], $ttl['categories']);

        // Brands
        $data['brands'] = $cachedData[$cacheKeys['brands']]
            ?? $this->getSidebarBrands($cacheKeys['brands'], $ttl['categories']);

        // Max price
        $data['max_price'] = $cachedData[$cacheKeys['max_price']]
            ?? $this->getMaxPrice($cacheKeys['max_price'], $ttl['product_lists']);

        return $data;
    }

    /**
     * Get sidebar categories
     */
    private function getSidebarCategories(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $categories = Category::select(['id', 'title', 'slug', 'parent_id', 'is_parent'])
                ->active()
                ->where('is_parent', 1)
                ->with([
                    'children' => fn($q) => $q->active()
                        ->select(['id', 'title', 'slug', 'parent_id'])
                        ->orderBy('title')
                ])
                ->orderBy('title')
                ->get();

            RedisHelper::put($key, $categories, $ttl);
            return $categories;
        });
    }

    /**
     * Get sidebar brands
     */
    private function getSidebarBrands(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $brands = DB::table('brands')
                ->select(['id', 'title', 'slug'])
                ->where('status', 'active')
                ->orderBy('title', 'ASC')
                ->get();

            RedisHelper::put($key, $brands, $ttl);
            return $brands;
        });
    }

    /**
     * Get maximum price for price filter
     */
    private function getMaxPrice(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $maxPrice = DB::table('products')
                ->where('status', 'active')
                ->max('price') ?? 1000;

            RedisHelper::put($key, $maxPrice, $ttl);
            return $maxPrice;
        });
    }

    /**
     * Cache product grids data
     */
    private function cacheProductGridsData(string $key, array $data, int $ttl): void
    {
        try {
            // Don't cache the paginated products object directly due to complexity
            // Instead, cache the data that doesn't change frequently
            $cacheableData = [
                'recent_products' => $data['recent_products'],
                'categories' => $data['categories'] ?? null,
                'brands' => $data['brands'] ?? null,
                'max_price' => $data['max_price'] ?? null,
            ];

            RedisHelper::put($key . ':sidebar', $cacheableData, $ttl);

            Log::info("Product grids sidebar data cached: {$key}");
        } catch (\Exception $e) {
            Log::error("Failed to cache product grids data: " . $e->getMessage());
        }
    }

    /**
     * Enhanced product search with Elasticsearch
     */
    public function productSearch(Request $request)
    {
        $query = $request->input('search', '');
        $perPage = 9;

        // Get recent products (cached)
        $ttl = $this->getTtlConfig();
        $recent_products = $this->getRecentProducts($ttl['product_lists']);

        if (empty($query)) {
            $products = Product::where('status', 'active')
                ->with(['images', 'cat_info'])
                ->paginate($perPage);

            return view('frontend.pages.product-grids')
                ->with('products', $products)
                ->with('recent_products', $recent_products)
                ->with('search_query', $query);
        }

        try {
            // Use enhanced search service
            $searchResult = $this->searchService->search($query, $perPage, $request->input('page', 1));

            // Convert to paginator for blade compatibility
            $products = new \Illuminate\Pagination\LengthAwarePaginator(
                collect($searchResult['products']),
                $searchResult['total'],
                $perPage,
                $request->input('page', 1),
                [
                    'path' => $request->url(),
                    'pageName' => 'page',
                ]
            );
            $products->appends($request->except('page'));

            Log::info("Search performed using: " . $searchResult['source']);
        } catch (\Exception $e) {
            Log::error('Search error: ' . $e->getMessage());

            // Fallback to original search
            $products = Product::where('title', 'ILIKE', "%{$query}%")
                ->where('status', 'active')
                ->orderBy('id', 'DESC')
                ->paginate($perPage);
        }

        return view('frontend.pages.product-grids')
            ->with('products', $products)
            ->with('recent_products', $recent_products)
            ->with('search_query', $query);
    }

    /**
     * AJAX autocomplete endpoint
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q', '');

        Log::info('Autocomplete query: ' . $query);

        if (strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'suggestions' => []
            ]);
        }

        try {
            // If you have search service, use it
            if (isset($this->searchService)) {
                $suggestions = $this->searchService->getAutocomplete($query, 10);
            } else {
                // Fallback to direct database query
                $products = Product::where('status', 'active')
                    ->where('title', 'ILIKE', "%{$query}%")
                    ->select('id', 'title', 'slug', 'price', 'discount', 'photo')
                    ->limit(10)
                    ->get();

                $suggestions = $products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'price' => $product->price,
                        'discount' => $product->discount ?? 0,
                        'photo' => $product->photo ? explode(',', $product->photo)[0] : null
                    ];
                });
            }

            return response()->json([
                'success' => true,
                'suggestions' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('Autocomplete error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'suggestions' => [],
                'error' => $e->getMessage()
            ]);
        }
    }

    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    public function home()
    {
        $ttl = $this->getTtlConfig();

        // Define all cache keys
        $cacheKeys = [
            'categories' => self::HOMEPAGE_CACHE_PREFIX . 'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX . 'banners',
            'products' => self::HOMEPAGE_CACHE_PREFIX . 'product_lists',
            'categoryBanners' => self::HOMEPAGE_CACHE_PREFIX . 'category_banners'
        ];

        // Try to get all data from Redis in one batch operation
        $cachedData = RedisHelper::mget(array_values($cacheKeys));

        // Process each data type
        $data = [];

        // Categories
        $data['categories'] = $cachedData[$cacheKeys['categories']]
            ?? $this->getCategoriesData($cacheKeys['categories'], $ttl['categories']);

        // Banners
        $data['banners'] = $cachedData[$cacheKeys['banners']]
            ?? $this->getBannersData($cacheKeys['banners'], $ttl['banners']);

        // Products - simplified for homepage (60 products only)
        $data['product_lists'] = $cachedData[$cacheKeys['products']]
            ?? $this->getHomepageProductsData($cacheKeys['products'], $ttl['product_lists']);

        // Category Banners (derived from categories)
        $data['categoryBanners'] = $cachedData[$cacheKeys['categoryBanners']]
            ?? $this->getCategoryBannersData($cacheKeys['categoryBanners'], $data['categories'], $ttl['categories']);

        return view('frontend.index', $data);
    }

    /**
     * Get TTL configuration with static caching
     */
    private function getTtlConfig(): array
    {
        if (self::$ttlConfig === null) {
            self::$ttlConfig = config('cache_keys.ttl');
        }

        return self::$ttlConfig;
    }

    /**
     * Get categories data with optimized query
     */
    private function getCategoriesData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $categories = Category::select(['id', 'title', 'slug', 'parent_id', 'photo', 'is_parent'])
                ->active()
                ->where('is_parent', 1)
                ->with([
                    'children' => fn($q) => $q->active()
                        ->select(['id', 'title', 'slug', 'parent_id'])
                        ->orderBy('title')
                ])
                ->orderBy('title')
                ->get();

            // Store in Redis for faster access
            RedisHelper::put($key, $categories, $ttl);

            return $categories;
        });
    }

    /**
     * Get banners data with optimized query
     */
    private function getBannersData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $banners = Banner::where('status', 'active')
                ->select(['id', 'title', 'description', 'photo'])
                ->orderByDesc('id')
                ->get();

            // Store in Redis for faster access
            RedisHelper::put($key, $banners, $ttl);

            return $banners;
        });
    }

    /**
     * Get homepage products data - optimized for exactly 60 products
     */
    private function getHomepageProductsData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            // Homepage needs exactly 60 products with all required relationships
            $products = Product::select([
                'id',
                'title',
                'slug',
                'price',
                'discount',
                'stock',
                'condition',
                'cat_id',
                'size',
                'summary' // Added for modal quick view
            ])
                ->where('status', 'active')
                ->with([
                    'images' => fn($q) => $q->select(['id', 'image_path', 'product_id']),
                    'cat_info' => fn($q) => $q->select(['id', 'title']) // Load category relationship
                ])
                ->latest('id')
                ->limit(60) // Fixed limit for homepage
                ->get();

            // Store in Redis
            $stored = RedisHelper::put($key, $products, $ttl);

            if (!$stored) {
                Log::warning("Failed to store homepage products in Redis for key: {$key}");
            }

            return $products;
        });
    }

    /**
     * Get category banners data (derived from categories)
     */
    private function getCategoryBannersData(string $key, $categories, int $ttl)
    {
        if (!$categories) {
            return collect();
        }

        return Cache::remember($key, $ttl, function () use ($key, $categories, $ttl) {
            $categoryBanners = $categories->filter(fn($cat) => !empty($cat->photo));

            // Store in Redis for faster access
            RedisHelper::put($key, $categoryBanners, $ttl);

            return $categoryBanners;
        });
    }

    /**
     * Batch cache warming method for homepage
     */
    public function warmUpHomepageCache(): array
    {
        $ttl = $this->getTtlConfig();
        $results = [];

        // Get Redis stats before warming
        $statsBefore = RedisHelper::getCacheStats();

        // Warm up all homepage data
        $dataTypes = [
            'categories' => fn() => $this->getCategoriesData(self::HOMEPAGE_CACHE_PREFIX . 'categories', $ttl['categories']),
            'banners' => fn() => $this->getBannersData(self::HOMEPAGE_CACHE_PREFIX . 'banners', $ttl['banners']),
            'products' => fn() => $this->getHomepageProductsData(self::HOMEPAGE_CACHE_PREFIX . 'product_lists', $ttl['product_lists']),
        ];

        foreach ($dataTypes as $type => $callback) {
            $startTime = microtime(true);
            try {
                $data = $callback();
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);

                $results[$type] = [
                    'status' => 'success',
                    'duration_ms' => $duration,
                    'records' => is_countable($data) ? count($data) : 'N/A'
                ];
            } catch (\Exception $e) {
                $results[$type] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                Log::error("Cache warming failed for {$type}: " . $e->getMessage());
            }
        }

        // Get Redis stats after warming
        $statsAfter = RedisHelper::getCacheStats();

        $results['redis_stats'] = [
            'memory_before' => $statsBefore['used_memory_human'] ?? 'N/A',
            'memory_after' => $statsAfter['used_memory_human'] ?? 'N/A'
        ];

        return $results;
    }

    /**
     * Warm up product grids cache
     */
    public function warmUpProductGridsCache(): array
    {
        $ttl = $this->getTtlConfig();
        $results = [];

        // Get Redis stats before warming
        $statsBefore = RedisHelper::getCacheStats();

        // Warm up product grids data
        $dataTypes = [
            'recent_products' => fn() => $this->getRecentProducts($ttl['product_lists']),
            'sidebar_categories' => fn() => $this->getSidebarCategories(self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories', $ttl['categories']),
            'sidebar_brands' => fn() => $this->getSidebarBrands(self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands', $ttl['categories']),
            'max_price' => fn() => $this->getMaxPrice(self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price', $ttl['product_lists']),
        ];

        foreach ($dataTypes as $type => $callback) {
            $startTime = microtime(true);
            try {
                $data = $callback();
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);

                $results[$type] = [
                    'status' => 'success',
                    'duration_ms' => $duration,
                    'records' => is_countable($data) ? count($data) : 'N/A'
                ];
            } catch (\Exception $e) {
                $results[$type] = [
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                Log::error("Product grids cache warming failed for {$type}: " . $e->getMessage());
            }
        }

        // Get Redis stats after warming
        $statsAfter = RedisHelper::getCacheStats();

        $results['redis_stats'] = [
            'memory_before' => $statsBefore['used_memory_human'] ?? 'N/A',
            'memory_after' => $statsAfter['used_memory_human'] ?? 'N/A'
        ];

        return $results;
    }

    /**
     * Clear homepage cache
     */
    public function clearHomepageCache(): bool
    {
        $keys = [
            self::HOMEPAGE_CACHE_PREFIX . 'categories',
            self::HOMEPAGE_CACHE_PREFIX . 'banners',
            self::HOMEPAGE_CACHE_PREFIX . 'product_lists',
            self::HOMEPAGE_CACHE_PREFIX . 'category_banners'
        ];

        try {
            // Clear from Redis
            RedisHelper::forgetMany($keys);

            // Clear from Laravel cache
            foreach ($keys as $key) {
                Cache::forget($key);
            }

            Log::info('Homepage cache cleared successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear homepage cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear product grids cache
     */
    public function clearProductGridsCache(): bool
    {
        try {
            // Clear all product grids related cache keys
            $pattern = self::PRODUCT_GRIDS_CACHE_PREFIX . '*';
            $keys = RedisHelper::keys($pattern);

            if (!empty($keys)) {
                RedisHelper::forgetMany($keys);
            }

            // Clear from Laravel cache as well
            foreach ($keys as $key) {
                Cache::forget($key);
            }

            Log::info('Product grids cache cleared successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear product grids cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get cache health status
     */
    public function getCacheHealth(): array
    {
        $homepageKeys = [
            'categories' => self::HOMEPAGE_CACHE_PREFIX . 'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX . 'banners',
            'products' => self::HOMEPAGE_CACHE_PREFIX . 'product_lists',
            'category_banners' => self::HOMEPAGE_CACHE_PREFIX . 'category_banners'
        ];

        $productGridsKeys = [
            'recent_products' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products',
            'sidebar_categories' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories',
            'sidebar_brands' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands',
            'max_price' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price'
        ];

        $health = [
            'homepage' => [],
            'product_grids' => []
        ];

        // Check homepage cache
        foreach ($homepageKeys as $name => $key) {
            $exists = RedisHelper::exists($key);
            $health['homepage'][$name] = [
                'cached' => $exists,
                'key' => $key
            ];
        }

        // Check product grids cache
        foreach ($productGridsKeys as $name => $key) {
            $exists = RedisHelper::exists($key);
            $health['product_grids'][$name] = [
                'cached' => $exists,
                'key' => $key
            ];
        }

        $health['redis_stats'] = RedisHelper::getCacheStats();

        return $health;
    }

    // public function productSearch(Request $request)
    // {
    //     $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
    //     $products = Product::orwhere('title', 'like', '%' . $request->search . '%')
    //         ->orwhere('slug', 'like', '%' . $request->search . '%')
    //         ->orwhere('description', 'like', '%' . $request->search . '%')
    //         ->orwhere('summary', 'like', '%' . $request->search . '%')
    //         ->orwhere('price', 'like', '%' . $request->search . '%')
    //         ->orderBy('id', 'DESC')
    //         ->paginate('9');
    //     return view('frontend.pages.product-grids')->with('products', $products)->with('recent_products', $recent_products);
    // }

    public function aboutUs()
    {
        return view('frontend.pages.about-us');
    }

    public function contact()
    {
        return view('frontend.pages.contact');
    }

    public function productDetail($slug)
    {
        $product_detail = Product::getProductBySlug($slug);
        // dd($product_detail);
        return view('frontend.pages.product_detail')->with('product_detail', $product_detail);
    }

    // public function productGrids()
    // {
    //     $products = Product::query();

    //     if (!empty($_GET['category'])) {
    //         $slug = explode(',', $_GET['category']);
    //         // dd($slug);
    //         $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
    //         // dd($cat_ids);
    //         $products->whereIn('cat_id', $cat_ids);
    //         // return $products;
    //     }
    //     if (!empty($_GET['brand'])) {
    //         $slugs = explode(',', $_GET['brand']);
    //         $brand_ids = Brand::select('id')->whereIn('slug', $slugs)->pluck('id')->toArray();
    //         return $brand_ids;
    //         $products->whereIn('brand_id', $brand_ids);
    //     }
    //     if (!empty($_GET['sortBy'])) {
    //         if ($_GET['sortBy'] == 'title') {
    //             $products = $products->where('status', 'active')->orderBy('title', 'ASC');
    //         }
    //         if ($_GET['sortBy'] == 'price') {
    //             $products = $products->orderBy('price', 'ASC');
    //         }
    //     }

    //     if (!empty($_GET['price'])) {
    //         $price = explode('-', $_GET['price']);
    //         // return $price;
    //         // if(isset($price[0]) && is_numeric($price[0])) $price[0]=floor(Helper::base_amount($price[0]));
    //         // if(isset($price[1]) && is_numeric($price[1])) $price[1]=ceil(Helper::base_amount($price[1]));

    //         $products->whereBetween('price', $price);
    //     }

    //     $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
    //     // Sort by number
    //     if (!empty($_GET['show'])) {
    //         $products = $products->where('status', 'active')->paginate($_GET['show']);
    //     } else {
    //         $products = $products->where('status', 'active')->paginate(9);
    //     }
    //     // Sort by name , price, category


    //     return view('frontend.pages.product-grids')->with('products', $products)->with('recent_products', $recent_products);
    // }
    public function productLists()
    {
        $products = Product::query();

        if (!empty($_GET['category'])) {
            $slug = explode(',', $_GET['category']);
            // dd($slug);
            $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            // dd($cat_ids);
            $products->whereIn('cat_id', $cat_ids)->paginate;
            // return $products;
        }
        if (!empty($_GET['brand'])) {
            $slugs = explode(',', $_GET['brand']);
            $brand_ids = Brand::select('id')->whereIn('slug', $slugs)->pluck('id')->toArray();
            return $brand_ids;
            $products->whereIn('brand_id', $brand_ids);
        }
        if (!empty($_GET['sortBy'])) {
            if ($_GET['sortBy'] == 'title') {
                $products = $products->where('status', 'active')->orderBy('title', 'ASC');
            }
            if ($_GET['sortBy'] == 'price') {
                $products = $products->orderBy('price', 'ASC');
            }
        }

        if (!empty($_GET['price'])) {
            $price = explode('-', $_GET['price']);
            // return $price;
            // if(isset($price[0]) && is_numeric($price[0])) $price[0]=floor(Helper::base_amount($price[0]));
            // if(isset($price[1]) && is_numeric($price[1])) $price[1]=ceil(Helper::base_amount($price[1]));

            $products->whereBetween('price', $price);
        }

        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        // Sort by number
        if (!empty($_GET['show'])) {
            $products = $products->where('status', 'active')->paginate($_GET['show']);
        } else {
            $products = $products->where('status', 'active')->paginate(6);
        }
        // Sort by name , price, category


        return view('frontend.pages.product-lists')->with('products', $products)->with('recent_products', $recent_products);
    }

    public function productFilter(Request $request)
    {
        $data = $request->all();
        // return $data;
        $showURL = "";
        if (!empty($data['show'])) {
            $showURL .= '&show=' . $data['show'];
        }

        $sortByURL = '';
        if (!empty($data['sortBy'])) {
            $sortByURL .= '&sortBy=' . $data['sortBy'];
        }

        $catURL = "";
        if (!empty($data['category'])) {
            foreach ($data['category'] as $category) {
                if (empty($catURL)) {
                    $catURL .= '&category=' . $category;
                } else {
                    $catURL .= ',' . $category;
                }
            }
        }

        $brandURL = "";
        if (!empty($data['brand'])) {
            foreach ($data['brand'] as $brand) {
                if (empty($brandURL)) {
                    $brandURL .= '&brand=' . $brand;
                } else {
                    $brandURL .= ',' . $brand;
                }
            }
        }
        // return $brandURL;

        $priceRangeURL = "";
        if (!empty($data['price_range'])) {
            $priceRangeURL .= '&price=' . $data['price_range'];
        }
        if (request()->is('e-shop.loc/product-grids')) {
            return redirect()->route('product-grids', $catURL . $brandURL . $priceRangeURL . $showURL . $sortByURL);
        } else {
            return redirect()->route('product-lists', $catURL . $brandURL . $priceRangeURL . $showURL . $sortByURL);
        }
    }

    public function productBrand(Request $request)
    {
        $products = Brand::getProductByBrand($request->slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        if (request()->is('e-shop.loc/product-grids')) {
            return view('frontend.pages.product-grids')->with('products', $products->products)->with('recent_products', $recent_products);
        } else {
            return view('frontend.pages.product-lists')->with('products', $products->products)->with('recent_products', $recent_products);
        }
    }
    public function productCat(Request $request)
    {
        $products = Category::getProductByCat($request->slug);
        // return $request->slug;
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        if (request()->is('e-shop.loc/product-grids')) {
            return view('frontend.pages.product-grids')->with('products', $products->products)->with('recent_products', $recent_products);
        } else {
            return view('frontend.pages.product-lists')->with('products', $products->products)->with('recent_products', $recent_products);
        }
    }
    public function productSubCat(Request $request)
    {
        $products = Category::getProductBySubCat($request->sub_slug);
        // return $products;
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        if (request()->is('e-shop.loc/product-grids')) {
            return view('frontend.pages.product-grids')->with('products', $products->sub_products)->with('recent_products', $recent_products);
        } else {
            return view('frontend.pages.product-lists')->with('products', $products->sub_products)->with('recent_products', $recent_products);
        }
    }

    public function blog()
    {
        $post = Post::query();

        if (!empty($_GET['category'])) {
            $slug = explode(',', $_GET['category']);
            // dd($slug);
            $cat_ids = PostCategory::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            return $cat_ids;
            $post->whereIn('post_cat_id', $cat_ids);
            // return $post;
        }
        if (!empty($_GET['tag'])) {
            $slug = explode(',', $_GET['tag']);
            // dd($slug);
            $tag_ids = PostTag::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            // return $tag_ids;
            $post->where('post_tag_id', $tag_ids);
            // return $post;
        }

        if (!empty($_GET['show'])) {
            $post = $post->where('status', 'active')->orderBy('id', 'DESC')->paginate($_GET['show']);
        } else {
            $post = $post->where('status', 'active')->orderBy('id', 'DESC')->paginate(9);
        }
        // $post=Post::where('status','active')->paginate(8);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        return view('frontend.pages.blog')->with('posts', $post)->with('recent_posts', $rcnt_post);
    }

    public function blogDetail($slug)
    {
        $post = Post::getPostBySlug($slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        // return $post;
        return view('frontend.pages.blog-detail')->with('post', $post)->with('recent_posts', $rcnt_post);
    }

    public function blogSearch(Request $request)
    {
        // return $request->all();
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $posts = Post::orwhere('title', 'like', '%' . $request->search . '%')
            ->orwhere('quote', 'like', '%' . $request->search . '%')
            ->orwhere('summary', 'like', '%' . $request->search . '%')
            ->orwhere('description', 'like', '%' . $request->search . '%')
            ->orwhere('slug', 'like', '%' . $request->search . '%')
            ->orderBy('id', 'DESC')
            ->paginate(8);
        return view('frontend.pages.blog')->with('posts', $posts)->with('recent_posts', $rcnt_post);
    }

    public function blogFilter(Request $request)
    {
        $data = $request->all();
        // return $data;
        $catURL = "";
        if (!empty($data['category'])) {
            foreach ($data['category'] as $category) {
                if (empty($catURL)) {
                    $catURL .= '&category=' . $category;
                } else {
                    $catURL .= ',' . $category;
                }
            }
        }

        $tagURL = "";
        if (!empty($data['tag'])) {
            foreach ($data['tag'] as $tag) {
                if (empty($tagURL)) {
                    $tagURL .= '&tag=' . $tag;
                } else {
                    $tagURL .= ',' . $tag;
                }
            }
        }
        // return $tagURL;
        // return $catURL;
        return redirect()->route('blog', $catURL . $tagURL);
    }

    public function blogByCategory(Request $request)
    {
        $post = PostCategory::getBlogByCategory($request->slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        return view('frontend.pages.blog')->with('posts', $post->post)->with('recent_posts', $rcnt_post);
    }

    public function blogByTag(Request $request)
    {
        // dd($request->slug);
        $post = Post::getBlogByTag($request->slug);
        // return $post;
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        return view('frontend.pages.blog')->with('posts', $post)->with('recent_posts', $rcnt_post);
    }

    // Login
    public function login()
    {
        return view('frontend.pages.login');
    }
    public function loginSubmit(Request $request)
    {
        $data = $request->all();
        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'])) {
            Session::put('user', $data['email']);
            request()->session()->flash('success', 'Successfully login');
            return redirect()->route('home');
        } else {
            request()->session()->flash('error', 'Invalid email and password pleas try again!');
            return redirect()->back();
        }
    }

    public function logout()
    {
        Session::forget('user');
        Auth::logout();
        request()->session()->flash('success', 'Logout successfully');
        return back();
    }

    public function register()
    {
        return view('frontend.pages.register');
    }
    public function registerSubmit(Request $request)
    {
        // return $request->all();
        $this->validate($request, [
            'name' => 'string|required|min:2',
            'email' => 'string|required|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);
        $data = $request->all();
        // dd($data);
        $check = $this->create($data);
        Session::put('user', $data['email']);
        if ($check) {
            request()->session()->flash('success', 'Successfully registered');
            return redirect()->route('home');
        } else {
            request()->session()->flash('error', 'Please try again!');
            return back();
        }
    }
    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active'
        ]);
    }
    // Reset password
    public function showResetForm()
    {
        return view('auth.passwords.old-reset');
    }

    public function subscribe(Request $request)
    {
        if (! Newsletter::isSubscribed($request->email)) {
            Newsletter::subscribePending($request->email);
            if (Newsletter::lastActionSucceeded()) {
                request()->session()->flash('success', 'Subscribed! Please check your email');
                return redirect()->route('home');
            } else {
                Newsletter::getLastError();
                return back()->with('error', 'Something went wrong! please try again');
            }
        } else {
            request()->session()->flash('error', 'Already Subscribed');
            return back();
        }
    }
}
