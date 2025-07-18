<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateMenCategoryToKidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Number of men's products to update
        $recordsToUpdate = 10;

        // New target category ID (kids)
        $newCatId = 3;

        $this->command->info("🔄 Updating first {$recordsToUpdate} men's products (cat_id = 1) to kid's category (cat_id = {$newCatId})...");

        // PostgreSQL-optimized update using CTE
        DB::statement("
            WITH cte AS (
                SELECT id
                FROM products
                WHERE cat_id = 1
                ORDER BY id ASC
                LIMIT {$recordsToUpdate}
            )
            UPDATE products
            SET cat_id = ?
            FROM cte
            WHERE products.id = cte.id
        ", [$newCatId]);

        $this->command->info("✅ Successfully updated {$recordsToUpdate} men's products to cat_id = {$newCatId} (kids)");
    }
}
