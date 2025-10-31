<?php

namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UpdateProductCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("🚀 Starting fast product category update...");
        
        // Disable query log and foreign key checks for speed
        DB::disableQueryLog();
        // DB::statement('SET FOREIGN_KEY_CHECKS=0');
        
        // Fetch all categories and pre-calculate top parents
        $categories = DB::table('categories')->select('id', 'parent_id')->get()->keyBy('id');
        $topParents = [];
        
        foreach ($categories as $cat) {
            $topParents[$cat->id] = $this->getTopParent($cat->id, $categories);
        }
        
        $this->command->info("📊 Pre-calculated top parents for " . count($topParents) . " categories");

        // Use larger chunks and batch updates
        $batchSize = 5000;
        $updateBatch = [];
        $processed = 0;
        
        DB::table('products')
            ->select('id', 'cat_id', 'child_cat_id')
            ->where('id', '>', 6000000)
            ->orderBy('id')
            ->chunk($batchSize, function ($products) use ($topParents, &$updateBatch, &$processed) {
                foreach ($products as $product) {
                    $currentCatId = $product->child_cat_id ?? $product->cat_id;
                    
                    if (!$currentCatId || !isset($topParents[$currentCatId])) {
                        continue;
                    }

                    $mainCatId = $topParents[$currentCatId];
                    $childCatId = ($mainCatId == $currentCatId) ? null : $currentCatId;

                    // Only update if values actually changed
                    if ($product->cat_id != $mainCatId || $product->child_cat_id != $childCatId) {
                        $updateBatch[] = [
                            'id' => $product->id,
                            'cat_id' => $mainCatId,
                            'child_cat_id' => $childCatId
                        ];
                    }
                    
                    $processed++;

                    // Execute batch update when batch is full
                    if (count($updateBatch) >= 1000) {
                        $this->executeBatchUpdate($updateBatch);
                        $updateBatch = [];
                    }
                }
                
                $this->command->info("✅ Processed {$processed} products (batch ending at ID {$products->last()->id})");
            });

        // Execute remaining batch
        if (!empty($updateBatch)) {
            $this->executeBatchUpdate($updateBatch);
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        DB::enableQueryLog();
        
        $this->command->info("🎉 Finished! Processed {$processed} products.");
    }

    private function getTopParent($catId, $categories)
    {
        while (isset($categories[$catId]) && $categories[$catId]->parent_id) {
            $catId = $categories[$catId]->parent_id;
        }
        return $catId;
    }

    private function executeBatchUpdate($batch)
    {
        if (empty($batch)) return;

        // Build bulk update query using CASE statements
        $ids = array_column($batch, 'id');
        $catIdCases = [];
        $childCatIdCases = [];

        foreach ($batch as $item) {
            $catIdCases[] = "WHEN {$item['id']} THEN {$item['cat_id']}";
            $childCatId = $item['child_cat_id'] ? $item['child_cat_id'] : 'NULL';
            $childCatIdCases[] = "WHEN {$item['id']} THEN {$childCatId}";
        }

        $idsStr = implode(',', $ids);
        $catIdCaseStr = implode(' ', $catIdCases);
        $childCatIdCaseStr = implode(' ', $childCatIdCases);

        $sql = "UPDATE products SET 
                cat_id = CASE id {$catIdCaseStr} END,
                child_cat_id = CASE id {$childCatIdCaseStr} END
                WHERE id IN ({$idsStr})";

        DB::statement($sql);
    }
}