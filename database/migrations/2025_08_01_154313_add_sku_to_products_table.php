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
        Schema::table('products', function (Blueprint $table) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sku')->nullable()->unique()->after('id');
            });

            // 🔍 Add full-text GIN index on SKU (for search)
            DB::statement("
                CREATE INDEX IF NOT EXISTS products_sku_gin_idx 
                ON products USING GIN (to_tsvector('english', sku));
            ");

            // 🔍 Enable pg_trgm extension for partial matching (if not already enabled)
            DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm");

            // 🔍 Add trigram index for fast LIKE/ILIKE search
            DB::statement("
                CREATE INDEX IF NOT EXISTS products_sku_trgm_idx 
                ON products USING GIN (sku gin_trgm_ops);
            ");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('sku');
        });

        DB::statement("DROP INDEX IF EXISTS products_sku_gin_idx");
        DB::statement("DROP INDEX IF EXISTS products_sku_trgm_idx");
    }
};
