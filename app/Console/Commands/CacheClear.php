<?php

namespace App\Console\Commands;

use App\Helpers\RedisHelper;
use Illuminate\Console\Command;

class CacheClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-redis {pattern? : The cache pattern to clear (homepage, products, all)}
                            {--confirm : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear Redis cache by pattern';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $pattern = $this->argument('pattern') ?? 'all';
        $confirm = $this->option('confirm');

        $patterns = $this->getPatternsForTarget($pattern);

        if (empty($patterns)) {
            $this->error("❌ Invalid pattern: {$pattern}");
            $this->line("Valid patterns: homepage, products, all");
            return 1;
        }

        // Show what will be cleared
        $this->warn("⚠️  This will clear the following cache patterns:");
        foreach ($patterns as $p) {
            $this->line("   - {$p}");
        }

        // Confirm
        if (!$confirm && !$this->confirm('Do you want to continue?', false)) {
            $this->info('Cache clear cancelled.');
            return 0;
        }

        $this->info("🗑️  Clearing cache...");
        $startTime = microtime(true);
        $totalDeleted = 0;

        try {
            foreach ($patterns as $p) {
                $deleted = RedisHelper::deletePattern($p);
                $totalDeleted += $deleted;
                $this->line("   ✓ Cleared {$deleted} keys matching: {$p}");
            }

            // Increment version for full page cache invalidation
            if ($pattern === 'homepage' || $pattern === 'all') {
                $newVersion = RedisHelper::incrementVersion('meta:cache:version');
                $this->line("   ✓ Incremented cache version to: {$newVersion}");
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->newLine();
            $this->info("✅ Cleared {$totalDeleted} cache keys in {$duration}ms");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to clear cache: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get cache patterns for target
     */
    private function getPatternsForTarget(string $target): array
    {
        return match ($target) {
            'homepage' => [
                'cache:homepage:*',
            ],
            'products' => [
                'product:*',
                'collection:products:*',
            ],
            'all' => [
                'cache:homepage:*',
                'product:*',
                'collection:products:*',
                'aggregate:products:*',
            ],
            default => [],
        };
    }
}
