<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use App\Helpers\UrlEncryptor;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantType;
use App\Models\VariantImage;
use App\Services\ProductFilterService;
use App\Services\ProductSearchService;
use App\Services\RecentProductService;
use App\User;
use Helper;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Spatie\Newsletter\Facades\Newsletter;

/**
 * FrontendController handles the frontend logic for the e-commerce application.
 * It manages the homepage, product grids, filtering, search, and user authentication.
 */
class FrontendController extends Controller
{
    private const RECENT_PRODUCTS_CACHE_PREFIX = 'cache:recent_products:';
    private const HOMEPAGE_CACHE_PREFIX = 'cache:homepage:';
    private const PRODUCT_GRIDS_CACHE_PREFIX = 'cache:product_grids:';
    private const CACHE_TTL = 3600; // Default cache TTL in seconds
    private static ?array $ttlConfig = null;

    protected $recentProductService;
    private ProductSearchService $searchService;

    /**
     * Constructor to initialize services.
     *
     * @param ProductSearchService $searchService
     * @param RecentProductService $recentProductService
     */
    public function __construct(ProductSearchService $searchService, RecentProductService $recentProductService)
    {
        $this->searchService = $searchService;
        $this->recentProductService = $recentProductService;
    }

    /**
     * Redirect authenticated users to their role-based route.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    /**
     * Display the homepage with ultra-fast Redis caching.
     * Optimized for 10M+ products with multi-tier caching strategy.
     * Target: < 15ms response time with cache hit, < 300ms with cache miss
     *
     * @return \Illuminate\View\View
     */
    public function home()
    {
        $startTime = microtime(true);

        // Get cache version for versioned cache invalidation
        $cacheVersion = RedisHelper::getVersion('meta:cache:version');
        $fullPageKey = self::HOMEPAGE_CACHE_PREFIX . "full_page_v{$cacheVersion}";

        // TIER 1: Try full page cache first (FASTEST PATH - 1-5ms)
        $cachedPage = RedisHelper::get($fullPageKey);
        if ($cachedPage !== null) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            Log::debug("Homepage: Full cache hit", [
                'duration_ms' => $duration,
                'cache_age_seconds' => RedisHelper::ttl($fullPageKey),
                'version' => $cacheVersion,
            ]);

            return view('frontend.index', $cachedPage);
        }

        // TIER 2: Full page cache miss - build from components (FAST PATH - 20-50ms)
        Log::info("Homepage: Full cache miss, building from components");

        $ttl = $this->getTtlConfig();

        // Define component cache keys
        $cacheKeys = [
            'categories' => self::HOMEPAGE_CACHE_PREFIX . 'categories',
            'banners' => self::HOMEPAGE_CACHE_PREFIX . 'banners',
            'featured' => self::HOMEPAGE_CACHE_PREFIX . 'products:featured',
            'categoryProducts' => self::HOMEPAGE_CACHE_PREFIX . 'category_products',
        ];

        // Batch fetch all components in one Redis call (pipeline optimization)
        $cachedComponents = RedisHelper::mget(array_values($cacheKeys));

        // Get or build each component
        $categories = $cachedComponents[$cacheKeys['categories']]
            ?? $this->getCategoriesData($cacheKeys['categories'], $ttl['categories']);

        $banners = $cachedComponents[$cacheKeys['banners']]
            ?? $this->getBannersData($cacheKeys['banners'], $ttl['banners']);

        $featuredProducts = $cachedComponents[$cacheKeys['featured']]
            ?? $this->getHomepageProductsData($cacheKeys['featured'], $ttl['product_lists']);

        $categoryProducts = $cachedComponents[$cacheKeys['categoryProducts']]
            ?? $this->getHomepageCategoryProducts($cacheKeys['categoryProducts'], $ttl['product_lists']);

        // Assemble final data structure
        $data = [
            'version' => $cacheVersion,
            'generated_at' => now()->toIso8601String(),
            'categories' => $categories,
            'banners' => $banners,
            'product_lists' => is_array($featuredProducts) ? array_slice($featuredProducts, 0, 12) : collect($featuredProducts)->take(12)->all(),
            'dynamicCategoryProducts' => $categoryProducts,
        ];

        // Cache complete page for ultra-fast subsequent requests (30 min TTL)
        RedisHelper::put($fullPageKey, $data, 1800);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("Homepage: Built and cached", [
            'duration_ms' => $duration,
            'cache_version' => $cacheVersion,
            'components_from_cache' => [
                'categories' => $cachedComponents[$cacheKeys['categories']] !== null,
                'banners' => $cachedComponents[$cacheKeys['banners']] !== null,
                'featured' => $cachedComponents[$cacheKeys['featured']] !== null,
                'category_products' => $cachedComponents[$cacheKeys['categoryProducts']] !== null,
            ],
        ]);

        return view('frontend.index', $data);
    }

    /**
     * Fetch featured categories with their products.
     *
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
    protected function getFeaturedCategoriesData(string $key, int $ttl)
    {
        $featuredCategories = Category::whereNull('parent_id')
            ->where('is_featured', true)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->limit(3)
            ->with(['products' => function ($query) {
                $query->where('status', 'active')
                    ->orderBy('id', 'DESC')
                    ->take(12);
            }])
            ->get();

        RedisHelper::put($key, $featuredCategories, $ttl);
        return $featuredCategories;
    }

    /**
     * Clear homepage cache.
     *
     * @return bool
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
            Log::info('Homepage cache cleared successfully');
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear homepage cache: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get TTL configuration from cache settings.
     *
     * @return array
     */
    private function getTtlConfig(): array
    {
        if (self::$ttlConfig === null) {
            self::$ttlConfig = config('cache_keys.ttl');
        }
        return self::$ttlConfig;
    }

    /**
     * Fetch categories data with caching.
     * Optimized: Minimal data fetching for homepage display
     *
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
    private function getCategoriesData(string $key, int $ttl)
    {
        // Query only active parent categories with minimal fields
        $categories = Category::select(['id', 'title', 'slug', 'photo'])
            ->whereNull('parent_id') // Only root categories for homepage
            ->where('status', 'active')
            ->orderBy('title', 'asc')
            ->limit(10) // Limit to 10 categories for homepage
            ->get();

        RedisHelper::put($key, $categories, $ttl);
        return $categories;
    }

    /**
     * Fetch banners data with caching.
     * Optimized: Only active banners with minimal eager loading
     *
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
    private function getBannersData(string $key, int $ttl)
    {
        $banners = Banner::select(['id', 'title', 'slug', 'photo', 'description', 'status', 'link_type', 'link'])
            ->with(['discounts' => fn($q) => $q->select(['discounts.id', 'discounts.title', 'discounts.type', 'discounts.value'])
                ->with(['categories' => fn($q2) => $q2->select(['categories.id', 'categories.title', 'categories.slug'])])
            ])
            ->where('status', 'active')
            ->latest('id')
            ->limit(5) // Limit to 5 banners for carousel
            ->get();

        RedisHelper::put($key, $banners, $ttl);
        return $banners;
    }

    /**
     * Get homepage category products (pre-grouped and optimized for display)
     * Optimized for 10M+ products using category-indexed queries
     */
    private function getHomepageCategoryProducts(string $key, int $ttl)
    {
        $startTime = microtime(true);

        // Get active featured categories (small query - fast)
        $categories = Category::select(['id', 'title', 'slug', 'sort_order'])
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->limit(4) // Top 4 categories for homepage
            ->get();

        if ($categories->isEmpty()) {
            RedisHelper::put($key, [], $ttl);
            return [];
        }

        $categoryProducts = [];
        $totalCacheHits = 0;
        $totalCacheMisses = 0;

        foreach ($categories as $category) {
            // STEP 1: Get product IDs for this category (indexed query - O(log n))
            $productIds = DB::table('products')
                ->select('id')
                ->where('cat_id', $category->id) // Uses cat_id index
                ->where('status', 'active')
                ->where('is_featured', 1)
                ->orderBy('id', 'DESC')
                ->limit(8) // 8 products per category
                ->pluck('id')
                ->toArray();

            if (count($productIds) < 4) {
                continue; // Skip categories with less than 4 products
            }

            // STEP 2: Try to fetch from product card cache
            $productCards = $this->batchFetchProductCards($productIds);
            $totalCacheHits += count($productCards);

            // STEP 3: Query database for cache misses
            $missingIds = array_diff($productIds, array_keys($productCards));

            if (!empty($missingIds)) {
                $totalCacheMisses += count($missingIds);

                $products = Product::select([
                    'id', 'title', 'slug', 'base_price', 'base_discount',
                    'base_stock', 'has_variants', 'cat_id', 'condition'
                ])
                    ->whereIn('id', $missingIds)
                    ->with([
                        'images' => fn($q) => $q->orderBy('sort_order', 'asc')
                            ->take(3)
                            ->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                        'variants' => fn($q) => $q->where('status', 'active')
                            ->where('stock', '>', 0)
                            ->select(['id', 'product_id', 'price', 'discount', 'stock', 'status'])
                            ->limit(1)
                            ->with([
                                'images' => fn($q) => $q->orderBy('sort_order', 'asc')
                                    ->take(3)
                                    ->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                            ])
                    ])
                    ->get();

                // Transform and cache
                foreach ($products as $product) {
                    $card = $this->transformProductForDisplay($product);
                    RedisHelper::put("product:card:{$product->id}", $card, 7200);
                    $productCards[$product->id] = $card;
                }
            }

            // STEP 4: Reassemble in correct order
            $orderedProducts = [];
            foreach ($productIds as $id) {
                if (isset($productCards[$id])) {
                    $orderedProducts[] = $productCards[$id];
                }
            }

            $categoryProducts[$category->slug] = [
                'title' => $category->title,
                'products' => $orderedProducts
            ];
        }

        // Cache the complete component
        RedisHelper::put($key, $categoryProducts, $ttl);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        Log::debug("Category products component built in {$duration}ms", [
            'categories_count' => count($categoryProducts),
            'total_products' => array_sum(array_map(fn($cat) => count($cat['products']), $categoryProducts)),
            'cache_hits' => $totalCacheHits,
            'cache_misses' => $totalCacheMisses,
        ]);

        return $categoryProducts;
    }

    /**
     * Get homepage featured products (all products section)
     * Optimized for 10M+ products using indexed queries and entity caching
     */
    private function getHomepageProductsData(string $key, int $ttl)
    {
        $startTime = microtime(true);

        // STEP 1: Get featured product IDs only (fast indexed query - O(log n))
        $productIds = DB::table('products')
            ->select('id')
            ->where('status', 'active')
            ->where('is_featured', 1)
            ->orderBy('id', 'DESC')
            ->limit(20) // Limit to 20 featured products for homepage
            ->pluck('id')
            ->toArray();

        if (empty($productIds)) {
            RedisHelper::put($key, [], $ttl);
            return [];
        }

        // STEP 2: Try to fetch product cards from entity cache
        $productCards = $this->batchFetchProductCards($productIds);

        // STEP 3: Query database for cache misses only
        $missingIds = array_diff($productIds, array_keys($productCards));

        if (!empty($missingIds)) {
            $products = Product::select([
                'id', 'title', 'slug', 'base_price', 'base_discount',
                'base_stock', 'has_variants', 'cat_id', 'condition'
            ])
                ->whereIn('id', $missingIds)
                ->with([
                    'images' => fn($q) => $q->orderBy('sort_order', 'asc')
                        ->take(3)
                        ->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                    'variants' => fn($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock', 'status'])
                        ->where('stock', '>', 0)
                        ->take(1) // Only first variant for homepage
                        ->with([
                            'images' => fn($q) => $q->orderBy('sort_order', 'asc')
                                ->take(3)
                                ->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                        ])
                ])
                ->get();

            // Cache individual product cards and add to results
            foreach ($products as $product) {
                $card = $this->transformProductForDisplay($product);
                RedisHelper::put("product:card:{$product->id}", $card, 7200); // 2 hour TTL
                $productCards[$product->id] = $card;
            }

            Log::debug("Featured products: DB query for " . count($missingIds) . " cache misses");
        }

        // STEP 4: Reassemble in correct order
        $orderedProducts = [];
        foreach ($productIds as $id) {
            if (isset($productCards[$id])) {
                $orderedProducts[] = $productCards[$id];
            }
        }

        // Cache the complete component
        RedisHelper::put($key, $orderedProducts, $ttl);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        Log::debug("Featured products component built in {$duration}ms", [
            'total_products' => count($orderedProducts),
            'cache_hits' => count($productIds) - count($missingIds),
            'cache_misses' => count($missingIds),
        ]);

        return $orderedProducts;
    }

    /**
     * Batch fetch product cards from Redis
     * Uses Redis MGET for efficient bulk retrieval
     */
    private function batchFetchProductCards(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $keys = array_map(fn($id) => "product:card:{$id}", $productIds);
        $cachedData = RedisHelper::mget($keys);

        $products = [];
        foreach ($productIds as $index => $id) {
            if ($cachedData[$keys[$index]] !== null) {
                $products[$id] = $cachedData[$keys[$index]];
            }
        }

        return $products;
    }

    /**
     * Transform product for display (reusable method)
     * Optimized for both variant and non-variant products
     */
    private function transformProductForDisplay($product)
    {
        // Select primary image based on product type
        if ($product->has_variants && $product->variants->count() > 0) {
            // For variant products, use first active variant's image
            $firstVariant = $product->variants->first();
            $primaryImage = $firstVariant?->images->first();
        } else {
            // For simple products, use product's primary image
            $primaryImage = $product->images->first();
        }

        // Set primary_image attribute for blade compatibility
        if ($primaryImage) {
            $product->primary_image = [
                'image_path' => $primaryImage->url,  // Use url accessor (handles storage path)
                'thumbnail_path' => $primaryImage->thumbnail_url ?? $primaryImage->url,
                'alt_text' => $product->title
            ];
        } else {
            $product->primary_image = null;
        }

        // Lightweight aggregation only; pricing & discount calculations moved to frontend
        if ($product->has_variants && $product->variants->count() > 0) {
            $product->stock = $product->variants->sum('stock');
            $product->max_discount = $product->base_discount ?? 0; // expose base discount for badge until JS recalculates
        } else {
            $product->stock = $product->base_stock;
            $product->max_discount = $product->base_discount ?? 0;
        }

        // Remove deprecated server-computed pricing fields to avoid confusion
        unset($product->discounted_price, $product->original_price);

        return $product;
    }

    /**
     * Fetch homepage products data with caching.
     *
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
    private function getHomepageProductsDataOLD(string $key, int $ttl)
    {
        // Check Redis cache first
        // $redisData = RedisHelper::get($key);
        // if ($redisData) {
        //     return $redisData;
        // }

        // Query products with optimized eager loading
        $products = Product::select([
            'id',
            'title',
            'slug',
            'base_price',
            'base_discount',
            'base_stock',
            'has_variants',
            'cat_id',
            'condition',
            'summary',
            'is_featured'
        ])
            ->where('status', 'active')
            ->where('is_featured', true)
            ->with([
                'images' => fn($q) => $q->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->select(['id', 'product_id', 'price', 'discount', 'stock', 'status'])
                    ->with([
                        'images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                            ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                        'primaryImage'
                    ])
            ])
            ->latest('id')
            ->where('has_variants', true)
            // ->where('id', 791)
            ->limit(60)
            ->get();

        // Transform products to include discounted price and primary image
        $products->transform(function ($product) {
            // dd($product->variants);
            // Select primary image (mimic backend product list logic)
            $primaryImage = $product->has_variants && $product->variants->count() > 0
                ? $product->variants->first()->primaryImage
                : $product->primaryImage;

            // Attach primary image to product for frontend use
            $product->primary_image = $primaryImage ? [
                'image_path' => $primaryImage->image_path,
                'thumbnail_path' => $primaryImage->thumbnail_path,
                'url' => $primaryImage->url,
                'thumbnail_url' => $primaryImage->thumbnail_url,
                'alt_text' => $primaryImage->alt_text ?? $product->title
            ] : null;

            // Calculate discounted price
            if ($product->has_variants) {
                $activeInStockVariants = $product->variants->where('status', 'active')->where('stock', '>', 0);

                if ($activeInStockVariants->count() > 0) {
                    // Always get the first variant
                    $firstVariant = $activeInStockVariants->first();

                    $product->original_price = $firstVariant->price;
                    $product->discounted_price = $firstVariant->price * (1 - ($firstVariant->discount ?? 0) / 100);
                    $product->max_discount = $firstVariant->discount ?? 0;
                } else {
                    $product->original_price = null;
                    $product->discounted_price = null;
                    $product->max_discount = 0;
                }
            } else {
                // Simple product (no variants)
                $product->original_price = $product->base_price;
                $product->discounted_price = $product->base_discount > 0
                    ? $product->base_price * (1 - $product->base_discount / 100)
                    : $product->base_price;
                $product->max_discount = $product->base_discount ?? 0;
            }

            // Calculate stock
            $product->stock = $product->has_variants
                ? $product->variants->sum('stock')
                : $product->base_stock;

            return $product;
        });

        // Cache the transformed data
        // RedisHelper::put($key, $products, $ttl);

        return $products;
    }

    /**
     * Fetch category banners data with caching.
     *
     * @param string $key Cache key
     * @param mixed $categories Categories data
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
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

    /**
     * Display product grids with filtering and caching.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
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

    /**
     * Generate optimized cache key for product grids.
     *
     * @param Request $request
     * @param string $slug
     * @return string
     */
    private function generateOptimizedCacheKey(Request $request, $slug = ''): string
    {
        $params = [
            'category_slug' => $slug,
            'category' => $request->input('category', ''),
            'brand' => $request->input('brand', ''),
            'price_range' => $request->input('price_range', ''),
            'min_rating' => $request->input('min_rating', ''),
            'min_discount' => $request->input('min_discount', ''),
            'sortBy' => $request->input('sortBy', 'latest'),
            'show' => $request->input('show', 12),
            'page' => $request->input('page', 1),
            'query' => $request->input('query', ''),
        ];
        return self::PRODUCT_GRIDS_CACHE_PREFIX . md5(json_encode($params));
    }

    private function fetchOptimizedProductGridsData(Request $request, array $ttl)
    {
        $show = $request->input('show', 12);
        $sortBy = $request->input('sortBy', 'latest');
        $query = $request->input('query', '');
        $categories = $request->input('category', []);
        $brands = $request->input('brand', []);
        $priceRange = $request->input('price_range', '');
        $minRatings = $request->input('min_rating', []);
        $minDiscounts = $request->input('min_discount', []);

        $productsQuery = Product::query()
            ->select([
                'products.id',
                'products.title',
                'products.slug',
                'products.base_price',
                'products.base_discount',
                'products.base_stock',
                'products.has_variants',
                'products.cat_id',
                'products.condition',
                'products.is_featured',
                'products.status',
            ])
            ->where('products.status', 'active')
            ->with([
                // Fetch up to 3 product images (primary first) for non-variant scrolling
                'images' => fn($q) => $q->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
                    ->take(3),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                    ->with([
                        // Fetch up to 3 images per active variant (primary first)
                        'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                            ->orderByDesc('is_primary')
                            ->orderBy('sort_order')
                            ->take(3)
                    ])
            ]);

        // Apply filters (categories, brands, price, ratings, discounts)
        if (!empty($categories)) {
            $productsQuery->whereIn('cat_id', Category::whereIn('slug', $categories)->pluck('id'));
        }
        if (!empty($brands)) {
            $productsQuery->whereIn('brand_id', Brand::whereIn('slug', $brands)->pluck('id'));
        }

        if ($priceRange) {
            [$minPrice, $maxPrice] = explode('-', $priceRange);
            $productsQuery->where(function ($query) use ($minPrice, $maxPrice) {
                $query->where(function ($q) use ($minPrice, $maxPrice) {
                    $q->where('has_variants', false)
                        ->whereRaw('
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END BETWEEN ? AND ?', [(float)$minPrice, (float)$maxPrice]);
                })
                    ->orWhere(function ($q) use ($minPrice, $maxPrice) {
                        $q->where('has_variants', true)
                            ->whereHas('variants', function ($subQuery) use ($minPrice, $maxPrice) {
                                $subQuery->where('status', 'active')
                                    ->whereRaw('
                                    CASE
                                        WHEN discount > 0 THEN
                                            price - (price * discount / 100)
                                        ELSE
                                            price
                                    END BETWEEN ? AND ?', [(float)$minPrice, (float)$maxPrice]);
                            });
                    });
            });
        }

        if (!empty($minRatings)) {
            $productsQuery->whereRaw('
                products.id IN (
                    SELECT product_id
                    FROM product_reviews
                    WHERE product_reviews.product_id = products.id
                    GROUP BY product_id
                    HAVING AVG(CAST(rate as DECIMAL(3,2))) >= ?
                )', [min(array_map('intval', $minRatings))]);
        }

        if (!empty($minDiscounts)) {
            $validDiscounts = array_filter(array_map('intval', $minDiscounts), fn($d) => $d >= 0 && $d <= 100);
            if (!empty($validDiscounts)) {
                $productsQuery->where(function ($query) use ($validDiscounts) {
                    $query->where('has_variants', false)
                        ->whereIn('base_discount', $validDiscounts)
                        ->orWhere(function ($q) use ($validDiscounts) {
                            $q->where('has_variants', true)
                                ->whereHas('variants', fn($subQuery) => $subQuery->whereIn('discount', $validDiscounts));
                        });
                });
            }
        }

        if ($query) {
            $productsQuery->where('title', 'ILIKE', "%{$query}%");
        }

        // Apply sorting
        if ($sortBy === 'price_low_high') {
            $productsQuery->orderByRaw('
                CASE
                    WHEN has_variants = false THEN
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END
                    ELSE
                        (SELECT MIN(
                            CASE
                                WHEN discount > 0 THEN
                                    price - (price * discount / 100)
                                ELSE
                                    price
                            END
                        ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                END ASC
            ');
        } elseif ($sortBy === 'price_high_low') {
            $productsQuery->orderByRaw('
                CASE
                    WHEN has_variants = false THEN
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END
                    ELSE
                        (SELECT MAX(
                            CASE
                                WHEN discount > 0 THEN
                                    price - (price * discount / 100)
                                ELSE
                                    price
                            END
                        ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                END DESC
            ');
        } else {
            $productsQuery->latest('id');
        }

        $products = $productsQuery->paginate($show);
        $products->appends($request->except('page'));

        // Iterate and mutate each product (frontend will handle pricing)
        foreach ($products as $product) {
            if ($product->has_variants && $product->variants->count() > 0) {
                $variantWithImage = $product->variants->first(fn($v) => $v->images->count() > 0);
                $primaryImage = $variantWithImage?->images->first() ?? $product->variants->first()?->images->first();
            } else {
                $primaryImage = $product->images->first();
            }

            if ($primaryImage) {
                $imagePath = $primaryImage->image_path;
                $thumbnailPath = $primaryImage->thumbnail_path ?? $primaryImage->image_path;
                if (strpos($imagePath, 'storage/') !== 0) {
                    $imagePath = 'storage/' . ltrim($imagePath, '/');
                }
                if (strpos($thumbnailPath, 'storage/') !== 0) {
                    $thumbnailPath = 'storage/' . ltrim($thumbnailPath, '/');
                }
                $product->primary_image = [
                    'image_path' => $imagePath,
                    'thumbnail_path' => $thumbnailPath,
                    'url' => asset($imagePath),
                    'thumbnail_url' => asset($thumbnailPath),
                    'alt_text' => $product->title,
                ];
            } else {
                $product->primary_image = null;
            }

            $product->max_discount = $product->has_variants
                ? ($product->variants->max('discount') ?? 0)
                : ($product->base_discount ?? 0);

            $product->stock = $product->has_variants
                ? (int)$product->variants->sum('stock')
                : (int)$product->base_stock;

            unset($product->original_price, $product->discounted_price);
        }

        $recentProducts = $this->getRecentProductsData(self::RECENT_PRODUCTS_CACHE_PREFIX . 'grids', $ttl['product_lists']);

        return [
            'products' => $products,
            'recent_products' => $recentProducts,
            'category' => null,
            'mainCategory' => null,
            'subCategory' => null,
            'show' => $show,
            'sortBy' => $sortBy,
            'price' => $priceRange,
        ];
    }

    /**
     * Fetch optimized product grids data.
     *
     * @param Request $request
     * @param array $ttl
     * @return array
     */
    private function fetchOptimizedProductGridsDataOLD(Request $request, array $ttl)
    {
        $show = $request->input('show', 12);
        $sortBy = $request->input('sortBy', 'latest');
        $query = $request->input('query', '');
        $categories = $request->input('category', []);
        $brands = $request->input('brand', []);
        $priceRange = $request->input('price_range', '');
        $minRatings = $request->input('min_rating', []);
        $minDiscounts = $request->input('min_discount', []);

        $productsQuery = Product::query()
            ->select([
                'products.id',
                'products.title',
                'products.slug',
                'products.base_price',
                'products.base_discount',
                'products.base_stock',
                'products.has_variants',
                'products.cat_id',
                'products.condition',
                'products.is_featured',
                'products.status',
            ])
            ->where('products.status', 'active')
            ->with([
                // include product_id so images are properly hydrated back to the product
                'images' => fn($q) => $q->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order'])
                    ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                    ->with(['images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                        ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
            ]);

        if (!empty($categories)) {
            $productsQuery->whereIn('cat_id', Category::whereIn('slug', $categories)->pluck('id'));
        }
        if (!empty($brands)) {
            $productsQuery->whereIn('brand_id', Brand::whereIn('slug', $brands)->pluck('id'));
        }

        if ($priceRange) {
            [$minPrice, $maxPrice] = explode('-', $priceRange);
            $productsQuery->where(function ($query) use ($minPrice, $maxPrice) {
                // Non-variant products
                $query->where(function ($q) use ($minPrice, $maxPrice) {
                    $q->where('has_variants', false)
                        ->whereRaw('
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END BETWEEN ? AND ?', [(float)$minPrice, (float)$maxPrice]);
                })
                    // Variant products (use lowest variant price)
                    ->orWhere(function ($q) use ($minPrice, $maxPrice) {
                        $q->where('has_variants', true)
                            ->whereHas('variants', function ($subQuery) use ($minPrice, $maxPrice) {
                                $subQuery->where('status', 'active')
                                    ->whereRaw('
                                    CASE
                                        WHEN discount > 0 THEN
                                            price - (price * discount / 100)
                                        ELSE
                                            price
                                    END BETWEEN ? AND ?', [(float)$minPrice, (float)$maxPrice]);
                            });
                    });
            });
        }

        if (!empty($minRatings)) {
            $productsQuery->whereRaw('
                products.id IN (
                    SELECT product_id
                    FROM product_reviews
                    WHERE product_reviews.product_id = products.id
                    GROUP BY product_id
                    HAVING AVG(CAST(rate as DECIMAL(3,2))) >= ?
                )', [min(array_map('intval', $minRatings))]);
        }

        if (!empty($minDiscounts)) {
            $validDiscounts = array_filter(array_map('intval', $minDiscounts), fn($d) => $d >= 0 && $d <= 100);
            if (!empty($validDiscounts)) {
                $productsQuery->where(function ($query) use ($validDiscounts) {
                    $query->where('has_variants', false)
                        ->whereIn('base_discount', $validDiscounts)
                        ->orWhere(function ($q) use ($validDiscounts) {
                            $q->where('has_variants', true)
                                ->whereHas('variants', fn($subQuery) => $subQuery->whereIn('discount', $validDiscounts));
                        })
                        ->orWhereHas('discounts', fn($subQuery) => $subQuery->where('type', 'percentage')
                            ->where('is_active', true)
                            ->where('starts_at', '<=', now())
                            ->where('ends_at', '>=', now())
                            ->whereIn('value', $validDiscounts));
                });
            }
        }

        if ($query) {
            $productsQuery->where('title', 'ILIKE', "%{$query}%");
        }

        if ($sortBy === 'price_low_high') {
            $productsQuery->orderByRaw('
                CASE
                    WHEN has_variants = false THEN
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END
                    ELSE
                        (SELECT MIN(
                            CASE
                                WHEN discount > 0 THEN
                                    price - (price * discount / 100)
                                ELSE
                                    price
                            END
                        ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                END ASC
            ');
        } elseif ($sortBy === 'price_high_low') {
            $productsQuery->orderByRaw('
                CASE
                    WHEN has_variants = false THEN
                        CASE
                            WHEN base_discount > 0 THEN
                                base_price - (base_price * base_discount / 100)
                            ELSE
                                base_price
                        END
                    ELSE
                        (SELECT MAX(
                            CASE
                                WHEN discount > 0 THEN
                                    price - (price * discount / 100)
                                ELSE
                                    price
                            END
                        ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                END DESC
            ');
        } else {
            $productsQuery->latest('id');
        }

        $products = $productsQuery->paginate($show);
        // Removed deprecated setPath call
        $products->appends($request->except('page'));

        $recentProducts = $this->getRecentProductsData(self::RECENT_PRODUCTS_CACHE_PREFIX . 'grids', $ttl['product_lists']);

        return [
            'products' => $products,
            'recent_products' => $recentProducts,
            'category' => null,
            'mainCategory' => null,
            'subCategory' => null,
            'show' => $show,
            'sortBy' => $sortBy,
            'price' => $priceRange,
        ];
    }

    /**
     * Hydrate relationships from cached data.
     *
     * @param array $cachedData
     * @return array
     */
    private function hydrateRelationshipsFromCache($cachedData)
    {
        if (isset($cachedData['products'])) {
            $cachedData['products'] = $cachedData['products']->map(function ($product) {
                if (isset($product['images'])) {
                    $product['images'] = collect($product['images'])->map(fn($img) => (object) $img);
                }
                if (isset($product['cat_info'])) {
                    $product['cat_info'] = (object) $product['cat_info'];
                }
                return (object) $product;
            });
        }
        if (isset($cachedData['recent_products'])) {
            $cachedData['recent_products'] = collect($cachedData['recent_products'])->map(fn($item) => (object) $item);
        }
        return $cachedData;
    }

    /**
     * Cache complete page data for product grids.
     *
     * @param string $cacheKey
     * @param array $data
     * @param int $ttl
     */
    private function cacheCompletePageData(string $cacheKey, array $data, int $ttl)
    {
        $cacheData = [
            'products_data' => [
                'data' => $data['products']->items(),
                'total' => $data['products']->total(),
                'per_page' => $data['products']->perPage(),
                'current_page' => $data['products']->currentPage(),
            ],
            'recent_products' => $data['recent_products'],
            'category' => $data['category'],
            'mainCategory' => $data['mainCategory'],
            'subCategory' => $data['subCategory'],
            'show' => $data['show'],
            'sortBy' => $data['sortBy'],
            'price' => $data['price'],
        ];
        RedisHelper::put($cacheKey, $cacheData, $ttl);
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

        if (!empty($data['min_rating'])) {
            $queryParams['min_rating'] = is_array($data['min_rating'])
                ? implode(',', $data['min_rating'])
                : $data['min_rating'];
        }

        if (!empty($data['min_discount'])) {
            $queryParams['min_discount'] = is_array($data['min_discount'])
                ? implode(',', $data['min_discount'])
                : $data['min_discount'];
        }

        $tempRequest = new Request($queryParams);
        $cacheKey = $this->generateOptimizedCacheKey($tempRequest);

        if (!RedisHelper::exists($cacheKey)) {
            $this->preWarmFilterCache($tempRequest);
        }

        Log::info("Filter processed in " . round((microtime(true) - $startTime) * 1000, 2) . "ms");
        return redirect()->route('product-grids', $queryParams);
    }

    public function applyFilters(Request $request, $encryptedFilters = null)
    {
        try {
            // Decode encrypted filters if provided
            if ($encryptedFilters) {
                $decodedFilters = json_decode(UrlEncryptor::decodePath($encryptedFilters), true);
                $request->merge($decodedFilters);
            }

            $slugPath = $request->input('category_slug', '');
            if ($slugPath) {
                try {
                    $slugPath = UrlEncryptor::decodePath($slugPath);
                } catch (\Exception $e) {
                    // Fallback to raw slugPath if not encrypted
                }
            }

            $productQuery = Product::with(['images', 'discounts', 'cat_info', 'sub_cat_info'])
                ->active();

            if ($slugPath) {
                $segments = explode('/', trim($slugPath, '/'));
                $currentCategory = Category::whereNull('parent_id')
                    ->where('status', 'active')
                    ->where('slug', $segments[0])
                    ->first();

                if (!$currentCategory) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Category not found',
                    ], 404);
                }

                array_shift($segments);
                foreach ($segments as $segment) {
                    $child = $currentCategory->children()
                        ->where('slug', $segment)
                        ->where('status', 'active')
                        ->first();

                    if (!$child) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Sub-category not found',
                        ], 404);
                    }
                    $currentCategory = $child;
                }

                $descendantIds = method_exists($currentCategory, 'descendantsAndSelf')
                    ? $currentCategory->descendantsAndSelf()->pluck('id')->toArray()
                    : $this->getDescendantIds($currentCategory);

                $productQuery->where(function ($query) use ($descendantIds) {
                    $query->whereIn('cat_id', $descendantIds)
                        ->orWhereIn('child_cat_id', $descendantIds);
                });
            }

            $this->applyFiltersToQuery($productQuery, $request);

            $perPage = $request->input('show', 12);
            $page = $request->input('page', 1);
            $products = $productQuery->paginate($perPage, ['*'], 'page', $page);

            $encodedSlugPath = $slugPath ? UrlEncryptor::encodePath($slugPath) : '';
            $basePath = $encodedSlugPath ? "/product-cat/{$encodedSlugPath}" : '/product-grids';
            $products->setPath($basePath);
            $products->appends($request->except(['page', '_token', 'quant', 'slug', 'category_slug']));

            $html = view('frontend.pages.product-grid-html', compact('products'))->render();

            return response()->json([
                'success' => true,
                'html' => $html,
                'message' => $products->isEmpty() ? 'No products found matching your criteria' : null,
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ], 200, ['Content-Type' => 'application/json']);
        } catch (\Exception $e) {
            Log::error('Apply Filters Error: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to apply filters: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function applyFiltersToQuery($productQuery, Request $request)
    {
        $brands = $request->input('brand', []);
        if (!empty($brands)) {
            if (is_string($brands)) {
                $brands = array_filter(explode(',', $brands));
            }
            if (!empty($brands)) {
                $brandIds = Brand::whereIn('slug', $brands)
                    ->where('status', 'active')
                    ->pluck('id')
                    ->toArray();
                if (!empty($brandIds)) {
                    $productQuery->whereIn('brand_id', $brandIds);
                }
            }
        }

        $priceRange = $request->input('price_range', '');
        if ($priceRange && str_contains($priceRange, '-')) {
            $priceParts = explode('-', $priceRange);
            if (count($priceParts) == 2) {
                $minPrice = (float) trim($priceParts[0]);
                $maxPrice = (float) trim($priceParts[1]);
                if ($minPrice >= 0 && $maxPrice > $minPrice) {
                    $productQuery->where(function ($query) use ($minPrice, $maxPrice) {
                        $query->where(function ($q) use ($minPrice, $maxPrice) {
                            $q->where('has_variants', false)
                                ->whereRaw('
                                CASE
                                    WHEN base_discount > 0 THEN
                                        base_price - (base_price * base_discount / 100)
                                    ELSE
                                        base_price
                                END BETWEEN ? AND ?', [$minPrice, $maxPrice]);
                        })
                            ->orWhere(function ($q) use ($minPrice, $maxPrice) {
                                $q->where('has_variants', true)
                                    ->whereHas('variants', function ($subQuery) use ($minPrice, $maxPrice) {
                                        $subQuery->where('status', 'active')
                                            ->whereRaw('
                                            CASE
                                                WHEN discount > 0 THEN
                                                    price - (price * discount / 100)
                                                ELSE
                                                    price
                                            END BETWEEN ? AND ?', [$minPrice, $maxPrice]);
                                    });
                            });
                    });
                }
            }
        }

        $minRatings = $request->input('min_rating', []);
        if (!empty($minRatings)) {
            $minRatings = is_array($minRatings) ? $minRatings : array_filter(explode(',', $minRatings));
            $minRating = min(array_map('intval', $minRatings));
            $productQuery->whereRaw('
                products.id IN (
                    SELECT product_id
                    FROM product_reviews
                    WHERE product_reviews.product_id = products.id
                    GROUP BY product_id
                    HAVING AVG(CAST(rate as DECIMAL(3,2))) >= ?
                )', [$minRating]);
        }

        $minDiscounts = $request->input('min_discount', []);
        if (!empty($minDiscounts)) {
            if (is_string($minDiscounts)) {
                $minDiscounts = array_filter(explode(',', $minDiscounts));
            }
            if (!empty($minDiscounts)) {
                $validDiscounts = array_filter(array_map('intval', $minDiscounts), fn($d) => $d >= 0 && $d <= 100);
                if (!empty($validDiscounts)) {
                    $productQuery->where(function ($query) use ($validDiscounts) {
                        $query->where('has_variants', false)
                            ->whereIn('base_discount', $validDiscounts)
                            ->orWhere(function ($q) use ($validDiscounts) {
                                $q->where('has_variants', true)
                                    ->whereHas('variants', fn($subQuery) => $subQuery->whereIn('discount', $validDiscounts));
                            })
                            ->orWhereHas('discounts', fn($subQuery) => $subQuery->where('type', 'percentage')
                                ->where('is_active', true)
                                ->where('starts_at', '<=', now())
                                ->where('ends_at', '>=', now())
                                ->whereIn('value', $validDiscounts));
                    });
                }
            }
        }

        $sortBy = $request->input('sortBy', 'latest');
        switch ($sortBy) {
            case 'price_low_high':
                $productQuery->orderByRaw('
                    CASE
                        WHEN has_variants = false THEN
                            CASE
                                WHEN base_discount > 0 THEN
                                    base_price - (base_price * base_discount / 100)
                                ELSE
                                    base_price
                            END
                        ELSE
                            (SELECT MIN(
                                CASE
                                    WHEN discount > 0 THEN
                                        price - (price * discount / 100)
                                    ELSE
                                        price
                                END
                            ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                    END ASC
                ');
                break;
            case 'price_high_low':
                $productQuery->orderByRaw('
                    CASE
                        WHEN has_variants = false THEN
                            CASE
                                WHEN base_discount > 0 THEN
                                    base_price - (base_price * base_discount / 100)
                                ELSE
                                    base_price
                            END
                        ELSE
                            (SELECT MAX(
                                CASE
                                    WHEN discount > 0 THEN
                                        price - (price * discount / 100)
                                    ELSE
                                        price
                                END
                            ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                    END DESC
                ');
                break;
            case 'rating_high_low':
                $productQuery->leftJoin('product_reviews', 'products.id', '=', 'product_reviews.product_id')
                    ->selectRaw('products.*, AVG(CAST(product_reviews.rate as DECIMAL(3,2))) as avg_rating')
                    ->groupBy('products.id')
                    ->orderByDesc('avg_rating');
                break;
            case 'name_a_z':
                $productQuery->orderBy('title', 'asc');
                break;
            case 'name_z_a':
                $productQuery->orderBy('title', 'desc');
                break;
            case 'latest':
            default:
                $productQuery->orderBy('created_at', 'desc');
                break;
        }
    }

    public function encryptFilters(Request $request)
    {
        try {
            $filters = $request->all();
            $encryptedFilters = UrlEncryptor::encodePath(json_encode($filters));
            return response()->json([
                'success' => true,
                'encryptedFilters' => $encryptedFilters,
            ]);
        } catch (\Exception $e) {
            Log::error('Filter encryption error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to encrypt filters',
            ], 500);
        }
    }

    protected function getDescendantIds($category)
    {
        $ids = [$category->id];
        $children = Category::where('parent_id', $category->id)
            ->where('status', 'active')
            ->get();

        foreach ($children as $child) {
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return array_unique($ids);
    }

    protected function collectDescendantIds($category, $descendantIds, $depth = 0)
    {
        if ($depth > 10) {
            return;
        }

        $children = $category->children()->where('status', 'active')->get();
        foreach ($children as $child) {
            if (!$descendantIds->contains($child->id)) {
                $descendantIds->push($child->id);
                $this->collectDescendantIds($child, $descendantIds, $depth + 1);
            }
        }
    }

    /**
     * Pre-warm cache for product filters.
     *
     * @param Request $request
     */
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
     * Restore paginator from cached data.
     *
     * @param array $paginatorData
     * @param Request $request
     * @return LengthAwarePaginator
     */
    private function restorePaginatorFromCache($paginatorData, Request $request)
    {
        return new LengthAwarePaginator(
            collect($paginatorData['data'])->map(fn($item) => (object) $item),
            $paginatorData['total'],
            $paginatorData['per_page'],
            $paginatorData['current_page'],
            [
                'path' => $request->input('category_slug') ? '/product-cat/' . $request->input('category_slug') : '/product-grids',
                'query' => $request->query(),
            ]
        );
    }

    /**
     * Warm up cache for common filter combinations.
     *
     * @return array
     */
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

    /**
     * Clear optimized cache for product grids.
     *
     * @return bool
     */
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
     * Handle product search functionality.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productSearch(Request $request)
    {
        $query = $request->input('search', '');
        $perPage = 9;
        $ttl = $this->getTtlConfig();
        $recent_products = $this->getRecentProductsData(self::RECENT_PRODUCTS_CACHE_PREFIX . 'grids', $ttl['product_lists']);

        if (empty($query)) {
            $products = Product::where('status', 'active')
                ->select([
                    'id',
                    'title',
                    'slug',
                    'base_price',
                    'base_discount',
                    'base_stock',
                    'has_variants',
                    'cat_id',
                    'condition',
                    'is_featured',
                ])
                ->with([
                    'images' => fn($q) => $q->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order'])
                        ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                    'cat_info' => fn($q) => $q->select(['id', 'title']),
                    'variants' => fn($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                        ->with(['images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                            ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
                ])
                ->paginate($perPage);

            return view('frontend.pages.product-grids')
                ->with('products', $products)
                ->with('recent_products', $recent_products)
                ->with('search_query', $query);
        }

        try {
            $searchResult = $this->searchService->search($query, $perPage, $request->input('page', 1));
            $products = new LengthAwarePaginator(
                collect($searchResult['products'])->map(function ($product) {
                    $product = (object) $product;
                    if ($product->has_variants) {
                        $product->price = ProductVariant::where('product_id', $product->id)
                            ->where('status', 'active')
                            ->min('price');
                        $product->images = VariantImage::whereIn('product_variant_id', ProductVariant::where('product_id', $product->id)->pluck('id'))
                            ->where('is_primary', true)
                            ->select(['id', 'image_path', 'product_variant_id'])
                            ->get();
                    } else {
                        $product->images = ProductImage::where('product_id', $product->id)
                            ->where('is_primary', true)
                            ->select(['id', 'image_path'])
                            ->get();
                    }
                    return $product;
                }),
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
                ->select([
                    'id',
                    'title',
                    'slug',
                    'base_price',
                    'base_discount',
                    'base_stock',
                    'has_variants',
                    'cat_id',
                    'condition',
                    'is_featured',
                ])
                ->with([
                    'images' => fn($q) => $q->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order'])
                        ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                    'cat_info' => fn($q) => $q->select(['id', 'title']),
                    'variants' => fn($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                        ->with(['images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                            ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
                ])
                ->paginate($perPage);
        }

        return view('frontend.pages.product-grids')
            ->with('products', $products)
            ->with('recent_products', $recent_products)
            ->with('search_query', $query);
    }

    /**
     * Provide autocomplete suggestions for search.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
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
                    ->select(['id', 'title', 'slug', 'base_price', 'base_discount', 'has_variants'])
                    ->with([
                        'images' => fn($q) => $q->select(['id','image_path','thumbnail_path','is_primary','sort_order'])
                            ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                        'variants' => fn($q) => $q->where('status', 'active')
                            ->select(['id', 'product_id', 'price', 'discount'])
                            ->with(['images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                                ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
                    ])
                    ->limit(10)
                    ->get();

                $suggestions = $products->map(function ($product) {
                    $price = $product->has_variants
                        ? ProductVariant::where('product_id', $product->id)
                        ->where('status', 'active')
                        ->min('price')
                        : ($product->base_discount > 0
                            ? $product->base_price - ($product->base_price * $product->base_discount / 100)
                            : $product->base_price);

                    $photo = $product->has_variants
                        ? VariantImage::whereIn('product_variant_id', ProductVariant::where('product_id', $product->id)->pluck('id'))
                        ->where('is_primary', true)
                        ->value('image_path')
                        : ProductImage::where('product_id', $product->id)
                        ->where('is_primary', true)
                        ->value('image_path');

                    return [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'price' => $price,
                        'discount' => $product->has_variants ? null : $product->base_discount,
                        'photo' => $photo
                    ];
                });
            }

            return response()->json(['success' => true, 'suggestions' => $suggestions]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'suggestions' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Check cache health for homepage and product grids.
     *
     * @return array
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
     * Display the about us page.
     *
     * @return \Illuminate\View\View
     */
    public function aboutUs()
    {
        return view('frontend.pages.about-us');
    }

    /**
     * Display the contact page.
     *
     * @return \Illuminate\View\View
     */
    public function contact()
    {
        return view('frontend.pages.contact');
    }

    // Updated controller method - productDetail in FrontendController or ProductController
    public function productDetail($slug)
    {
        // Single optimized query with all necessary relations
        $product_detail = Product::where('slug', $slug)
            ->where('status', 'active')
            ->with([
                // Product images - only what we need
                'images' => fn($q) => $q->select([
                    'id',
                    'product_id',
                    'image_path',
                    'thumbnail_path',
                    'is_primary',
                    'sort_order'
                ])
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order'),

                // Category info
                'cat_info' => fn($q) => $q->select(['id', 'title', 'slug']),
                'sub_cat_info' => fn($q) => $q->select(['id', 'title', 'slug']),

                // Reviews with user info
                'getReview.user_info' => fn($q) => $q->select(['id', 'name', 'photo']),

                // Active variants with all needed data - FIXED relationship
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select([
                        'id',
                        'product_id',
                        'sku',
                        'price',
                        'discount',
                        'stock',
                        'status',
                        'variant_values'
                    ])
                    ->with([
                        // Variant images
                        'images' => fn($iq) => $iq->select([
                            'id',
                            'product_variant_id',
                            'image_path',
                            'thumbnail_path',
                            'is_primary',
                            'sort_order'
                        ])
                            ->orderByDesc('is_primary')
                            ->orderBy('sort_order'),

                        // Variant options with types - FIXED foreign key
                        'variantOptions' => fn($vq) => $vq->select([
                            'product_variant_options.id',
                            'variant_type_id',
                            'value',
                            'display_value',
                            'hex_color',
                            'sort_order'
                        ])
                            ->with([
                                'variantType' => fn($tq) => $tq->select([
                                    'id',
                                    'name',
                                    'display_name',
                                    'sort_order'
                                ])
                            ])
                    ])
                    // Pre-sort by discounted price
                    ->orderByRaw('price * (1 - COALESCE(discount, 0)/100) ASC'),

                // Related products with their primary image only
                'rel_prods' => fn($q) => $q->where('status', 'active')
                    ->limit(8)
                    ->with([
                        'images' => fn($iq) => $iq->select([
                            'id',
                            'product_id',
                            'image_path',
                            'thumbnail_path',
                            'is_primary',
                            'sort_order'
                        ])
                            ->where('is_primary', true)
                            ->limit(1)
                    ])
            ])
            ->firstOrFail();

        // Build variant types efficiently using hashmap approach
        $variantTypes = $this->buildVariantTypes($product_detail);

        // Process variants for JavaScript
        $processedVariants = $this->processVariantsForFrontend($product_detail);

        // Set initial display values
        $this->setInitialProductDisplay($product_detail);

        // Related products already loaded via eager loading
        $related_products = $product_detail->rel_prods;

        // Get recent products - always fetch fresh to show latest views
        // (Recently viewed changes frequently, so caching causes stale data issues)
        $recent_products = $this->recentProductService->getRecentProducts();

        // Track product view asynchronously
        dispatch(function () use ($product_detail) {
            $this->recentProductService->trackProductView($product_detail->id);
        })->afterResponse();

        return view('frontend.pages.product_detail', compact(
            'product_detail',
            'related_products',
            'recent_products',
            'processedVariants',
            'variantTypes'
        ));
    }

    /**
     * Build variant types from loaded variants
     */
    protected function buildVariantTypes($product)
    {
        if (!$product->has_variants || $product->variants->isEmpty()) {
            return collect();
        }

        $typeMap = [];

        foreach ($product->variants as $variant) {
            foreach ($variant->variantOptions as $option) {
                $type = $option->variantType;

                // Initialize type group if not exists
                if (!isset($typeMap[$type->id])) {
                    $typeMap[$type->id] = [
                        'id' => $type->id,
                        'name' => $type->name,
                        'display_name' => $type->display_name,
                        'sort_order' => $type->sort_order,
                        'options' => []
                    ];
                }

                // Add option if not already present (avoid duplicates)
                if (!isset($typeMap[$type->id]['options'][$option->id])) {
                    $typeMap[$type->id]['options'][$option->id] = $option;
                }
            }
        }

        // Convert to collection and sort
        return collect($typeMap)
            ->sortBy('sort_order')
            ->values()
            ->map(function ($type) {
                return (object) [
                    'id' => $type['id'],
                    'name' => $type['name'],
                    'display_name' => $type['display_name'],
                    'sort_order' => $type['sort_order'],
                    'options' => collect($type['options'])
                        ->sortBy('sort_order')
                        ->values()
                ];
            });
    }

    /**
     * Process variants for JavaScript consumption
     */
    protected function processVariantsForFrontend($product)
    {
        if (!$product->has_variants || $product->variants->isEmpty()) {
            return [];
        }

        return $product->variants->map(function ($variant) {
            // Process images with proper path
            $processedImages = $variant->images->map(function ($img) {
                $path = $img->image_path;
                if (strpos($path, 'storage/') !== 0) {
                    $path = 'storage/' . ltrim($path, '/');
                }
                return [
                    'image_path' => asset($path),
                    'is_primary' => $img->is_primary
                ];
            })->toArray();

            // Build variant values from loaded options
            $variantValues = $variant->variantOptions->mapWithKeys(function ($opt) {
                return [$opt->variantType->name => $opt->value];
            })->toArray();

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'discount' => (float) ($variant->discount ?? 0),
                'stock' => (int) $variant->stock,
                'status' => $variant->status,
                'variant_values' => $variantValues,
                'images' => $processedImages
            ];
        })->toArray();
    }

    /**
     * Set initial product display values
     */
    protected function setInitialProductDisplay($product)
    {
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            // Try to get cheapest in-stock variant
            $inStockVariants = $product->variants->filter(fn($v) => $v->stock > 0);

            if ($inStockVariants->isNotEmpty()) {
                // Already sorted by discounted price, so first is cheapest
                $selectedVariant = $inStockVariants->first();
            } else {
                // All out of stock - use first active variant
                $selectedVariant = $product->variants->first();
            }

            $product->original_price = $selectedVariant->price;
            $product->discounted_price = $selectedVariant->discounted_price;
            $product->discount_percentage = $selectedVariant->discount ?? 0;
            $product->current_stock = $selectedVariant->stock;
            $product->current_sku = $selectedVariant->sku;
        } else {
            // Simple product (no variants)
            $base_discount = $product->base_discount ?? 0;
            $product->original_price = $product->base_price ?? 0;
            $product->discounted_price = $base_discount > 0
                ? $product->original_price * (1 - $base_discount / 100)
                : $product->original_price;
            $product->discount_percentage = $base_discount;
            $product->current_stock = $product->base_stock ?? 0;
            $product->current_sku = $product->base_sku ?? 'N/A';
        }
    }

    /**
     * API endpoint to get variant details by ID
     * Route: /api/product/variant/{variantId}
     */
    public function getVariantDetails($variantId)
    {
        try {
            $variant = ProductVariant::where('id', $variantId)
                ->where('status', 'active')
                ->with([
                    'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])->orderBy('sort_order'),
                    'product' => fn($q) => $q->select(['id', 'slug'])
                ])
                ->select(['id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status', 'variant_values'])
                ->first();

            if (!$variant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Variant not found or inactive'
                ], 404);
            }

            $discountedPrice = $variant->price * (1 - ($variant->discount ?? 0) / 100);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => number_format($variant->price, 2),
                    'discount' => $variant->discount ?? 0,
                    'discounted_price' => number_format($discountedPrice, 2),
                    'stock' => $variant->stock,
                    'in_stock' => $variant->stock > 0,
                    'images' => $variant->images->map(fn($img) => [
                        'id' => $img->id,
                        'image_path' => asset((strpos($img->image_path, 'storage/') === 0 ? $img->image_path : 'storage/' . ltrim($img->image_path, '/'))),
                        'thumbnail_path' => asset((strpos($img->thumbnail_path ?? $img->image_path, 'storage/') === 0 ? ($img->thumbnail_path ?? $img->image_path) : 'storage/' . ltrim($img->thumbnail_path ?? $img->image_path, '/'))),
                        'is_primary' => $img->is_primary
                    ])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching variant details'
            ], 500);
        }
    }

    /**
     * API endpoint to check variant availability by options
     * Route: /api/product/{slug}/check-variant
     */
    /**
     * Updated checkVariantAvailability (removed count condition for partial matches consistency)
     */
    public function checkVariantAvailability(Request $request, $slug)
    {
        $request->validate([
            'options' => 'required|array'
        ]);

        $product = Product::where('slug', $slug)
            ->where('status', 'active')
            ->where('has_variants', true)
            ->firstOrFail();

        // If client asked for candidate matches (partial selection), return
        // all variants that contain the provided option key/value pairs.
        $options = $request->options ?? [];
        if ($request->input('mode') === 'candidates') {
            $variantsQuery = ProductVariant::where('product_id', $product->id)
                ->where('status', 'active')
                ->with([
                    'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                ]);

            // Apply JSON containment per option pair
            foreach ($options as $k => $v) {
                $variantsQuery->whereJsonContains('variant_values', [$k => $v]);
            }

            $matched = $variantsQuery->get();

            if ($matched->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching variants found for provided options',
                    'data' => ['variants' => []]
                ], 404);
            }

            // Map variants to a lightweight structure
            $out = $matched->map(function ($variant) {
                $processedImages = $variant->images->map(function ($img) {
                    $path = $img->image_path;
                    if (strpos($path, 'storage/') !== 0) {
                        $path = 'storage/' . ltrim($path, '/');
                    }
                    return [
                        'image_path' => asset($path),
                        'is_primary' => $img->is_primary
                    ];
                })->toArray();

                // Ensure variant_values is an array
                $vals = $variant->variant_values;
                if (is_string($vals)) {
                    try { $vals = json_decode($vals, true) ?? []; } catch (\Exception $e) { $vals = []; }
                }

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => (float) $variant->price,
                    'discount' => (float) ($variant->discount ?? 0),
                    'stock' => (int) $variant->stock,
                    'variant_values' => $vals,
                    'images' => $processedImages
                ];
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => [ 'variants' => $out ]
            ]);
        }

        // Default, backwards-compatible: try to find a single exact variant match
        // (same behavior as before)
        // Find matching variant
        $variant = ProductVariant::where('product_id', $product->id)
            ->where('status', 'active')
            ->whereJsonContains('variant_values', $options)
            ->with([
                'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                    ->orderByDesc('is_primary')
                    ->orderBy('sort_order')
            ])
            ->first();

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found with selected options'
            ], 404);
        }

        // Process images
        $processedImages = $variant->images->map(function ($img) {
            $path = $img->image_path;
            if (strpos($path, 'storage/') !== 0) {
                $path = 'storage/' . ltrim($path, '/');
            }
            return [
                'image_path' => asset($path),
                'is_primary' => $img->is_primary
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => (float) $variant->price,
                'discount' => (float) ($variant->discount ?? 0),
                'stock' => (int) $variant->stock,
                'discounted_price' => (float) $variant->discounted_price,
                'images' => $processedImages
            ]
        ]);
    }

    /**
     * Display product lists with filtering.
     *
     * @return \Illuminate\View\View
     */
    public function productLists()
    {
        $products = Product::query()
            ->select([
                'id',
                'title',
                'slug',
                'base_price',
                'base_discount',
                'base_stock',
                'has_variants',
                'cat_id',
                'condition',
                'is_featured',
            ])
            ->where('status', 'active')
            ->with([
                'images' => fn($q) => $q->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order'])
                    ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                        ->with(['images' => fn($q) => $q->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order'])
                            ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
            ]);

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
                $products->orderBy('title', 'ASC');
            } elseif ($_GET['sortBy'] == 'price') {
                $products->orderByRaw('
                    CASE
                        WHEN has_variants = false THEN
                            CASE
                                WHEN base_discount > 0 THEN
                                    base_price - (base_price * base_discount / 100)
                                ELSE
                                    base_price
                            END
                        ELSE
                            (SELECT MIN(
                                CASE
                                    WHEN discount > 0 THEN
                                        price - (price * discount / 100)
                                    ELSE
                                        price
                                END
                            ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                    END ASC
                ');
            }
        }

        if (!empty($_GET['price'])) {
            $price = explode('-', $_GET['price']);
            if (count($price) === 2 && is_numeric($price[0]) && is_numeric($price[1])) {
                $products->where(function ($query) use ($price) {
                    $query->where(function ($q) use ($price) {
                        $q->where('has_variants', false)
                            ->whereRaw('
                            CASE
                                WHEN base_discount > 0 THEN
                                    base_price - (base_price * base_discount / 100)
                                ELSE
                                    base_price
                            END BETWEEN ? AND ?', [(float)$price[0], (float)$price[1]]);
                    })
                        ->orWhere(function ($q) use ($price) {
                            $q->where('has_variants', true)
                                ->whereHas('variants', function ($subQuery) use ($price) {
                                    $subQuery->where('status', 'active')
                                        ->whereRaw('
                                        CASE
                                            WHEN discount > 0 THEN
                                                price - (price * discount / 100)
                                            ELSE
                                                price
                                        END BETWEEN ? AND ?', [(float)$price[0], (float)$price[1]]);
                                });
                        });
                });
            }
        }

        $recent_products = $this->getRecentProductsData('recent_latest', 3600);
        $products = $products->paginate($_GET['show'] ?? 6);

        return view('frontend.pages.product-lists')
            ->with('products', $products)
            ->with('recent_products', $recent_products);
    }

    /**
     * Display products by brand.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productBrand(Request $request)
    {
        $products = Brand::getProductByBrand($request->slug);
        return view('frontend.pages.product-grids', compact('products'));
    }

    /**
     * Display products by category.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function productCat(Request $request)
    {
        $filters = [
            'category_slug' => $request->slug,
            'brand' => $request->get('brand', []),
            'query' => $request->get('query'),
            'price_range' => $request->get('price'),
            'min_rating' => $request->get('min_rating', []),
            'min_discount' => $request->get('min_discount', []),
            'sortBy' => $request->get('sortBy', ''),
        ];

        $perPage = $request->get('show', 12);
        $page = $request->get('page', 1);

        $category = Category::where('slug', $request->slug)->firstOrFail();
        $descendantIds = method_exists($category, 'descendantsAndSelf')
            ? $category->descendantsAndSelf()->pluck('id')->toArray()
            : $this->getDescendantIds($category);

        $productQuery = Product::with([
            'images' => fn($q) => $q->select(['id','image_path','thumbnail_path','is_primary','sort_order'])
                ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
            'cat_info' => fn($q) => $q->select(['id', 'title']),
            'variants' => fn($q) => $q->where('status', 'active')
                ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                ->with(['images' => fn($q) => $q->select(['id','image_path','thumbnail_path','product_id','is_primary','sort_order'])
                    ->orderByDesc('is_primary')->orderBy('sort_order')->take(3)])
        ])
            ->where('status', 'active')
            ->where(function ($query) use ($descendantIds) {
                $query->whereIn('cat_id', $descendantIds)
                    ->orWhereIn('child_cat_id', $descendantIds);
            });

        $this->applyFiltersToQuery($productQuery, $request);

        $products = $productQuery->paginate($perPage);
        // Removed deprecated setPath call (category slug)
        $products->appends($request->except(['page', '_token']));

        return view('frontend.pages.product-grids', [
            'products' => $products,
            'category' => $category,
            'show' => $perPage,
            'sortBy' => $filters['sortBy'],
            'price' => $filters['price_range'],
            'recent_products' => $this->getRecentProductsData('recent_latest', 3600),
        ]);
    }

    public function productSubCat(Request $request, $encryptedPath)
    {
        try {
            $slugPath = UrlEncryptor::decodePath($encryptedPath);
            $segments = explode('/', trim($slugPath, '/'));
            $currentCategory = Category::whereNull('parent_id')
                ->where('status', 'active')
                ->where('slug', $segments[0])
                ->firstOrFail();

            array_shift($segments);
            foreach ($segments as $segment) {
                $child = $currentCategory->children()
                    ->where('slug', $segment)
                    ->where('status', 'active')
                    ->firstOrFail();
                $currentCategory = $child;
            }

            $descendantIds = $this->getDescendantIds($currentCategory);
            $cacheKey = 'product_count_' . md5(serialize([
                'category' => $currentCategory->id,
                'descendant_ids' => $descendantIds,
                'filters' => $request->except(['page', '_token'])
            ]));

            $productQuery = Product::with([
                'images' => fn($q) => $q->select(['id','image_path','thumbnail_path','product_id','is_primary','sort_order'])
                    ->orderByDesc('is_primary')->orderBy('sort_order')->take(3),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'sub_cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                    ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_variant_id', 'is_primary'])->where('is_primary', true)])
            ])
                ->where('status', 'active')
                ->where(function ($query) use ($descendantIds) {
                    $query->whereIn('cat_id', $descendantIds)
                        ->orWhereIn('child_cat_id', $descendantIds);
                });

            $this->applyFiltersToQuery($productQuery, $request);

            $perPage = $request->input('show', 12);
            $products = $productQuery->paginate($perPage);
            // Removed deprecated setPath call (encrypted path)
            $products->appends($request->except(['page', '_token']));

            $totalProducts = RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($productQuery) {
                return $productQuery->count();
            });

            $maxPrice = RedisHelper::remember($cacheKey . '_max_price', self::CACHE_TTL, function () use ($descendantIds) {
                return Product::where('status', 'active')
                    ->where(function ($query) use ($descendantIds) {
                        $query->whereIn('cat_id', $descendantIds)
                            ->orWhereIn('child_cat_id', $descendantIds);
                    })
                    ->selectRaw('
                        MAX(
                            CASE
                                WHEN has_variants = false THEN
                                    CASE
                                        WHEN base_discount > 0 THEN
                                            base_price - (base_price * base_discount / 100)
                                        ELSE
                                            base_price
                                    END
                                ELSE
                                    (SELECT MAX(
                                        CASE
                                            WHEN discount > 0 THEN
                                                price - (price * discount / 100)
                                            ELSE
                                                price
                                        END
                                    ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                            END
                        ) as max_price
                    ')
                    ->value('max_price') ?? 1000;
            });

            $recentProducts = $this->recentProductService->getRecentProducts();

            if ($request->wantsJson()) {
                $html = view('frontend.pages.product-grid-html', compact('products'))->render();
                return response()->json([
                    'success' => true,
                    'html' => $html,
                    'message' => $products->isEmpty() ? 'No products found with current filters' : null,
                    'total' => $products->total(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'debug_info' => [
                        'total_before_pagination' => $totalProducts,
                        'applied_filters' => $request->except(['page', '_token']),
                    ]
                ]);
            }

            $appliedFilters = [
                'brands' => $request->input('brand', []),
                'price_range' => $request->input('price_range', ''),
                'min_rating' => $request->input('min_rating', []),
                'min_discount' => $request->input('min_discount', []),
                'sortBy' => $request->input('sortBy', 'latest'),
                'show' => $request->input('show', 12),
            ];

            if (config('app.debug')) {
                Log::debug('productSubCat: Processed request', [
                    'category_id' => $currentCategory->id,
                    'descendant_ids' => $descendantIds,
                    'total_products' => $totalProducts,
                    'filters' => $appliedFilters,
                    'sql' => $productQuery->toSql(),
                    'bindings' => $productQuery->getBindings()
                ]);
            }

            return view('frontend.pages.product-grids', [
                'products' => $products,
                'mainCategory' => $currentCategory,
                'max_price' => $maxPrice,
                'recent_products' => $recentProducts,
                'applied_filters' => $appliedFilters,
                'has_filters' => $this->hasFiltersApplied($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Product category filter error: ' . $e->getMessage(), [
                'encrypted_path' => $encryptedPath,
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to apply filters: ' . $e->getMessage(),
                ], 500);
            }

            abort(500, 'Error loading category: ' . $e->getMessage());
        }
    }

    /**
     * Display products for a subcategory
     */
    public function productSubCatOLD(Request $request, $encryptedPath)
    {
        // try {
            $slugPath = UrlEncryptor::decodePath($encryptedPath);
            $segments = explode('/', trim($slugPath, '/'));
            $currentCategory = Category::whereNull('parent_id')
                ->where('status', 'active')
                ->where('slug', $segments[0])
                ->firstOrFail();

            array_shift($segments);
            foreach ($segments as $segment) {
                $child = $currentCategory->children()
                    ->where('slug', $segment)
                    ->where('status', 'active')
                    ->firstOrFail();
                $currentCategory = $child;
            }

            $descendantIds = $this->getDescendantIds($currentCategory);
            $cacheKey = 'product_count_' . md5(serialize([
                'category' => $currentCategory->id,
                'descendant_ids' => $descendantIds,
                'filters' => $request->except(['page', '_token'])
            ]));

            $productQuery = Product::with([
                'images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->where('is_primary', true),
                'cat_info' => fn($q) => $q->select(['id', 'title']),
                'sub_cat_info' => fn($q) => $q->select(['id', 'title']),
                'variants' => fn($q) => $q->where('status', 'active')
                    ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                    ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->where('is_primary', true)])
            ])
                ->where('status', 'active')
                ->where(function ($query) use ($descendantIds) {
                    $query->whereIn('cat_id', $descendantIds)
                        ->orWhereIn('child_cat_id', $descendantIds);
                });

            $this->applyFiltersToQuery($productQuery, $request);

            $perPage = $request->input('show', 12);
            $products = RedisHelper::remember($cacheKey . '_results_' . $request->input('page', 1), self::CACHE_TTL, function () use ($productQuery, $perPage, $request, $encryptedPath) {
                $products = $productQuery->paginate($perPage);
                // Removed deprecated setPath call (encrypted path nested)
                $products->appends($request->except(['page', '_token']));
                return $products;
            });

            $totalProducts = RedisHelper::remember($cacheKey, self::CACHE_TTL, function () use ($productQuery) {
                return $productQuery->count();
            });

            $maxPrice = RedisHelper::remember($cacheKey . '_max_price', self::CACHE_TTL, function () use ($descendantIds) {
                return Product::where('status', 'active')
                    ->where(function ($query) use ($descendantIds) {
                        $query->whereIn('cat_id', $descendantIds)
                            ->orWhereIn('child_cat_id', $descendantIds);
                    })
                    ->selectRaw('
                        MAX(
                            CASE
                                WHEN has_variants = false THEN
                                    CASE
                                        WHEN base_discount > 0 THEN
                                            base_price - (base_price * base_discount / 100)
                                        ELSE
                                            base_price
                                    END
                                ELSE
                                    (SELECT MAX(
                                        CASE
                                            WHEN discount > 0 THEN
                                                price - (price * discount / 100)
                                            ELSE
                                                price
                                        END
                                    ) FROM product_variants pv WHERE pv.product_id = products.id AND pv.status = \'active\')
                            END
                        ) as max_price
                    ')
                    ->value('max_price') ?? 1000;
            });

            $recentProducts = $this->recentProductService->getRecentProducts();

            if ($request->wantsJson()) {
                $html = view('frontend.pages.product-grid-html', compact('products'))->render();
                return response()->json([
                    'success' => true,
                    'html' => $html,
                    'message' => $products->isEmpty() ? 'No products found with current filters' : null,
                    'total' => $products->total(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'debug_info' => [
                        'total_before_pagination' => $totalProducts,
                        'applied_filters' => $request->except(['page', '_token']),
                    ]
                ]);
            }

            $appliedFilters = [
                'brands' => $request->input('brand', []),
                'price_range' => $request->input('price_range', ''),
                'min_rating' => $request->input('min_rating', []),
                'min_discount' => $request->input('min_discount', []),
                'sortBy' => $request->input('sortBy', 'latest'),
                'show' => $request->input('show', 12),
            ];

            if (config('app.debug')) {
                Log::debug('productSubCat: Processed request', [
                    'category_id' => $currentCategory->id,
                    'descendant_ids' => $descendantIds,
                    'total_products' => $totalProducts,
                    'filters' => $appliedFilters,
                    'sql' => $productQuery->toSql(),
                    'bindings' => $productQuery->getBindings()
                ]);
            }

            return view('frontend.pages.product-grids', [
                'products' => $products,
                'mainCategory' => $currentCategory,
                'max_price' => $maxPrice,
                'recent_products' => $recentProducts,
                'applied_filters' => $appliedFilters,
                'has_filters' => $this->hasFiltersApplied($request),
            ]);
        // } catch (\Exception $e) {
        //     Log::error('Product category filter error: ' . $e->getMessage(), [
        //         'encrypted_path' => $encryptedPath,
        //         'request_data' => $request->all(),
        //         'trace' => $e->getTraceAsString()
        //     ]);

        //     if ($request->wantsJson()) {
        //         return response()->json([
        //             'success' => false,
        //             'message' => 'Failed to apply filters: ' . $e->getMessage(),
        //         ], 500);
        //     }

        //     abort(500, 'Error loading category: ' . $e->getMessage());
        // }
    }

    /**
     * Check if any filters are applied in the request.
     *
     * @param Request $request
     * @return bool
     */
    protected function hasFiltersApplied(Request $request)
    {
        $filterKeys = ['brand', 'price_range', 'min_rating', 'min_discount'];

        foreach ($filterKeys as $key) {
            $value = $request->input($key);
            if (!empty($value)) {
                return true;
            }
        }

        if (
            $request->input('sortBy', 'latest') !== 'latest' ||
            $request->input('show', 12) != 12
        ) {
            return true;
        }

        return false;
    }

    private function getRecentProductsData(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $recentProducts = Product::where('status', 'active')
                ->select([
                    'id',
                    'title',
                    'slug',
                    'base_price',
                    'base_discount',
                    'base_stock',
                    'has_variants',
                    'cat_id',
                    'condition',
                    'summary'
                ])
                ->with([
                    'images' => fn($q) => $q->select(['id', 'product_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                        ->where('is_primary', true),
                    'cat_info' => fn($q) => $q->select(['id', 'title']),
                    'variants' => fn($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                        ->with([
                            'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order'])
                                ->where('is_primary', true)
                        ])
                ])
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $recentProducts->transform(function ($product) {
                // Handle primary image
                if ($product->has_variants && $product->variants->count() > 0) {
                    $minVariant = $product->variants
                        ->sortBy(function ($variant) {
                            return $variant->discount > 0
                                ? $variant->price - ($variant->price * $variant->discount / 100)
                                : $variant->price;
                        })
                        ->first();

                    if ($minVariant) {
                        $product->discounted_price = $minVariant->discount > 0
                            ? $minVariant->price - ($minVariant->price * $minVariant->discount / 100)
                            : $minVariant->price;

                        // Get variant's primary image
                        $primaryImage = $minVariant->images->first();
                        if ($primaryImage) {
                            $imagePath = $primaryImage->image_path;
                            $thumbnailPath = $primaryImage->thumbnail_path ?? $primaryImage->image_path;

                            // Ensure proper storage path
                            if (strpos($imagePath, 'storage/') !== 0) {
                                $imagePath = 'storage/' . ltrim($imagePath, '/');
                            }
                            if (strpos($thumbnailPath, 'storage/') !== 0) {
                                $thumbnailPath = 'storage/' . ltrim($thumbnailPath, '/');
                            }

                            $product->primary_image = [
                                'image_path' => $imagePath,
                                'thumbnail_path' => $thumbnailPath,
                                'url' => asset($imagePath),
                                'thumbnail_url' => asset($thumbnailPath),
                                'alt_text' => $product->title
                            ];
                        }
                    } else {
                        $product->discounted_price = null;
                        $product->primary_image = null;
                    }
                } else {
                    // Simple product
                    $product->discounted_price = $product->base_discount > 0
                        ? $product->base_price - ($product->base_price * $product->base_discount / 100)
                        : $product->base_price;

                    // Get product's primary image
                    $primaryImage = $product->images->first();
                    if ($primaryImage) {
                        $imagePath = $primaryImage->image_path;
                        $thumbnailPath = $primaryImage->thumbnail_path ?? $primaryImage->image_path;

                        // Ensure proper storage path
                        if (strpos($imagePath, 'storage/') !== 0) {
                            $imagePath = 'storage/' . ltrim($imagePath, '/');
                        }
                        if (strpos($thumbnailPath, 'storage/') !== 0) {
                            $thumbnailPath = 'storage/' . ltrim($thumbnailPath, '/');
                        }

                        $product->primary_image = [
                            'image_path' => $imagePath,
                            'thumbnail_path' => $thumbnailPath,
                            'url' => asset($imagePath),
                            'thumbnail_url' => asset($thumbnailPath),
                            'alt_text' => $product->title
                        ];
                    } else {
                        $product->primary_image = null;
                    }
                }

                $product->in_wishlist = class_exists('Helper') && method_exists('Helper', 'isProductInWishlist')
                    ? Helper::isProductInWishlist($product->slug)
                    : false;

                return $product;
            });

            if (!RedisHelper::put($key, $recentProducts, $ttl)) {
                Log::warning("Failed to store recent products in Redis for key: {$key}");
            }

            return $recentProducts;
        });
    }

    /**
     * Fetch recent products with caching.
     *
     * @param string $key Cache key
     * @param int $ttl Time to live
     * @return \Illuminate\Support\Collection
     */
    private function getRecentProductsDataOLD(string $key, int $ttl)
    {
        return Cache::remember($key, $ttl, function () use ($key, $ttl) {
            $recentProducts = Product::where('status', 'active')
                ->select([
                    'id',
                    'title',
                    'slug',
                    'base_price',
                    'base_discount',
                    'base_stock',
                    'has_variants',
                    'cat_id',
                    'condition',
                    'summary'
                ])
                ->with([
                    'images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->where('is_primary', true),
                    'cat_info' => fn($q) => $q->select(['id', 'title']),
                    'variants' => fn($q) => $q->where('status', 'active')
                        ->select(['id', 'product_id', 'price', 'discount', 'stock'])
                        ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->where('is_primary', true)])
                ])
                ->orderBy('id', 'DESC')
                ->limit(3)
                ->get();

            $recentProducts->transform(function ($product) {
                if ($product->has_variants) {
                    $minVariant = $product->variants->sortBy(function ($variant) {
                        return $variant->discount > 0 ? $variant->price - ($variant->price * $variant->discount / 100) : $variant->price;
                    })->first();
                    $product->discounted_price = $minVariant ? ($minVariant->discount > 0 ? $minVariant->price - ($minVariant->price * $minVariant->discount / 100) : $minVariant->price) : null;
                    $product->images = $minVariant ? $minVariant->images : collect();
                } else {
                    $product->discounted_price = $product->base_discount > 0
                        ? $product->base_price - ($product->base_price * $product->base_discount / 100)
                        : $product->base_price;
                }
                $product->in_wishlist = class_exists('Helper') && method_exists('Helper', 'isProductInWishlist')
                    ? Helper::isProductInWishlist($product->slug)
                    : false;
                return $product;
            });

            if (!RedisHelper::put($key, $recentProducts, $ttl)) {
                Log::warning("Failed to store recent products in Redis for key: {$key}");
            }

            return $recentProducts;
        });
    }

    /**
     * Display blog page with filtering.
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
     * Display blog post details by slug.
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
     * Handle blog search functionality.
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
     * Handle blog filter requests.
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
     * Display blog posts by category.
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
     * Display blog posts by tag.
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
     * Display login page.
     *
     * @return \Illuminate\View\View
     */
    public function login()
    {
        return view('frontend.pages.login');
    }

    /**
     * Handle login submission.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function loginSubmit(Request $request)
    {
        $data = $request->all();
        // Capture pre-login session id from cookie (Laravel will regenerate the session id on successful login)
        $sessionCookieName = config('session.cookie');
        $preLoginSessionId = $request->cookie($sessionCookieName) ?: $request->session()->getId();

        if (Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'status' => 'active'])) {
            Session::put('user', $data['email']);

            // Merge session recent products to user account using the pre-login session id
            $this->recentProductService->handleUserLogin(Auth::id(), $preLoginSessionId);

            Session::flash('success', 'Successfully login');
            return redirect()->route('home');
        }

        Session::flash('error', 'Invalid email and password please try again!');
        return redirect()->back();
    }

    /**
     * Handle logout.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function logout()
    {
        Session::forget('user');
        Auth::logout();
        Session::flash('success', 'Logout successfully');
        return back();
    }

    /**
     * Display registration page.
     *
     * @return \Illuminate\View\View
     */
    public function register()
    {
        return view('frontend.pages.register');
    }

    /**
     * Handle registration submission.
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
            Session::flash('success', 'Successfully registered');
            return redirect()->route('home');
        }

        Session::flash('error', 'Please try again!');
        return back();
    }

    /**
     * Create a new user.
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
     * Display password reset form.
     *
     * @return \Illuminate\View\View
     */
    public function showResetForm()
    {
        return view('auth.passwords.old-reset');
    }

    /**
     * Handle newsletter subscription.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function subscribe(Request $request)
    {
        if (!Newsletter::isSubscribed($request->email)) {
            Newsletter::subscribePending($request->email);
            if (Newsletter::lastActionSucceeded()) {
                Session::flash('success', 'Subscribed! Please check your email');
                return redirect()->route('home');
            }

            Session::flash('error', 'Something went wrong! please try again');
            return back();
        }

        Session::flash('error', 'Already Subscribed');
        return back();
    }

    /**
     * Display cart page.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function cart(Request $request)
    {
        if (!$request->session()->has('coupon_set')) {
            session()->forget('coupon');
            session()->put('coupon_set', true);
        }

        return view('frontend.pages.cart');
    }
}
