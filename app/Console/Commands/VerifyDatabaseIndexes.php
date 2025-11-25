<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyDatabaseIndexes extends Command
{
    protected $signature = 'homepage:verify-indexes';
    protected $description = 'Verify all critical database indexes for 10M+ products optimization';

    public function handle()
    {
        $this->info('🔍 Verifying database indexes for 10M+ products...');
        $this->newLine();

        $criticalIndexes = [
            'products' => [
                'idx_products_status_featured' => 'Homepage featured products query',
                'idx_products_category_status' => 'Category products with featured filter',
                'idx_products_brand_status' => 'Brand products filtering',
                'idx_status_featured' => 'Status and featured flag',
                'idx_cat_status' => 'Category and status',
                'idx_homepage_perf' => 'Homepage performance composite',
            ],
            'categories' => [
                'idx_categories_parent_status' => 'Parent category lookups',
                'idx_categories_featured' => 'Featured categories',
            ],
            'product_variants' => [
                'idx_variants_product_status' => 'Active variants lookup',
                'idx_product_variants_product_status' => 'Variant product status',
            ],
            'product_images' => [
                'idx_images_product_primary' => 'Product images with primary',
                'idx_product_primary' => 'Product primary images',
            ],
            'variant_images' => [
                'idx_variant_images_variant_sort' => 'Variant images sorting',
                'idx_variant_primary' => 'Variant primary images',
            ],
            'banners' => [
                'idx_banners_status_id' => 'Active banners ordered',
            ],
        ];

        $totalIndexes = 0;
        $existingIndexes = 0;
        $missingIndexes = [];

        foreach ($criticalIndexes as $table => $indexes) {
            $this->info("📊 Checking table: {$table}");

            foreach ($indexes as $indexName => $purpose) {
                $totalIndexes++;

                if ($this->indexExists($table, $indexName)) {
                    $this->line("  ✅ {$indexName} - {$purpose}");
                    $existingIndexes++;
                } else {
                    $this->error("  ❌ {$indexName} - MISSING!");
                    $missingIndexes[] = [
                        'table' => $table,
                        'index' => $indexName,
                        'purpose' => $purpose
                    ];
                }
            }

            $this->newLine();
        }

        // Summary
        $this->info('📈 Summary:');
        $this->info("   Total Critical Indexes: {$totalIndexes}");
        $this->info("   Existing: {$existingIndexes}");
        $this->info("   Missing: " . count($missingIndexes));
        $this->newLine();

        if (empty($missingIndexes)) {
            $this->info('✅ All critical indexes are in place!');
            $this->info('🚀 Database is optimized for 10M+ products.');
            $this->newLine();

            // Show performance estimate
            $this->info('📊 Expected Performance:');
            $this->info('   • Homepage (cached): 5-15ms');
            $this->info('   • Homepage (no cache): 200-300ms');
            $this->info('   • Category browse: 30-50ms');
            $this->info('   • Product search: 100-200ms');
            $this->info('   • Variant lookup: 15-30ms');

            return Command::SUCCESS;
        } else {
            $this->error('⚠️  Some critical indexes are missing!');
            $this->newLine();
            $this->info('Missing indexes:');

            foreach ($missingIndexes as $missing) {
                $this->line("  • {$missing['table']}.{$missing['index']} - {$missing['purpose']}");
            }

            $this->newLine();
            $this->info('💡 Fix by running:');
            $this->line('   php artisan homepage:optimize-db');
            $this->line('   OR');
            $this->line('   php artisan migrate');

            return Command::FAILURE;
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = config('database.default');

        try {
            if ($driver === 'pgsql') {
                $result = DB::select(
                    "SELECT COUNT(*) as count
                     FROM pg_indexes
                     WHERE tablename = ?
                     AND indexname = ?",
                    [$table, $indexName]
                );
            } else {
                $database = config("database.connections.{$driver}.database");
                $result = DB::select(
                    "SELECT COUNT(*) as count
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = ?
                     AND TABLE_NAME = ?
                     AND INDEX_NAME = ?",
                    [$database, $table, $indexName]
                );
            }

            return $result[0]->count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
