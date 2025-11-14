<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS brand_category_brand_id_idx ON brand_category (brand_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS brand_category_category_id_idx ON brand_category (category_id)');
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS brand_category_brand_id_category_id_unique ON brand_category (brand_id, category_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS product_variant_option_assignments_variant_idx ON product_variant_option_assignments (product_variant_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS product_variant_option_assignments_option_idx ON product_variant_option_assignments (product_variant_option_id)');

        DB::statement('CREATE INDEX IF NOT EXISTS product_variant_type_selections_type_idx ON product_variant_type_selections (product_variant_type_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS brand_category_brand_id_idx');
        DB::statement('DROP INDEX IF EXISTS brand_category_category_id_idx');
        DB::statement('DROP INDEX IF EXISTS brand_category_brand_id_category_id_unique');

        DB::statement('DROP INDEX IF EXISTS product_variant_option_assignments_variant_idx');
        DB::statement('DROP INDEX IF EXISTS product_variant_option_assignments_option_idx');

        DB::statement('DROP INDEX IF EXISTS product_variant_type_selections_type_idx');
    }
};
