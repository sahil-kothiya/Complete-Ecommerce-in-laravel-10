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
            $table->foreignId('cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('child_cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->timestamps();

            // Indexes (optimized for lean table at 10M+ scale)
            $table->index(['status', 'is_featured'], 'idx_status_featured');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('title', 'idx_title');
            $table->index('slug'); // Explicit for fast slug lookups
            $table->index(['cat_id', 'status'], 'idx_cat_status'); // Category filtering
            $table->index(['brand_id', 'status'], 'idx_brand_status'); // Brand filtering
        });

        // PG-specific indexes for high-scale queries
        DB::statement("CREATE INDEX IF NOT EXISTS products_title_tsvector_idx ON products USING GIN (to_tsvector('english', title || ' ' || coalesce(summary, '')));"); // Include summary for better search
        DB::statement("CREATE INDEX IF NOT EXISTS idx_homepage_perf ON products (status, is_featured, created_at DESC);");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_featured_active ON products (created_at DESC) WHERE status = 'active' AND is_featured = true;");
        DB::statement("CREATE INDEX IF NOT EXISTS idx_category_active ON products (cat_id, created_at DESC) WHERE status = 'active';"); // Category pagination
        DB::statement("CREATE INDEX IF NOT EXISTS idx_brand_active ON products (brand_id, created_at DESC) WHERE status = 'active';"); // Brand pagination
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