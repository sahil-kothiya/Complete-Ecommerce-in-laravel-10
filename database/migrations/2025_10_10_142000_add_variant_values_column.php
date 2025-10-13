<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('product_variants', 'variant_values')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->json('variant_values')->nullable()->after('stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_variants', 'variant_values')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('variant_values');
            });
        }
    }
};
