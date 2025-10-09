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
        Schema::create('product_variant_type_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_type_id')->constrained('product_variant_types')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_type_id']); // One type per product (unique constraint includes an index)
        });

        // PostgreSQL does not allow subqueries in partial index predicates (can't reference other tables).
        // Create a simple index on product_id instead. If you need an index scoped to active products,
        // consider adding a denormalized `product_is_active` boolean column on this table or maintaining
        // a materialized view. For now create a straightforward index which is compatible with Postgres.
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_class c WHERE c.relname = 'idx_product_type_active') THEN
                CREATE INDEX idx_product_type_active ON product_variant_type_selections (product_id);
            END IF;
        END $$;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_type_selections');
    }
};
