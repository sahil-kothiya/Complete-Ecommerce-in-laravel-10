<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Jobs\WarmFilterCacheJob;

/**
 * Analyze user behavior and warm cache for popular filter combinations
 *
 * Usage:
 * php artisan cache:warm-filters --analyze   (identify popular combos)
 * php artisan cache:warm-filters --warm      (warm cache for popular combos)
 * php artisan cache:warm-filters --auto      (analyze + warm)
 */
class WarmFilterCacheCommand extends Command
{
    protected $signature = 'cache:warm-filters
                            {--analyze : Analyze and identify popular filter combinations}
                            {--warm : Warm cache for identified popular combinations}
                            {--auto : Analyze and warm cache automatically}
                            {--limit=100 : Number of top combinations to warm}';

    protected $description = 'Analyze and warm cache for popular filter combinations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔥 Filter Cache Warmer Started');
        $this->info('Time: ' . now()->toDateTimeString());
        $this->newLine();

        if ($this->option('analyze') || $this->option('auto')) {
            $this->analyzePopularCombos();
        }

        if ($this->option('warm') || $this->option('auto')) {
            $this->warmPopularCombos();
        }

        if (!$this->option('analyze') && !$this->option('warm') && !$this->option('auto')) {
            $this->error('Please specify --analyze, --warm, or --auto option');
            return 1;
        }

        $this->newLine();
        $this->info('✅ Filter Cache Warmer Completed');
        return 0;
    }

    /**
     * Analyze popular filter combinations from Redis analytics
     */
    private function analyzePopularCombos(): void
    {
        $this->info('📊 Analyzing popular filter combinations...');

        // Get hot combos from Redis sorted set with proper namespace
        $analyticsKey = 'ecommerce:v1:analytics:hot_combos';
        $hotCombos = Redis::zrevrange($analyticsKey, 0, -1, 'WITHSCORES');

        if (empty($hotCombos)) {
            $this->warn('No filter analytics data found. Cache warming will use default popular combos.');
            $this->createDefaultPopularCombos();
            return;
        }

        $this->table(
            ['Rank', 'Cache Key', 'Access Count'],
            collect($hotCombos)->chunk(2)->map(function($chunk, $index) {
                return [
                    $index + 1,
                    substr($chunk[0], 0, 60) . '...',
                    $chunk[1]
                ];
            })->take(20)->toArray()
        );

        $this->info("Total tracked combinations: " . (count($hotCombos) / 2));
        $this->newLine();
    }

    /**
     * Warm cache for popular combinations
     */
    private function warmPopularCombos(): void
    {
        $limit = (int) $this->option('limit');
        $this->info("🔥 Warming cache for top {$limit} filter combinations...");

        // Get popular combos
        $popularCombos = $this->getPopularCombinations($limit);

        if (empty($popularCombos)) {
            $this->warn('No popular combinations found. Using defaults...');
            $popularCombos = $this->getDefaultCombinations();
        }

        $bar = $this->output->createProgressBar(count($popularCombos));
        $bar->start();

        foreach ($popularCombos as $combo) {
            // Dispatch job to warm cache for this combo (pages 1-5)
            WarmFilterCacheJob::dispatch($combo['filters'], 1, 5, 12);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Dispatched " . count($popularCombos) . " cache warming jobs");
    }

    /**
     * Get popular filter combinations from analytics
     */
    private function getPopularCombinations(int $limit): array
    {
        $hotCombos = Redis::zrevrange('hot_filter_combos', 0, $limit - 1, 'WITHSCORES');
        $combinations = [];

        for ($i = 0; $i < count($hotCombos); $i += 2) {
            $cacheKey = $hotCombos[$i];
            $score = $hotCombos[$i + 1] ?? 0;

            // Extract filters from cache key
            $filters = $this->extractFiltersFromCacheKey($cacheKey);

            if ($filters) {
                $combinations[] = [
                    'filters' => $filters,
                    'score' => $score
                ];
            }
        }

        return $combinations;
    }

    /**
     * Extract filter parameters from cache key
     * Format: filter:products:{hash}:p{page}:pp{perPage}
     */
    private function extractFiltersFromCacheKey(string $cacheKey): ?array
    {
        // This is a simplified version - in production, you'd need to store
        // the actual filter params alongside the hash or decode them
        // For now, return null and use defaults
        return null;
    }

    /**
     * Create default popular combinations based on business logic
     */
    private function createDefaultPopularCombos(): void
    {
        $defaults = $this->getDefaultCombinations();
        $analyticsKey = 'ecommerce:v1:analytics:hot_combos';

        foreach ($defaults as $combo) {
            $hash = md5(json_encode($combo['filters']));
            $cacheKey = "ecommerce:v1:filters:products:{$hash}:page:1:size:12";

            // Add to hot combos with initial score
            Redis::zadd($analyticsKey, 100, $cacheKey);
        }

        $this->info('✅ Created ' . count($defaults) . ' default popular combinations');
    }

    /**
     * Default popular filter combinations based on common user behavior
     */
    private function getDefaultCombinations(): array
    {
        return [
            // Popular categories only
            ['filters' => ['category_id' => [1]]],
            ['filters' => ['category_id' => [2]]],
            ['filters' => ['category_id' => [3]]],

            // Popular brands
            ['filters' => ['brand' => ['Nike']]],
            ['filters' => ['brand' => ['Adidas']]],
            ['filters' => ['brand' => ['Apple']]],

            // Price ranges
            ['filters' => ['price_range' => '0-50']],
            ['filters' => ['price_range' => '50-100']],
            ['filters' => ['price_range' => '100-200']],

            // High ratings
            ['filters' => ['min_rating' => [4]]],
            ['filters' => ['min_rating' => [5]]],

            // Discounts
            ['filters' => ['min_discount' => [20]]],
            ['filters' => ['min_discount' => [50]]],

            // Category + Brand combinations
            ['filters' => ['category_id' => [1], 'brand' => ['Nike']]],
            ['filters' => ['category_id' => [2], 'brand' => ['Apple']]],

            // Category + Price
            ['filters' => ['category_id' => [1], 'price_range' => '0-100']],

            // Brand + Rating
            ['filters' => ['brand' => ['Nike'], 'min_rating' => [4]]],

            // Discount + Rating
            ['filters' => ['min_discount' => [20], 'min_rating' => [4]]],

            // All products sorted
            ['filters' => ['sortBy' => 'latest']],
            ['filters' => ['sortBy' => 'price_low_high']],
        ];
    }

    /**
     * Show cache statistics
     */
    private function showStats(): void
    {
        $metricsPrefix = 'ecommerce:v1:metrics';
        $analyticsKey = 'ecommerce:v1:analytics:hot_combos';

        $this->info('📈 Cache Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tier 1 Hits', Cache::get("{$metricsPrefix}:cache_hit:tier1", 0)],
                ['Tier 2 Hits', Cache::get("{$metricsPrefix}:cache_hit:tier2", 0)],
                ['Cache Misses', Cache::get("{$metricsPrefix}:cache_miss", 0)],
                ['Hot Combos', Redis::zcard($analyticsKey)],
            ]
        );
    }
}
