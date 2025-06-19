<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

use App\Models\Product;
use App\Models\Category;
use App\Models\Banner;
use App\Models\Post;
use App\Models\Wishlist;
use App\Models\Cart;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\Settings;
use App\User;

use App\Observers\ProductObserver;
use App\Observers\CategoryObserver;
use App\Observers\BannerObserver;
use App\Observers\CartObserver;
use App\Observers\OrderObserver;
use App\Observers\PostObserver;
use App\Observers\ReviewObserver;
use App\Observers\SettingsObserver;
use App\Observers\UserObserver;
use App\Observers\WishlistObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Register model observers
        Product::observe(ProductObserver::class);
        Category::observe(CategoryObserver::class);
        Banner::observe(BannerObserver::class);
        Post::observe(PostObserver::class);
        Settings::observe(SettingsObserver::class);
        User::observe(UserObserver::class);
        Cart::observe(CartObserver::class);
        Wishlist::observe(WishlistObserver::class);
        ProductReview::observe(ReviewObserver::class);
        Order::observe(OrderObserver::class); // Optional

        // Load cache TTL and prefix config
        $ttl    = Config::get('cache_keys.ttl');
        $prefix = Config::get('cache_keys');

        /**
         * Global site settings (shared to all views)
         */
        $settings = Cache::store('redis')->remember(
            $prefix['home_prefix'] . 'settings',
            $ttl['settings'],
            fn() => Settings::first()
        );
        // dd($settings);
        View::share('settings', $settings);

        /**
         * Authenticated user-specific wishlist/cart counts (for header)
         */
        View::composer('*', function ($view) use ($ttl, $prefix) {
            if (Auth::check()) {
                $userId = Auth::id();

                $wishlistCountKey = "user:{$userId}:wishlist:count";
                $cartCountKey     = "user:{$userId}:cart:count";

                $wishlistCount = Cache::store('redis')->remember(
                    $wishlistCountKey,
                    $ttl['wishlist'],
                    fn() => Wishlist::where('user_id', $userId)->whereNull('cart_id')->count()
                );

                $cartCount = Cache::store('redis')->remember(
                    $cartCountKey,
                    $ttl['cart'],
                    fn() => Cart::where('user_id', $userId)->whereNull('order_id')->count()
                );

                View::share('wishlistCount', $wishlistCount);
                View::share('cartCount', $cartCount);
            }
        });
    }
}
