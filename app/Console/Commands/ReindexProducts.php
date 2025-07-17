<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Elasticsearch\Client;
use App\Services\ElasticsearchService;

class ReindexProducts extends Command
{
    protected $signature = 'elasticsearch:reindex-products';
    protected $description = 'Delete and recreate product index and reindex all products';

    private ElasticsearchService $elasticsearch;
    private Client $client;

    public function __construct(ElasticsearchService $elasticsearch, Client $client)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
        $this->client = $client;
    }

    public function handle(): int
    {
        $index = config('elasticsearch.index');

        $this->info("🔄 Attempting to delete index: $index");

        try {
            if ($this->client->indices()->exists(['index' => $index])) {
                $this->client->indices()->delete(['index' => $index]);
                $this->info("✅ Index '$index' deleted");
            } else {
                $this->warn("⚠️ Index '$index' does not exist");
            }
        } catch (\Exception $e) {
            $this->error("❌ Failed to delete index: " . $e->getMessage());
            return 1;
        }

        $this->info("📦 Creating new index: $index");

        if (!$this->elasticsearch->createIndex()) {
            $this->error("❌ Failed to create index '$index'");
            return 1;
        }

        $this->info("🚀 Starting product reindexing...");
        $exitCode = $this->call('elasticsearch:index-products');

        if ($exitCode === 0) {
            $this->info("✅ Reindexing completed successfully");
        } else {
            $this->error("❌ Reindexing failed during product indexing");
        }

        return $exitCode;
    }
}
