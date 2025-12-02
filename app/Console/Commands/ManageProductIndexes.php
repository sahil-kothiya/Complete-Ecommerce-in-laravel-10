<?php

namespace App\Console\Commands;

use App\Services\ProductIndexService;
use App\Services\FastFilterService;
use App\Services\RedisKeyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

/**
 * Manage product filter indexes
 *
 * Usage:
 * php artisan indexes:build           - Build all indexes
 * php artisan indexes:stats           - Show index statistics
 * php artisan indexes:clean           - Clean up temp keys
 * php artisan indexes:test            - Test filter performance
 */
class ManageProductIndexes extends Command
{
    protected $signature = 'indexes:manage
                            {action : Action to perform: build|stats|clean|test}
                            {--force : Force rebuild even if indexes exist}';

    protected $description = 'Manage product filter indexes for fast filtering';

    private ProductIndexService $indexService;
    private FastFilterService $filterService;

    public function __construct()
    {
        parent::__construct();
        $this->indexService = app(ProductIndexService::class);
        $this->filterService = app(FastFilterService::class);
    }

    public function handle()
    {
        $action = $this->argument('action');

        match($action) {
            'build' => $this->buildIndexes(),
            'stats' => $this->showStats(),
            'clean' => $this->cleanTempKeys(),
            'test' => $this->testPerformance(),
            default => $this->error("Unknown action: {$action}. Use: build|stats|clean|test")
        };
    }

    /**
     * Build all product indexes
     */
    private function buildIndexes(): void
    {
        $this->info('Building product indexes...');
        $this->info('This may take 5-10 minutes for 10M+ products.');

        if (!$this->option('force')) {
            // Use RedisKeyManager pattern for new structure
            $pattern = RedisKeyManager::pattern('index');
            $existingKeys = count(Redis::keys($pattern));
            if ($existingKeys > 0) {
                if (!$this->confirm("Found {$existingKeys} existing index keys. Rebuild?")) {
                    $this->info('Cancelled.');
                    return;
                }
            }
        }

        $bar = $this->output->createProgressBar(5);
        $bar->start();

        // Pass the progress bar and console output to the service
        $stats = $this->indexService->buildAllIndexes(function($step, $message, $progress = null) use ($bar) {
            $bar->advance();
            $this->newLine();
            $this->line($message);
            if ($progress !== null) {
                $this->line("  Progress: {$progress}");
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Indexes built successfully!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Categories indexed', $stats['categories']],
                ['Brands indexed', $stats['brands']],
                ['Price ranges', $stats['price_ranges']],
                ['Rating levels', $stats['ratings']],
                ['Discount levels', $stats['discounts']],
                ['Total products', number_format($stats['total_products_indexed'])],
                ['Build time', $stats['build_time_seconds'] . 's'],
            ]
        );
    }

    /**
     * Show index statistics
     */
    private function showStats(): void
    {
        $this->info('Fetching index statistics...');

        // Parallel fetch for speed
        $indexStats = $this->indexService->getIndexStats();
        $filterStats = $this->filterService->getStats();

        $this->info('=== Index Overview ===');
        $this->table(
            ['Index Type', 'Count', 'Total Products'],
            [
                ['Categories', count($indexStats['categories']), array_sum(array_column($indexStats['categories'], 'product_count'))],
                ['Brands', count($indexStats['brands']), array_sum(array_column($indexStats['brands'], 'product_count'))],
                ['Price Ranges', count($indexStats['price_ranges']), array_sum(array_column($indexStats['price_ranges'], 'product_count'))],
                ['Ratings', count($indexStats['ratings']), array_sum(array_column($indexStats['ratings'], 'product_count'))],
                ['Discounts', count($indexStats['discounts']), array_sum(array_column($indexStats['discounts'], 'product_count'))],
            ]
        );

        $this->newLine();
        $this->info('=== Memory Usage (Redis) ===');
        $this->line("Total index keys: {$indexStats['total_keys']}");
        $this->line("Temp filter keys: {$filterStats['temp_keys']}");
        $this->line("Est. memory usage: {$indexStats['total_memory_mb']} MB");
        
        // Show Redis key structure
        $this->newLine();
        $this->info('=== Redis Key Structure ===');
        $this->line("Namespace: " . RedisKeyManager::namespace());
        $this->line("Category pattern: " . RedisKeyManager::pattern('index', 'cat'));
        $this->line("Brand pattern: " . RedisKeyManager::pattern('index', 'brand'));
        $this->line("Price pattern: " . RedisKeyManager::pattern('index', 'price'));

        $this->newLine();
        $this->info('=== Top 5 Categories by Products ===');
        $topCategories = collect($indexStats['categories'])
            ->sortByDesc('product_count')
            ->take(5)
            ->values();

        $this->table(
            ['Key', 'Products'],
            $topCategories->map(fn($cat) => [$cat['key'], number_format($cat['product_count'])])->toArray()
        );

        $this->newLine();
        $this->info('=== Top 5 Brands by Products ===');
        $topBrands = collect($indexStats['brands'])
            ->sortByDesc('product_count')
            ->take(5)
            ->values();

        $this->table(
            ['Key', 'Products'],
            $topBrands->map(fn($brand) => [$brand['key'], number_format($brand['product_count'])])->toArray()
        );
    }

    /**
     * Clean up temporary keys
     */
    private function cleanTempKeys(): void
    {
        $this->info('Cleaning up temporary filter keys...');

        $deleted = $this->filterService->cleanupTempKeys();

        $this->info("Deleted {$deleted} temporary keys.");
    }

    /**
     * Test filter performance
     */
    private function testPerformance(): void
    {
        $this->info('Running filter performance tests...');
        $this->newLine();

        // Test 1: Single category filter
        $this->info('Test 1: Single category filter');
        $start = microtime(true);
        $results = $this->filterService->getFilteredProductIds(['category_id' => 1]);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->line("  Results: " . count($results) . " products");
        $this->line("  Time: {$elapsed}ms");
        $this->newLine();

        // Test 2: Category + Brand filter
        $this->info('Test 2: Category + Brand filter');
        $start = microtime(true);
        $results = $this->filterService->getFilteredProductIds([
            'category_id' => 1,
            'brands' => [1, 2]
        ]);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->line("  Results: " . count($results) . " products");
        $this->line("  Time: {$elapsed}ms");
        $this->newLine();

        // Test 3: Multi-filter combination
        $this->info('Test 3: Complex multi-filter');
        $start = microtime(true);
        $results = $this->filterService->getFilteredProductIds([
            'category_id' => 1,
            'brands' => [1, 2, 3],
            'price_range' => '100-500',
            'min_rating' => 4
        ]);
        $elapsed = round((microtime(true) - $start) * 1000, 2);
        $this->line("  Results: " . count($results) . " products");
        $this->line("  Time: {$elapsed}ms");
        $this->newLine();

        // Test 4: Estimate vs actual
        $this->info('Test 4: Estimation accuracy');
        $filters = ['category_id' => 1, 'brands' => [1, 2]];
        $estimated = $this->filterService->estimateResultCount($filters);
        $actual = count($this->filterService->getFilteredProductIds($filters));
        $accuracy = $actual > 0 ? round(($estimated / $actual) * 100, 2) : 0;
        $this->line("  Estimated: {$estimated}");
        $this->line("  Actual: {$actual}");
        $this->line("  Accuracy: {$accuracy}%");

        $this->newLine();
        $this->info('Performance tests completed!');
    }
}
