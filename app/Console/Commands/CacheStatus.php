<?php

namespace App\Console\Commands;

use App\Helpers\RedisHelper;
use App\Services\CacheWarmupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class CacheStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:status {--detailed : Show detailed cache information}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display Redis cache status and health information';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(CacheWarmupService $cacheWarmupService)
    {
        $detailed = $this->option('detailed');

        $this->info("📊 Redis Cache Status");
        $this->newLine();

        try {
            // Redis connection status
            $this->displayRedisStatus();

            // Homepage cache status
            $this->displayHomepageCacheStatus($cacheWarmupService);

            // Cache statistics
            if ($detailed) {
                $this->displayDetailedStatistics();
            }

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to get cache status: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Display Redis connection status
     */
    private function displayRedisStatus(): void
    {
        $this->line("🔗 <options=bold>Redis Connection</>");

        $info = RedisHelper::getRedisInfo();

        if (empty($info)) {
            $this->error("   ✗ Redis is not connected");
            return;
        }

        $this->info("   ✓ Redis is connected");
        $this->line("   Version: {$info['version']}");
        $this->line("   Uptime: {$info['uptime_days']} days");
        $this->line("   Connected clients: {$info['connected_clients']}");
        $this->line("   Memory used: {$info['used_memory']}");
        $this->line("   Memory peak: {$info['used_memory_peak']}");
        $this->line("   Operations/sec: {$info['instantaneous_ops_per_sec']}");

        $hitRate = $info['hit_rate'];
        $hitRateColor = $hitRate >= 90 ? 'green' : ($hitRate >= 70 ? 'yellow' : 'red');
        $this->line("   Hit rate: <fg={$hitRateColor}>{$hitRate}%</>");

        $this->newLine();
    }

    /**
     * Display homepage cache status
     */
    private function displayHomepageCacheStatus(CacheWarmupService $service): void
    {
        $this->line("🏠 <options=bold>Homepage Cache Status</>");

        $status = $service->getWarmupStatus();

        $cacheVersion = RedisHelper::getVersion('meta:cache:version');
        $this->line("   Cache Version: {$cacheVersion}");
        $this->newLine();

        foreach ($status as $name => $info) {
            $statusIcon = $info['cached'] ? '✓' : '✗';
            $statusColor = $info['cached'] ? 'green' : 'red';

            $this->line("   <fg={$statusColor}>{$statusIcon}</> " . ucwords(str_replace('_', ' ', $name)));

            if ($info['cached']) {
                $expiresIn = $info['expires_in'] ?? 'N/A';
                $this->line("      Expires in: {$expiresIn}");
            }
        }

        $this->newLine();
    }

    /**
     * Display detailed statistics
     */
    private function displayDetailedStatistics(): void
    {
        $this->line("📈 <options=bold>Detailed Statistics</>");

        // Count keys by pattern
        $patterns = [
            'homepage' => 'cache:homepage:*',
            'products' => 'product:*',
            'categories' => 'category:*',
            'collections' => 'collection:*',
        ];

        foreach ($patterns as $name => $pattern) {
            $keys = RedisHelper::scanKeys($pattern, 1000);
            $count = count($keys);
            $this->line("   " . ucfirst($name) . " keys: {$count}");
        }

        $this->newLine();

        // Total keys
        $totalKeys = RedisHelper::dbSize();
        $this->line("   <options=bold>Total keys in database: {$totalKeys}</>");

        $this->newLine();
    }
}
