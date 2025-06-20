<?php

namespace App\Http\Controllers;

use App\Models\Banner;
use App\Models\Product;
use App\Models\Category;
use App\Models\PostTag;
use App\Models\PostCategory;
use App\Models\Post;
use App\Models\Cart;
use App\Models\Brand;
use App\Models\ProductImage;
use App\User;
use Auth;
use Session;
use Newsletter;
use DB;
use Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class FrontendController extends Controller
{

    public function index(Request $request)
    {
        return redirect()->route($request->user()->role);
    }

    public function home()
    {
        $prefix = Config::get('cache_keys.home_prefix');
        $ttl = Config::get('cache_keys.ttl');

        $cacheGet = fn($tag, $key) => Cache::tags($tag)->get($key);
        $dataSource = collect(); // to track data source

        // ───── Banners
        $bannersKey = "{$prefix}:banners";
        $banners = $cacheGet(['homepage', 'banners'], $bannersKey);
        $dataSource->put('banners', $banners ? 'cache' : 'query');
        if (!$banners) {
            $banners = Cache::tags(['homepage', 'banners'])->remember($bannersKey, $ttl['banners'], function () {
                return Banner::select('id', 'title', 'description', 'photo', 'status')
                    ->where('status', 'active')->orderByDesc('id')->take(3)->get();
            });
        }

        // ───── Small Category List
        $categoryListKey = "{$prefix}:category_lists";
        $category_lists = $cacheGet(['homepage', 'categories'], $categoryListKey);
        $dataSource->put('category_lists', $category_lists ? 'cache' : 'query');
        if (!$category_lists) {
            $category_lists = Cache::tags(['homepage', 'categories'])->remember($categoryListKey, $ttl['category_lists'], function () {
                return Category::select('id', 'title', 'slug', 'photo', 'status', 'is_parent')
                    ->where('status', 'active')->where('is_parent', 1)
                    ->orderBy('title')->take(3)->get();
            });
        }

        // ───── Full Categories
        $categoriesKey = "{$prefix}:categories";
        $categories = $cacheGet(['homepage', 'categories'], $categoriesKey);
        $dataSource->put('categories', $categories ? 'cache' : 'query');
        if (!$categories) {
            $categories = Cache::tags(['homepage', 'categories'])->remember($categoriesKey, $ttl['categories'], function () {
                return Category::select('id', 'title', 'slug', 'status', 'is_parent')
                    ->where('status', 'active')->where('is_parent', 1)
                    ->orderBy('title')->get();
            });
        }

        // ───── Featured Products
        $featuredKey = "{$prefix}:featured_products";
        $featured = $cacheGet(['homepage', 'products'], $featuredKey);
        $dataSource->put('featured_products', $featured ? 'cache' : 'query');
        if (!$featured) {
            $featured = Cache::tags(['homepage', 'products'])->remember($featuredKey, $ttl['featured_products'], function () {
                return Product::with(['images', 'cat_info'])
                    ->select('id', 'title', 'slug', 'price', 'discount', 'status', 'is_featured', 'cat_id')
                    ->where('status', 'active')->where('is_featured', 1)
                    ->orderByDesc('price')->take(2)->get();
            });
        }

        // ───── Recent Posts
        $postsKey = "{$prefix}:posts";
        $posts = $cacheGet(['homepage', 'posts'], $postsKey);
        $dataSource->put('posts', $posts ? 'cache' : 'query');
        if (!$posts) {
            $posts = Cache::tags(['homepage', 'posts'])->remember($postsKey, $ttl['posts'], function () {
                return Post::select('id', 'title', 'slug', 'photo', 'status', 'created_at')
                    ->where('status', 'active')->orderByDesc('id')->take(3)->get();
            });
        }

        // ───── Product Cards (from Redis or DB)
        $productIds = Product::select('id')
            ->where('status', 'active')->orderByDesc('id')->take(60)->pluck('id');

        $ttlSeconds = $ttl['product_card'] * 60;
        $cachedProductCards = collect();
        $productCardSource = [];

        foreach ($productIds as $productId) {
            $key = "json_product_card:{$productId}";

            if (Redis::exists($key)) {
                $jsonData = json_decode(Redis::get($key), true);
                $product = Product::hydrate([$jsonData])[0];

                if (isset($jsonData['images'])) {
                    $product->setRelation('images', collect($jsonData['images'])->mapInto(ProductImage::class));
                }

                if (isset($jsonData['cat_info'])) {
                    $product->setRelation('cat_info', new Category($jsonData['cat_info']));
                }

                $cachedProductCards->push($product);
                $productCardSource[] = 'cache';
            } else {
                $product = Product::with(['images', 'cat_info'])
                    ->select('id', 'title', 'slug', 'price', 'discount', 'stock', 'condition', 'status', 'cat_id')
                    ->find($productId);

                if ($product) {
                    Redis::setex($key, $ttlSeconds, $product->toJson());
                    Cache::tags(['product_card'])->put("product_card:{$productId}", $product, $ttl['product_card']);
                    $cachedProductCards->push($product);
                    $productCardSource[] = 'query';
                }
            }
        }

        $mostUsedProductCardSource = array_count_values($productCardSource);
        $dataSource->put('product_cards', ($mostUsedProductCardSource['query'] ?? 0) > 0 ? 'query' : 'cache');

        // ───── Final Log
        Log::info('Homepage content source', $dataSource->toArray());

        return view('frontend.index', [
            'banners' => $banners,
            'category_lists' => $category_lists,
            'categories' => $categories,
            'featured' => $featured,
            'posts' => $posts,
            'products' => $cachedProductCards,
        ]);
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
        // dd($product_detail);
        return view('frontend.pages.product_detail')->with('product_detail', $product_detail);
    }

    public function productGrids(Request $request)
    {
        $products = Product::with(['images']) // eager load images
            ->where('status', 'active');      // apply initial active status filter

        // Filter by category slug
        if (!empty($request->get('category'))) {
            $slug = explode(',', $request->get('category'));
            $cat_ids = Category::whereIn('slug', $slug)->pluck('id')->toArray();
            $products->whereIn('cat_id', $cat_ids);
        }

        // Filter by brand slug
        if (!empty($request->get('brand'))) {
            $slugs = explode(',', $request->get('brand'));
            $brand_ids = Brand::whereIn('slug', $slugs)->pluck('id')->toArray();
            $products->whereIn('brand_id', $brand_ids);
        }

        // Sort options
        if (!empty($request->get('sortBy'))) {
            switch ($request->get('sortBy')) {
                case 'title':
                    $products->orderBy('title', 'ASC');
                    break;
                case 'price':
                    $products->orderBy('price', 'ASC');
                    break;
                case 'category':
                    $products->orderBy('cat_id', 'ASC');
                    break;
                case 'brand':
                    $products->orderBy('brand_id', 'ASC');
                    break;
            }
        }

        // Price range filtering
        if (!empty($request->get('price'))) {
            $price = explode('-', $request->get('price'));
            if (count($price) === 2) {
                $products->whereBetween('price', [$price[0], $price[1]]);
            }
        }

        // Pagination
        $perPage = $request->get('show', 9);
        $products = $products->paginate($perPage)->appends($request->query());

        // Recent products
        $recent_products = Product::where('status', 'active')
            ->latest('id')
            ->limit(3)
            ->get();

        return view('frontend.pages.product-grids', [
            'products' => $products,
            'recent_products' => $recent_products
        ]);
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
