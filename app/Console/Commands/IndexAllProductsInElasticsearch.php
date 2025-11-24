<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bulk index products in Elasticsearch for fast searching
 *
 * Usage:
 * php artisan elasticsearch:index-all --chunk=5000
 */
class IndexAllProductsInElasticsearch extends Command
{
    protected $signature = 'elasticsearch:index-all
                            {--chunk=5000 : Number of products to process at once}
                            {--force : Force re-indexing even if index exists}';

    protected $description = 'Bulk index all active products in Elasticsearch';

    private ElasticsearchService $elasticsearch;

    public function __construct()
    {
        parent::__construct();
        $this->elasticsearch = app(ElasticsearchService::class);
    }

    public function handle()
    {
        if (!$this->elasticsearch->isAvailable()) {
            $this->error('Elasticsearch is not available. Please check the connection.');
            return 1;
        }

        $this->info('Starting bulk product indexing in Elasticsearch...');
        $this->newLine();

        // Create index if it doesn't exist
        $this->info('Checking/creating Elasticsearch index...');
        $this->elasticsearch->createIndex();
        $this->newLine();

        // Get total count
        $totalProducts = Product::where('status', 'active')->count();

        if ($totalProducts === 0) {
            $this->warn('No active products found to index.');
            return 0;
        }

        $this->info("Found {$totalProducts} active products to index.");

        if (!$this->option('force')) {
            if (!$this->confirm('Do you want to proceed with indexing?')) {
                $this->info('Indexing cancelled.');
                return 0;
            }
        }

        $chunkSize = (int) $this->option('chunk');
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $indexed = 0;
        $failed = 0;
        $startTime = microtime(true);

        // Process in chunks for memory efficiency using chunkById for better performance with large datasets
        Product::where('status', 'active')
            ->select([
                'id', 'title', 'slug', 'summary', 'description',
                'cat_id', 'child_cat_id', 'brand_id',
                'base_price', 'base_discount', 'has_variants',
                'is_featured', 'status', 'created_at', 'updated_at', 'condition'
            ])
            ->with([
                'cat_info:id,title',
                'sub_cat_info:id,title',
                'brand:id,title'
            ])
            ->withSum('variants', 'stock')
            ->chunkById($chunkSize, function ($products) use (&$indexed, &$failed, $bar) {
                $batch = [];

                foreach ($products as $product) {
                    // Calculate total stock from variants_sum_stock or use 0 if no variants
                    $totalStock = $product->has_variants
                        ? ($product->variants_sum_stock ?? 0)
                        : 0;

                    $batch[] = [
                        'id' => $product->id,
                        'title' => $product->title,
                        'slug' => $product->slug,
                        'summary' => $product->summary ?? '',
                        'description' => strip_tags($product->description ?? ''),
                        'cat_id' => $product->cat_id,
                        'child_cat_id' => $product->child_cat_id,
                        'brand_id' => $product->brand_id,
                        'price' => (float) $product->base_price,
                        'discount' => (float) $product->base_discount,
                        'stock' => (int) $totalStock,
                        'is_featured' => (bool) $product->is_featured,
                        'status' => $product->status,
                        'condition' => $product->condition,
                        'created_at' => $product->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $product->updated_at?->format('Y-m-d H:i:s'),
                    ];

                    $bar->advance();
                }

                // Bulk index this batch
                if (!empty($batch)) {
                    $success = $this->elasticsearch->bulkIndexProducts($batch);

                    if ($success) {
                        $indexed += count($batch);
                    } else {
                        $failed += count($batch);
                    }
                }

                // Clear memory
                unset($batch);
                unset($products);
            });

        $bar->finish();
        $this->newLine(2);

        $elapsedTime = round(microtime(true) - $startTime, 2);

        $this->info('Indexing completed!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total products', number_format($totalProducts)],
                ['Successfully indexed', number_format($indexed)],
                ['Failed', number_format($failed)],
                ['Time elapsed', $elapsedTime . 's'],
                ['Products/second', round($totalProducts / $elapsedTime, 2)],
            ]
        );

        // Show index stats
        $this->newLine();
        $this->info('Elasticsearch index statistics:');
        $stats = $this->elasticsearch->getIndexStats();

        if (!isset($stats['error'])) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total documents', number_format($stats['total_documents'] ?? 0)],
                    ['Index size', $stats['index_size'] ?? 'N/A'],
                ]
            );
        }

        $this->newLine();
        $this->info('✅ Products are now searchable in Elasticsearch!');
        $this->info('Test search: php artisan tinker');
        $this->line('  app(\App\Services\ElasticsearchService::class)->searchProducts("test", 10)');

        return 0;
    }
}
