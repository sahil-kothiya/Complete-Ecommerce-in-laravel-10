<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Exception;

class RedisHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:health 
                            {--fix : Attempt to fix common issues}
                            {--detailed : Show detailed statistics}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Redis health, persistence status, and performance metrics for 10M+ products';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🔍 Redis Health Check for E-Commerce System (10M+ Products)');
        $this->newLine();

        try {
            $redis = Redis::connection();
            
            // 1. Connection Check
            $this->checkConnection($redis);
            
            // 2. Memory Check
            $this->checkMemory($redis);
            
            // 3. Persistence Check
            $this->checkPersistence($redis);
            
            // 4. Performance Metrics
            $this->checkPerformance($redis);
            
            // 5. Key Statistics
            $this->checkKeyStats($redis);
            
            // 6. Detailed stats if requested
            if ($this->option('detailed')) {
                $this->showDetailedStats($redis);
            }
            
            // 7. Auto-fix if requested
            if ($this->option('fix')) {
                $this->attemptFixes($redis);
            }
            
            $this->newLine();
            $this->info('✅ Health check complete!');
            
            return 0;
        } catch (Exception $e) {
            $this->error('❌ Redis connection failed: ' . $e->getMessage());
            $this->newLine();
            $this->warn('Troubleshooting steps:');
            $this->line('1. Ensure Redis is running: redis-server');
            $this->line('2. Check connection settings in .env');
            $this->line('3. Verify Redis configuration: config/database.php');
            $this->line('4. Check disk space and permissions');
            
            return 1;
        }
    }

    /**
     * Check Redis connection
     */
    private function checkConnection($redis): void
    {
        $this->info('📡 Connection Status');
        
        $ping = $redis->ping();
        if ($ping === 'PONG' || $ping === true) {
            $this->line('   ✓ Connection: <fg=green>OK</fg=green>');
        } else {
            $this->line('   ✗ Connection: <fg=red>FAILED</fg=red>');
        }
        
        $info = $redis->info('server');
        $this->line("   • Redis Version: {$info['redis_version']}");
        $this->line("   • Uptime: " . $this->formatUptime($info['uptime_in_seconds']));
        $this->newLine();
    }

    /**
     * Check memory usage
     */
    private function checkMemory($redis): void
    {
        $this->info('💾 Memory Status');
        
        $info = $redis->info('memory');
        
        $usedMemory = $this->formatBytes($info['used_memory']);
        $peakMemory = $this->formatBytes($info['used_memory_peak']);
        
        $this->line("   • Used Memory: {$usedMemory}");
        $this->line("   • Peak Memory: {$peakMemory}");
        
        // Check maxmemory setting
        $maxmemory = $redis->config('GET', 'maxmemory')['maxmemory'] ?? 0;
        if ($maxmemory > 0) {
            $maxMemoryFormatted = $this->formatBytes($maxmemory);
            $usedPercent = round(($info['used_memory'] / $maxmemory) * 100, 2);
            
            $color = $usedPercent > 90 ? 'red' : ($usedPercent > 70 ? 'yellow' : 'green');
            $this->line("   • Max Memory: {$maxMemoryFormatted}");
            $this->line("   • Usage: <fg={$color}>{$usedPercent}%</fg={$color}>");
            
            if ($usedPercent > 90) {
                $this->warn('   ⚠ WARNING: Memory usage critical!');
            }
        } else {
            $this->warn('   ⚠ No maxmemory limit set (not recommended for production)');
        }
        
        // Eviction policy
        $evictionPolicy = $redis->config('GET', 'maxmemory-policy')['maxmemory-policy'] ?? 'none';
        $this->line("   • Eviction Policy: {$evictionPolicy}");
        
        if ($evictionPolicy === 'noeviction') {
            $this->warn('   ⚠ Eviction disabled - writes will fail when memory is full!');
        }
        
        $this->newLine();
    }

    /**
     * Check persistence configuration
     */
    private function checkPersistence($redis): void
    {
        $this->info('💿 Persistence Status');
        
        $info = $redis->info('persistence');
        
        // RDB Status
        $rdbEnabled = ($redis->config('GET', 'save')['save'] ?? '') !== '';
        $this->line('   RDB Snapshots:');
        if ($rdbEnabled) {
            $lastSave = date('Y-m-d H:i:s', $info['rdb_last_save_time']);
            $this->line("   • Status: <fg=green>Enabled</fg=green>");
            $this->line("   • Last Save: {$lastSave}");
            
            if (isset($info['rdb_last_bgsave_status']) && $info['rdb_last_bgsave_status'] === 'ok') {
                $this->line("   • Last BGSAVE: <fg=green>OK</fg=green>");
            } else {
                $this->line("   • Last BGSAVE: <fg=red>FAILED</fg=red>");
                $this->error('   ✗ RDB persistence failing - this causes write errors!');
            }
        } else {
            $this->line("   • Status: <fg=yellow>Disabled</fg=yellow>");
        }
        
        // AOF Status
        $aofEnabled = ($redis->config('GET', 'appendonly')['appendonly'] ?? 'no') === 'yes';
        $this->line('   AOF (Append Only File):');
        if ($aofEnabled) {
            $this->line("   • Status: <fg=green>Enabled</fg=green>");
            $aofSize = $this->formatBytes($info['aof_current_size'] ?? 0);
            $this->line("   • AOF Size: {$aofSize}");
            
            if (isset($info['aof_last_bgrewrite_status']) && $info['aof_last_bgrewrite_status'] === 'ok') {
                $this->line("   • Last Rewrite: <fg=green>OK</fg=green>");
            }
        } else {
            $this->line("   • Status: <fg=yellow>Disabled</fg=yellow>");
        }
        
        // Check stop-writes-on-bgsave-error
        $stopWrites = $redis->config('GET', 'stop-writes-on-bgsave-error')['stop-writes-on-bgsave-error'] ?? 'yes';
        if ($stopWrites === 'yes') {
            $this->warn('   ⚠ stop-writes-on-bgsave-error is ENABLED');
            $this->warn('     This WILL block writes if RDB save fails!');
            $this->line('     Recommended: Set to "no" for cache workloads');
        } else {
            $this->line('   • stop-writes-on-bgsave-error: <fg=green>Disabled</fg=green> (Good for cache)');
        }
        
        $this->newLine();
    }

    /**
     * Check performance metrics
     */
    private function checkPerformance($redis): void
    {
        $this->info('⚡ Performance Metrics');
        
        $info = $redis->info('stats');
        
        $totalCommands = $info['total_commands_processed'] ?? 0;
        $opsPerSec = $info['instantaneous_ops_per_sec'] ?? 0;
        
        $this->line("   • Total Commands: " . number_format($totalCommands));
        $this->line("   • Ops/Second: " . number_format($opsPerSec));
        
        // Hit rate
        $keyspaceInfo = $redis->info('stats');
        if (isset($keyspaceInfo['keyspace_hits']) && isset($keyspaceInfo['keyspace_misses'])) {
            $hits = $keyspaceInfo['keyspace_hits'];
            $misses = $keyspaceInfo['keyspace_misses'];
            $total = $hits + $misses;
            
            if ($total > 0) {
                $hitRate = round(($hits / $total) * 100, 2);
                $color = $hitRate > 80 ? 'green' : ($hitRate > 50 ? 'yellow' : 'red');
                $this->line("   • Cache Hit Rate: <fg={$color}>{$hitRate}%</fg={$color}>");
                
                if ($hitRate < 50) {
                    $this->warn('   ⚠ Low hit rate - consider reviewing cache strategy');
                }
            }
        }
        
        // Slow log
        $slowLogCount = count($redis->slowlog('get', 10));
        if ($slowLogCount > 0) {
            $this->warn("   ⚠ {$slowLogCount} slow commands in last 10");
            $this->line('     Run: redis-cli slowlog get 10');
        }
        
        $this->newLine();
    }

    /**
     * Check key statistics
     */
    private function checkKeyStats($redis): void
    {
        $this->info('🔑 Key Statistics');
        
        // Get keys by namespace
        $namespaces = [
            'ec:p:*' => 'Products',
            'ec:v:*' => 'Variants',
            'ec:cat:*' => 'Categories',
            'ec:br:*' => 'Brands',
            'ec:flt:*' => 'Filters',
            'ec:pg:*' => 'Pages',
            'ec:usr:*' => 'Users',
        ];
        
        $totalKeys = 0;
        foreach ($namespaces as $pattern => $label) {
            $keys = $redis->keys($pattern);
            $count = count($keys);
            $totalKeys += $count;
            
            if ($count > 0) {
                $this->line(sprintf('   • %-12s: %s keys', $label, number_format($count)));
            }
        }
        
        $this->line('   ' . str_repeat('-', 30));
        $this->line(sprintf('   • %-12s: %s keys', 'TOTAL', number_format($totalKeys)));
        
        $this->newLine();
    }

    /**
     * Show detailed statistics
     */
    private function showDetailedStats($redis): void
    {
        $this->info('📊 Detailed Statistics');
        
        $allInfo = $redis->info();
        
        $sections = ['clients', 'cpu', 'replication', 'persistence'];
        foreach ($sections as $section) {
            if (isset($allInfo[$section])) {
                $this->line("   [{$section}]");
                foreach ($allInfo[$section] as $key => $value) {
                    $this->line("     • {$key}: {$value}");
                }
            }
        }
        
        $this->newLine();
    }

    /**
     * Attempt to fix common issues
     */
    private function attemptFixes($redis): void
    {
        $this->info('🔧 Attempting Auto-Fixes');
        
        try {
            // Fix 1: Disable stop-writes-on-bgsave-error
            $stopWrites = $redis->config('GET', 'stop-writes-on-bgsave-error')['stop-writes-on-bgsave-error'] ?? 'yes';
            if ($stopWrites === 'yes') {
                $redis->config('SET', 'stop-writes-on-bgsave-error', 'no');
                $this->line('   ✓ Disabled stop-writes-on-bgsave-error');
            }
            
            // Fix 2: Set maxmemory if not set
            $maxmemory = $redis->config('GET', 'maxmemory')['maxmemory'] ?? 0;
            if ($maxmemory == 0) {
                $redis->config('SET', 'maxmemory', '4gb');
                $this->line('   ✓ Set maxmemory to 4GB');
            }
            
            // Fix 3: Set eviction policy
            $evictionPolicy = $redis->config('GET', 'maxmemory-policy')['maxmemory-policy'] ?? 'noeviction';
            if ($evictionPolicy === 'noeviction') {
                $redis->config('SET', 'maxmemory-policy', 'allkeys-lru');
                $this->line('   ✓ Set eviction policy to allkeys-lru');
            }
            
            $this->info('   ✅ Fixes applied successfully');
        } catch (Exception $e) {
            $this->error('   ✗ Fix failed: ' . $e->getMessage());
        }
        
        $this->newLine();
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Format uptime to human readable
     */
    private function formatUptime($seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}d";
        if ($hours > 0) $parts[] = "{$hours}h";
        if ($minutes > 0) $parts[] = "{$minutes}m";
        
        return implode(' ', $parts) ?: '< 1m';
    }
}
