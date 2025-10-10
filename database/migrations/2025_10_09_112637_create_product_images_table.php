<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('image_path'); // S3/CDN path
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_primary'], 'idx_product_primary');
            $table->index(['product_id', 'sort_order'], 'idx_product_sort');
        });

        // Partial index for primary images
        DB::statement("CREATE INDEX idx_primary_product_images ON product_images (product_id) WHERE is_primary = true;");
        
        // GIN for search
        DB::statement("CREATE INDEX IF NOT EXISTS product_images_path_gin_idx ON product_images USING GIN (to_tsvector('english', image_path));");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
