<?php

namespace App\Console\Commands;

use App\Services\RedisCacheService;
use App\Services\CacheWarmupService;
use Illuminate\Console\Command;

class CacheWarmup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warmup {target=all : The cache target to warmup (all, homepage, products)}
                            {--limit=100 : Number of products to warmup for products target}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up Redis cache with critical data for optimal performance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(CacheWarmupService $cacheWarmupService)
    {
        $target = $this->argument('target');
        $limit = (int) $this->option('limit');

        $this->info("🔥 Starting cache warmup for target: {$target}");
        $startTime = microtime(true);

        try {
            switch ($target) {
                case 'homepage':
                    $this->warmupHomepage($cacheWarmupService);
                    break;

                case 'products':
                    $this->warmupProducts($cacheWarmupService, $limit);
                    break;

                case 'all':
                    $this->warmupHomepage($cacheWarmupService);
                    $this->warmupProducts($cacheWarmupService, $limit);
                    break;

                default:
                    $this->error("❌ Invalid target: {$target}");
                    $this->line("Valid targets: all, homepage, products");
                    return 1;
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->newLine();
            $this->info("✅ Cache warmup completed successfully in {$duration}ms");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Cache warmup failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Warm up homepage cache
     */
    private function warmupHomepage(CacheWarmupService $service): void
    {
        $this->newLine();
        $this->line("📄 Warming up homepage cache...");

        $results = $service->warmupHomepage();

        if ($results['success']) {
            $this->info("   ✓ Homepage cache warmed up in {$results['duration_ms']}ms");

            foreach ($results['operations'] as $operation => $result) {
                if ($result['success']) {
                    $count = $result['count'] ?? $result['products_count'] ?? 'N/A';
                    $time = $result['duration_ms'];
                    $this->line("   → {$operation}: {$count} items ({$time}ms)");
                } else {
                    $this->error("   ✗ {$operation}: Failed");
                }
            }
        } else {
            $this->error("   ✗ Homepage warmup failed");
            foreach ($results['errors'] as $error) {
                $this->error("     - {$error}");
            }
        }
    }

    /**
     * Warm up product caches
     */
    private function warmupProducts(CacheWarmupService $service, int $limit): void
    {
        $this->newLine();
        $this->line("🛍️  Warming up product caches (limit: {$limit})...");

        $results = $service->warmupTopProducts($limit);

        if ($results['success']) {
            $count = $results['cached_count'];
            $time = $results['duration_ms'];
            $this->info("   ✓ Cached {$count} products in {$time}ms");

            if (!empty($results['errors'])) {
                $errorCount = count($results['errors']);
                $this->warn("   ⚠ {$errorCount} products failed:");
                foreach (array_slice($results['errors'], 0, 5) as $error) {
                    $this->line("     - {$error}");
                }
            }
        } else {
            $this->error("   ✗ Product warmup failed");
            foreach ($results['errors'] as $error) {
                $this->error("     - {$error}");
            }
        }
    }
}
