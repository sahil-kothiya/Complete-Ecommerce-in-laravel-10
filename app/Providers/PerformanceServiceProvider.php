<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

/**
 * Performance Optimization Service Provider
 * Registers performance-related services and optimizations
 */
class PerformanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        // Register performance monitoring singleton
        $this->app->singleton('performance.monitor', function ($app) {
            return new \stdClass(); // Placeholder for future performance monitoring
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Force HTTPS in production
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Optimize view caching
        if ($this->app->environment('production')) {
            View::share('environment', 'production');
        }

        // Register Blade directives for performance
        $this->registerBladeDirectives();

        // Optimize session configuration
        $this->optimizeSessionConfiguration();
    }

    /**
     * Register custom Blade directives for performance.
     *
     * @return void
     */
    protected function registerBladeDirectives()
    {
        // @preload directive for critical resources
        Blade::directive('preload', function ($expression) {
            return "<?php echo '<link rel=\"preload\" href=\"' . {$expression} . '\" as=\"style\">'; ?>";
        });

        // @dnsPrefetch directive
        Blade::directive('dnsPrefetch', function ($expression) {
            return "<?php echo '<link rel=\"dns-prefetch\" href=\"' . {$expression} . '\">'; ?>";
        });

        // @preconnect directive
        Blade::directive('preconnect', function ($expression) {
            return "<?php echo '<link rel=\"preconnect\" href=\"' . {$expression} . '\" crossorigin>'; ?>";
        });

        // @critical directive for inlining critical CSS
        Blade::directive('critical', function ($expression) {
            return "<?php echo '<style>' . file_get_contents(public_path({$expression})) . '</style>'; ?>";
        });
    }

    /**
     * Optimize session configuration for performance.
     *
     * @return void
     */
    protected function optimizeSessionConfiguration()
    {
        // Use Redis for sessions in production
        if ($this->app->environment('production')) {
            config(['session.driver' => 'redis']);
        }

        // Optimize session cookie settings
        config([
            'session.http_only' => true,
            'session.secure' => $this->app->environment('production'),
            'session.same_site' => 'lax',
        ]);
    }
}
