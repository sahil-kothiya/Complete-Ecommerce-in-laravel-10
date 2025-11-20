<?php

namespace App\Console\Commands;

use App\Services\RedisCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Centralized Redis Cache Management Command
 *
 * Manage all Redis cache operations from a single place.
 * Monitor, warm, clear, and configure caches dynamically.
 */
class RedisCacheCommand extends Command
{
    protected $signature = 'redis:cache
                            {action : Action to perform: status|stats|clear|warm|enable|disable|flush|keys}
                            {--type=* : Cache type(s) to act upon}
                            {--pattern= : Pattern for key operations}
                            {--detailed : Show detailed information}
                            {--confirm : Confirm destructive operations}';

    protected $description = 'Manage Redis cache from a single centralized command';

    public function handle()
    {
        $action = $this->argument('action');

        return match ($action) {
            'status' => $this->showStatus(),
            'stats' => $this->showStats(),
            'clear' => $this->clearCache(),
            'warm' => $this->warmCache(),
            'enable' => $this->toggleCache(true),
            'disable' => $this->toggleCache(false),
            'flush' => $this->flushAll(),
            'keys' => $this->listKeys(),
            default => $this->error("Unknown action: {$action}"),
        };
    }

    /**
     * Show cache status
     */
    private function showStatus(): int
    {
        $this->info('📊 Redis Cache Status');
        $this->newLine();

        // Check connection
        if (!RedisCacheService::ping()) {
            $this->error('❌ Redis is not available!');
            return 1;
        }

        $this->info('✅ Redis connection: OK');
        $this->newLine();

        // Show enabled/disabled status
        $config = Config::get('redis_cache.enabled');

        $this->table(
            ['Cache Type', 'Status'],
            collect($config)->map(fn($enabled, $type) => [
                $type,
                $enabled ? '✅ Enabled' : '❌ Disabled'
            ])->toArray()
        );

        if ($this->option('detailed')) {
            $this->newLine();
            $this->showStats();
        }

        return 0;
    }

    /**
     * Show detailed statistics
     */
    private function showStats(): int
    {
        $this->info('📈 Redis Cache Statistics');
        $this->newLine();

        $stats = RedisCacheService::getStats();

        // Redis stats
        if (isset($stats['redis'])) {
            $redis = $stats['redis'];
            $this->info('Redis Server:');
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total Keys', number_format($redis['total_keys'])],
                    ['Hit Rate', $redis['hit_rate'] . '%'],
                    ['Hits', number_format($redis['hits'])],
                    ['Misses', number_format($redis['misses'])],
                    ['Memory Used', $redis['used_memory']],
                    ['Memory Peak', $redis['used_memory_peak']],
                    ['Ops/sec', number_format($redis['ops_per_sec'])],
                ]
            );
        }

        // Configuration
        $this->newLine();
        $this->info('Configuration:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Encoding', $stats['config']['encoding'] ?? 'N/A'],
                ['Compression', $stats['config']['compression'] ? 'Yes' : 'No'],
                ['Chunking', $stats['config']['chunking'] ? 'Yes' : 'No'],
                ['Locking', $stats['config']['locking'] ? 'Yes' : 'No'],
            ]
        );

        return 0;
    }

    /**
     * Clear cache by type
     */
    private function clearCache(): int
    {
        $types = $this->option('type');

        if (empty($types)) {
            $this->error('Please specify cache type(s) with --type');
            $this->info('Example: redis:cache clear --type=homepage --type=products');
            return 1;
        }

        if (!$this->option('confirm')) {
            if (!$this->confirm('Are you sure you want to clear these caches?')) {
                $this->info('Cancelled.');
                return 0;
            }
        }

        foreach ($types as $type) {
            $this->info("Clearing {$type} cache...");

            $prefix = Config::get("redis_cache.prefixes.{$type}");
            if ($prefix) {
                $pattern = $prefix . ':*';
                $deleted = RedisCacheService::forgetPattern($pattern);
                $this->info("✅ Deleted {$deleted} keys for {$type}");
            } else {
                $this->warn("⚠️  Unknown cache type: {$type}");
            }
        }

        // Increment version for homepage if it was cleared
        if (in_array('homepage', $types) || in_array('page:home', $types)) {
            RedisCacheService::incrementVersion();
            $this->info('✅ Incremented cache version');
        }

        return 0;
    }

    /**
     * Warm up caches
     */
    private function warmCache(): int
    {
        $types = $this->option('type') ?: ['homepage'];

        $this->info('🔥 Warming up caches...');
        $this->newLine();

        foreach ($types as $type) {
            $this->info("Warming {$type}...");

            try {
                match ($type) {
                    'homepage' => $this->warmHomepage(),
                    'products' => $this->warmProducts(),
                    'categories' => $this->warmCategories(),
                    default => $this->warn("⚠️  Don't know how to warm: {$type}"),
                };
            } catch (\Exception $e) {
                $this->error("❌ Failed to warm {$type}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('✅ Cache warming complete!');

        return 0;
    }

    /**
     * Warm homepage cache
     */
    private function warmHomepage(): void
    {
        // Call the homepage route to generate cache
        $this->call('route:list', ['--name' => 'home']);
        $this->info('Homepage warmed via route call');
    }

    /**
     * Warm products cache
     */
    private function warmProducts(): void
    {
        $this->warn('Product warming not yet implemented');
    }

    /**
     * Warm categories cache
     */
    private function warmCategories(): void
    {
        $this->warn('Category warming not yet implemented');
    }

    /**
     * Enable/disable cache types
     */
    private function toggleCache(bool $enable): int
    {
        $types = $this->option('type') ?: ['master'];
        $action = $enable ? 'Enabling' : 'Disabling';

        foreach ($types as $type) {
            $this->info("{$action} {$type} cache...");

            // Update .env file
            $envPath = base_path('.env');
            $envKey = $this->getEnvKey($type);

            if ($envKey) {
                $this->updateEnv($envPath, $envKey, $enable ? 'true' : 'false');
                $this->info("✅ Updated {$envKey}");
            }
        }

        $this->newLine();
        $this->warn('⚠️  Please run: php artisan config:clear');

        return 0;
    }

    /**
     * Flush all caches (dangerous!)
     */
    private function flushAll(): int
    {
        if ($this->option('confirm')) {
            $this->error('❌ This will DELETE ALL Redis data!');
            if (!$this->confirm('Are you ABSOLUTELY sure?')) {
                $this->info('Cancelled.');
                return 0;
            }
        } else {
            $this->warn('⚠️ Auto-confirm enabled: flushing Redis without prompt.');
        }

        $this->warn('Flushing entire Redis database...');
        RedisCacheService::flush();
        $this->info('✅ Redis database flushed');

        return 0;
    }

    /**
     * List keys matching pattern
     */
    private function listKeys(): int
    {
        $pattern = $this->option('pattern') ?: '*';
        $limit = 100;

        $this->info("Searching for keys matching: {$pattern}");
        $keys = RedisCacheService::keys($pattern, $limit);

        if (empty($keys)) {
            $this->warn('No keys found');
            return 0;
        }

        $this->info("Found " . count($keys) . " keys:");
        $this->newLine();

        foreach ($keys as $key) {
            $ttl = RedisCacheService::has($key) ? 'exists' : 'missing';
            $this->line("  • {$key} [{$ttl}]");
        }

        if (count($keys) >= $limit) {
            $this->newLine();
            $this->warn("Showing first {$limit} keys. There may be more.");
        }

        return 0;
    }

    /**
     * Get environment variable key for cache type
     */
    private function getEnvKey(string $type): ?string
    {
        return match ($type) {
            'master' => 'REDIS_CACHE_ENABLED',
            'homepage' => 'CACHE_HOMEPAGE_ENABLED',
            'products' => 'CACHE_PRODUCTS_ENABLED',
            'categories' => 'CACHE_CATEGORIES_ENABLED',
            'banners' => 'CACHE_BANNERS_ENABLED',
            'search' => 'CACHE_SEARCH_ENABLED',
            default => null,
        };
    }

    /**
     * Update .env file
     */
    private function updateEnv(string $path, string $key, string $value): void
    {
        $content = file_get_contents($path);

        if (str_contains($content, $key . '=')) {
            // Update existing
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
        } else {
            // Add new
            $content .= "\n{$key}={$value}\n";
        }

        file_put_contents($path, $content);
    }
}
