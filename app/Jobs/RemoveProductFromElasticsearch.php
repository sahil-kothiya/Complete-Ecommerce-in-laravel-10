<?php

namespace App\Jobs;

use App\Services\ElasticsearchService;
use Elasticsearch\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveProductFromElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 30;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    private int|string $productId;

    /**
     * Create a new job instance.
     */
    public function __construct(int|string $productId)
    {
        $this->productId = $productId;
        $this->onQueue('search');
    }

    /**
     * Execute the job.
     */
    public function handle(Client $client): void
    {
        try {
            Log::info("Removing product {$this->productId} from Elasticsearch");

            $response = $client->delete([
                'index' => config('elasticsearch.index'),
                'id' => $this->productId
            ]);

            if (isset($response['result']) && $response['result'] === 'deleted') {
                Log::info("Successfully removed product {$this->productId} from Elasticsearch");
            } elseif (isset($response['result']) && $response['result'] === 'not_found') {
                Log::info("Product {$this->productId} not found in Elasticsearch (already removed)");
            } else {
                Log::warning("Unexpected response when removing product {$this->productId} from Elasticsearch", $response);
            }
        } catch (\Elasticsearch\Common\Exceptions\Missing404Exception $e) {
            // Product not found in index, which is fine
            Log::info("Product {$this->productId} not found in Elasticsearch index (404)");
        } catch (\Exception $e) {
            Log::error("Error removing product {$this->productId} from Elasticsearch: " . $e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("RemoveProductFromElasticsearch job failed for product {$this->productId}: " . $exception->getMessage());

        // You might want to queue for manual cleanup or retry with different strategy
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return ['elasticsearch', 'product-remove', "product:{$this->productId}"];
    }
}
