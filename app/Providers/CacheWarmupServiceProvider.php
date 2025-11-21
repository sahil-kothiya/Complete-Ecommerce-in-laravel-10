<?php

namespace App\Providers;

use App\Jobs\WarmHomepageCacheJob;
use App\Services\RedisCacheService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

/**
 * CacheWarmupServiceProvider
 *
 * Automatically warms up homepage cache when `php artisan serve` is run.
 * This ensures the first homepage load is lightning fast.
 */
class CacheWarmupServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        // Only run if auto warmup is enabled
        if (!Config::get('cache_warmup.enabled', true)) {
            return;
        }

        // Only run if Redis cache is enabled
        if (!Config::get('redis_cache.enabled.master', false)) {
            return;
        }

        // Only warm on specific conditions
        if ($this->shouldWarmCache()) {
            $this->warmCache();
        }
    }

    /**
     * Determine if cache should be warmed
     *
     * @return bool
     */
    private function shouldWarmCache(): bool
    {
        // Check if running via artisan serve
        $runningInConsole = $this->app->runningInConsole();

        // Check if cache is already warm
        $cacheAlreadyWarmed = RedisCacheService::has('meta:cache:warmed');

        // Check warmup mode
        $warmupMode = Config::get('cache_warmup.mode', 'on_serve');

        // Check if specific command is running
        if ($runningInConsole && $warmupMode === 'on_serve') {
            // Check if 'serve' command is being executed
            $argv = $_SERVER['argv'] ?? [];
            $isServeCommand = in_array('serve', $argv);

            if ($isServeCommand && !$cacheAlreadyWarmed) {
                return true;
            }
        }

        // Always warm on first boot if configured
        if ($warmupMode === 'on_boot' && !$cacheAlreadyWarmed) {
            return true;
        }

        // Check if warmup is forced
        if (Config::get('cache_warmup.force', false)) {
            return true;
        }

        return false;
    }

    /**
     * Warm the cache
     *
     * @return void
     */
    private function warmCache(): void
    {
        $startTime = microtime(true);
        $mode = Config::get('cache_warmup.execution_mode', 'sync');

        try {
            if ($mode === 'job' && Config::get('queue.default') !== 'sync') {
                // Dispatch as a background job
                WarmHomepageCacheJob::dispatch();

                Log::info('🔥 Cache warmup job dispatched to queue');

                if ($this->app->runningInConsole()) {
                    echo "\n";
                    echo "┌─────────────────────────────────────────────────────────┐\n";
                    echo "│  🔥 Cache Warmup Initiated                              │\n";
                    echo "│  ✓ Background job dispatched to queue                  │\n";
                    echo "│  ⏱️  Cache will be ready in ~30-60 seconds              │\n";
                    echo "└─────────────────────────────────────────────────────────┘\n";
                    echo "\n";
                }
            } else {
                // Execute synchronously (blocking)
                Log::info('🔥 Starting synchronous cache warmup...');

                if ($this->app->runningInConsole()) {
                    echo "\n";
                    echo "┌─────────────────────────────────────────────────────────┐\n";
                    echo "│  🔥 Warming Homepage Cache...                           │\n";
                    echo "│  ⏳ Please wait...                                      │\n";
                    echo "└─────────────────────────────────────────────────────────┘\n";
                }

                // Execute the job directly
                $job = new WarmHomepageCacheJob();
                $job->handle();

                $duration = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('✅ Synchronous cache warmup completed', [
                    'duration_ms' => $duration
                ]);

                if ($this->app->runningInConsole()) {
                    echo "\n";
                    echo "┌─────────────────────────────────────────────────────────┐\n";
                    echo "│  ✅ Cache Warmup Complete!                              │\n";
                    echo "│  ⏱️  Duration: {$duration}ms                            │\n";
                    echo "│  🚀 Homepage will load in ~10-20ms!                     │\n";
                    echo "└─────────────────────────────────────────────────────────┘\n";
                    echo "\n";
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ Cache warmup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($this->app->runningInConsole()) {
                echo "\n";
                echo "┌─────────────────────────────────────────────────────────┐\n";
                echo "│  ⚠️  Cache Warmup Failed                                 │\n";
                echo "│  Error: " . substr($e->getMessage(), 0, 45) . "│\n";
                echo "│  Homepage will still work (slower first load)           │\n";
                echo "└─────────────────────────────────────────────────────────┘\n";
                echo "\n";
            }
        }
    }
}
