<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use App\Services\ElasticsearchService;

class IndexProducts extends Command
{
    protected $signature = 'elasticsearch:index-products {--chunk=1000}';
    protected $description = 'Index products in Elasticsearch';

    private ElasticsearchService $elasticsearch;

    public function __construct(ElasticsearchService $elasticsearch)
    {
        parent::__construct();
        $this->elasticsearch = $elasticsearch;
    }

    public function handle()
    {
        $this->info('Starting product indexing...');
        
        // Create index if not exists
        if (!$this->elasticsearch->createIndex()) {
            $this->error('Failed to create Elasticsearch index');
            return 1;
        }
        
        $chunkSize = $this->option('chunk');
        $totalProducts = Product::where('status', 'active')->count();
        $this->info("Total products to index: {$totalProducts}");
        
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();
        
        $indexed = 0;
        Product::where('status', 'active')
            ->chunk($chunkSize, function ($products) use ($bar, &$indexed) {
                $productsArray = $products->map(function ($product) {
                    return $product->toSearchableArray();
                })->toArray();
                
                if ($this->elasticsearch->bulkIndexProducts($productsArray)) {
                    $indexed += count($productsArray);
                    $bar->advance(count($productsArray));
                }
            });
        
        $bar->finish();
        $this->newLine();
        $this->info("Successfully indexed {$indexed} products");
        
        return 0;
    }
}