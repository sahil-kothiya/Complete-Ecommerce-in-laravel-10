<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * These indexes are CRITICAL for homepage performance with 10M+ products.
     * They enable O(log n) lookups instead of O(n) table scans.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            // Drop existing indexes if they exist (to avoid duplicates)
            $this->dropIndexIfExists('products', 'idx_products_status_featured');
            $this->dropIndexIfExists('products', 'idx_products_category_status');
            $this->dropIndexIfExists('products', 'idx_products_brand_status');

            // Composite index for homepage featured products query
            // Covers: WHERE status='active' AND is_featured=1 ORDER BY id DESC
            DB::statement('CREATE INDEX idx_products_status_featured ON products(status, is_featured, id)');

            // Composite index for category-based queries
            // Covers: WHERE cat_id=X AND status='active' AND is_featured=1 ORDER BY id DESC
            DB::statement('CREATE INDEX idx_products_category_status ON products(cat_id, status, is_featured, id)');

            // Composite index for brand-based queries
            // Covers: WHERE brand_id=X AND status='active' ORDER BY id DESC
            DB::statement('CREATE INDEX idx_products_brand_status ON products(brand_id, status, id)');
        });

        // Index for product variants (if not already exists)
        if (Schema::hasTable('product_variants')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $this->dropIndexIfExists('product_variants', 'idx_variants_product_status');

                // Index for variant queries
                DB::statement('CREATE INDEX idx_variants_product_status ON product_variants(product_id, status, stock)');
            });
        }

        // Index for product images (if not already exists)
        if (Schema::hasTable('product_images')) {
            Schema::table('product_images', function (Blueprint $table) {
                $this->dropIndexIfExists('product_images', 'idx_images_product_primary');

                // Index for image queries
                DB::statement('CREATE INDEX idx_images_product_primary ON product_images(product_id, is_primary, sort_order)');
            });
        }

        // Index for categories
        Schema::table('categories', function (Blueprint $table) {
            $this->dropIndexIfExists('categories', 'idx_categories_parent_status');
            $this->dropIndexIfExists('categories', 'idx_categories_featured');

            // Index for parent category queries
            DB::statement('CREATE INDEX idx_categories_parent_status ON categories(parent_id, status, sort_order)');

            // Index for featured categories
            DB::statement('CREATE INDEX idx_categories_featured ON categories(is_featured, status, sort_order)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop product indexes
        $this->dropIndexIfExists('products', 'idx_products_status_featured');
        $this->dropIndexIfExists('products', 'idx_products_category_status');
        $this->dropIndexIfExists('products', 'idx_products_brand_status');

        // Drop variant indexes
        if (Schema::hasTable('product_variants')) {
            $this->dropIndexIfExists('product_variants', 'idx_variants_product_status');
        }

        // Drop image indexes
        if (Schema::hasTable('product_images')) {
            $this->dropIndexIfExists('product_images', 'idx_images_product_primary');
        }

        // Drop category indexes
        $this->dropIndexIfExists('categories', 'idx_categories_parent_status');
        $this->dropIndexIfExists('categories', 'idx_categories_featured');
    }

    /**
     * Helper method to drop index if it exists (PostgreSQL compatible)
     */
    private function dropIndexIfExists(string $table, string $indexName): void
    {
        // PostgreSQL-specific query
        if (DB::getDriverName() === 'pgsql') {
            $exists = DB::select(
                "SELECT COUNT(*) as count
                 FROM pg_indexes
                 WHERE schemaname = 'public'
                 AND tablename = ?
                 AND indexname = ?",
                [$table, $indexName]
            );

            if ($exists[0]->count > 0) {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            }
        } else {
            // MySQL-specific query
            $exists = DB::select(
                "SELECT COUNT(*) as count
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                 AND table_name = ?
                 AND index_name = ?",
                [$table, $indexName]
            );

            if ($exists[0]->count > 0) {
                DB::statement("DROP INDEX {$indexName} ON {$table}");
            }
        }
    }
};
