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
        Schema::create('variant_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->string('image_path'); // S3/CDN path
            $table->boolean('is_primary')->default(false);
            $table->smallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_variant_id', 'is_primary'], 'idx_variant_primary');
            $table->index(['product_variant_id', 'sort_order'], 'idx_variant_sort');
        });

        // Partial for primary images
        DB::statement("CREATE INDEX idx_primary_variant_images ON variant_images (product_variant_id) WHERE is_primary = true;");
        // GIN for full-text on paths if needed (e.g., search by filename)
        DB::statement("CREATE INDEX IF NOT EXISTS variant_images_path_gin_idx ON variant_images USING GIN (to_tsvector('english', image_path));");
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_images');
    }
};
