<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            // Already has index, but add trigram for LIKE searches
        });

        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm;");
        DB::statement("CREATE INDEX IF NOT EXISTS product_variants_sku_trgm_idx ON product_variants USING GIN (sku gin_trgm_ops);");
        DB::statement("CREATE INDEX IF NOT EXISTS product_variants_sku_gin_idx ON product_variants USING GIN (to_tsvector('english', sku));");
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            //
        });

        DB::statement("DROP INDEX IF EXISTS product_variants_sku_trgm_idx;");
        DB::statement("DROP INDEX IF EXISTS product_variants_sku_gin_idx;");
    }
};
