<?php

namespace App\Console\Commands;

use App\Services\OptimizedFilterCacheService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Build Price Index Command
 *
 * Builds Redis SORTED SET price indexes for fast price range queries.
 * Designed for 10M+ products - runs as background job.
 */
class BuildPriceIndexCommand extends Command
{
    protected $signature = 'filters:build-price-index
                            {--category= : Specific category slug}
                            {--chunk=5000 : Chunk size for processing}
                            {--force : Skip confirmation}';

    protected $description = 'Build price range indexes for 10M+ products';

    private OptimizedFilterCacheService $filterCache;

    public function __construct(OptimizedFilterCacheService $filterCache)
    {
        parent::__construct();
        $this->filterCache = $filterCache;
    }

    public function handle()
    {
        $this->info('💰 Building Price Range Indexes');
        $this->newLine();

        // Get product count
        $totalProducts = DB::table('products')
            ->where('status', 'active')
            ->count();

        $this->info("Total active products: " . number_format($totalProducts));
        
        if ($totalProducts > 1000000) {
            $this->warn("⚠️  Large dataset detected ({$totalProducts} products)");
            $this->warn("This operation may take 10-30 minutes");
            $this->newLine();

            if (!$this->option('force')) {
                if (!$this->confirm('Continue with price index build?')) {
                    $this->info('Cancelled.');
                    return 0;
                }
            }
        }

        $category = $this->option('category');

        if ($category) {
            $this->buildCategoryIndex($category);
        } else {
            $this->buildAllIndexes();
        }

        $this->newLine();
        $this->info('✅ Price index build complete!');

        return 0;
    }

    /**
     * Build indexes for all categories
     */
    private function buildAllIndexes(): void
    {
        // Build global index
        $this->info('Building global price index...');
        $startTime = microtime(true);
        $this->filterCache->buildPriceRangeIndex('all');
        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("  ✓ Global index built in {$elapsed}s");

        // Get top categories
        $categories = DB::table('categories')
            ->whereNull('parent_id')
            ->where('status', 'active')
            ->limit(20)
            ->get(['slug', 'title']);

        if ($categories->isEmpty()) {
            $this->warn('No categories found');
            return;
        }

        $this->newLine();
        $this->info("Building indexes for {$categories->count()} categories...");

        $bar = $this->output->createProgressBar($categories->count());
        $bar->start();

        foreach ($categories as $category) {
            try {
                $this->filterCache->buildPriceRangeIndex($category->slug);
                $bar->advance();
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed for {$category->slug}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Build index for single category
     */
    private function buildCategoryIndex(string $slug): void
    {
        $this->info("Building price index for category: {$slug}");
        
        $startTime = microtime(true);
        $this->filterCache->buildPriceRangeIndex($slug);
        $elapsed = round(microtime(true) - $startTime, 2);
        
        $this->info("  ✓ Index built in {$elapsed}s");
    }
}
