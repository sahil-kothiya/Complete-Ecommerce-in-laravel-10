<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ElasticsearchService;
use Elasticsearch\Client;

class ClearElasticsearchData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'elasticsearch:clear {--force : Force deletion without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear all data from Elasticsearch index temporarily';

    protected ElasticsearchService $elasticsearchService;
    protected Client $client;

    /**
     * Create a new command instance.
     */
    public function __construct(ElasticsearchService $elasticsearchService, Client $client)
    {
        parent::__construct();
        $this->elasticsearchService = $elasticsearchService;
        $this->client = $client;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $indexName = config('elasticsearch.index');

        // Check if Elasticsearch is available
        if (!$this->elasticsearchService->isAvailable()) {
            $this->error('Elasticsearch is not available or not running.');
            $this->info('Please start Elasticsearch and try again.');
            return 1;
        }

        // Get current index stats
        $stats = $this->elasticsearchService->getIndexStats();
        $documentCount = $stats['document_count'] ?? 0;

        $this->info("Current Elasticsearch Index: {$indexName}");
        $this->info("Documents in index: {$documentCount}");

        // Check cluster health and disk space
        $this->checkClusterHealth();

        // Confirm deletion unless --force flag is used
        if (!$this->option('force')) {
            if (!$this->confirm('Are you sure you want to clear all Elasticsearch data?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Clearing Elasticsearch data...');

        try {
            // Check if index exists
            $exists = $this->client->indices()->exists(['index' => $indexName]);

            if (!$exists) {
                $this->warn("Index '{$indexName}' does not exist.");
                return 0;
            }

            // Remove read-only block if present (due to disk space issues)
            $this->removeReadOnlyBlock($indexName);

            // Delete the entire index (faster than delete by query)
            $this->info("Deleting index '{$indexName}'...");
            $this->client->indices()->delete(['index' => $indexName]);
            $this->info("Index '{$indexName}' deleted successfully.");

            // Recreate the index with proper mappings
            $this->info('Recreating index with fresh structure...');
            if ($this->elasticsearchService->createIndex()) {
                $this->info('Index recreated successfully.');
            } else {
                $this->warn('Failed to recreate index. You may need to recreate it manually.');
            }

            // Get updated stats
            $newStats = $this->elasticsearchService->getIndexStats();
            $newCount = $newStats['document_count'] ?? 0;

            $this->info("Elasticsearch data cleared successfully.");
            $this->info("Documents before: {$documentCount}");
            $this->info("Documents after: {$newCount}");

            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to clear Elasticsearch data: ' . $e->getMessage());
            
            // Try alternative method
            $this->warn('Attempting alternative method: delete by query...');
            return $this->deleteByQuery($indexName);
        }
    }

    /**
     * Remove read-only block from index.
     */
    protected function removeReadOnlyBlock(string $indexName): void
    {
        try {
            $this->info('Checking for read-only blocks...');
            
            // Remove read-only block
            $this->client->indices()->putSettings([
                'index' => $indexName,
                'body' => [
                    'index' => [
                        'blocks' => [
                            'read_only_allow_delete' => null
                        ]
                    ]
                ]
            ]);
            
            $this->info('Read-only blocks removed (if any existed).');
        } catch (\Exception $e) {
            $this->warn('Could not remove read-only block: ' . $e->getMessage());
        }
    }

    /**
     * Check cluster health and disk space.
     */
    protected function checkClusterHealth(): void
    {
        try {
            $health = $this->client->cluster()->health();
            $this->info("Cluster Status: " . ($health['status'] ?? 'unknown'));

            // Check for disk space issues
            $stats = $this->client->nodes()->stats([
                'metric' => 'fs'
            ]);

            foreach ($stats['nodes'] ?? [] as $node) {
                $total = $node['fs']['total']['total_in_bytes'] ?? 0;
                $available = $node['fs']['total']['available_in_bytes'] ?? 0;
                
                if ($total > 0) {
                    $percentUsed = round((($total - $available) / $total) * 100, 2);
                    $availableMB = round($available / (1024 * 1024), 2);
                    
                    $this->info("Disk Usage: {$percentUsed}% (Available: {$availableMB} MB)");
                    
                    if ($percentUsed > 90) {
                        $this->warn('⚠️  WARNING: Disk space is critically low!');
                        $this->warn('This may cause Elasticsearch to mark indices as read-only.');
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn('Could not check cluster health: ' . $e->getMessage());
        }
    }

    /**
     * Alternative method: Delete by query.
     */
    protected function deleteByQuery(string $indexName): int
    {
        try {
            $this->removeReadOnlyBlock($indexName);

            $response = $this->client->deleteByQuery([
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass()
                    ]
                ]
            ]);

            $deleted = $response['deleted'] ?? 0;
            $this->info("Successfully deleted {$deleted} documents.");

            // Refresh the index
            $this->client->indices()->refresh(['index' => $indexName]);
            $this->info('Index refreshed.');

            return 0;
        } catch (\Exception $e) {
            $this->error('Alternative method also failed: ' . $e->getMessage());
            return 1;
        }
    }
}
