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
use App\Services\RecentProductService;
use App\User;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Spatie\Newsletter\Facades\Newsletter;

class FrontendController extends Controller
{
    private const RECENT_PRODUCTS_CACHE_PREFIX = 'cache:recent_products:';
    private const HOMEPAGE_CACHE_PREFIX = 'cache:homepage:';
    private const PRODUCT_GRIDS_CACHE_PREFIX = 'cache:product_grids:';
    private static ?array $ttlConfig = null;
    protected $recentProductService;

    private ProductSearchService $searchService;

    public function __construct(ProductSearchService $searchService, RecentProductService $recentProductService)
    {
        $this->searchService = $searchService;
        $this->recentProductService = $recentProductService;
    }

    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    public function home()
    {
        $ttl = $this->getTtlConfig();
        $cacheKeys = [
            'categories' => self::HOMEPAGE_CACHE_PREFIX . 'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX . 'banners',
            'products' => self::HOMEPAGE_CACHE_PREFIX . 'product_lists',
            'categoryBanners' => self::HOMEPAGE_CACHE_PREFIX . 'category_banners',
            'featuredCategories' => self::HOMEPAGE_CACHE_PREFIX . 'featured_categories'
        ];

        $cachedData = RedisHelper::mget(array_values($cacheKeys));

        $products = $cachedData[$cacheKeys['products']]
            ?? $this->getHomepageProductsData($cacheKeys['products'], $ttl['product_lists']);

        $usedProductIds = [];
        $dynamicCategoryProducts = [];
        $rootCategories = Category::whereNull('parent_id')->where('status', 'active')->get();

        $totalProductLimit = 60; // Exactly 60 products
        $minProductsPerCategory = 4; // Minimum products per category
        $minAllProducts = 8; // Minimum products in "All Products" section
        $maxAllProducts = 12; // Maximum products in "All Products" section
        $remainingProducts = $totalProductLimit;

        // Step 1: Collect eligible categories with at least 4 products
        $eligibleCategories = [];
        foreach ($rootCategories as $cat) {
            $catProducts = $products->filter(function ($product) use ($cat, &$usedProductIds) {
                return $product->cat_info?->id === $cat->id &&
                    $product->is_featured &&
                    !in_array($product->id, $usedProductIds);
            })->take($minProductsPerCategory);

            if ($catProducts->count() >= $minProductsPerCategory) {
                $eligibleCategories[] = $cat;
            }
        }

        // Step 2: Calculate maximum number of categories with minimum products
        $maxCategories = floor(($totalProductLimit - $minAllProducts) / $minProductsPerCategory);
        $categoryCount = min(count($eligibleCategories), $maxCategories);

        // Step 3: Allocate products in multiples of 4 (4, 8, 12, 16, ...)
        $categoryAssignments = [];
        $totalCategoryProducts = 0;
        if ($categoryCount > 0) {
            // Start with minimum products per category
            $productsPerCategory = array_fill(0, $categoryCount, $minProductsPerCategory);
            $totalCategoryProducts = $categoryCount * $minProductsPerCategory;
            $allProductsCount = $totalProductLimit - $totalCategoryProducts;

            // Adjust if "All Products" is out of range
            while ($allProductsCount > $maxAllProducts && $categoryCount > 0) {
                // Find category to increase products (in multiples of 4)
                for ($i = 0; $i < $categoryCount; $i++) {
                    $productsPerCategory[$i] += 4;
                    $totalCategoryProducts += 4;
                    $allProductsCount = $totalProductLimit - $totalCategoryProducts;
                    if ($allProductsCount <= $maxAllProducts) {
                        break;
                    }
                }
                // If still exceeding, reduce category count
                if ($allProductsCount > $maxAllProducts) {
                    $categoryCount--;
                    $productsPerCategory = array_slice($productsPerCategory, 0, $categoryCount);
                    $totalCategoryProducts = array_sum($productsPerCategory);
                    $allProductsCount = $totalProductLimit - $totalCategoryProducts;
                }
            }

            // Ensure "All Products" meets minimum
            if ($allProductsCount < $minAllProducts && $categoryCount > 0) {
                $categoryCount--;
                $productsPerCategory = array_slice($productsPerCategory, 0, $categoryCount);
                $totalCategoryProducts = array_sum($productsPerCategory);
                $allProductsCount = $totalProductLimit - $totalCategoryProducts;
            }

            // Fill remaining products if needed
            while ($allProductsCount < $maxAllProducts && $totalCategoryProducts > 0) {
                for ($i = 0; $i < $categoryCount; $i++) {
                    $productsPerCategory[$i] += 4;
                    $totalCategoryProducts += 4;
                    $allProductsCount = $totalProductLimit - $totalCategoryProducts;
                    if ($allProductsCount >= $minAllProducts) {
                        break;
                    }
                }
            }

            // Assign categories
            for ($i = 0; $i < $categoryCount; $i++) {
                $cat = $eligibleCategories[$i];
                $catProducts = $products->filter(function ($product) use ($cat, &$usedProductIds) {
                    return $product->cat_info?->id === $cat->id &&
                        $product->is_featured &&
                        !in_array($product->id, $usedProductIds);
                })->take($productsPerCategory[$i]);

                if ($catProducts->count() >= $minProductsPerCategory) {
                    $dynamicCategoryProducts[$cat->slug] = [
                        'title' => $cat->title,
                        'products' => $catProducts
                    ];
                    $usedProductIds = array_merge($usedProductIds, $catProducts->pluck('id')->toArray());
                    $remainingProducts -= $catProducts->count();
                }
            }
        } else {
            // No categories qualify, allocate all to "All Products"
            $allProductsCount = $totalProductLimit;
        }

        // Step 4: Assign remaining products to "All Products" section
        $allProducts = $products->filter(function ($product) use ($usedProductIds) {
            return $product->is_featured && !in_array($product->id, $usedProductIds);
        })->take($remainingProducts);

        $data = [
            'categories' => $cachedData[$cacheKeys['categories']]
                ?? $this->getCategoriesData($cacheKeys['categories'], $ttl['categories']),
            'banners' => $cachedData[$cacheKeys['banners']]
                ?? $this->getBannersData($cacheKeys['banners'], $ttl['banners']),
            'product_lists' => $allProducts,
            'categoryBanners' => $cachedData[$cacheKeys['categoryBanners']]
                ?? $this->getCategoryBannersData($cacheKeys['categoryBanners'], $cachedData[$cacheKeys['categories']] ?? null, $ttl['categories']),
            'featuredCategories' => $cachedData[$cacheKeys['featuredCategories']]
                ?? $this->getFeaturedCategoriesData($cacheKeys['featuredCategories'], $ttl['categories']),
            'dynamicCategoryProducts' => $dynamicCategoryProducts
        ];

        return view('frontend.index', $data);
    }

    protected function getFeaturedCategoriesData(string $key, int $ttl)
    {
        $featuredCategories = Category::whereNull('parent_id')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->limit(3) // Change this number to adjust how many featured categories to show
            ->with(['products' => function ($query) {
                $query->where('status', 'active')
                    ->orderBy('id', 'DESC')
                    ->take(12); // Products per category (adjustable)
            }])
            ->get();

        RedisHelper::put($key, $featuredCategories, $ttl);

        return $featuredCategories;
    }

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
            Log::info('Homepage cache cleared successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear homepage cache: ' . $e->getMessage());
            return false;
        }
    }

    private function getTtlConfig(): array
    {
        if (self::$ttlConfig === null) {
            self::$ttlConfig = config('cache_keys.ttl');
        }
        return self::$ttlConfig;
    }

    private function getCategoriesData(string $key, int $ttl)
    {
        $redisData = RedisHelper::get($key);
        if ($redisData) {
            return $redisData;
        }

        $categories = Category::select(['id', 'title', 'slug', 'parent_id', 'photo'])
            ->active()
            ->with([
                'children' => fn($q) => $q->active()
                    ->select(['id', 'title', 'slug', 'parent_id'])
                    ->orderBy('title')
            ])
            ->orderBy('title')
            ->get();

        RedisHelper::put($key, $categories, $ttl);
        return $categories;
    }

    private function getBannersData(string $key, int $ttl)
    {
        $redisData = RedisHelper::get($key);
        if ($redisData) {
            return $redisData;
        }

        $banners = Banner::with(['discounts.categories'])
            ->where('status', 'active')
            ->select(['id', 'title', 'description', 'photo', 'link_type', 'link'])
            ->orderByDesc('id')
            ->get();

        RedisHelper::put($key, $banners, $ttl);
        return $banners;
    }

    private function getHomepageProductsData(string $key, int $ttl)
    {
        $redisData = RedisHelper::get($key);
        if ($redisData) {
            return $redisData;
        }

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
            'summary',
            'is_featured'
        ])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->with([
                'images' => fn($q) => $q->select(['id', 'image_path', 'product_id']),
                'cat_info' => fn($q) => $q->select(['id', 'title'])
            ])
            ->latest('id')
            ->limit(350)
            ->get();

        RedisHelper::put($key, $products, $ttl);
        return $products;
    }

    private function getCategoryBannersData(string $key, $categories, int $ttl)
    {
        if (!$categories) {
            return collect();
        }

        $redisData = RedisHelper::get($key);
        if ($redisData) {
            return $redisData;
        }

        $categoryBanners = $categories->filter(fn($cat) => !empty($cat->photo));
        RedisHelper::put($key, $categoryBanners, $ttl);
        return $categoryBanners;
    }

    // Product Grids Related Functions

    public function productGrids(Request $request)
    {
        $startTime = microtime(true);
        $ttl = $this->getTtlConfig();
        $cacheKey = $this->generateOptimizedCacheKey($request);
        $cachedData = RedisHelper::get($cacheKey);

        if ($cachedData) {
            if (isset($cachedData['products_data'])) {
                $cachedData['products'] = $this->restorePaginatorFromCache($cachedData['products_data'], $request);
                unset($cachedData['products_data']);
            }

            $cachedData = $this->hydrateRelationshipsFromCache($cachedData);
            Log::info("Product grids served from cache in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
            return view('frontend.pages.product-grids', $cachedData);
        }

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

        $params = array_filter($params, fn($value) => !empty($value));
        $paramString = http_build_query($params);
        $hash = md5($paramString);

        return self::PRODUCT_GRIDS_CACHE_PREFIX . 'complete_page:' . $hash;
    }

    private function fetchOptimizedProductGridsData(Request $request, array $ttl): array
    {
        $sidebarCacheKeys = [
            'recent_products' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'recent_products',
            'categories' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_categories',
            'brands' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'sidebar_brands',
            'max_price' => self::PRODUCT_GRIDS_CACHE_PREFIX . 'max_price'
        ];

        $cachedSidebarData = RedisHelper::mget(array_values($sidebarCacheKeys));

        $sidebarData = [
            'recent_products' => $cachedSidebarData[$sidebarCacheKeys['recent_products']] ?? $this->getRecentProductsOptimized($ttl['product_lists']),
            'categories' => $cachedSidebarData[$sidebarCacheKeys['categories']] ?? $this->getSidebarCategoriesOptimized($ttl['categories']),
            'brands' => $cachedSidebarData[$sidebarCacheKeys['brands']] ?? $this->getSidebarBrandsOptimized($ttl['categories']),
            'max_price' => $cachedSidebarData[$sidebarCacheKeys['max_price']] ?? $this->getMaxPriceOptimized($ttl['product_lists'])
        ];

        $products = $this->buildOptimizedProductsQuery($request);
        return array_merge($sidebarData, ['products' => $products]);
    }

    private function buildOptimizedProductsQuery(Request $request)
    {
        $query = Product::query();
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

        $this->applySortingOptimized($query, $request->get('sortBy'));

        if ($priceRange = $request->get('price')) {
            $this->applyPriceFilter($query, $priceRange);
        }

        $query->where('products.status', 'active')
            ->with([
                'images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->orderBy('is_primary', 'desc'),
                'cat_info' => fn($q) => $q->select(['id', 'title', 'slug']),
                'brand' => fn($q) => $q->select(['id', 'title', 'slug'])
            ]);

        $perPage = min((int)$request->get('show', 9), 30);
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

    private function applyPriceFilter($query, $priceRange)
    {
        $prices = explode('-', $priceRange);
        if (count($prices) === 2 && is_numeric($prices[0]) && is_numeric($prices[1])) {
            $query->whereBetween('products.price', [(float)$prices[0], (float)$prices[1]]);
        }
    }

    private function getCategoryIdsOptimized(string $categoryParam): array
    {
        $slugs = explode(',', $categoryParam);
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'cat_ids:' . md5(implode(',', $slugs));

        return RedisHelper::remember($cacheKey, 3600, function () use ($slugs) {
            return Category::whereIn('slug', $slugs)->pluck('id')->toArray();
        });
    }

    private function getBrandIdsOptimized(string $brandParam): array
    {
        $slugs = explode(',', $brandParam);
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . 'brand_ids:' . md5(implode(',', $slugs));

        return RedisHelper::remember($cacheKey, 3600, function () use ($slugs) {
            return Brand::whereIn('slug', $slugs)->pluck('id')->toArray();
        });
    }

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
            $categories = Category::select(['id', 'title', 'slug', 'parent_id'])
                ->active()
                ->with([
                    'children' => function ($q) {
                        $q->active()
                            ->select(['id', 'title', 'slug', 'parent_id'])
                            ->orderBy('title');
                    }
                ])
                ->orderBy('title')
                ->get();

            return $categories->map(function ($category) {
                return [
                    'id' => $category->id,
                    'title' => $category->title,
                    'slug' => $category->slug,
                    'parent_id' => $category->parent_id,
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

    private function hydrateRelationshipsFromCache(array $cachedData): array
    {
        if (isset($cachedData['recent_products']) && is_array($cachedData['recent_products'])) {
            $cachedData['recent_products'] = collect($cachedData['recent_products'])->map(function ($product) {
                $productObj = (object) $product;
                $productObj->images = collect($product['images'] ?? [])->map(function ($image) {
                    return (object) $image;
                });
                return $productObj;
            });
        }

        if (isset($cachedData['categories']) && is_array($cachedData['categories'])) {
            $cachedData['categories'] = collect($cachedData['categories'])->map(function ($category) {
                $categoryObj = (object) $category;
                $categoryObj->children = collect($category['children'] ?? [])->map(function ($child) {
                    return (object) $child;
                });
                return $categoryObj;
            });
        }

        if (isset($cachedData['brands']) && is_array($cachedData['brands'])) {
            $cachedData['brands'] = collect($cachedData['brands'])->map(function ($brand) {
                return (object) $brand;
            });
        }

        return $cachedData;
    }

    private function cacheCompletePageData(string $key, array $data, int $ttl): void
    {
        try {
            $cacheableData = [
                'recent_products' => $data['recent_products'],
                'categories' => $data['categories'],
                'brands' => $data['brands'],
                'max_price' => $data['max_price'],
            ];

            if (isset($data['products']) && $data['products'] instanceof LengthAwarePaginator) {
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

            $cacheTtl = min($ttl, 900);
            RedisHelper::put($key, $cacheableData, $cacheTtl);
        } catch (\Exception $e) {
            Log::error("Failed to cache complete page data: " . $e->getMessage());
        }
    }

    public function productFilter(Request $request)
    {
        $startTime = microtime(true);

        $data = $request->all();
        $queryParams = [];

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

        $tempRequest = new Request($queryParams);
        $cacheKey = $this->generateOptimizedCacheKey($tempRequest);

        if (!RedisHelper::exists($cacheKey)) {
            $this->preWarmFilterCache($tempRequest);
        }

        Log::info("Filter processed in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");

        return redirect()->route('product-grids', $queryParams);
    }

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

    private function restorePaginatorFromCache(array $paginationData, Request $request)
    {
        $items = collect($paginationData['items'])->map(function ($item) {
            $product = (object) $item;

            $product->images = collect($item['images'] ?? [])->map(function ($image) {
                return (object) $image;
            });

            if (isset($item['cat_info']) && $item['cat_info']) {
                $product->cat_info = (object) $item['cat_info'];
            }

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

    public function warmUpCommonFilters(): array
    {
        $results = [];
        $ttl = $this->getTtlConfig();

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

    public function autocomplete(Request $request)
    {
        $query = $request->input('q', '');

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
            return response()->json(['success' => false, 'suggestions' => [], 'error' => $e->getMessage()]);
        }
    }

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
        return view('frontend.pages.product_detail')->with('product_detail', $product_detail);
    }

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

    public function productBrand(Request $request)
    {
        $products = Brand::getProductByBrand($request->slug);
        return view('frontend.pages.product-grids', compact('products'));
    }

    public function productCat(Request $request)
    {
        $startTime = microtime(true);
        $category = Category::where('slug', $request->slug)->firstOrFail();
        $ttl = $this->getTtlConfig();

        // Simple cache key generation
        $show = $request->show ?? 12;
        $page = $request->page ?? 1;
        $sortBy = $request->sortBy ?? '';
        $price = $request->price ?? '';

        // Create cache key for complete page
        $cacheKey = self::PRODUCT_GRIDS_CACHE_PREFIX . "category_{$category->id}_show_{$show}_page_{$page}_sort_{$sortBy}_price_{$price}";

        // Try to get from cache first
        $cachedData = RedisHelper::get($cacheKey);

        if ($cachedData && is_array($cachedData)) {
            Log::info("Product category served from cache in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
            return view('frontend.pages.product-grids', $cachedData);
        }

        // If not cached, fetch fresh data
        $data = $this->fetchCategoryPageData($category, $show, $page, $sortBy, $price, $ttl);
        // dd($data);
        // Cache the complete page data
        if (!RedisHelper::put($cacheKey, $data, $ttl['product_lists'])) {
            Log::warning("Failed to cache category page data for key: {$cacheKey}");
        }

        Log::info("Product category served fresh in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
        return view('frontend.pages.product-grids', $data);
    }

    private function fetchCategoryPageData($category, $show, $page, $sortBy, $price, $ttl)
    {
        // Get recent products with caching
        $recentProductsKey = self::RECENT_PRODUCTS_CACHE_PREFIX . 'latest_3';
        $recentProducts = RedisHelper::get($recentProductsKey);

        if (!$recentProducts) {
            $recentProducts = $this->getRecentProductsData($recentProductsKey, $ttl['product_lists']);
        }

        // Build category products query
        $productsQuery = Product::where('status', 'active')
            ->where('cat_id', $category->id)
            ->select([
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
            ->with([
                'images' => fn($q) => $q->select(['id', 'image_path', 'product_id']),
                'cat_info' => fn($q) => $q->select(['id', 'title'])
            ]);

        // Apply sorting
        if ($sortBy) {
            switch ($sortBy) {
                case 'title':
                    $productsQuery->orderBy('title', 'ASC');
                    break;
                case 'price':
                    $productsQuery->orderBy('price', 'ASC');
                    break;
                case 'price_desc':
                    $productsQuery->orderBy('price', 'DESC');
                    break;
                default:
                    $productsQuery->orderBy('id', 'DESC');
            }
        } else {
            $productsQuery->orderBy('id', 'DESC');
        }

        // Apply price filter
        if ($price && str_contains($price, '-')) {
            $priceRange = explode('-', $price);
            if (count($priceRange) === 2 && is_numeric($priceRange[0]) && is_numeric($priceRange[1])) {
                $productsQuery->whereBetween('price', [(float)$priceRange[0], (float)$priceRange[1]]);
            }
        }

        // Get paginated products
        $products = $productsQuery->paginate($show, ['*'], 'page', $page);

        // Transform products to include calculated discount prices and wishlist status
        $products->getCollection()->transform(function ($product) {
            // Calculate discounted price
            if ($product->discount > 0) {
                $product->discounted_price = $product->price - ($product->price * $product->discount / 100);
            } else {
                $product->discounted_price = $product->price;
            }

            // Check if product is in wishlist (assuming Helper::isProductInWishlist exists)
            if (class_exists('Helper') && method_exists('Helper', 'isProductInWishlist')) {
                $product->in_wishlist = Helper::isProductInWishlist($product->slug);
            } else {
                $product->in_wishlist = false;
            }

            return $product;
        });

        return [
            'products' => $products,
            'recent_products' => $recentProducts,
            'category' => $category, // Add category data for breadcrumbs/page title
            'show' => $show,
            'sortBy' => $sortBy,
            'price' => $price
        ];
    }

    private function getRecentProductsData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $recentProducts = Product::where('status', 'active')
                ->select([
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
                ->with([
                    'images' => fn($q) => $q->select(['id', 'image_path', 'product_id']),
                    'cat_info' => fn($q) => $q->select(['id', 'title'])
                ])
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            // Transform recent products to include calculated prices and wishlist status
            $recentProducts->transform(function ($product) {
                // Calculate discounted price
                if ($product->discount > 0) {
                    $product->discounted_price = $product->price - ($product->price * $product->discount / 100);
                } else {
                    $product->discounted_price = $product->price;
                }

                // Check if product is in wishlist
                if (class_exists('Helper') && method_exists('Helper', 'isProductInWishlist')) {
                    $product->in_wishlist = Helper::isProductInWishlist($product->slug);
                } else {
                    $product->in_wishlist = false;
                }

                return $product;
            });

            // Store in Redis with error handling
            if (!RedisHelper::put($key, $recentProducts, $ttl)) {
                Log::warning("Failed to store recent products in Redis for key: {$key}");
            }

            return $recentProducts;
        });
    }


    public function productSubCat(Request $request, $subCategoryId)
    {
        $startTime = microtime(true);

        $category = Category::where('slug', $request->slug)->firstOrFail();

        $subCategory = Category::where('slug', $request->sub_slug)
            ->where('parent_id', $category->id)
            ->firstOrFail();
        $category = $subCategory->parent;

        $show   = max((int) $request->input('show', 21), 1);
        $page   = (int) $request->input('page', 1);
        $sortBy = $request->input('sortBy', 'default');
        $price  = $request->input('price', '');

        $minPrice = null;
        $maxPrice = null;
        if ($price && str_contains($price, '-')) {
            [$minPrice, $maxPrice] = explode('-', $price);
            $minPrice = (float) $minPrice;
            $maxPrice = (float) $maxPrice;
        }

        $cacheKey = "cached_products_cat{$category->id}_childcat{$subCategory->id}_page{$page}_limit{$show}_sort{$sortBy}";

        if ($minPrice && $maxPrice) {
            $cacheKey .= "_min{$minPrice}_max{$maxPrice}";
        } else {
            $cacheKey .= "_min_max";
        }
        $ttl = $this->getTtlConfig();

        if (RedisHelper::has($cacheKey)) {
            $cachedData = RedisHelper::get($cacheKey);

            // Convert cached products array back to paginator
            if (isset($cachedData['products']) && isset($cachedData['recent_products'])) {
                $paginatorData = $cachedData['products'];

                // Create paginator from cached data
                $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
                    collect($paginatorData['data'])->map(function ($item) {
                        // Convert images back to objects
                        if (isset($item['images'])) {
                            $item['images'] = collect($item['images'])->map(fn($img) => (object) $img);
                        }
                        return (object) $item;
                    }),
                    $paginatorData['total'],
                    $paginatorData['per_page'],
                    $paginatorData['current_page'],
                    [
                        'path' => request()->url(),
                        'query' => request()->query()
                    ]
                );

                return view('frontend.pages.product-grids', [
                    'products'        => $paginated,
                    'recent_products' => collect($cachedData['recent_products'])->map(fn($item) => (object) $item),
                    'category'        => $category,
                    'subCategory'     => $subCategory,
                    'show'            => $show,
                    'sortBy'          => $sortBy,
                    'price'           => $price,
                ]);
            }
        }

        $query = Product::with(['images', 'discounts', 'cat_info', 'sub_cat_info'])
            ->active()
            ->where('cat_id', $category->id)
            ->where('child_cat_id', $subCategory->id);

        if ($minPrice && $maxPrice) {
            $query->whereBetween('price', [$minPrice, $maxPrice]);
        }

        if ($sortBy === 'price') {
            $query->orderBy('price', 'ASC');
        } elseif ($sortBy === 'price_desc') {
            $query->orderBy('price', 'DESC');
        } else {
            $query->latest();
        }

        $products = $query->paginate($show);
        $recent_products = Product::with(['images'])->active()->latest()->take(3)->get();

        $cacheData = [
            'products' => $products->toArray(),
            'recent_products' => $recent_products->toArray(),
            'limit' => $show,
            'sort' => $sortBy,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
        ];

        RedisHelper::put($cacheKey, $cacheData, $ttl['product_grids']);

        return view('frontend.pages.product-grids', [
            'products'        => $products,
            'recent_products' => $recent_products,
            'category'        => $category,
            'subCategory'     => $subCategory,
            'show'            => $show,
            'sortBy'          => $sortBy,
            'price'           => $price,
        ]);
    }

    // public function productSubCat(Request $request)
    // {
    //     dd($request->all());
    //     $products = Category::getProductBySubCat($request->sub_slug);
    //     $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
    //     $view = request()->is('e-shop.loc/product-grids') ? 'product-grids' : 'product-lists';

    //     return view("frontend.pages.{$view}")
    //         ->with('products', $products->sub_products)
    //         ->with('recent_products', $recent_products);
    // }

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

    public function blogDetail($slug)
    {
        $post = Post::getPostBySlug($slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog-detail')
            ->with('post', $post)
            ->with('recent_posts', $rcnt_post);
    }

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

    public function blogFilter(Request $request)
    {
        $data = $request->all();
        $catURL = !empty($data['category']) ? '&category=' . implode(',', $data['category']) : '';
        $tagURL = !empty($data['tag']) ? '&tag=' . implode(',', $data['tag']) : '';

        return redirect()->route('blog', $catURL . $tagURL);
    }

    public function blogByCategory(Request $request)
    {
        $post = PostCategory::getBlogByCategory($request->slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog')
            ->with('posts', $post->post)
            ->with('recent_posts', $rcnt_post);
    }

    public function blogByTag(Request $request)
    {
        $post = Post::getBlogByTag($request->slug);
        $rcnt_post = Post::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();

        return view('frontend.pages.blog')
            ->with('posts', $post)
            ->with('recent_posts', $rcnt_post);
    }

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
        }

        request()->session()->flash('error', 'Invalid email and password please try again!');
        return redirect()->back();
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

    public function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => 'active'
        ]);
    }

    public function showResetForm()
    {
        return view('auth.passwords.old-reset');
    }

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

    public function cart(Request $request)
    {
        if (!$request->session()->has('coupon_set')) {
            session()->forget('coupon');
            session()->put('coupon_set', true);
        }

        return view('frontend.pages.cart');
    }
}
