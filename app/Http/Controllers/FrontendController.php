<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\Product;
use App\Services\ProductSearchService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Spatie\Newsletter\Facades\Newsletter;

class FrontendController extends Controller
{
    private ProductSearchService $searchService;
    private const HOMEPAGE_CACHE_PREFIX = 'cache:homepage:';
    private const PRODUCT_GRIDS_CACHE_PREFIX = 'cache:product_grids:';
    private static ?array $ttlConfig = null;

    /**
     * Constructor to initialize the ProductSearchService.
     *
     * @param ProductSearchService $searchService
     */
    public function __construct(ProductSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    // Homepage Related Functions

    /**
     * Redirects authenticated user to their role-specific route.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    /**
     * Renders the homepage with cached data for categories, banners, and products.
     *
     * @return \Illuminate\View\View
     */
    public function home()
    {
        $ttl = $this->getTtlConfig();
        $cacheKeys = [
            'categories' => self::HOMEPAGE_CACHE_PREFIX . 'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX . 'banners',
            'products' => self::HOMEPAGE_CACHE_PREFIX . 'product_lists',
            'categoryBanners' => self::HOMEPAGE_CACHE_PREFIX . 'category_banners'
        ];

        $cachedData = RedisHelper::mget(array_values($cacheKeys));

        $data = [
            'categories' => $cachedData[$cacheKeys['categories']]
                ?? $this->getCategoriesData($cacheKeys['categories'], $ttl['categories']),
            'banners' => $cachedData[$cacheKeys['banners']]
                ?? $this->getBannersData($cacheKeys['banners'], $ttl['banners']),
            'product_lists' => $cachedData[$cacheKeys['products']]
                ?? $this->getHomepageProductsData($cacheKeys['products'], $ttl['product_lists']),
            'categoryBanners' => $cachedData[$cacheKeys['categoryBanners']]
                ?? $this->getCategoryBannersData($cacheKeys['categoryBanners'], $cachedData[$cacheKeys['categories']] ?? null, $ttl['categories']),
        ];

        return view('frontend.index', $data);
    }

    /**
     * Warms up the homepage cache for faster access.
     *
     * @return array Cache warming results with status and statistics
     */
    public function warmUpHomepageCache(): array
    {
        $ttl = $this->getTtlConfig();
        $results = [];
        $statsBefore = RedisHelper::getCacheStats();

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

        $statsAfter = RedisHelper::getCacheStats();
        $results['redis_stats'] = [
            'memory_before' => $statsBefore['used_memory_human'] ?? 'N/A',
            'memory_after' => $statsAfter['used_memory_human'] ?? 'N/A'
        ];

        return $results;
    }

    /**
     * Clears the homepage cache.
     *
     * @return bool Success status of cache clearing
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
            RedisHelper::forgetMany($keys);
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
     * Retrieves TTL configuration for caching.
     *
     * @return array TTL configuration
     */
    private function getTtlConfig(): array
    {
        if (self::$ttlConfig === null) {
            self::$ttlConfig = config('cache_keys.ttl');
        }
        return self::$ttlConfig;
    }

    /**
     * Fetches categories data with caching.
     *
     * @param string $key Cache key
     * @param int $ttl Time-to-live for cache
     * @return \Illuminate\Support\Collection
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

            RedisHelper::put($key, $categories, $ttl);
            return $categories;
        });
    }

    /**
     * Fetches banners data with caching.
     *
     * @param string $key Cache key
     * @param int $ttl Time-to-live for cache
     * @return \Illuminate\Support\Collection
     */
    private function getBannersData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $banners = Banner::where('status', 'active')
                ->select(['id', 'title', 'description', 'photo'])
                ->orderByDesc('id')
                ->get();

            RedisHelper::put($key, $banners, $ttl);
            return $banners;
        });
    }

    /**
     * Fetches homepage products data with caching.
     *
     * @param string $key Cache key
     * @param int $ttl Time-to-live for cache
     * @return \Illuminate\Support\Collection
     */
    private function getHomepageProductsData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
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
                'summary'
            ])
                ->where('status', 'active')
                ->with([
                    'images' => fn($q) => $q->select(['id', 'image_path', 'product_id']),
                    'cat_info' => fn($q) => $q->select(['id', 'title'])
                ])
                ->latest('id')
                ->limit(60)
                ->get();

            if (!RedisHelper::put($key, $products, $ttl)) {
                Log::warning("Failed to store homepage products in Redis for key: {$key}");
            }
            return $products;
        });
    }

    /**
     * Fetches category banners data with caching.
     *
     * @param string $key Cache key
     * @param mixed $categories Categories data
     * @param int $ttl Time-to-live for cache
     * @return \Illuminate\Support\Collection
     */
    private function getCategoryBannersData(string $key, $categories, int $ttl)
    {
        if (!$categories) {
            return collect();
        }

        return Cache::remember($key, $ttl, function () use ($key, $categories, $ttl) {
            $categoryBanners = $categories->filter(fn($cat) => !empty($cat->photo));
            RedisHelper::put($key, $categoryBanners, $ttl);
            return $categoryBanners;
        });
    }

    // Product Grids Related Functions

    public function productGrids(Request $request)
    {
        $startTime = microtime(true);
        $ttl = $this->getTtlConfig();

        // Generate cache key with all parameters
        $cacheKey = $this->generateOptimizedCacheKey($request);

        // Try to get complete page data from cache first
        $cachedData = RedisHelper::get($cacheKey);

        if ($cachedData) {
            // Restore paginator from cache
            if (isset($cachedData['products_data'])) {
                $cachedData['products'] = $this->restorePaginatorFromCache($cachedData['products_data'], $request);
                unset($cachedData['products_data']);
            }

            // Convert cached arrays back to collections/models for proper relationship handling
            $cachedData = $this->hydrateRelationshipsFromCache($cachedData);

            Log::info("Product grids served from cache in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
            return view('frontend.pages.product-grids', $cachedData);
        }

        // If not in cache, fetch and cache the data
        $data = $this->fetchOptimizedProductGridsData($request, $ttl);
        $this->cacheCompletePageData($cacheKey, $data, $ttl['product_lists']);

        Log::info("Product grids served fresh in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
        return view('frontend.pages.product-grids', $data);
    }

    private function generateOptimizedCacheKey(Request $request): string
    {
        $params = [
            'category' => $request->get('category', ''),
            'brand' => $request->get('brand', ''),
            'sortBy' => $request->get('sortBy', ''),
            'price' => $request->get('price', ''),
            'show' => $request->get('show', '9'),
            'page' => $request->get('page', '1')
        ];

        // Remove empty values
        $params = array_filter($params, fn($value) => !empty($value));

        // Create a more specific cache key
        $paramString = http_build_query($params);
        $hash = md5($paramString);

        return self::PRODUCT_GRIDS_CACHE_PREFIX . 'complete_page:' . $hash;
    }

    private function fetchOptimizedProductGridsData(Request $request, array $ttl): array
    {
        // Get all sidebar data in one go using mget
        $sidebarCacheKeys = [
            'recent_products' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products',
            'categories' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories',
            'brands' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands',
            'max_price' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price'
        ];

        $cachedSidebarData = RedisHelper::mget(array_values($sidebarCacheKeys));

        // Prepare sidebar data with fallbacks
        $sidebarData = [
            'recent_products' => $cachedSidebarData[$sidebarCacheKeys['recent_products']]
                ?? $this->getRecentProductsOptimized($ttl['product_lists']),
            'categories' => $cachedSidebarData[$sidebarCacheKeys['categories']]
                ?? $this->getSidebarCategoriesOptimized($ttl['categories']),
            'brands' => $cachedSidebarData[$sidebarCacheKeys['brands']]
                ?? $this->getSidebarBrandsOptimized($ttl['categories']),
            'max_price' => $cachedSidebarData[$sidebarCacheKeys['max_price']]
                ?? $this->getMaxPriceOptimized($ttl['product_lists'])
        ];

        // Build products query with optimized joins
        $products = $this->buildOptimizedProductsQuery($request);

        return array_merge($sidebarData, ['products' => $products]);
    }

    // Optimized product query builder
    private function buildOptimizedProductsQuery(Request $request)
    {
        $query = Product::query();

        // Select only necessary fields
        $query->select([
            'products.id',
            'products.title',
            'products.slug',
            'products.price',
            'products.discount',
            'products.stock',
            'products.condition',
            'products.cat_id',
            'products.brand_id',
            'products.size',
            'products.summary'
        ]);

        // Apply filters with optimized queries
        if ($categorySlug = $request->get('category')) {
            $categoryIds = $this->getCategoryIdsOptimized($categorySlug);
            if (!empty($categoryIds)) {
                $query->whereIn('products.cat_id', $categoryIds);
            }
        }

        if ($brandSlug = $request->get('brand')) {
            $brandIds = $this->getBrandIdsOptimized($brandSlug);
            if (!empty($brandIds)) {
                $query->whereIn('products.brand_id', $brandIds);
            }
        }

        // Apply sorting
        $this->applySortingOptimized($query, $request->get('sortBy'));

        // Apply price filter
        if ($priceRange = $request->get('price')) {
            $this->applyPriceFilter($query, $priceRange);
        }

        // Apply base conditions and eager loading
        $query->where('products.status', 'active')
            ->with([
                'images' => function ($q) {
                    $q->select(['id', 'image_path', 'product_id', 'is_primary'])
                        ->orderBy('is_primary', 'desc');
                },
                'cat_info' => function ($q) {
                    $q->select(['id', 'title', 'slug']);
                },
                'brand' => function ($q) {
                    $q->select(['id', 'title', 'slug']);
                }
            ]);

        $perPage = min((int)$request->get('show', 9), 30); // Limit max per page

        return $query->paginate($perPage);
    }

    private function applySortingOptimized($query, $sortBy)
    {
        switch ($sortBy) {
            case 'title':
                $query->orderBy('products.title', 'ASC');
                break;
            case 'price':
                $query->orderBy('products.price', 'ASC');
                break;
            case 'category':
                $query->join('categories', 'products.cat_id', '=', 'categories.id')
                    ->orderBy('categories.title', 'ASC')
                    ->addSelect('categories.title as category_title');
                break;
            case 'brand':
                $query->join('brands', 'products.brand_id', '=', 'brands.id')
                    ->orderBy('brands.title', 'ASC')
                    ->addSelect('brands.title as brand_title');
                break;
            default:
                $query->orderBy('products.id', 'DESC');
        }
    }

    // Optimized price filter
    private function applyPriceFilter($query, $priceRange)
    {
        $prices = explode('-', $priceRange);
        if (count($prices) === 2 && is_numeric($prices[0]) && is_numeric($prices[1])) {
            $query->whereBetween('products.price', [(float)$prices[0], (float)$prices[1]]);
        }
    }

    // Optimized category ID retrieval
    private function getCategoryIdsOptimized(string $categoryParam): array
    {
        $slugs = explode(',', $categoryParam);
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'cat_ids:' . md5(implode(',', $slugs));

        return RedisHelper::remember($cacheKey, 3600, function () use ($slugs) {
            return Category::whereIn('slug', $slugs)->pluck('id')->toArray();
        });
    }

    // Optimized brand ID retrieval
    private function getBrandIdsOptimized(string $brandParam): array
    {
        $slugs = explode(',', $brandParam);
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'brand_ids:' . md5(implode(',', $slugs));

        return RedisHelper::remember($cacheKey, 3600, function () use ($slugs) {
            return Brand::whereIn('slug', $slugs)->pluck('id')->toArray();
        });
    }

    // Optimized sidebar data methods with proper relationship caching
    private function getRecentProductsOptimized(int $ttl)
    {
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products';

        return RedisHelper::remember($cacheKey, $ttl, function () {
            $products = Product::where('status', 'active')
                ->with(['images' => function ($q) {
                    $q->select(['id', 'image_path', 'product_id', 'is_primary'])
                        ->orderBy('is_primary', 'desc');
                }])
                ->select(['id', 'title', 'slug', 'price', 'discount'])
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            // Convert to array format that preserves relationship data
            return $products->map(function ($product) {
                return [
                    'id' => $product->id,
                    'title' => $product->title,
                    'slug' => $product->slug,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'images' => $product->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'image_path' => $image->image_path,
                            'product_id' => $image->product_id,
                            'is_primary' => $image->is_primary,
                        ];
                    })->toArray()
                ];
            })->toArray();
        });
    }

    private function getSidebarCategoriesOptimized(int $ttl)
    {
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories';

        return RedisHelper::remember($cacheKey, $ttl, function () {
            $categories = Category::select(['id', 'title', 'slug', 'parent_id', 'is_parent'])
                ->active()
                ->where('is_parent', 1)
                ->with([
                    'children' => function ($q) {
                        $q->active()
                            ->select(['id', 'title', 'slug', 'parent_id'])
                            ->orderBy('title');
                    }
                ])
                ->orderBy('title')
                ->get();

            // Convert to array format that preserves relationship data
            return $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
                    'is_parent' => $category->is_parent,
                    'children' => $category->children->map(function ($child) {
                        return [
                            'id' => $child->id,
                            'title' => $child->title,
                            'slug' => $child->slug,
                            'parent_id' => $child->parent_id,
                        ];
                    })->toArray()
                ];
            })->toArray();
        });
    }

    private function getSidebarBrandsOptimized(int $ttl)
    {
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands';

        return RedisHelper::remember($cacheKey, $ttl, function () {
            return Brand::select(['id', 'title', 'slug'])
                ->where('status', 'active')
                ->orderBy('title', 'ASC')
                ->get()
                ->map(function ($brand) {
                    return [
                        'id' => $brand->id,
                        'title' => $brand->title,
                        'slug' => $brand->slug,
                    ];
                })->toArray();
        });
    }

    private function getMaxPriceOptimized(int $ttl)
    {
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price';

        return RedisHelper::remember($cacheKey, $ttl, function () {
            return Product::where('status', 'active')->max('price') ?? 1000;
        });
    }

    // Hydrate relationships from cached data
    private function hydrateRelationshipsFromCache(array $cachedData): array
    {
        // Convert recent_products back to collection-like structure
        if (isset($cachedData['recent_products']) && is_array($cachedData['recent_products'])) {
            $cachedData['recent_products'] = collect($cachedData['recent_products'])->map(function ($product) {
                $productObj = (object) $product;
                $productObj->images = collect($product['images'] ?? [])->map(function ($image) {
                    return (object) $image;
                });
                return $productObj;
            });
        }

        // Convert categories back to collection-like structure
        if (isset($cachedData['categories']) && is_array($cachedData['categories'])) {
            $cachedData['categories'] = collect($cachedData['categories'])->map(function ($category) {
                $categoryObj = (object) $category;
                $categoryObj->children = collect($category['children'] ?? [])->map(function ($child) {
                    return (object) $child;
                });
                return $categoryObj;
            });
        }

        // Convert brands back to collection-like structure
        if (isset($cachedData['brands']) && is_array($cachedData['brands'])) {
            $cachedData['brands'] = collect($cachedData['brands'])->map(function ($brand) {
                return (object) $brand;
            });
        }

        return $cachedData;
    }

    // Optimized complete page caching
    private function cacheCompletePageData(string $key, array $data, int $ttl): void
    {
        try {
            $cacheableData = [
                'recent_products' => $data['recent_products'],
                'categories' => $data['categories'],
                'brands' => $data['brands'],
                'max_price' => $data['max_price'],
            ];

            // Cache paginator data separately
            if (isset($data['products']) && $data['products'] instanceof LengthAwarePaginator) {
                // Convert products with relationships to cacheable format
                $productsWithRelations = $data['products']->getCollection()->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'stock' => $product->stock,
                        'condition' => $product->condition,
                        'cat_id' => $product->cat_id,
                        'brand_id' => $product->brand_id,
                        'size' => $product->size,
                        'summary' => $product->summary,
                        'images' => $product->images->map(function ($image) {
                            return [
                                'id' => $image->id,
                                'image_path' => $image->image_path,
                                'product_id' => $image->product_id,
                                'is_primary' => $image->is_primary,
                            ];
                        })->toArray(),
                        'cat_info' => $product->cat_info ? [
                            'id' => $product->cat_info->id,
                            'title' => $product->cat_info->title,
                            'slug' => $product->cat_info->slug,
                        ] : null,
                        'brand' => $product->brand ? [
                            'id' => $product->brand->id,
                            'title' => $product->brand->title,
                            'slug' => $product->brand->slug,
                        ] : null,
                    ];
                })->toArray();

                $cacheableData['products_data'] = [
                    'items' => $productsWithRelations,
                    'total' => $data['products']->total(),
                    'per_page' => $data['products']->perPage(),
                    'current_page' => $data['products']->currentPage(),
                    'last_page' => $data['products']->lastPage(),
                    'from' => $data['products']->firstItem(),
                    'to' => $data['products']->lastItem(),
                    'path' => $data['products']->path(),
                ];
            }

            // Cache for shorter time to ensure freshness
            $cacheTtl = min($ttl, 900); // 15 minutes max
            RedisHelper::put($key, $cacheableData, $cacheTtl);
        } catch (\Exception $e) {
            Log::error("Failed to cache complete page data: " . $e->getMessage());
        }
    }

    // Enhanced product filter with caching
    public function productFilter(Request $request)
    {
        $startTime = microtime(true);

        $data = $request->all();
        $queryParams = [];

        // Build query parameters
        if (!empty($data['show'])) {
            $queryParams['show'] = $data['show'];
        }

        if (!empty($data['sortBy'])) {
            $queryParams['sortBy'] = $data['sortBy'];
        }

        if (!empty($data['category'])) {
            $queryParams['category'] = is_array($data['category'])
                ? implode(',', $data['category'])
                : $data['category'];
        }

        if (!empty($data['brand'])) {
            $queryParams['brand'] = is_array($data['brand'])
                ? implode(',', $data['brand'])
                : $data['brand'];
        }

        if (!empty($data['price_range'])) {
            $queryParams['price'] = $data['price_range'];
        }

        // Pre-warm cache for this filter combination
        $tempRequest = new Request($queryParams);
        $cacheKey = $this->generateOptimizedCacheKey($tempRequest);

        if (!RedisHelper::exists($cacheKey)) {
            // Pre-generate cache in background if possible
            $this->preWarmFilterCache($tempRequest);
        }

        Log::info("Filter processed in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");

        return redirect()->route('product-grids', $queryParams);
    }

    // Pre-warm cache for filter combinations
    private function preWarmFilterCache(Request $request): void
    {
        try {
            $ttl = $this->getTtlConfig();
            $cacheKey = $this->generateOptimizedCacheKey($request);

            if (!RedisHelper::exists($cacheKey)) {
                $data = $this->fetchOptimizedProductGridsData($request, $ttl);
                $this->cacheCompletePageData($cacheKey, $data, $ttl['product_lists']);
            }
        } catch (\Exception $e) {
            Log::warning("Failed to pre-warm filter cache: " . $e->getMessage());
        }
    }

    /**
     * Restores paginator from cached data with relationships
     */
    private function restorePaginatorFromCache(array $paginationData, Request $request)
    {
        // Convert cached items back to objects with relationships
        $items = collect($paginationData['items'])->map(function ($item) {
            $product = (object) $item;

            // Restore images relationship
            $product->images = collect($item['images'] ?? [])->map(function ($image) {
                return (object) $image;
            });

            // Restore cat_info relationship
            if (isset($item['cat_info']) && $item['cat_info']) {
                $product->cat_info = (object) $item['cat_info'];
            }

            // Restore brand relationship
            if (isset($item['brand']) && $item['brand']) {
                $product->brand = (object) $item['brand'];
            }

            return $product;
        });

        $paginator = new LengthAwarePaginator(
            $items,
            $paginationData['total'],
            $paginationData['per_page'],
            $paginationData['current_page'],
            [
                'path' => $request->url(),
                'pageName' => 'page',
            ]
        );

        $paginator->appends($request->except('page'));
        return $paginator;
    }

    // Batch cache warming for common combinations
    public function warmUpCommonFilters(): array
    {
        $results = [];
        $ttl = $this->getTtlConfig();

        // Common filter combinations
        $commonFilters = [
            ['show' => '9', 'page' => '1'],
            ['show' => '15', 'page' => '1'],
            ['show' => '21', 'page' => '1'],
            ['sortBy' => 'price', 'show' => '9', 'page' => '1'],
            ['sortBy' => 'title', 'show' => '9', 'page' => '1'],
            ['sortBy' => 'category', 'show' => '9', 'page' => '1'],
        ];

        foreach ($commonFilters as $index => $params) {
            $startTime = microtime(true);
            try {
                $request = new Request($params);
                $cacheKey = $this->generateOptimizedCacheKey($request);

                if (!RedisHelper::exists($cacheKey)) {
                    $data = $this->fetchOptimizedProductGridsData($request, $ttl);
                    $this->cacheCompletePageData($cacheKey, $data, $ttl['product_lists']);
                }

                $results["filter_combo_{$index}"] = [
                    'status' => 'success',
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'params' => $params
                ];
            } catch (\Exception $e) {
                $results["filter_combo_{$index}"] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'params' => $params
                ];
            }
        }

        return $results;
    }

    // Clear optimized cache
    public function clearOptimizedCache(): bool
    {
        try {
            $patterns = [
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'complete_page:*',
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'cat_ids:*',
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'brand_ids:*',
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products',
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_*',
                self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price'
            ];

            $clearedKeys = 0;
            foreach ($patterns as $pattern) {
                $keys = RedisHelper::keys($pattern);
                if (!empty($keys)) {
                    RedisHelper::forgetMany($keys);
                    $clearedKeys += count($keys);
                }
            }

            Log::info("Optimized cache cleared. Keys: {$clearedKeys}");
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear optimized cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Performs enhanced product search with Elasticsearch.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productSearch(Request $request)
    {
        $query = $request->input('search', '');
        $perPage = 9;
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
            $searchResult = $this->searchService->search($query, $perPage, $request->input('page', 1));
            $products = new LengthAwarePaginator(
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
     * Provides autocomplete suggestions for product search.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function autocomplete(Request $request)
    {
        $query = $request->input('q', '');
        Log::info('Autocomplete query: ' . $query);

        if (strlen($query) < 2) {
            return response()->json(['success' => false, 'suggestions' => []]);
        }

        try {
            if (isset($this->searchService)) {
                $suggestions = $this->searchService->getAutocomplete($query, 10);
            } else {
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

            return response()->json(['success' => true, 'suggestions' => $suggestions]);
        } catch (\Exception $e) {
            Log::error('Autocomplete error: ' . $e->getMessage());
            return response()->json(['success' => false, 'suggestions' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Retrieves cache health status for homepage and product grids.
     *
     * @return array Cache health status
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

        foreach ($homepageKeys as $name => $key) {
            $exists = RedisHelper::exists($key);
            $health['homepage'][$name] = [
                'cached' => $exists,
                'key' => $key
            ];
        }

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

    /**
     * Renders the about us page.
     *
     * @return \Illuminate\View\View
     */
    public function aboutUs()
    {
        return view('frontend.pages.about-us');
    }

    /**
     * Renders the contact page.
     *
     * @return \Illuminate\View\View
     */
    public function contact()
    {
        return view('frontend.pages.contact');
    }

    /**
     * Displays product details by slug.
     *
     * @param string $slug
     * @return \Illuminate\View\View
     */
    public function productDetail($slug)
    {
        $product_detail = Product::getProductBySlug($slug);
        return view('frontend.pages.product_detail')->with('product_detail', $product_detail);
    }

    /**
     * Renders the product lists page with filtering.
     *
     * @return \Illuminate\View\View
     */
    public function productLists()
    {
        $products = Product::query();

        if (!empty($_GET['category'])) {
            $slug = explode(',', $_GET['category']);
            $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            $products->whereIn('cat_id', $cat_ids);
        }

        if (!empty($_GET['brand'])) {
            $slugs = explode(',', $_GET['brand']);
            $brand_ids = Brand::select('id')->whereIn('slug', $slugs)->pluck('id')->toArray();
            $products->whereIn('brand_id', $brand_ids);
        }

        if (!empty($_GET['sortBy'])) {
            if ($_GET['sortBy'] == 'title') {
                $products->where('status', 'active')->orderBy('title', 'ASC');
            } elseif ($_GET['sortBy'] == 'price') {
                $products->orderBy('price', 'ASC');
            }
        }

        if (!empty($_GET['price'])) {
            $price = explode('-', $_GET['price']);
            if (count($price) === 2 && is_numeric($price[0]) && is_numeric($price[1])) {
                $products->whereBetween('price', [(float)$price[0], (float)$price[1]]);
            }
        }

        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $products = $products->where('status', 'active')->paginate($_GET['show'] ?? 6);

        return view('frontend.pages.product-lists')
            ->with('products', $products)
            ->with('recent_products', $recent_products);
    }

    /**
     * Displays products by brand.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productBrand(Request $request)
    {
        $products = Brand::getProductByBrand($request->slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $view = request()->is('e-shop.loc/product-grids') ? 'product-grids' : 'product-lists';

        return view("frontend.pages.{$view}")
            ->with('products', $products->products)
            ->with('recent_products', $recent_products);
    }

    /**
     * Displays products by category.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productCat(Request $request)
    {
        $products = Category::getProductByCat($request->slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $view = request()->is('e-shop.loc/product-grids') ? 'product-grids' : 'product-lists';

        return view("frontend.pages.{$view}")
            ->with('products', $products->products)
            ->with('recent_products', $recent_products);
    }

    /**
     * Displays products by subcategory.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productSubCat(Request $request)
    {
        $products = Category::getProductBySubCat($request->sub_slug);
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $view = request()->is('e-shop.loc/product-grids') ? 'product-grids' : 'product-lists';

        return view("frontend.pages.{$view}")
            ->with('products', $products->sub_products)
            ->with('recent_products', $recent_products);
    }

    /**
     * Renders the blog page with filtering.
     *
     * @return \Illuminate\View\View
     */
    public function blog()
    {
        $post = Post::query();

        if (!empty($_GET['category'])) {
            $slug = explode(',', $_GET['category']);
            $cat_ids = PostCategory::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            $post->whereIn('post_cat_id', $cat_ids);
        }

        if (!empty($_GET['tag'])) {
            $slug = explode(',', $_GET['tag']);
            $tag_ids = PostTag::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            $post->where('post_tag_id', $tag_ids);
        }

        $post = $post->where('status', 'active')->orderBy('id', 'DESC')->paginate($_GET['show'] ?? 9);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog')
            ->with('posts', $post)
            ->with('recent_posts', $rcnt_post);
    }

    /**
     * Displays blog post details by slug.
     *
     * @param string $slug
     * @return \Illuminate\View\View
     */
    public function blogDetail($slug)
    {
        $post = Post::getPostBySlug($slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog-detail')
            ->with('post', $post)
            ->with('recent_posts', $rcnt_post);
    }

    /**
     * Searches blog posts based on query.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function blogSearch(Request $request)
    {
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $posts = Post::orwhere('title', 'like', '%' . $request->search . '%')
            ->orwhere('quote', 'like', '%' . $request->search . '%')
            ->orwhere('summary', 'like', '%' . $request->search . '%')
            ->orwhere('description', 'like', '%' . $request->search . '%')
            ->orwhere('slug', 'like', '%' . $request->search . '%')
            ->orderBy('id', 'DESC')
            ->paginate(8);

        return view('frontend.pages.blog')
            ->with('posts', $posts)
            ->with('recent_posts', $rcnt_post);
    }

    /**
     * Filters blog posts by category and tag.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function blogFilter(Request $request)
    {
        $data = $request->all();
        $catURL = !empty($data['category']) ? '&category=' . implode(',', $data['category']) : '';
        $tagURL = !empty($data['tag']) ? '&tag=' . implode(',', $data['tag']) : '';

        return redirect()->route('blog', $catURL . $tagURL);
    }

    /**
     * Displays blog posts by category.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function blogByCategory(Request $request)
    {
        $post = PostCategory::getBlogByCategory($request->slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog')
            ->with('posts', $post->post)
            ->with('recent_posts', $rcnt_post);
    }

    /**
     * Displays blog posts by tag.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function blogByTag(Request $request)
    {
        $post = Post::getBlogByTag($request->slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog')
            ->with('posts', $post)
            ->with('recent_posts', $rcnt_post);
    }

    /**
     * Renders the login page.
     *
     * @return \Illuminate\View\View
     */
    public function login()
    {
        return view('frontend.pages.login');
    }

    /**
     * Handles login submission.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginSubmit(Request $request)
    {
        $data = $request->all();
        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'])) {
            Session::put('user', $data['email']);
            request()->session()->flash('success', 'Successfully login');
            return redirect()->route('home');
        }

        request()->session()->flash('error', 'Invalid email and password please try again!');
        return redirect()->back();
    }

    /**
     * Logs out the user.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        Session::forget('user');
        Auth::logout();
        request()->session()->flash('success', 'Logout successfully');
        return back();
    }

    /**
     * Renders the registration page.
     *
     * @return \Illuminate\View\View
     */
    public function register()
    {
        return view('frontend.pages.register');
    }

    /**
     * Handles registration submission.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function registerSubmit(Request $request)
    {
        $this->validate($request, [
            'name' => 'string|required|min:2',
            'email' => 'string|required|unique:users,email',
            'password' => 'required|min:6|confirmed',
        ]);

        $data = $request->all();
        $check = $this->create($data);
        Session::put('user', $data['email']);

        if ($check) {
            request()->session()->flash('success', 'Successfully registered');
            return redirect()->route('home');
        }

        request()->session()->flash('error', 'Please try again!');
        return back();
    }

    /**
     * Creates a new user.
     *
     * @param array $data
     * @return \App\User
     */
    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active'
        ]);
    }

    /**
     * Renders the password reset form.
     *
     * @return \Illuminate\View\View
     */
    public function showResetForm()
    {
        return view('auth.passwords.old-reset');
    }

    /**
     * Handles newsletter subscription.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function subscribe(Request $request)
    {
        if (!Newsletter::isSubscribed($request->email)) {
            Newsletter::subscribePending($request->email);
            if (Newsletter::lastActionSucceeded()) {
                request()->session()->flash('success', 'Subscribed! Please check your email');
                return redirect()->route('home');
            }

            request()->session()->flash('error', 'Something went wrong! please try again');
            return back();
        }

        request()->session()->flash('error', 'Already Subscribed');
        return back();
    }
}
