<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variant_option_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('product_variant_option_id')->constrained('product_variant_options')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_variant_id', 'product_variant_option_id']);
            $table->index(['product_variant_id', 'product_variant_option_id'], 'idx_variant_option');
        });

        // PostgreSQL does not allow subqueries in partial index predicates. Create a plain index instead.
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_class c WHERE c.relname = 'idx_assignment_active') THEN
                CREATE INDEX idx_assignment_active ON product_variant_option_assignments (product_variant_id);
            END IF;
        END $$;");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_option_assignments');
    }
};
