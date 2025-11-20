<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

use App\Services\RedisCacheService;

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
use App\Services\DiscountService;
use App\Services\ResponseCacheService;
use Carbon\Carbon;

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
        // Singleton for Redis cache configuration to avoid repeated config calls
        $this->app->singleton('redis.cache.config', function () {
            return Config::get('redis_cache');
        });

        $this->app->singleton(ResponseCacheService::class);
        // $this->app->singleton(DiscountService::class, function () {
        //     return new DiscountService();
        // });
    }

    public function boot(): void
    {
        // Force PHP locale and Carbon locale to English so logs and
        // locale-aware output remain in default English
        @setlocale(LC_ALL, 'en_US.UTF-8');
        if (class_exists('\Locale')) {
            // set Intl default locale if extension available
            try {
                \Locale::setDefault('en');
            } catch (\Throwable $e) {
                // ignore if not supported
            }
        }
        Carbon::setLocale('en');

        $this->registerObservers();

        // Skip processing for backend routes - early return for performance
        if ($this->shouldSkipRoutes()) {
            return;
        }

        $this->shareGlobalData();
        $this->initializeCriticalCaches();
        // $this->setupUserSpecificData();

        View::composer('*', function ($view) {
            $view->with('discountService', app(DiscountService::class));
        });
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
            self::$ttlConfig = Config::get('redis_cache.ttl', []);
        }

        return self::$ttlConfig;
    }

    /**
     * Get settings from cache with optimized fallback chain
     */
    private function getCachedSettings(string $key, int $ttl): ?Settings
    {
        // Try Redis first (fastest)
        $settings = RedisCacheService::get($key);

        if ($settings !== null) {
            return $settings;
        }

        // Try Laravel cache (medium speed)
        $settings = Cache::get($key);

        if ($settings !== null) {
            // Store in Redis for next time
            RedisCacheService::put($key, $settings, $ttl);
            return $settings;
        }

        // Last resort: Database query (slowest)
        $settings = Settings::select([
            'description',
            'short_des',
            'photo',
            'address',
            'phone',
            'email',
            'logo'
        ])->first();

        if ($settings) {
            // Store in both caches
            RedisCacheService::put($key, $settings, $ttl);
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
        $cachedCounts = RedisCacheService::mget([$wishlistKey, $cartKey]);

        $result = [];

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
                    RedisCacheService::put($cartKey, $count, $ttl['cart']);
                    return $count;
                }
            );
        }

        return $result;
    }

    /**
     * Initialize critical caches on first boot
     * Only runs if Redis is enabled and caches are empty
     */
    private function initializeCriticalCaches(): void
    {
        // Only in production/staging and if Redis is enabled
        if (!app()->environment(['production', 'local']) || !Config::get('redis_cache.enabled.master', false)) {
            return;
        }

        // Check if already initialized (avoid warming on every request)
        $initKey = 'meta:cache:initialized';
        if (RedisCacheService::has($initKey)) {
            return;
        }

        try {
            // Warm critical data on first boot
            $this->warmUpCriticalCaches();

            // Mark as initialized (expires in 1 hour, will re-warm if Redis is flushed)
            RedisCacheService::put($initKey, true, 3600);
        } catch (\Throwable $e) {
            Log::warning('Cache initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Warm up critical caches (settings, categories, banners)
     */
    private function warmUpCriticalCaches(): void
    {
        $config = Config::get('redis_cache');

        if (!$config['warming']['enabled'] ?? false) {
            return;
        }

        $ttl = $this->getTtlConfig();

        // Warm settings (always needed)
        if ($config['warming']['warm_items']['homepage'] ?? true) {
            $settingsKey = 'page:home:settings';
            $this->getCachedSettings($settingsKey, $ttl['settings'] ?? 86400);
            Log::info('Cache warmed: settings');
        }

        // Warm categories if enabled
        if ($config['warming']['warm_items']['categories'] ?? true) {
            try {
                $categories = Category::where('status', 'active')
                    ->select('id', 'title', 'slug', 'photo', 'parent_id')
                    ->orderBy('title', 'ASC')
                    ->get();

                RedisCacheService::put('component:categories', $categories, $ttl['categories'] ?? 43200);
                Log::info('Cache warmed: categories', ['count' => $categories->count()]);
            } catch (\Throwable $e) {
                Log::warning('Failed to warm categories cache: ' . $e->getMessage());
            }
        }

        // Warm featured products if enabled
        if ($config['warming']['warm_items']['featured_products'] ?? true) {
            try {
                $featured = Product::where('status', 'active')
                    ->where('is_featured', 1)
                    ->select('id', 'title', 'slug', 'base_price', 'has_variants')
                    ->limit(20)
                    ->get();

                RedisCacheService::put('component:featured', $featured, $ttl['featured_products'] ?? 3600);
                Log::info('Cache warmed: featured products', ['count' => $featured->count()]);
            } catch (\Throwable $e) {
                Log::warning('Failed to warm featured products cache: ' . $e->getMessage());
            }
        }
    }
}
