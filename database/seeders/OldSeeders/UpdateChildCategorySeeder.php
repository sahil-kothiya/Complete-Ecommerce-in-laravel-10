<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateChildCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $recordsToUpdate = 5000;
        $parentCatId = 1;
        $newChildCatId = 4;

        $this->command->info("🔄 Updating first {$recordsToUpdate} products with cat_id = {$parentCatId} and child_cat_id IS NULL to child_cat_id = {$newChildCatId}...");

        DB::statement("
            WITH cte AS (
                SELECT id
                FROM products
                WHERE cat_id = ? AND child_cat_id IS NULL
                ORDER BY id ASC
                LIMIT {$recordsToUpdate}
            )
            UPDATE products
            SET child_cat_id = ?
            FROM cte
            WHERE products.id = cte.id
        ", [$parentCatId, $newChildCatId]);

        $this->command->info("✅ Successfully updated {$recordsToUpdate} products with new child_cat_id = {$newChildCatId}");
    }
}
