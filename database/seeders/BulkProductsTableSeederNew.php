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
        "0122b-wsd000t.jpg",
        "01bc5-mpd006b.jpg",
        "01f42-pwt004b.jpg",
        "02090-pms003a.jpg",
        "032f0-image3xxl-1-.jpg",
        "0455e-c1.jpg",
        "04776-pms000a.jpg",
        "04ec4-pmtk001t.jpg",
        "07e30-mtk006b.jpg",
        "086c2-a1.jpg",
        "08ec9-n3.jpg",
        "093a2-4_2.jpg",
        "09a16-mpd000t_6.jpg",
        "0a402-image4xxl-3-.jpg",
        "0bc05-pwt000a.jpg",
        "0c2d2-wbk012c-royal-blue.jpg",
        "0c88d-mtk009a.jpg",
        "0dfc6-mtk000b.jpg",
        "10551-pmtk006a.jpg",
        "11f4f-image1xxl.jpg",
        "127dd-image2xxl-1-.jpg",
        "18b18-wbk003b.jpg",
        "1a69c-image3xxl-4-.jpg",
        "1d3f9-mtk009b.jpg",
        "1d60f-2.jpg",
        "1ddb2-9.jpg",
        "1f9e4-v3.jpg",
        "21951-image4xxl.jpg",
        "2282b-wsd008t.jpg",
        "25079-11000876_4923876_480.jpg",
        "27081-pwt004a.jpg",
        "277fa-image2.jpg",
        "294b5-image2xxl-2-.jpg",
        "2b287-image2xxl-1-.jpg",
        "2bf67-6.jpg",
        "2bf67-61.jpg",
        "2bf67-63.jpg",
        "2defc-pwd002t.jpg",
        "2f92d-green.jpg",
        "2f92d-image1xxl-1-.jpg",
        "2f92d_green.jpg",
        "31ccc-pwd000b.jpg",
        "35a3f-mpd012b.jpg",
        "35f37-wsd008a.jpg",
        "36cb0-5_2.jpg",
        "372d5-image2xxl-5-.jpg",
        "3a815-pmo002a.jpg",
        "3c1c8-image3xxl.jpg",
        "3c47b-pwt001a_1.jpg",
        "3e659-pmo002t.jpg",
        "3f214-pwt004t.jpg",
        "405b7-pmtk004t.jpg",
        "40749-image3xxl.jpg",
        "41715-pmtk001b.jpg",
        "429a3-image1xxl-3-.jpg",
        "4319c-image4xxl-1-.jpg",
        "43f35-2_2.jpg",
        "481c6-m0.jpg",
        "48fc8-image2xxl-3-.jpg",
        "4a8a8-image4xxl-2-.jpg",
        "51c1b-pmo000a.jpg",
        "52d0c-mtk000t.jpg",
        "53103-11000876_4923878_480.jpg",
        "53b38-wsd013a.jpg",
        "543ea-mtk004a.jpg",
        "554fe-11087120_5289711_480.jpg",
        "56245-pmp002a.jpg",
        "57e1d-pmtk001a.jpg",
        "594d8-pmtk006t.jpg",
        "5a8ba-7.jpg",
        "5ca87-m1.jpg",
        "5ded8-image1xxl-5-.jpg",
        "5f9ad-pwt001b.jpg",
        "604e7-mtk009t.jpg",
        "6201a-8.jpg",
        "62db1-image2xxl-5-.jpg",
        "64625-wsd000b.jpg",
        "646e6-8_2.jpg",
        "68d00-4.jpg",
        "6a8b5-wsd013t.jpg",
        "6cb7c-pmo002b.jpg",
        "6df9e-11000876_4923882_480.jpg",
        "6e71c-image1xxl-2-.jpg",
        "7039c-image1xxl-6-.jpg",
        "70794-image4xxl.jpg",
        "727db-5.jpg",
        "729d0-b1.jpg",
        "73e33-image2xxl-3-.jpg",
        "742e0-image1xxl-7-.jpg",
        "74840-image4xxl-6-.jpg",
        "74d1c-pmtk004b.jpg",
        "759f6-image2xxl-3-.jpg",
        "76d94-image1xxl-3-.jpg",
        "78689-image4xxl-1-.jpg",
        "78a4b-pwd001t.jpg",
        "7e425-pwd002b.jpg",
        "80540-mpd003b.jpg",
        "832bd-11087120_5289707_480.jpg",
        "840c9-pwd000t.jpg",
        "87131-mpd000b.jpg",
        "88519-9_2.jpg",
        "892a5-pmp002t.jpg",
        "8d383-mtk006a.jpg",
        "9002f-pwt003t.jpg",
        "929fb-a2.jpg",
        "93d69-pmtk005t.jpg",
        "942b2-pwd000a.jpg",
        "94efc-mpd012a.jpg",
        "967a0-image2xxl.jpg",
        "96edf-image3xxl.jpg",
        "97b6b-6_2.jpg",
        "97d6f-mtk004t.jpg",
        "9b7f8-v2.jpg",
        "9cbb3-mtk006t.jpg",
        "9d808-wsd013b.jpg",
        "9e254-mpd003a.jpg",
        "9e31b-wsd000a.jpg",
        "9fee1-7_2.jpg",
        "a1b1d-image1xxl-2-.jpg",
        "a1fe9-pwd002a.jpg",
        "a35ee-1_2.jpg",
        "a8337-image2xxl-8-.jpg",
        "a88c7-pwd001a.jpg",
        "aa423-mpd012t.jpg",
        "ab822-image1xxl1.jpg",
        "ae5f0-image2xxl.jpg",
        "b2890-pmtk000a.jpg",
        "b50c5-image2xxl-2-.jpg",
        "b9cf1-pwt000t.jpg",
        "b9e23-image3.jpg",
        "ba959-image4xxl-1-.jpg",
        "bc43c-image1xxl.jpg",
        "bd7a2-pms000t.jpg",
        "bdb32-pmo000c.jpg",
        "be3aa-image11.jpg",
        "be567-wbk003t.jpg",
        "be78d-pms004a.jpg",
        "c10d9-pmo000b.jpg",
        "c2ae6-n1.jpg",
        "c3d77-c2.jpg",
        "c4a7d-mpd003t.jpg",
        "c5a0d-n2.jpg",
        "c5e8e-a3.jpg",
        "c6269-wbk012d-pink.jpg",
        "c6b37-pmtk001c.jpg",
        "calvin-klein.jpg",
        "calvin.jpg",
        "d3fdb-image2xxl-4-.jpg",
        "edcd0-image1xxl-5-.jpg"
    ];

    public function run(): void
    {
        $faker = \Faker\Factory::create();
        $allImages = collect($this->images);
        $now = Carbon::now();

        $lastId = DB::table('products')->max('id') ?? 0;
        $startId = $lastId + 1;

        // $totalProducts = 10_000_000; //10 Million
        $totalProducts = 100000;
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
