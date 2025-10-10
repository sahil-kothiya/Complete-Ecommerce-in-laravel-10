<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->enum('condition', ['default', 'new', 'hot'])->default('default');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->boolean('is_featured')->default(false);
            
            // NEW: Add variant mode flag
            $table->boolean('has_variants')->default(false);
            
            // Base pricing (used when has_variants = false)
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('base_discount', 10, 2)->nullable();
            $table->integer('base_stock')->nullable();
            $table->string('base_sku')->nullable()->unique();
            
            $table->foreignId('cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('child_cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->timestamps();

            // Indexes
            $table->index(['status', 'is_featured'], 'idx_status_featured');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index(['status', 'has_variants'], 'idx_status_variants');
            $table->index('title', 'idx_title');
            $table->index('slug');
            $table->index(['cat_id', 'status'], 'idx_cat_status');
            $table->index(['brand_id', 'status'], 'idx_brand_status');
            $table->index('base_sku'); // For non-variant products
        });

        // PG-specific optimizations
        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm;");
        DB::statement("CREATE INDEX products_title_tsvector_idx ON products USING GIN (to_tsvector('english', title || ' ' || coalesce(summary, '')));");
        DB::statement("CREATE INDEX idx_homepage_perf ON products (status, is_featured, created_at DESC);");
        DB::statement("CREATE INDEX idx_featured_active ON products (created_at DESC) WHERE status = 'active' AND is_featured = true;");
        DB::statement("CREATE INDEX idx_category_active ON products (cat_id, created_at DESC) WHERE status = 'active';");
        DB::statement("CREATE INDEX idx_brand_active ON products (brand_id, created_at DESC) WHERE status = 'active';");
        
        // NEW: Partial indexes for variant/non-variant products
        DB::statement("CREATE INDEX idx_products_no_variants ON products (id, base_price) WHERE has_variants = false AND status = 'active';");
        DB::statement("CREATE INDEX idx_products_with_variants ON products (id) WHERE has_variants = true AND status = 'active';");

        // CHECK constraints
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_variant_logic CHECK (
            (has_variants = false AND base_price IS NOT NULL AND base_sku IS NOT NULL) OR
            (has_variants = true AND base_price IS NULL)
        );");
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_base_price_positive CHECK (base_price IS NULL OR base_price > 0);");
        DB::statement("ALTER TABLE products ADD CONSTRAINT chk_base_stock_non_negative CHECK (base_stock IS NULL OR base_stock >= 0);");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
}