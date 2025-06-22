<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;

use App\Helpers\RedisHelper;

use App\Models\{
    Settings,
    Wishlist,
    Cart,
    Product,
    Category,
    Banner,
    Post,
    Order,
    ProductReview
};
use App\User;

use App\Observers\{
    ProductObserver,
    CategoryObserver,
    BannerObserver,
    CartObserver,
    OrderObserver,
    PostObserver,
    ReviewObserver,
    SettingsObserver,
    UserObserver,
    WishlistObserver
};

class AppServiceProvider extends ServiceProvider
{
    /**
     * Model-Observer mappings
     */
    private const OBSERVERS = [
        Product::class => ProductObserver::class,
        Category::class => CategoryObserver::class,
        Banner::class => BannerObserver::class,
        Post::class => PostObserver::class,
        Settings::class => SettingsObserver::class,
        User::class => UserObserver::class,
        Cart::class => CartObserver::class,
        Wishlist::class => WishlistObserver::class,
        ProductReview::class => ReviewObserver::class,
        Order::class => OrderObserver::class,
    ];

    /**
     * Backend routes to skip
     */
    private const SKIP_ROUTES = ['admin*', 'api*'];

    /**
     * Cache TTL configuration
     */
    private static ?array $ttlConfig = null;

    public function register(): void
    {
        // Singleton for TTL configuration to avoid repeated config calls
        $this->app->singleton('cache.ttl', function () {
            return Config::get('cache_keys.ttl');
        });
    }

    public function boot(): void
    {
        $this->registerObservers();

        // Skip processing for backend routes - early return for performance
        if ($this->shouldSkipRoutes()) {
            return;
        }

        $this->shareGlobalData();
        $this->setupUserSpecificData();
    }

    /**
     * Register all model observers
     */
    private function registerObservers(): void
    {
        foreach (self::OBSERVERS as $model => $observer) {
            $model::observe($observer);
        }
    }

    /**
     * Check if current route should be skipped - optimized with static cache
     */
    private function shouldSkipRoutes(): bool
    {
        static $shouldSkip = null;

        if ($shouldSkip === null) {
            $request = request();
            $shouldSkip = false;

            foreach (self::SKIP_ROUTES as $pattern) {
                if ($request->is($pattern)) {
                    $shouldSkip = true;
                    break;
                }
            }
        }

        return $shouldSkip;
    }

    /**
     * Share global data across all views with batch Redis operations
     */
    private function shareGlobalData(): void
    {
        $ttl = $this->getTtlConfig();
        $settingsKey = 'cache:homepage:settings';

        // Try Redis first, then Laravel cache, then DB
        $settings = $this->getCachedSettings($settingsKey, $ttl['settings']);

        if ($settings) {
            View::share('settings', $settings);
        }
    }

    /**
     * Get TTL configuration with static caching
     */
    private function getTtlConfig(): array
    {
        if (self::$ttlConfig === null) {
            self::$ttlConfig = app('cache.ttl');
        }

        return self::$ttlConfig;
    }

    /**
     * Get settings from cache with optimized fallback chain
     */
    private function getCachedSettings(string $key, int $ttl): ?Settings
    {
        // Try Redis first (fastest)
        $settings = RedisHelper::get($key);

        if ($settings !== null) {
            return $settings;
        }

        // Try Laravel cache (medium speed)
        $settings = Cache::get($key);

        if ($settings !== null) {
            // Store in Redis for next time
            RedisHelper::put($key, $settings, $ttl);
            return $settings;
        }

        // Last resort: Database query (slowest)
        $settings = Settings::select([
            'short_des',
            'photo',
            'address',
            'phone',
            'email',
            'logo'
        ])->first();

        if ($settings) {
            // Store in both caches
            RedisHelper::put($key, $settings, $ttl);
            Cache::put($key, $settings, $ttl);
        }

        return $settings;
    }

    /**
     * Setup user-specific data with optimized caching
     */
    private function setupUserSpecificData(): void
    {
        View::composer('*', function ($view) {
            if (!Auth::check()) {
                return;
            }

            $userId = Auth::id();

            // Use static cache to avoid repeated config calls
            static $ttl = null;
            if ($ttl === null) {
                $ttl = $this->getTtlConfig();
            }

            $counts = $this->getUserCounts($userId, $ttl);
            $view->with($counts);
        });
    }

    /**
     * Get user's wishlist and cart counts with batch Redis operations
     */
    private function getUserCounts(int $userId, array $ttl): array
    {
        $wishlistKey = "user:{$userId}:wishlist:count";
        $cartKey = "user:{$userId}:cart:count";

        // Try to get both counts from Redis in one operation
        $cachedCounts = RedisHelper::mget([$wishlistKey, $cartKey]);

        $result = [];

        // Handle wishlist count
        if ($cachedCounts[$wishlistKey] !== null) {
            $result['wishlistCount'] = $cachedCounts[$wishlistKey];
        } else {
            $result['wishlistCount'] = Cache::remember(
                $wishlistKey,
                $ttl['wishlist'],
                function () use ($userId, $wishlistKey, $ttl) {
                    $count = Wishlist::where('user_id', $userId)
                        ->whereNull('cart_id')
                        ->count();

                    // Store in Redis too
                    RedisHelper::put($wishlistKey, $count, $ttl['wishlist']);
                    return $count;
                }
            );
        }

        // Handle cart count
        if ($cachedCounts[$cartKey] !== null) {
            $result['cartCount'] = $cachedCounts[$cartKey];
        } else {
            $result['cartCount'] = Cache::remember(
                $cartKey,
                $ttl['cart'],
                function () use ($userId, $cartKey, $ttl) {
                    $count = Cart::where('user_id', $userId)
                        ->whereNull('order_id')
                        ->count();

                    // Store in Redis too
                    RedisHelper::put($cartKey, $count, $ttl['cart']);
                    return $count;
                }
            );
        }

        return $result;
    }

    /**
     * Warm up cache for critical data (call this from a command/job)
     */
    public function warmUpCache(): void
    {
        $ttl = $this->getTtlConfig();
        $prefix = 'cache:homepage:';

        // Warm up settings
        $settingsKey = $prefix . 'settings';
        $this->getCachedSettings($settingsKey, $ttl['settings']);

        // You can add more cache warming here
        // Example: categories, banners, etc.
    }
}
