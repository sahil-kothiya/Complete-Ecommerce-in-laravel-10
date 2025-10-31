<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BulkProductsTableSeederNew extends Seeder
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
        $faker = \Faker\Factory::create();
        $allImages = collect($this->images);
        $now = Carbon::now();

        $lastId = DB::table('products')->max('id') ?? 0;
        $startId = $lastId + 1;

        $totalProducts = 10_000_000; //10 Million
        // $totalProducts = 100000;
        $batchSize = 1500;
        $totalBatches = (int) ceil($totalProducts / $batchSize);

        // Disable triggers (foreign key, etc.) for performance
        DB::statement('ALTER TABLE products DISABLE TRIGGER ALL;');
        DB::statement('ALTER TABLE product_images DISABLE TRIGGER ALL;');

        for ($batch = 0; $batch < $totalBatches; $batch++) {
            DB::transaction(function () use (
                $batch,
                $batchSize,
                $totalProducts,
                &$startId,
                $faker,
                $now,
                $allImages
            ) {
                $products = [];
                $productImages = [];

                for ($i = 0; $i < $batchSize; $i++) {
                    $productIndex = $batch * $batchSize + $i;
                    if ($productIndex >= $totalProducts) break;

                    $title = "Sample Product $productIndex";
                    $slug = Str::slug($title) . '-' . $productIndex;
                    $createdAt = $now->copy()->subDays(random_int(0, 30));
                    $updatedAt = $now;

                    $products[] = [
                        'title'         => $title,
                        'slug'          => $slug,
                        'summary'       => $faker->sentence(6),
                        'description'   => 'Seeded product description',
                        'stock'         => random_int(10, 100),
                        'size'          => 'M',
                        'condition'     => 'new',
                        'status'        => 'active',
                        'price'         => random_int(500, 5000),
                        'discount'      => random_int(0, 50),
                        'is_featured'   => random_int(0, 1),
                        'cat_id'        => random_int(1, 7),
                        'child_cat_id'  => null,
                        'brand_id'      => random_int(1, 5),
                        'created_at'    => $createdAt,
                        'updated_at'    => $updatedAt,
                    ];
                }

                DB::table('products')->insert($products);

                $insertedIds = range($startId, $startId + count($products) - 1);
                foreach ($insertedIds as $productId) {
                    $selectedImages = $allImages->random(3)->values();
                    foreach ($selectedImages as $index => $imageName) {
                        $productImages[] = [
                            'product_id' => $productId,
                            'image_path' => 'storage/photos/1/Products/' . $imageName,
                            'is_primary' => $index === 0,
                            'sort_order' => $index,
                        ];
                    }
                }

                DB::table('product_images')->insert($productImages);

                $startId += count($products);
                unset($products, $productImages);
                gc_collect_cycles(); // Clean up memory
            });

            $this->command->info("✅ Batch " . ($batch + 1) . "/$totalBatches complete");
        }

        // Re-enable triggers
        DB::statement('ALTER TABLE products ENABLE TRIGGER ALL;');
        DB::statement('ALTER TABLE product_images ENABLE TRIGGER ALL;');

        $this->command->info("🎉 Done seeding $totalProducts products with images.");
    }
}
