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

            // Core
            $table->string('title');
            $table->string('slug')->unique();

            // Optional
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();

            // Inventory & status
            $table->integer('stock')->default(1);
            $table->string('size')->nullable()->default('M');
            $table->enum('condition', ['default', 'new', 'hot'])->default('default');
            $table->enum('status', ['active', 'inactive'])->default('inactive');

            // Pricing
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();
            $table->boolean('is_featured')->default(false);

            // Foreign Keys
            $table->foreignId('cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('child_cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');

            // Timestamps
            $table->timestamps();

            // Laravel indexes (Postgres compatible)
            $table->index(['status', 'is_featured'], 'idx_status_featured');
            $table->index(['status', 'created_at'], 'idx_status_created');
            $table->index('title', 'idx_title');
        });

        // ✅ Now apply PostgreSQL-specific indexes *after* table is created
        DB::statement("
            CREATE INDEX IF NOT EXISTS products_title_tsvector_idx 
            ON products USING GIN (to_tsvector('english', title));
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_homepage_perf 
            ON products (status, is_featured, created_at DESC);
        ");

        DB::statement("
            CREATE INDEX IF NOT EXISTS idx_featured_active 
            ON products (created_at DESC) 
            WHERE status = 'active' AND is_featured = true;
        ");
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

    /*
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('description')->nullable();
            $table->integer('stock')->default(1);
            $table->string('size')->nullable()->default('M');
            $table->enum('condition', ['default', 'new', 'hot'])->default('default');
            $table->enum('status', ['active', 'inactive'])->default('inactive');
            $table->decimal('price', 10, 2);
            $table->decimal('discount', 10, 2)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->foreignId('cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('child_cat_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->foreignId('brand_id')->nullable()->constrained('brands')->onDelete('set null');
            $table->timestamps();

            // Optional: PostgreSQL index tuning
            $table->index(['status', 'is_featured']);
        });
    }
    **/
}
