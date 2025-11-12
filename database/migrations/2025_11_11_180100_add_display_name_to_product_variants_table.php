<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variants', 'display_name')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->string('display_name')->nullable();
                $table->index('display_name', 'idx_product_variants_display_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'display_name')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropIndex('idx_product_variants_display_name');
                $table->dropColumn('display_name');
            });
        }
    }
};
