<?php

namespace App\Jobs;

use App\Services\ProductFilterService;
use App\Services\SmartFilterCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Background job to warm up cache for popular filter combinations
 * Should run during off-peak hours (e.g., 2-4 AM)
 */
class WarmFilterCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes max
    public $tries = 2;

    private array $filters;
    private int $startPage;
    private int $endPage;
    private int $perPage;

    /**
     * Create a new job instance.
     */
    public function __construct(array $filters, int $startPage = 1, int $endPage = 5, int $perPage = 12)
    {
        $this->filters = $filters;
        $this->startPage = $startPage;
        $this->endPage = $endPage;
        $this->perPage = $perPage;
    }

    /**
     * Execute the job.
     */
    public function handle(ProductFilterService $filterService, SmartFilterCacheService $cacheService): void
    {
        Log::info('Starting filter cache warm-up', [
            'filters' => $this->filters,
            'pages' => "{$this->startPage}-{$this->endPage}"
        ]);

        $startTime = microtime(true);
        $cachedPages = 0;

        try {
            for ($page = $this->startPage; $page <= $this->endPage; $page++) {
                // Check if already cached
                $cacheKey = $this->generateCacheKey($page);
                if ($cacheService->getFilteredProducts($this->filters, $page, $this->perPage)) {
                    Log::debug("Page {$page} already cached, skipping");
                    continue;
                }

                // Fetch from database/search engine
                $results = $filterService->getFilteredProducts($this->filters, $page, $this->perPage);

                if (empty($results['products'])) {
                    Log::info("No more products at page {$page}, stopping");
                    break;
                }

                // Store in cache with appropriate tier
                $cacheService->storeFilteredProducts($this->filters, $page, $this->perPage, $results);
                $cachedPages++;

                // Small delay to avoid overwhelming the system
                usleep(100000); // 100ms
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Filter cache warm-up completed', [
                'filters' => $this->filters,
                'cached_pages' => $cachedPages,
                'duration_ms' => $duration
            ]);

        } catch (\Exception $e) {
            Log::error('Filter cache warm-up failed', [
                'filters' => $this->filters,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function generateCacheKey(int $page): string
    {
        ksort($this->filters);
        $hash = md5(json_encode($this->filters));
        return "filter:products:{$hash}:p{$page}:pp{$this->perPage}";
    }
}
