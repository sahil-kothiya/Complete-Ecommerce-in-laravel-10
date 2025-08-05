<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add code fields to categories
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code', 3)->nullable()->unique()->after('slug');
            $table->boolean('code_locked')->default(false)->after('code');
            $table->timestamp('code_generated_at')->nullable()->after('code_locked');

            $table->index('code', 'categories_code_index');
        });

        // Add code fields to brands
        Schema::table('brands', function (Blueprint $table) {
            $table->string('code', 3)->nullable()->unique()->after('slug');
            $table->boolean('code_locked')->default(false)->after('code');
            $table->timestamp('code_generated_at')->nullable()->after('code_locked');

            $table->index('code', 'brands_code_index');
        });

        // Enforce uppercase format using CHECK constraint (PostgreSQL)
        DB::statement("ALTER TABLE categories ADD CONSTRAINT chk_category_code_uppercase CHECK (code IS NULL OR code = UPPER(code))");
        DB::statement("ALTER TABLE brands ADD CONSTRAINT chk_brand_code_uppercase CHECK (code IS NULL OR code = UPPER(code))");
    }

    public function down(): void
    {
        // Drop CHECK constraints first
        DB::statement("ALTER TABLE categories DROP CONSTRAINT IF EXISTS chk_category_code_uppercase");
        DB::statement("ALTER TABLE brands DROP CONSTRAINT IF EXISTS chk_brand_code_uppercase");

        // Drop columns in reverse order
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('categories_code_index');
            $table->dropColumn(['code_generated_at', 'code_locked', 'code']);
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropIndex('brands_code_index');
            $table->dropColumn(['code_generated_at', 'code_locked', 'code']);
        });
    }
};
