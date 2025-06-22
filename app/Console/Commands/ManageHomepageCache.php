<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\FrontendController;
use App\Helpers\RedisHelper;

class ManageHomepageCache extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cache:homepage 
                           {action : The action to perform (warm|clear|status|health)}
                           {--force : Force the action without confirmation}';

    /**
     * The console command description.
     */
    protected $description = 'Manage homepage cache (warm up, clear, check status)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');
        $controller = new FrontendController();

        switch ($action) {
            case 'warm':
                return $this->warmUpCache($controller);
            case 'clear':
                return $this->clearCache($controller);
            case 'status':
                return $this->showCacheStatus($controller);
            case 'health':
                return $this->showCacheHealth($controller);
            default:
                $this->error("Invalid action: {$action}");
                $this->info("Available actions: warm, clear, status, health");
                return 1;
        }
    }

    /**
     * Warm up the cache
     */
    private function warmUpCache(FrontendController $controller): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will warm up the homepage cache. Continue?')) {
                $this->info('Cache warming cancelled.');
                return 0;
            }
        }

        $this->info('Starting cache warm-up...');
        $this->newLine();

        $startTime = microtime(true);
        $results = $controller->warmUpHomepageCache();
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        // Display results
        $this->info('Cache Warm-up Results:');
        $this->line('─────────────────────────────────────');

        foreach ($results as $type => $result) {
            if ($type === 'redis_stats') {
                continue; // Handle separately
            }

            if ($result['status'] === 'success') {
                $this->info("✓ {$type}: Success ({$result['duration_ms']}ms, {$result['records']} records)");
            } else {
                $this->error("✗ {$type}: Failed - {$result['error']}");
            }
        }

        // Show Redis stats if available
        if (isset($results['redis_stats'])) {
            $this->newLine();
            $this->info('Redis Memory Usage:');
            $this->line("Before: {$results['redis_stats']['memory_before']}");
            $this->line("After:  {$results['redis_stats']['memory_after']}");
        }

        $this->newLine();
        $this->info("Total time: {$totalTime}ms");

        return 0;
    }

    /**
     * Clear the cache
     */
    private function clearCache(FrontendController $controller): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will clear all homepage cache. Continue?')) {
                $this->info('Cache clearing cancelled.');
                return 0;
            }
        }

        $this->info('Clearing homepage cache...');

        $success = $controller->clearHomepageCache();

        if ($success) {
            $this->info('✓ Homepage cache cleared successfully');
        } else {
            $this->error('✗ Failed to clear homepage cache');
            return 1;
        }

        return 0;
    }

    /**
     * Show cache status
     */
    private function showCacheStatus(FrontendController $controller): int
    {
        $this->info('Homepage Cache Status:');
        $this->line('─────────────────────────────────────');

        $health = $controller->getCacheHealth();

        foreach ($health as $name => $status) {
            if ($name === 'redis_stats') {
                continue; // Handle separately
            }

            $cached = $status['cached'] ? '✓ Cached' : '✗ Not cached';
            $this->line("{$name}: {$cached}");
        }

        // Show Redis stats
        if (isset($health['redis_stats']) && !empty($health['redis_stats'])) {
            $this->newLine();
            $this->info('Redis Statistics:');
            $stats = $health['redis_stats'];
            $this->line("Memory Used: {$stats['used_memory_human']}");
            $this->line("Peak Memory: {$stats['used_memory_peak_human']}");
        }

        return 0;
    }

    /**
     * Show detailed cache health
     */
    private function showCacheHealth(FrontendController $controller): int
    {
        $this->info('Detailed Cache Health Check:');
        $this->line('─────────────────────────────────────');

        $health = $controller->getCacheHealth();

        // Create a table for better visualization
        $headers = ['Cache Type', 'Status', 'Key'];
        $rows = [];

        foreach ($health as $name => $status) {
            if ($name === 'redis_stats') {
                continue;
            }

            $rows[] = [
                $name,
                $status['cached'] ? '<info>✓ Cached</info>' : '<error>✗ Not cached</error>',
                $status['key']
            ];
        }

        $this->table($headers, $rows);

        // Show Redis connection and stats
        $this->newLine();
        $this->info('Redis Health:');

        try {
            $redisConnected = RedisHelper::exists('test_connection_key');
            $this->line('Connection: ' . ($redisConnected !== null ? '✓ Connected' : '✗ Disconnected'));

            if (isset($health['redis_stats']) && !empty($health['redis_stats'])) {
                $stats = $health['redis_stats'];
                $this->line("Memory Used: {$stats['used_memory_human']} / Peak: {$stats['used_memory_peak_human']}");
            }
        } catch (\Exception $e) {
            $this->error('Redis Error: ' . $e->getMessage());
        }

        // Performance recommendations
        $this->newLine();
        $this->info('Recommendations:');

        $uncachedCount = 0;
        foreach ($health as $name => $status) {
            if ($name !== 'redis_stats' && !$status['cached']) {
                $uncachedCount++;
            }
        }

        if ($uncachedCount > 0) {
            $this->line("• {$uncachedCount} cache types are not cached. Run 'php artisan cache:homepage warm' to improve performance.");
        } else {
            $this->line('• All cache types are properly cached. Performance should be optimal.');
        }

        return 0;
    }
}
