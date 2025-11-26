<?php

namespace App\Jobs;

use App\Services\ProductIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Background job to rebuild product indexes
 * Runs asynchronously to avoid blocking live traffic
 */
class RebuildProductIndexesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job timeout (2 hours for large datasets)
     */
    public $timeout = 7200;

    /**
     * Number of times to retry job
     */
    public $tries = 1;

    /**
     * Execute the job
     */
    public function handle(ProductIndexService $indexService): void
    {
        $startTime = microtime(true);
        
        Log::info('🚀 Starting background index rebuild job');
        
        try {
            // Build all indexes with progress logging
            $stats = $indexService->buildAllIndexes(function($step, $message, $progress) {
                Log::info("Index rebuild progress: {$message}");
            });
            
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info('✅ Index rebuild completed successfully', [
                'duration_seconds' => $duration,
                'stats' => $stats
            ]);
            
            // Store rebuild completion time
            Cache::put('index_last_rebuild_time', now()->toDateTimeString(), 86400 * 7); // 7 days
            
            // Clear the rebuild lock
            Cache::forget('index_rebuild_in_progress');
            
            // Clear health check cache to reflect new status
            Cache::forget('index_health_status');
            
        } catch (\Exception $e) {
            Log::error('❌ Index rebuild job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Clear the rebuild lock on failure
            Cache::forget('index_rebuild_in_progress');
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Index rebuild job failed permanently', [
            'error' => $exception->getMessage()
        ]);
        
        // Clear the rebuild lock
        Cache::forget('index_rebuild_in_progress');
    }
}
