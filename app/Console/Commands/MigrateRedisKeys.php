<?php

namespace App\Console\Commands;

use App\Services\RedisKeyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Migrate Redis Keys to New Structure
 *
 * Migrates from old inconsistent key naming to standardized RedisKeyManager format
 */
class MigrateRedisKeys extends Command
{
    protected $signature = 'redis:migrate-keys
                            {--dry-run : Show what would be migrated without actually doing it}
                            {--force : Force migration without confirmation}';

    protected $description = 'Migrate Redis keys to standardized structure';

    public function handle()
    {
        $this->info('🔄 Redis Key Migration Tool');
        $this->newLine();

        $dryRun = $this->option('dry-run');

        // Step 1: Show current key structure
        $this->info('📊 Analyzing current Redis keys...');
        $analysis = $this->analyzeCurrentKeys();
        
        $this->table(
            ['Pattern', 'Count', 'Example'],
            $analysis
        );
        
        $this->newLine();
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->showMigrationPlan();
            return 0;
        }

        if (!$this->option('force')) {
            if (!$this->confirm('This will clear old Redis keys and rebuild indexes. Continue?')) {
                $this->info('Migration cancelled.');
                return 0;
            }
        }

        // Step 2: Clear old keys
        $this->info('🧹 Clearing old keys...');
        $cleared = $this->clearOldKeys();
        $this->info("   ✓ Cleared {$cleared} old keys");

        // Step 3: Rebuild indexes with new structure
        $this->info('🏗️  Rebuilding indexes with new structure...');
        $this->call('indexes:manage', ['action' => 'build', '--force' => true]);

        // Step 4: Rebuild filter cache
        $this->info('🔧 Rebuilding filter cache...');
        $this->call('filters:optimize', ['--clear' => true]);

        $this->newLine();
        $this->info('✅ Migration complete!');
        $this->newLine();

        // Step 5: Verify new structure
        $this->info('📊 New Redis structure:');
        $newAnalysis = $this->analyzeCurrentKeys();
        $this->table(
            ['Pattern', 'Count', 'Example'],
            $newAnalysis
        );

        return 0;
    }

    /**
     * Analyze current Redis keys
     */
    private function analyzeCurrentKeys(): array
    {
        $patterns = [
            'ecom:*',
            'ecommerce:v1:*',
            'index:*',
            'uf_*',
            'cache:*',
            'temp:*',
        ];

        $analysis = [];

        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            $count = count($keys);

            if ($count > 0) {
                $example = $keys[0] ?? 'N/A';
                $analysis[] = [
                    'pattern' => $pattern,
                    'count' => $count,
                    'example' => strlen($example) > 60 ? substr($example, 0, 57) . '...' : $example
                ];
            }
        }

        return $analysis;
    }

    /**
     * Show migration plan
     */
    private function showMigrationPlan(): void
    {
        $this->newLine();
        $this->info('📋 Migration Plan:');
        $this->newLine();

        $plan = [
            ['Step', 'Action', 'Details'],
            ['1', 'Clear Old Keys', 'Remove ecommerce:v1:*, uf_*, cache:homepage:*, etc.'],
            ['2', 'Rebuild Indexes', 'Create ecom:index:cat:{id}, ecom:index:brand:{id}, etc.'],
            ['3', 'Rebuild Filters', 'Create ecom:filter:meta:{slug}, ecom:filter:price_idx:{slug}'],
            ['4', 'Update Settings', 'Migrate to ecom:settings:global'],
        ];

        $this->table($plan[0], array_slice($plan, 1));

        $this->newLine();
        $this->info('New Key Structure:');
        $this->line('  ecom:index:cat:{id}          → Category indexes');
        $this->line('  ecom:index:brand:{id}        → Brand indexes');
        $this->line('  ecom:index:price:{range}     → Price range indexes');
        $this->line('  ecom:filter:meta:{slug}      → Filter metadata (HASH)');
        $this->line('  ecom:filter:price_idx:{slug} → Price sorted set (ZSET)');
        $this->line('  ecom:settings:global         → Global settings');
        $this->line('  ecom:cache:product:{id}      → Product cache');
        $this->line('  ecom:temp:filter:{hash}      → Temporary filter results');
    }

    /**
     * Clear old keys
     */
    private function clearOldKeys(): int
    {
        $patterns = RedisKeyManager::getOldPatterns();
        $totalCleared = 0;

        foreach ($patterns as $pattern) {
            $keys = Redis::keys($pattern);
            
            if (!empty($keys)) {
                // Delete in chunks to avoid blocking
                foreach (array_chunk($keys, 1000) as $chunk) {
                    Redis::del(...$chunk);
                    $totalCleared += count($chunk);
                }
            }
        }

        return $totalCleared;
    }
}
