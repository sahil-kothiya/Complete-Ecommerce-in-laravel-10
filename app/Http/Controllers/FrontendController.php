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

    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    private const CACHE_PREFIX = 'cache:homepage:';
    /**
     * TTL configuration cache
     */
    private static ?array $ttlConfig = null;

    public function home()
    {
        $ttl = $this->getTtlConfig();

        // Define all cache keys
        $cacheKeys = [
            'categories' => self::CACHE_PREFIX . 'categories',
            'banners' => self::CACHE_PREFIX . 'banners',
            'products' => self::CACHE_PREFIX . 'product_lists',
            'categoryBanners' => self::CACHE_PREFIX . 'category_banners'
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
            'categories' => fn() => $this->getCategoriesData(self::CACHE_PREFIX . 'categories', $ttl['categories']),
            'banners' => fn() => $this->getBannersData(self::CACHE_PREFIX . 'banners', $ttl['banners']),
            'products' => fn() => $this->getHomepageProductsData(self::CACHE_PREFIX . 'product_lists', $ttl['product_lists']),
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
     * Clear homepage cache
     */
    public function clearHomepageCache(): bool
    {
        $keys = [
            self::CACHE_PREFIX . 'categories',
            self::CACHE_PREFIX . 'banners',
            self::CACHE_PREFIX . 'product_lists',
            self::CACHE_PREFIX . 'category_banners'
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
     * Get cache health status
     */
    public function getCacheHealth(): array
    {
        $keys = [
            'categories' => self::CACHE_PREFIX . 'categories',
            'banners' => self::CACHE_PREFIX . 'banners',
            'products' => self::CACHE_PREFIX . 'product_lists',
            'category_banners' => self::CACHE_PREFIX . 'category_banners'
        ];

        $health = [];
        foreach ($keys as $name => $key) {
            $exists = RedisHelper::exists($key);
            $health[$name] = [
                'cached' => $exists,
                'key' => $key
            ];
        }

        $health['redis_stats'] = RedisHelper::getCacheStats();

        return $health;
    }
    // public function home()
    // {
    //     // $startTime = microtime(true);
    //     $homepage = RedisCacheManager::get('page', 'home');

    //     if (!$homepage) {
    //         // Cache miss: fetch fresh data
    //         $banners = Banner::where('status', 'active')
    //             ->select('id', 'title', 'description', 'photo')
    //             ->orderBy('id', 'desc')
    //             ->get();

    //         $categories = Category::where('status', 'active')
    //             ->where('is_parent', 1)
    //             ->select('id', 'title', 'slug', 'photo')
    //             ->get();

    //         $categoryBanners = $categories->take(3);

    //         $product_lists = Product::with(['images:id,image_path,product_id'])
    //             ->where('status', 'active')
    //             ->select('id', 'title', 'slug', 'price', 'discount', 'stock', 'condition', 'cat_id', 'size')
    //             ->latest()
    //             ->limit(60)
    //             ->get();

    //         $featured = Product::with(['images:id,image_path,product_id', 'cat_info:id,title'])
    //             ->where('is_featured', 1)
    //             ->where('status', 'active')
    //             ->select('id', 'title', 'slug', 'discount', 'cat_id')
    //             ->orderBy('id', 'desc')
    //             ->limit(1)
    //             ->get();

    //         $latest_products = Product::with(['images:id,image_path,product_id'])
    //             ->where('status', 'active')
    //             ->select('id', 'title', 'slug', 'price', 'discount')
    //             ->latest()
    //             ->limit(6)
    //             ->get();

    //         $posts = Post::select('id', 'title', 'slug', 'photo', 'created_at')
    //             ->latest()
    //             ->limit(3)
    //             ->get();

    //         // Store all in Redis
    //         $homepage = compact(
    //             'banners',
    //             'categories',
    //             'categoryBanners',
    //             'product_lists',
    //             'featured',
    //             'latest_products',
    //             'posts'
    //         );

    //         RedisCacheManager::put('page', 'home', $homepage, 3600); // cache for 1 hour
    //         // $endTime = microtime(true);
    //         // // Calculate and display the elapsed time
    //         // $elapsedTime = $endTime - $startTime;
    //         // echo "Elapsed Time: $elapsedTime seconds" . PHP_EOL;
    //         // exit;
    //     }

    //     return view('frontend.index', $homepage);
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

    public function productGrids()
    {
        $products = Product::query();

        if (!empty($_GET['category'])) {
            $slug = explode(',', $_GET['category']);
            // dd($slug);
            $cat_ids = Category::select('id')->whereIn('slug', $slug)->pluck('id')->toArray();
            // dd($cat_ids);
            $products->whereIn('cat_id', $cat_ids);
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
            $products = $products->where('status', 'active')->paginate(9);
        }
        // Sort by name , price, category


        return view('frontend.pages.product-grids')->with('products', $products)->with('recent_products', $recent_products);
    }
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
    public function productSearch(Request $request)
    {
        $recent_products = Product::where('status', 'active')->orderBy('id', 'DESC')->limit(3)->get();
        $products = Product::orwhere('title', 'like', '%' . $request->search . '%')
            ->orwhere('slug', 'like', '%' . $request->search . '%')
            ->orwhere('description', 'like', '%' . $request->search . '%')
            ->orwhere('summary', 'like', '%' . $request->search . '%')
            ->orwhere('price', 'like', '%' . $request->search . '%')
            ->orderBy('id', 'DESC')
            ->paginate('9');
        return view('frontend.pages.product-grids')->with('products', $products)->with('recent_products', $recent_products);
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
