<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();

            // Foreign key to products
            $table->foreignId('product_id')
                ->constrained('products')
                ->onDelete('cascade'); // Delete images when product is deleted

            $table->string('image_path');    // Use text if paths are long (Laravel will auto-cast)
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('sort_order')->default(0);

            $table->timestamps();

            // Indexes
            $table->index(['product_id', 'is_primary'], 'idx_product_primary');
            $table->index(['product_id', 'sort_order'], 'idx_product_sort');
        });

        // Optional: Partial index for primary images only
        DB::statement("CREATE INDEX idx_primary_images ON product_images (product_id) WHERE is_primary = true;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
