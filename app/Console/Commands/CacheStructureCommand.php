<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Services\SmartFilterCacheService;

/**
 * Manage and view Redis cache structure
 *
 * Usage:
 * php artisan cache:structure --show     (show current structure)
 * php artisan cache:structure --clear    (clear all filter cache)
 * php artisan cache:structure --stats    (show cache statistics)
 */
class CacheStructureCommand extends Command
{
    protected $signature = 'cache:structure
                            {--show : Show cache structure and organization}
                            {--clear : Clear all filter cache}
                            {--clear-products : Clear only product cache, keep metrics}
                            {--stats : Show cache statistics}
                            {--analyze : Analyze Redis key distribution}';

    protected $description = 'View and manage Redis cache structure';

    private SmartFilterCacheService $cacheService;

    public function __construct(SmartFilterCacheService $cacheService)
    {
        parent::__construct();
        $this->cacheService = $cacheService;
    }

    public function handle()
    {
        $this->info('🔧 Redis Cache Structure Manager');
        $this->newLine();

        if ($this->option('show')) {
            $this->showStructure();
        }

        if ($this->option('clear')) {
            $this->clearCache();
        }

        if ($this->option('clear-products')) {
            $this->clearProductsOnly();
        }

        if ($this->option('stats')) {
            $this->showStats();
        }

        if ($this->option('analyze')) {
            $this->analyzeKeys();
        }

        if (!$this->option('show') && !$this->option('clear') &&
            !$this->option('clear-products') && !$this->option('stats') &&
            !$this->option('analyze')) {
            $this->error('Please specify an option: --show, --clear, --clear-products, --stats, or --analyze');
            return 1;
        }

        return 0;
    }

    private function showStructure(): void
    {
        $structure = $this->cacheService->getCacheStructure();

        $this->info('📁 Cache Namespace Structure:');
        $this->newLine();

        $this->table(
            ['Component', 'Prefix'],
            [
                ['Namespace', $structure['namespace']],
                ['Version', $structure['version']],
                ['Filters', $structure['structure']['filters']],
                ['Indexes', $structure['structure']['indexes']],
                ['Metrics', $structure['structure']['metrics']],
                ['Engagement', $structure['structure']['engagement']],
                ['Analytics', $structure['structure']['analytics']],
            ]
        );

        $this->newLine();
        $this->info('📝 Example Keys:');
        foreach ($structure['example_keys'] as $type => $example) {
            $this->line("  {$type}: <fg=cyan>{$example}</>");
        }
        $this->newLine();
    }

    private function clearCache(): void
    {
        if (!$this->confirm('⚠️  This will clear ALL filter cache. Continue?', false)) {
            $this->info('Cancelled.');
            return;
        }

        $this->info('🗑️  Clearing all filter cache...');
        $count = $this->cacheService->clearAllFilterCache();

        $this->newLine();
        $this->info("✅ Cleared {$count} cache keys");
    }

    private function clearProductsOnly(): void
    {
        if (!$this->confirm('Clear only product cache (keep metrics/analytics)?', true)) {
            $this->info('Cancelled.');
            return;
        }

        $this->info('🗑️  Clearing product cache...');
        $count = $this->cacheService->clearProductCache();

        $this->newLine();
        $this->info("✅ Cleared {$count} product cache keys");
    }

    private function showStats(): void
    {
        $stats = $this->cacheService->getStats();

        $this->info('📊 Cache Statistics:');
        $this->newLine();

        $totalRequests = $stats['tier1_hits'] + $stats['tier2_hits'] + $stats['misses'];
        $hitRate = $totalRequests > 0
            ? round((($stats['tier1_hits'] + $stats['tier2_hits']) / $totalRequests) * 100, 2)
            : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Tier 1 Hits', number_format($stats['tier1_hits'])],
                ['Tier 2 Hits', number_format($stats['tier2_hits'])],
                ['Cache Misses', number_format($stats['misses'])],
                ['Total Requests', number_format($totalRequests)],
                ['Hit Rate', $hitRate . '%'],
                ['Hot Combos Tracked', number_format($stats['hot_combos_count'])],
            ]
        );

        if (!empty($stats['top_combos'])) {
            $this->newLine();
            $this->info('🔥 Top 10 Popular Filter Combinations:');

            $topCombos = [];
            for ($i = 0; $i < count($stats['top_combos']); $i += 2) {
                $key = $stats['top_combos'][$i];
                $score = $stats['top_combos'][$i + 1] ?? 0;

                $topCombos[] = [
                    ($i / 2) + 1,
                    substr($key, 0, 60) . '...',
                    number_format($score)
                ];
            }

            $this->table(['Rank', 'Cache Key', 'Access Count'], array_slice($topCombos, 0, 10));
        }
    }

    private function analyzeKeys(): void
    {
        $this->info('🔍 Analyzing Redis key distribution...');
        $this->newLine();

        // Get all keys with ecommerce namespace
        $allKeys = Redis::keys('ecommerce:*');

        $distribution = [
            'filters' => 0,
            'indexes' => 0,
            'metrics' => 0,
            'engagement' => 0,
            'analytics' => 0,
            'other' => 0,
        ];

        $totalSize = 0;

        foreach ($allKeys as $key) {
            // Categorize key
            if (str_contains($key, ':filters:')) {
                $distribution['filters']++;
            } elseif (str_contains($key, ':indexes:')) {
                $distribution['indexes']++;
            } elseif (str_contains($key, ':metrics:')) {
                $distribution['metrics']++;
            } elseif (str_contains($key, ':engagement:')) {
                $distribution['engagement']++;
            } elseif (str_contains($key, ':analytics:')) {
                $distribution['analytics']++;
            } else {
                $distribution['other']++;
            }

            // Estimate size
            $type = Redis::type($key);
            if ($type === 'string') {
                $totalSize += strlen(Redis::get($key) ?? '');
            }
        }

        $this->table(
            ['Category', 'Count', 'Percentage'],
            collect($distribution)->map(function($count, $category) use ($allKeys) {
                $total = count($allKeys);
                $percentage = $total > 0 ? round(($count / $total) * 100, 2) : 0;
                return [$category, number_format($count), $percentage . '%'];
            })->toArray()
        );

        $this->newLine();
        $this->info('📦 Storage Info:');
        $this->line("  Total Keys: " . number_format(count($allKeys)));
        $this->line("  Estimated Size: " . $this->formatBytes($totalSize));

        // Redis memory info
        $info = Redis::info('memory');
        if (isset($info['used_memory_human'])) {
            $this->line("  Redis Memory Used: {$info['used_memory_human']}");
        }
        if (isset($info['used_memory_peak_human'])) {
            $this->line("  Redis Memory Peak: {$info['used_memory_peak_human']}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
