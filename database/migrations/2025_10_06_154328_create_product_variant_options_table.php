<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_type_id')->constrained('product_variant_types')->onDelete('cascade'); // Global type (color/size)
            $table->string('value'); // e.g., 'red', 'M'
            $table->string('display_value'); // e.g., 'Red', 'Medium'
            $table->string('hex_color')->nullable(); // For colors
            $table->integer('sort_order')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['variant_type_id', 'value']); // No dupes per type
            $table->index(['variant_type_id', 'status'], 'idx_type_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_options');
    }
};
