<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ElasticsearchService;
use Elasticsearch\Client;

class ReindexProducts extends Command
{
    protected $signature = 'elasticsearch:reindex-products';
    protected $description = 'Delete and recreate product index';

    private ElasticsearchService $elasticsearch;
    private Client $client;

    public function __construct(ElasticsearchService $elasticsearch, Client $client)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
        $this->client = $client;
    }

    public function handle()
    {
        $index = config('elasticsearch.index');

        $this->info('Deleting existing index...');

        try {
            if ($this->client->indices()->exists(['index' => $index])) {
                $this->client->indices()->delete(['index' => $index]);
                $this->info('Index deleted successfully');
            }
        } catch (\Exception $e) {
            $this->warn('Index deletion failed: ' . $e->getMessage());
        }

        $this->info('Creating new index...');
        if (!$this->elasticsearch->createIndex()) {
            $this->error('Failed to create new index');
            return 1;
        }

        $this->info('Reindexing products...');
        $this->call('elasticsearch:index-products');

        return 0;
    }
}
