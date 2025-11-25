<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeHomepageDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'homepage:optimize-db';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create database indexes to optimize homepage loading performance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('🚀 Optimizing database indexes for homepage performance (10M+ products)...');
        $this->newLine();

        // Check if migration-based indexes already exist
        $migrationIndexesExist = $this->checkMigrationIndexes();

        if ($migrationIndexesExist) {
            $this->info('✅ Performance indexes already created via migrations!');
            $this->info('   Migration: 2025_11_15_000000_add_performance_indexes_for_caching.php');
            $this->newLine();
            $this->info('📊 Existing indexes optimized for 10M+ products:');
            $this->info('   • idx_products_status_featured (products)');
            $this->info('   • idx_products_category_status (products)');
            $this->info('   • idx_products_brand_status (products)');
            $this->info('   • idx_variants_product_status (product_variants)');
            $this->info('   • idx_images_product_primary (product_images)');
            $this->info('   • idx_categories_parent_status (categories)');
            $this->info('   • idx_categories_featured (categories)');
            $this->newLine();
            $this->info('💡 Run "php artisan homepage:warmup-cache" to pre-populate Redis cache.');
            return Command::SUCCESS;
        }

        // Only create indexes if migration hasn't run
        $this->warn('⚠️  Migration indexes not found. Creating fallback indexes...');
        $this->newLine();

        $indexes = [
            // Products table - Critical for homepage queries with 10M+ products
            [
                'table' => 'products',
                'name' => 'idx_products_status_featured',
                'columns' => ['status', 'is_featured', 'id'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_products_status_featured ON products(status, is_featured, id)',
                'purpose' => 'Homepage featured products (covers WHERE status AND is_featured)'
            ],
            [
                'table' => 'products',
                'name' => 'idx_products_category_status',
                'columns' => ['cat_id', 'status', 'is_featured', 'id'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_products_category_status ON products(cat_id, status, is_featured, id)',
                'purpose' => 'Category products with featured filter'
            ],
            [
                'table' => 'products',
                'name' => 'idx_products_brand_status',
                'columns' => ['brand_id', 'status', 'id'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_products_brand_status ON products(brand_id, status, id)',
                'purpose' => 'Brand products filtering'
            ],

            // Categories table - For navigation and filtering
            [
                'table' => 'categories',
                'name' => 'idx_categories_parent_status',
                'columns' => ['parent_id', 'status', 'sort_order'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_categories_parent_status ON categories(parent_id, status, sort_order)',
                'purpose' => 'Parent category lookups with sorting'
            ],
            [
                'table' => 'categories',
                'name' => 'idx_categories_featured',
                'columns' => ['is_featured', 'status', 'sort_order'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_categories_featured ON categories(is_featured, status, sort_order)',
                'purpose' => 'Featured categories for homepage'
            ],

            // Banners table - For homepage carousel
            [
                'table' => 'banners',
                'name' => 'idx_banners_status_id',
                'columns' => ['status', 'id'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_banners_status_id ON banners(status, id DESC)',
                'purpose' => 'Active banners ordered by newest'
            ],

            // Product images - For faster image loading
            [
                'table' => 'product_images',
                'name' => 'idx_images_product_primary',
                'columns' => ['product_id', 'is_primary', 'sort_order'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_images_product_primary ON product_images(product_id, is_primary, sort_order)',
                'purpose' => 'Product images with primary flag'
            ],

            // Variant images - For variant product images
            [
                'table' => 'variant_images',
                'name' => 'idx_variant_images_variant_sort',
                'columns' => ['product_variant_id', 'sort_order'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_variant_images_variant_sort ON variant_images(product_variant_id, sort_order)',
                'purpose' => 'Variant images ordering'
            ],

            // Product variants - For variant products
            [
                'table' => 'product_variants',
                'name' => 'idx_variants_product_status',
                'columns' => ['product_id', 'status', 'stock'],
                'sql' => 'CREATE INDEX IF NOT EXISTS idx_variants_product_status ON product_variants(product_id, status, stock)',
                'purpose' => 'Active variants with stock info'
            ],
        ];

        $created = 0;
        $skipped = 0;

        foreach ($indexes as $index) {
            if ($this->indexExists($index['table'], $index['name'])) {
                $this->warn("⏭️  Index {$index['name']} already exists");
                $skipped++;
                continue;
            }

            try {
                DB::statement($index['sql']);
                $this->info("✅ {$index['name']} - {$index['purpose']}");
                $created++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to create {$index['name']}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("✨ Optimization complete!");
        $this->info("   Created: {$created} indexes");
        $this->info("   Skipped: {$skipped} indexes (already exist)");
        $this->newLine();
        $this->info("💡 Run 'php artisan homepage:warmup-cache' to pre-populate Redis cache for instant page loads.");

        return Command::SUCCESS;
    }

    /**
     * Check if migration-based indexes exist
     */
    private function checkMigrationIndexes(): bool
    {
        $criticalIndexes = [
            'idx_products_status_featured',
            'idx_products_category_status',
            'idx_categories_parent_status'
        ];

        $existingCount = 0;
        foreach ($criticalIndexes as $indexName) {
            $driver = config('database.default');

            if ($driver === 'pgsql') {
                $result = DB::select(
                    "SELECT COUNT(*) as count
                     FROM pg_indexes
                     WHERE indexname = ?",
                    [$indexName]
                );
            } else {
                $database = config("database.connections.{$driver}.database");
                $result = DB::select(
                    "SELECT COUNT(*) as count
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = ?
                     AND INDEX_NAME = ?",
                    [$database, $indexName]
                );
            }

            if ($result[0]->count > 0) {
                $existingCount++;
            }
        }

        return $existingCount >= 2; // At least 2 of 3 critical indexes exist
    }

    /**
     * Check if an index exists on a table
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = config('database.default');

        if ($driver === 'pgsql') {
            // PostgreSQL
            $result = DB::select(
                "SELECT COUNT(*) as count
                 FROM pg_indexes
                 WHERE tablename = ?
                 AND indexname = ?",
                [$table, $indexName]
            );
        } else {
            // MySQL/MariaDB
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
    }
}
