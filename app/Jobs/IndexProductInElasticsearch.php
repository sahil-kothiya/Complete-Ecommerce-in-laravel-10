<?php

namespace App\Jobs;

use App\Services\ElasticsearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IndexProductInElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    private array $productData;

    /**
     * Create a new job instance.
     */
    public function __construct(array $productData)
    {
        $this->productData = $productData;
        $this->onQueue('search');
    }

    /**
     * Execute the job.
     */
    public function handle(ElasticsearchService $elasticsearch): void
    {
        try {
            Log::info("Indexing product {$this->productData['id']} in Elasticsearch");

            $success = $elasticsearch->indexProduct($this->productData);

            if ($success) {
                Log::info("Successfully indexed product {$this->productData['id']} in Elasticsearch");
            } else {
                Log::warning("Failed to index product {$this->productData['id']} in Elasticsearch");
                $this->fail('Failed to index product in Elasticsearch');
            }
        } catch (\Exception $e) {
            Log::error("Error indexing product {$this->productData['id']} in Elasticsearch: " . $e->getMessage());
            $this->fail($e);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("IndexProductInElasticsearch job failed for product {$this->productData['id']}: " . $exception->getMessage());

        // You could dispatch a notification or alternative action here
        // For example, queue for manual review or use a fallback search method
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return ['elasticsearch', 'product-index', "product:{$this->productData['id']}"];
    }
}
