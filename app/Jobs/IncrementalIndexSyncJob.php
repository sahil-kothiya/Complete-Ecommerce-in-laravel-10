<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ProductIndexService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Incremental Index Sync Job
 * 
 * Updates indexes for recently modified products only
 * Much faster than full rebuild (runs every 5-10 minutes)
 */
class IncrementalIndexSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600; // 10 minutes
    public $tries = 3;
    
    private int $lookbackMinutes;
    
    /**
     * Create a new job instance
     * 
     * @param int $lookbackMinutes How far back to check for changes (default: 15 minutes)
     */
    public function __construct(int $lookbackMinutes = 15)
    {
        $this->lookbackMinutes = $lookbackMinutes;
    }

    /**
     * Execute the job
     */
    public function handle(ProductIndexService $indexService): void
    {
        $startTime = microtime(true);
        $since = now()->subMinutes($this->lookbackMinutes);
        
        Log::info('🔄 Starting incremental index sync', [
            'lookback_minutes' => $this->lookbackMinutes,
            'since' => $since->toDateTimeString()
        ]);
        
        try {
            // Get recently modified products
            $updatedProducts = Product::where('updated_at', '>=', $since)
                ->orWhere('created_at', '>=', $since)
                ->get();
            
            $count = $updatedProducts->count();
            
            if ($count === 0) {
                Log::info('✅ No products to sync');
                return;
            }
            
            Log::info("Syncing {$count} recently modified products");
            
            // Update indexes for each product
            foreach ($updatedProducts as $product) {
                try {
                    $indexService->updateProductIndexes($product);
                } catch (\Exception $e) {
                    Log::error("Failed to update indexes for product {$product->id}", [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            $duration = round(microtime(true) - $startTime, 2);
            
            Log::info('✅ Incremental sync completed', [
                'products_synced' => $count,
                'duration_seconds' => $duration
            ]);
            
        } catch (\Exception $e) {
            Log::error('❌ Incremental sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Incremental index sync failed permanently', [
            'error' => $exception->getMessage(),
            'lookback_minutes' => $this->lookbackMinutes
        ]);
    }
}
