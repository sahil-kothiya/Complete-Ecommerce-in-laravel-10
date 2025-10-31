<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Carbon\Carbon;

class BulkProductsTableSeeder extends Seeder
{
    protected $images = [
        "01bc5-mpd006b.webp",
        "01f42-pwt004b.webp",
        "0a402-image4xxl-3-.webp",
        "0bc05-pwt000a.webp",
        "0c2d2-wbk012c-royal-blue.webp",
        "0c88d-mtk009a.webp",
        "0dfc6-mtk000b.webp",
        "1a69c-image3xxl-4-.webp",
        "1d3f9-mtk009b.webp",
        "1d60f-2.webp",
        "1ddb2-9.webp",
        "1f9e4-v3.webp",
        "2b287-image2xxl-1-.webp",
        "2bf67-6.webp",
        "2bf67-63.webp",
        "2defc-pwd002t.webp",
        "2f92d_green.webp",
        "bc43c-image1xxl.webp",
        "bd7a2-pms000t.webp",
        "bdb32-pmo000c.webp",
        "be3aa-image11.webp",
        "be567-wbk003t.webp",
        "be78d-pms004a.webp",
        "c10d9-pmo000b.webp",
        "c2ae6-n1.webp",
        "c3d77-c2.webp",
        "c4a7d-mpd003t.webp",
        "c5a0d-n2.webp",
        "c5e8e-a3.webp",
        "c6269-wbk012d-pink.webp",
        "c6b37-pmtk001c.webp",
        "calvin-klein.webp",
        "calvin.webp",
        "d3fdb-image2xxl-4-.webp",
        "edcd0-image1xxl-5-.webp"
    ];

    public function run(): void
    {
        ini_set('memory_limit', '4G');
        ini_set('max_execution_time', 0);

        $faker = Faker::create();
        $now = Carbon::now();

        $totalProducts = 10_000_000;
        $safeProductBatchSize = 1500; // Reduced to avoid hitting 65535 param limit
        $totalBatches = (int) ceil($totalProducts / $safeProductBatchSize);

        $this->command->info("🚀 Seeding $totalProducts products in $totalBatches batches of $safeProductBatchSize...");

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $products = [];
            $productImages = [];

            for ($i = 0; $i < $safeProductBatchSize; $i++) {
                $productIndex = $batch * $safeProductBatchSize + $i;
                if ($productIndex >= $totalProducts) break;

                $title = "Sample Product $productIndex";
                $slug = Str::slug($title) . '-' . $productIndex;

                $products[] = [
                    'title'         => $title,
                    'slug'          => $slug,
                    'summary'       => $faker->sentence,
                    'description'   => $faker->paragraph,
                    'stock'         => rand(10, 100),
                    'size'          => 'M',
                    'condition'     => 'new',
                    'status'        => 'active',
                    'price'         => rand(500, 5000),
                    'discount'      => rand(0, 50),
                    'is_featured'   => rand(0, 1),
                    'cat_id'        => rand(1, 7),
                    'child_cat_id'  => null,
                    'brand_id'      => rand(1, 5),
                    'created_at'    => $now->copy()->subDays(rand(0, 30)),
                    'updated_at'    => $now,
                ];
            }

            DB::table('products')->insert($products);

            // Workaround: Re-fetch the last batch of inserted product IDs via created_at
            $recentProducts = DB::table('products')
                ->orderBy('id', 'desc')
                ->limit($safeProductBatchSize)
                ->get(['id']);

            foreach ($recentProducts as $product) {
                $selectedImages = collect($this->images)->random(3)->values();
                foreach ($selectedImages as $index => $imageName) {
                    $productImages[] = [
                        'product_id' => $product->id,
                        'image_path' => 'storage/photos/1/Products/' . $imageName,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ];
                }
            }

            DB::table('product_images')->insert($productImages);

            $this->command->info("✅ Batch " . ($batch + 1) . "/$totalBatches complete with {$safeProductBatchSize} products");
        }

        $this->command->info("🎉 Done seeding $totalProducts products with images.");
    }
}
