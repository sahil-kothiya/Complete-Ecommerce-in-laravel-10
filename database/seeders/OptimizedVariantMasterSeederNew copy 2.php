<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class OptimizedVariantMasterSeederNew extends Seeder
{
    protected int $startingProductId;
    protected bool $enableTruncate = false;
    protected int $targetProducts = 1000;
    protected int $variantProducts = 900;
    protected int $variantsPerProduct = 4;
    protected int $batchSize = 500;
    protected int $imageBatchSize = 500;

    protected int $totalProductsSeeded = 0;
    protected int $totalVariantsSeeded = 0;
    protected int $currentProductId = 0;
    protected float $startTime;

    protected array $cachedImages;
    protected array $cachedCategories = [];
    protected array $cachedBrands = [];
    protected array $cachedVariantOptions = [];

    protected array $productTemplates = [
        'smartphones' => [
            'names' => ['Premium Smartphone', 'Flagship Phone', 'Pro Smartphone', 'Ultra Phone', 'Elite Smartphone'],
            'summaries' => ['Latest technology for seamless connectivity.', 'High-performance device for work and play.'],
            'description' => 'Designed for the tech-savvy user with cutting-edge features and superior performance.',
            'price_range' => [20000, 100000]
        ],
        'laptops' => [
            'names' => ['Gaming Laptop', 'Business Laptop', 'Ultrabook', 'Workstation', 'Pro Laptop'],
            'summaries' => ['Powerful computing for professionals.', 'Ideal for gaming and productivity.'],
            'description' => 'Crafted for professionals and gamers with high performance and reliability.',
            'price_range' => [30000, 150000]
        ],
        'audio' => [
            'names' => ['Wireless Headphones', 'Bluetooth Speaker', 'Gaming Headset', 'Studio Monitors', 'Earbuds'],
            'summaries' => ['Immersive sound for music lovers.', 'Crystal-clear audio for all your needs.'],
            'description' => 'Experience superior sound quality with advanced audio technology.',
            'price_range' => [1000, 20000]
        ],
        'shoes' => [
            'names' => ['Running Shoes', 'Casual Sneakers', 'Formal Shoes', 'Sports Shoes', 'Canvas Shoes'],
            'summaries' => ['Classic design with modern comfort.', 'Durable footwear for all occasions.'],
            'description' => 'Crafted for the modern man who values quality, style, and comfort.',
            'price_range' => [2000, 15000]
        ],
        'women' => [
            'names' => ['Designer Dress', 'Casual Top', 'Formal Blouse', 'Summer Dress', 'Party Wear'],
            'summaries' => ['Elegant and comfortable for everyday wear.', 'Perfect for both casual and formal occasions.'],
            'description' => 'Designed specifically for women who appreciate style, comfort, and elegance.',
            'price_range' => [1500, 8000]
        ],
        'kids' => [
            'names' => ['Kids T-Shirt', 'Shorts', 'Jacket', 'Dress', 'Casual Wear'],
            'summaries' => ['Fun and durable clothing for kids.', 'Comfortable fit for active children.'],
            'description' => 'Made for kids with comfort, durability, and fun designs in mind.',
            'price_range' => [500, 3000]
        ],
        'furniture' => [
            'names' => ['Modern Sofa', 'Dining Table', 'Office Chair', 'Bookshelf', 'Coffee Table'],
            'summaries' => ['Stylish addition to any home.', 'Durable and comfortable furniture.'],
            'description' => 'Transform your living space with elegant designs and premium materials.',
            'price_range' => [5000, 50000]
        ],
        'kitchen_appliances' => [
            'names' => ['Mixer Grinder', 'Toaster', 'Blender', 'Coffee Maker', 'Food Processor'],
            'summaries' => ['Modern appliances for easy cooking.', 'Enhance your kitchen experience.'],
            'description' => 'Simplify your cooking with innovative appliances and modern technology.',
            'price_range' => [2000, 25000]
        ],
        'gym_equipment' => [
            'names' => ['Treadmill', 'Dumbbell Set', 'Yoga Mat', 'Exercise Bike', 'Weight Bench'],
            'summaries' => ['Build your strength with quality gear.', 'Perfect for home workouts.'],
            'description' => 'Built for fitness enthusiasts to achieve their health and fitness goals.',
            'price_range' => [1000, 100000]
        ],
        'outdoor_sports' => [
            'names' => ['Camping Tent', 'Hiking Backpack', 'Sports Shoes', 'Water Bottle', 'Sleeping Bag'],
            'summaries' => ['Gear for your next adventure.', 'Durable equipment for outdoor activities.'],
            'description' => 'Gear up for outdoor adventures with reliable and durable equipment.',
            'price_range' => [500, 30000]
        ],
        'skin_care' => [
            'names' => ['Face Moisturizer', 'Cleanser', 'Sunscreen', 'Face Mask', 'Serum'],
            'summaries' => ['Gentle care for all skin types.', 'Premium skincare for daily use.'],
            'description' => 'Nourish your skin with high-quality ingredients and advanced formulations.',
            'price_range' => [300, 5000]
        ],
        'makeup' => [
            'names' => ['Lipstick', 'Foundation', 'Eyeliner', 'Mascara', 'Blush'],
            'summaries' => ['Enhance your beauty with quality cosmetics.', 'Professional makeup for any occasion.'],
            'description' => 'Achieve a flawless look with professional-grade cosmetics and beauty products.',
            'price_range' => [200, 3000]
        ]
    ];

    protected array $variantTypes = [
        'color' => [
            'display_name' => 'Color',
            'options' => ['Red', 'Blue', 'Green', 'Black', 'White'],
            'hex_colors' => ['#FF0000', '#0000FF', '#00FF00', '#000000', '#FFFFFF']
        ],
        'size' => [
            'display_name' => 'Size',
            'options' => ['S', 'M', 'L', 'XL', 'XXL'],
            'hex_colors' => []
        ],
        'storage' => [
            'display_name' => 'Storage',
            'options' => ['64GB', '128GB', '256GB', '512GB'],
            'hex_colors' => []
        ],
        'ram' => [
            'display_name' => 'RAM',
            'options' => ['4GB', '8GB', '16GB', '32GB', '64GB'],
            'hex_colors' => []
        ],
        'screen_size' => [
            'display_name' => 'Screen Size',
            'options' => ['13-inch', '14-inch', '15-inch', '16-inch', '17-inch'],
            'hex_colors' => []
        ],
    ];

    protected array $categoryVariantTypes = [
        'smartphones' => ['color', 'ram', 'storage'],
        'laptops' => ['ram', 'screen_size', 'storage'],
        'audio' => ['color'],
        'shoes' => ['color', 'size'],
        'women' => ['color', 'size'],
        'kids' => ['color', 'size'],
        'furniture' => ['color'],
        'kitchen_appliances' => [],
        'gym_equipment' => ['size'],
        'outdoor_sports' => ['size'],
        'skin_care' => ['size'],
        'makeup' => ['color'],
    ];

    protected $images = [
        "00497a4a-3fc5-47c9-93ba-842393d35f46.webp",
        "00846c49-7137-4af6-8317-f1a7d578d8c2.webp",
        "0156bece-7587-4bb4-bf9c-00d0168d6656.webp",
        "021a2a91-7595-4999-9fd7-22465f160c43.webp",
        "0237a1ac-b56e-4c15-9789-8ddc9bd7f064.webp",
        "04cd210b-b297-48df-8813-bb4b1ff5c6c8.webp",
        "05331b5f-664e-42ee-9e50-877bf1377ecc.webp",
        "059926d6-36fc-4a13-8f0c-b3b45e1233de.webp",
        "0707c8a3-4490-47cd-9cda-fcfde0f06cef.webp",
        "07a0fd0b-a61f-4b2f-bc7a-94de73e74837.webp",
        "08e639f0-fbd4-4fdd-858d-97028cacb2e6.webp",
        "095e4164-fc6a-496a-82be-1a4329dde539.webp",
        "1409e8df-fafa-4aee-b2f6-7219ff79f065.webp",
        "1417dd87-781e-4a1e-95db-4e8c1211c79d.webp",
        "14cb9bfb-418c-4d22-857b-65d9da9cb0df.webp",
        "1508966f-1038-476f-919d-4b5dd1666d90.webp",
        "15408c36-b7df-4f2a-815a-fc3bc09358b6.webp",
        "17151bee-8464-4457-9f79-9a060665e86e.webp",
        "180de963-d17b-4a22-8857-401e90f1b963.webp",
        "181b0b1e-8e21-4327-91d5-52252d40e39d.webp",
        "1956ec81-3ad2-4faa-be22-b88b0d63f50d.webp",
        "206ff910-bbf7-4ed1-9a70-f300205815b5.webp",
        "20ba8fb4-f3b6-4f27-8b9a-341b4053c32a.webp",
        "20d1ef96-0cb8-421d-a556-dfda6d72f4d9.webp",
        "21ed957a-df01-42b7-b2ce-3441a20af592.webp",
        "22af1205-ead5-474b-91b6-de89553b039a.webp",
        "2339fe8a-4e2b-4ff9-9cdf-dca5c2279ea6.webp",
        "2501e267-fbbe-4ade-9b77-7d4304ba6b27.webp",
        "254e6ade-127b-4224-bf9e-a7419b87b57d.webp",
        "2563e59a-d467-412c-ab3c-9294d559fc70.webp",
        "2627a0e5-6cd9-487b-b75b-0025658adcbe.webp",
        "276010a7-5506-4481-875e-3cd04a8ee25c.webp",
        "3199ab14-3c02-4b80-bb07-1d676d1b7da0.webp",
        "3335010e-f606-4a2b-83b5-49ff03ee46ad.webp",
        "33d651c7-eb24-4c88-9bf6-970c701a225f.webp",
        "346d999f-2947-4bb0-835f-92b05fdd8317.webp",
        "34fd23d1-9447-4b08-b237-e84b208221ec.webp",
        "37a54979-07ee-4cca-a773-038c97030919.webp",
        "392dc0a3-9e6c-4095-a577-1be927dbc491.webp",
        "3c7881a0-db22-4c68-a18c-c119fe33157f.webp",
        "3c812305-91bf-40d5-8301-b5c4827bb68f.webp",
        "3fb6a907-39a3-4df8-af85-255c10bf2637.webp",
        "42ceb321-8679-4bed-adcf-2b48b050f4ad.webp",
        "43763d10-71a3-4f84-b298-8947ffb17bbf.webp",
        "4480e6e9-51dd-4f11-a094-06c2d0f69108.webp",
        "449dd908-0025-4450-b14d-f8de30a38e4e.webp",
        "44dfed28-ef5e-4b15-bf9f-889effd593f9.webp",
        "490ba931-e182-4f4a-9187-1eda9c738bdf.webp",
        "4b2a18d4-f26e-4507-9257-4719d5b94f9f.webp",
        "4b7773db-2610-435e-89ea-ace6e2554881.webp",
        "4eb38d66-aa49-42ef-b66e-d703c0c2b554.webp",
        "506280b8-ffb7-4a54-b42c-8b82c40cbd65.webp",
        "5190f28a-35e0-461b-aa75-55a0ad911dbf.webp",
        "52a5c396-7d47-425a-bdc5-d76d1c82fad4.webp",
        "53cc2ba3-561d-46f7-8fcf-3b8525c59b76.webp",
        "54aea5d4-b8d9-4bd1-9516-5bd2c4922feb.webp",
        "54b03731-8d2b-4519-8b20-bcd1f2a5dc06.webp",
        "54b931f5-692a-4df5-9337-929ee022b313.webp",
        "54f09718-56a6-49f5-8bee-dda0c230ef85.webp",
        "56e68d4f-2c12-42da-b145-9e3f08199814.webp",
        "576b3068-3192-4f77-8f32-a772599b91c7.webp",
        "577d68e0-d03b-4558-b362-11edf71e08e6.webp",
        "5833f3e4-f43f-477c-a817-31825a20cc8b.webp",
        "59a4f8b5-afac-41ba-85ff-d551a6c80573.webp",
        "59bce5f3-b433-4af5-bd18-9703fd277536.webp",
        "5b9d2198-ddbe-424e-98ac-83eda7f537ea.webp",
        "5bfc4a24-0603-45ac-ae76-6cb4d9547750.webp",
        "5c57fdd5-7a65-422d-829f-10decbe81e34.webp",
        "5d42d176-1b52-43b8-9656-5f8df44910ce.webp",
        "5e5158e8-c2ca-4d15-a1fe-fb161a718ad8.webp",
        "5f40c65b-f3fc-4187-a43a-f3a518bfd0e5.webp",
        "5fa42928-ade8-4982-b3e4-4f7e37639b41.webp",
        "5fab7109-9d6a-47ee-9b9f-646c9779d42a.webp",
        "6097495c-eb0a-42c2-a7d0-6ea9c8fdbe6f.webp",
        "61d48963-ee82-4817-aef1-cee40b04797a.webp",
        "61e2898e-617b-493a-a6d5-286529618c58.webp",
        "6294d963-fbe8-4c6b-81cc-0d0247b166bf.webp",
        "62ca90cc-77a8-4e6f-8d04-01bb0118eab1.webp",
        "6466e73f-fe9b-47f2-baa1-99c7e0812d58.webp",
        "656ee23d-bcd2-4dc1-a18f-14f3e4285267.webp",
        "6626de1e-0bad-445e-9c74-084e43c69b95.webp",
        "69da07c2-42f7-479c-b01f-536a8082cbe4.webp",
        "6a3a8272-43fd-4bfc-83ca-f5eddf910d97.webp",
        "6b1da216-7072-4918-9077-c2f5aa0b75ca.webp",
        "6b866d5c-1049-4d7c-b563-9de8e37ecd2f.webp",
        "6bd798b8-bde7-4809-a624-963eda7ffe30.webp",
        "6d15cc45-63b0-4458-9d3c-fbad0f1459c0.webp",
        "6d28eef2-998f-4de8-bb74-87bba6dd0146.webp",
        "6e096ebf-0908-47f6-84f7-0d9f2566a501.webp",
        "6e2b528a-f307-4600-8eaf-b79b7f2512c8.webp",
        "6e53dc58-85e8-4f4f-a688-6c3889cb3bee.webp",
        "6f7c39b4-0803-40d0-b7b9-a3eb094afd37.webp",
        "7190d555-af5a-4049-8e82-a879f88127b3.webp",
        "7205ad2f-705d-43b2-a9ac-2a7a8b6237db.webp",
        "7360f2e9-bcfc-41e5-9d09-9d0d5d0a2b37.webp",
        "73cb08ba-f144-4c8c-826c-f101d9216586.webp",
        "744306f6-4a9d-4e63-b277-23c017085237.webp",
        "775f2205-848b-40c4-b48d-ab6dcd785554.webp",
        "776aab22-ec00-4a2c-bad4-ea01aba9017e.webp",
        "77dbc725-aed6-4e3f-a449-77bafe60b697.webp",
        "79207a1d-8f93-49ba-9948-847d9b0a9821.webp",
        "797bc909-60b3-4847-9398-67a86ac93ebb.webp",
        "7b0c951a-68dd-4554-a64d-35a710fb1fa6.webp",
        "7b49c5d5-c0a6-4ef5-8d42-9c464389ec3a.webp",
        "7c02bc19-6371-4d88-bb8f-bfb023c280dd.webp",
        "7c0506b7-755f-423b-9979-f110470cb4a4.webp",
        "7c26316a-20cd-4827-a6f4-435a62c246cd.webp",
        "7cf946ec-ae9c-415f-9923-b785a719ffa0.webp",
        "7f493e57-c09e-4c88-ac7a-9341b4c788ca.webp",
        "8122c116-6b27-497d-a6a2-9ff138405121.webp",
        "818097b1-03fa-49f8-b98e-73b932bbeb1b.webp",
        "82ff762c-2272-4997-9d6c-cb1e32662ddf.webp",
        "83730e92-bbea-4417-b1c7-e966d1566477.webp",
        "8388df12-e701-4a23-9f4c-a2b3b3c8780d.webp",
        "83bea5ee-2820-421d-80be-c9d5449f9ed3.webp",
        "85558907-feb6-4b54-870c-4f3970f3ceac.webp",
        "85cea55f-dc8c-4896-a1f0-5d05599e779c.webp",
        "88dddfeb-afdc-43f6-ae1b-9f9c71cc2bcf.webp",
        "892d83be-be16-4b16-90a6-fab508ee2ed9.webp",
        "89d08686-9cbf-4f2e-9f99-fc7cda2005e5.webp",
        "8cdea5b9-17fb-4a65-bbd1-078064d3b593.webp",
        "8fd5a6ab-c3c5-494d-9212-846b2f34ba5a.webp",
        "90ac8e43-ac74-47d9-b5f3-02258b07f90b.webp",
        "91a17b49-8ef2-4d25-95b2-dcae1487ffce.webp",
        "93c282a1-28d9-46ab-a5fe-7d6dbdeac83c.webp",
        "961de4a3-8263-45c3-9cad-d2cd08976e53.webp",
        "97637309-d1af-4376-beba-c7d6970637c7.webp",
        "97f79b5a-8365-424b-9718-cdd909075f92.webp",
        "9a0d992e-5c64-4ac2-80b8-76b3f9c56862.webp",
        "9c1b2d04-3d33-4884-9ace-25935ec220f4.webp",
        "9dd4f960-4f15-429f-a081-370c1b54e2c3.webp",
        "9ee186fc-1227-4acc-8197-7740a0886e3d.webp",
        "9ef7d602-dedb-4a01-85e6-68bfd1e5eacd.webp",
        "9f31e5d1-33e3-45ad-a403-608a0a40b6b8.webp",
        "9fd6b31f-6437-40f8-aa5d-30275289e08d.webp",
        "a0b447cb-a774-4e28-92ab-e32debdec19b.webp",
        "a15d9fea-1c86-4b98-aeae-13dc2d3a2025.webp",
        "a1823ab5-0a2b-44b5-aa93-9e402710240d.webp",
        "a259d402-cb17-476f-9109-f120621ed3c0.webp",
        "a29ca1fe-40c8-4e21-be74-e281108d8cc0.webp",
        "a2b411b3-356a-488f-a0f7-ada9a9a55bee.webp",
        "a336c637-5260-43b0-a91e-06b5172a5b87.webp",
        "a48e9cfa-fdf4-434e-bea3-966c12f1794b.webp",
        "a59f8ae2-a237-497 ngon4973-94f9-4f8db862afac.webp",
        "a70896e0-5de2-46bb-9866-ea6a7322092e.webp",
        "a79eead7-5743-41cc-8d4c-a7443f630683.webp",
        "a8769d80-2a3e-4b53-bf34-b342b0f236ab.webp",
        "a8a8ba0b-d30c-46cc-8b1c-5ae8b05fee84.webp",
        "a9d1961b-796f-4456-94bf-f26bd6c36d22.webp",
        "abb49a25-3dce-47ad-b6ad-cd8e5743fa61.webp",
        "ac253524-e830-4d0f-a546-75f6bac6f4ec.webp",
        "ad2f2568-a254-42ec-95ac-6ee1cd0bfd81.webp",
        "ad735a6f-b735-4d51-bffd-27d15f369b91.webp",
        "afb0e35f-3668-42f9-bfa8-7ae9dbe53fdb.webp",
        "b1d850c3-31ab-4ca0-8439-419601ada299.webp",
        "b30f4a96-fad1-4f21-840c-6b38475fc74b.webp",
        "b3262b3a-b9ad-4c6f-b46e-c98c2d256e01.webp",
        "b327d5d2-fa75-41a8-bacd-0ad165b7b14c.webp",
        "b44b6426-c880-4036-9b65-7a778cdddb5f.webp",
        "b71c8398-ed9e-4aea-9d09-fd2f63129444.webp",
        "b73f3892-108e-4b9f-b76d-43d7c443bf63.webp",
        "b8099a8b-a23a-46f3-8861-90affe952cf7.webp",
        "b966be47-8f16-4c94-86fc-d95242a2ab1a.webp",
        "bb1d1619-3338-418f-824c-4b59a436213e.webp",
        "bb4652aa-fc4a-4d53-a3d3-03719244c171.webp",
        "bc43c-image1xxl.webp",
        "bca2e060-942a-4cec-afeb-260ea7c190c4.webp",
        "bd7a2-pms000t.webp",
        "d3fdb-image2xxl-4-.webp",
        "f305b29f-7afc-4ba8-b9a0-56dcf9b273e8.webp",
        "f675d915-6630-46a8-98f8-33006ed1575c.webp",
        "f7042e18-5bd6-45a4-8da0-8b60ccbbf587.webp",
        "f85033ca-32c5-46ac-a2dd-530d2bf3abe1.webp",
        "fa0855ea-1b4f-4de1-8af8-6f76a14023b3.webp",
        "fa687950-ee02-433f-8500-e15d26e59344.webp",
        "fc5876f1-ba63-497d-867e-db0d1e2f38d2.webp",
        "fcbe7abb-650c-4712-ae24-6c3340f3236b.webp",
        "fdc37297-08d4-4b2f-ad75-a561d358536f.webp",
        "fe7dee33-7015-4d9c-bdf6-e4bc7f60234b.webp",
        "ff3310a1-c17a-40e6-a454-afdb689629f4.webp",
    ];

    public function __construct()
    {
        $lastId = DB::table('products')->max('id');
        $this->startingProductId = $lastId ? $lastId + 1 : 1;
        $this->cachedImages = $this->images;
    }

    public function run(): void
    {
        $this->startTime = microtime(true);
        $this->logInfo('=== STARTING OPTIMIZED VARIANT SEEDER ===');
        $this->logInfo("Mode: " . ($this->enableTruncate ? 'FRESH' : 'INCREMENTAL'));
        $this->logInfo("Target: " . number_format($this->targetProducts) . " products");
        $this->logInfo("Starting Product ID: " . number_format($this->startingProductId));

        try {
            if ($this->enableTruncate) {
                $this->handleFreshMode();
            } else {
                $this->handleIncrementalMode();
            }

            $this->seedVariantTypesAndOptions();
            $this->seedProducts();
            $this->logResults();

        } catch (Exception $e) {
            try {
                DB::rollBack();
            } catch (Exception $_) {
            }

            $this->logError("SEEDING FAILED: " . $e->getMessage());
            throw $e;
        }
    }

    protected function handleFreshMode(): void
    {
        $this->logInfo('Truncating tables...');

        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $tables = [
            'variant_images', 'product_variant_option_assignments', 'product_variants',
            'product_variant_options', 'product_variant_types', 'product_variant_type_selections',
            'product_images', 'products', 'brand_category', 'category_filter',
            'categories', 'filters', 'brands'
        ];

        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement("SELECT setval('products_id_seq', {$this->startingProductId}, false)");

        $this->currentProductId = $this->startingProductId;
        $this->seedBasicData();

        $this->logInfo('Fresh mode setup completed');
    }

    protected function handleIncrementalMode(): void
    {
        $maxProductId = DB::table('products')->max('id') ?? ($this->startingProductId - 1);
        $this->currentProductId = max($maxProductId + 1, $this->startingProductId);
        $this->totalProductsSeeded = DB::table('products')->count();
        $this->totalVariantsSeeded = DB::table('product_variants')->count();

        $this->logInfo("Current products: " . number_format($this->totalProductsSeeded));
        $this->logInfo("Next product ID: " . number_format($this->currentProductId));

        $this->verifyBasicData();
    }

    protected function seedBasicData(): void
    {
        $this->logInfo('Seeding basic data (categories, brands, filters)...');

        $existingCodes = [];
        $filters = $this->createFilters();
        $categories = $this->createCategories($existingCodes);
        $brands = $this->createBrands();

        $this->attachRelationships($categories, $filters, $brands);

        $this->logInfo('Basic data seeded successfully');
    }

    protected function createFilters()
    {
        if (DB::table('filters')->count() > 0) {
            return collect(DB::table('filters')->get())->keyBy('name');
        }

        $filters = [
            ['name' => 'price', 'title' => 'Price Range', 'description' => 'Filter by price', 'status' => 'active'],
            ['name' => 'brand', 'title' => 'Brands', 'description' => 'Filter by brand', 'status' => 'active'],
            ['name' => 'rating', 'title' => 'Ratings', 'description' => 'Filter by rating', 'status' => 'active'],
            ['name' => 'discount', 'title' => 'Discounts', 'description' => 'Filter by discount', 'status' => 'active'],
        ];

        DB::table('filters')->insert($filters);
        return collect(DB::table('filters')->get())->keyBy('name');
    }

    protected function createCategories(array &$existingCodes)
    {
        if (DB::table('categories')->count() > 0) {
            return collect(DB::table('categories')->get())->keyBy('slug');
        }

        $now = Carbon::now();

        $roots = [
            ['title' => 'Electronics', 'slug' => 'electronics', 'summary' => 'Latest electronics'],
            ['title' => 'Fashion', 'slug' => 'fashion', 'summary' => 'Trendy fashion'],
            ['title' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'summary' => 'Home essentials'],
            ['title' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'summary' => 'Sports equipment'],
            ['title' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'summary' => 'Beauty products'],
        ];

        $rootData = [];
        foreach ($roots as $idx => $cat) {
            $code = $this->generateUniqueCode($cat['title'], $existingCodes);
            $rootData[] = array_merge($cat, [
                'parent_id' => null,
                'level' => 0,
                'path' => null,
                'has_children' => true,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => $idx < 3,
                'sort_order' => $idx + 1,
                'photo' => '/storage/photos/1/Category/mini-banner' . ($idx % 3 + 1) . '.webp',
                'seo_title' => $cat['title'],
                'seo_description' => $cat['summary'],
                'added_by' => 1,
                'code' => $code,
                'code_generated_at' => $now,
                'code_locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('categories')->insert($rootData);

        $rootCats = collect(DB::table('categories')->where('level', 0)->get())->keyBy('slug');

        $level1Map = [
            'electronics' => [
                ['title' => 'Mobiles & Accessories', 'slug' => 'mobiles'],
                ['title' => 'Laptops & Accessories', 'slug' => 'laptops'],
                ['title' => 'Audio', 'slug' => 'audio'],
            ],
            'fashion' => [
                ['title' => 'Men', 'slug' => 'men'],
                ['title' => 'Women', 'slug' => 'women'],
                ['title' => 'Kids', 'slug' => 'kids'],
            ],
            'home-kitchen' => [
                ['title' => 'Furniture', 'slug' => 'furniture'],
                ['title' => 'Kitchen Appliances', 'slug' => 'kitchen-appliances'],
            ],
            'sports-fitness' => [
                ['title' => 'Gym Equipment', 'slug' => 'gym-equipment'],
                ['title' => 'Outdoor Sports', 'slug' => 'outdoor-sports'],
            ],
            'beauty-personal-care' => [
                ['title' => 'Skin Care', 'slug' => 'skin-care'],
                ['title' => 'Makeup', 'slug' => 'makeup'],
            ],
        ];

        $level1Data = [];
        foreach ($level1Map as $parentSlug => $children) {
            $parent = $rootCats[$parentSlug] ?? null;
            foreach ($children as $idx => $cat) {
                $code = $this->generateUniqueCode($cat['title'], $existingCodes);
                $level1Data[] = [
                    'title' => $cat['title'],
                    'slug' => $cat['slug'],
                    'summary' => $cat['title'],
                    'photo' => null,
                    'parent_id' => $parent ? $parent->id : null,
                    'level' => 1,
                    'path' => $parent ? (string)$parent->id : null,
                    'sort_order' => $idx + 1,
                    'has_children' => in_array($cat['slug'], ['mobiles', 'men']),
                    'children_count' => 0,
                    'products_count' => 0,
                    'status' => 'active',
                    'is_featured' => false,
                    'seo_title' => $cat['title'],
                    'seo_description' => $cat['title'],
                    'added_by' => 1,
                    'code' => $code,
                    'code_generated_at' => $now,
                    'code_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('categories')->insert($level1Data);

        $level1Cats = collect(DB::table('categories')->where('level', 1)->get())->keyBy('slug');

        $level2Map = [
            'mobiles' => [['title' => 'Smartphones', 'slug' => 'smartphones']],
            'men' => [['title' => 'Shoes', 'slug' => 'shoes']],
        ];

        $level2Data = [];
        foreach ($level2Map as $parentSlug => $children) {
            $parent = $level1Cats[$parentSlug];
            foreach ($children as $cat) {
                $code = $this->generateUniqueCode($cat['title'], $existingCodes);
                $level2Data[] = [
                    'title' => $cat['title'],
                    'slug' => $cat['slug'],
                    'summary' => $cat['title'],
                    'photo' => null,
                    'parent_id' => $parent ? $parent->id : null,
                    'level' => 2,
                    'path' => $parent ? ($parent->path . '/' . $parent->id) : null,
                    'sort_order' => 1,
                    'has_children' => false,
                    'children_count' => 0,
                    'products_count' => 0,
                    'status' => 'active',
                    'is_featured' => false,
                    'seo_title' => $cat['title'],
                    'seo_description' => $cat['title'],
                    'added_by' => 1,
                    'code' => $code,
                    'code_generated_at' => $now,
                    'code_locked' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (!empty($level2Data)) {
            DB::table('categories')->insert($level2Data);
        }

        return collect(DB::table('categories')->get())->keyBy('slug');
    }

    protected function createBrands()
    {
        if (DB::table('brands')->count() > 0) {
            return collect(DB::table('brands')->get())->keyBy('slug');
        }

        $brands = [
            ['title' => 'Apple', 'slug' => 'apple', 'status' => 'active'],
            ['title' => 'Samsung', 'slug' => 'samsung', 'status' => 'active'],
            ['title' => 'Dell', 'slug' => 'dell', 'status' => 'active'],
            ['title' => 'Sony', 'slug' => 'sony', 'status' => 'active'],
            ['title' => 'Nike', 'slug' => 'nike', 'status' => 'active'],
            ['title' => 'Adidas', 'slug' => 'adidas', 'status' => 'active'],
            ['title' => 'Ikea', 'slug' => 'ikea', 'status' => 'active'],
            ['title' => 'Prestige', 'slug' => 'prestige', 'status' => 'active'],
            ['title' => 'Decathlon', 'slug' => 'decathlon', 'status' => 'active'],
            ['title' => 'L\'Oreal', 'slug' => 'loreal', 'status' => 'active'],
            ['title' => 'Lakme', 'slug' => 'lakme', 'status' => 'active'],
        ];

        DB::table('brands')->insert($brands);
        return collect(DB::table('brands')->get())->keyBy('slug');
    }

    protected function attachRelationships($categories, $filters, $brands): void
    {
        if (DB::table('category_filter')->count() == 0) {
            $cfData = [];
            foreach ($categories as $cat) {
                foreach ($filters as $filter) {
                    $cfData[] = ['category_id' => $cat->id, 'filter_id' => $filter->id];
                }
            }
            DB::table('category_filter')->insert($cfData);
        }

        if (DB::table('brand_category')->count() == 0) {
            $mappings = [
                'apple' => ['smartphones', 'laptops'],
                'samsung' => ['smartphones', 'audio'],
                'dell' => ['laptops'],
                'sony' => ['audio'],
                'nike' => ['shoes', 'gym-equipment'],
                'adidas' => ['shoes', 'outdoor-sports'],
                'ikea' => ['furniture'],
                'prestige' => ['kitchen-appliances'],
                'decathlon' => ['gym-equipment', 'outdoor-sports'],
                'loreal' => ['skin-care', 'makeup'],
                'lakme' => ['makeup'],
            ];

            $bcData = [];
            foreach ($mappings as $brandSlug => $categorySlugs) {
                $brandId = $brands[$brandSlug]->id;
                foreach ($categorySlugs as $catSlug) {
                    if (isset($categories[$catSlug])) {
                        $bcData[] = ['brand_id' => $brandId, 'category_id' => $categories[$catSlug]->id];
                    }
                }
            }
            DB::table('brand_category')->insert($bcData);
        }
    }

    protected function verifyBasicData(): void
    {
        $counts = [
            'categories' => DB::table('categories')->count(),
            'brands' => DB::table('brands')->count(),
            'filters' => DB::table('filters')->count(),
        ];

        if ($counts['categories'] == 0 || $counts['brands'] == 0 || $counts['filters'] == 0) {
            $this->logWarning('Missing basic data, creating...');
            $this->seedBasicData();
        } else {
            $this->logInfo("Found: {$counts['categories']} categories, {$counts['brands']} brands, {$counts['filters']} filters");
        }
    }

    protected function seedVariantTypesAndOptions(): void
    {
        if (DB::table('product_variant_types')->count() > 0) {
            $this->logInfo('Variant types already exist');
            $this->cacheVariantOptions();
            return;
        }

        $this->logInfo('Creating variant types and options...');

        $now = Carbon::now();
        $typesData = [];
        $optionsData = [];

        foreach ($this->variantTypes as $name => $config) {
            $typeId = count($typesData) + 1;
            $typesData[] = [
                'id' => $typeId,
                'name' => $name,
                'display_name' => $config['display_name'],
                'sort_order' => $typeId,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($config['options'] as $idx => $value) {
                $optionsData[] = [
                    'variant_type_id' => $typeId,
                    'value' => $value,
                    'display_value' => ucfirst($value),
                    'hex_color' => $config['hex_colors'][$idx] ?? null,
                    'sort_order' => $idx,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('product_variant_types')->insert($typesData);
        DB::table('product_variant_options')->insert($optionsData);

        $this->cacheVariantOptions();
        $this->logInfo('Variant types created');
    }

    protected function cacheVariantOptions(): void
    {
        $types = DB::table('product_variant_types')->get()->keyBy('name');
        $options = DB::table('product_variant_options')->get()->groupBy('variant_type_id');

        foreach ($types as $name => $type) {
            $this->cachedVariantOptions[$name] = [
                'type_id' => $type->id,
                'options' => $options[$type->id]->keyBy('value')->map(fn($o) => $o->id)->toArray(),
            ];
        }
    }

    protected function seedProducts(): void
    {
        $this->logInfo('Starting product seeding...');

        $categories = $this->getLeafCategories();
        $brands = collect(DB::table('brands')->get())->keyBy('slug');

        $remaining = $this->targetProducts - $this->totalProductsSeeded;
        if ($remaining <= 0) {
            $this->logInfo('Target already reached');
            return;
        }

        $variantCategories = ['smartphones', 'laptops', 'audio', 'shoes', 'women', 'kids', 'furniture', 'makeup'];
        $allCategories = array_keys($this->productTemplates);

        $variantCats = array_intersect_key($categories, array_flip($variantCategories));
        $perVariantCat = (int)ceil($this->variantProducts / count($variantCats));

        foreach ($variantCats as $slug => $catData) {
            if ($this->totalProductsSeeded >= $this->targetProducts || $this->totalProductsSeeded >= $this->variantProducts) break;

            $targetCount = min($perVariantCat, $this->variantProducts - $this->totalProductsSeeded);
            $this->seedCategoryProducts($slug, $catData, $targetCount, $brands, true);

            $actualCount = DB::table('products')->where('child_cat_id', $catData['id'])->count();
            DB::table('categories')->where('id', $catData['id'])->update(['products_count' => $actualCount]);
        }

        $perNonVariantCat = (int)ceil(($this->targetProducts - $this->variantProducts) / count($allCategories));

        foreach ($allCategories as $slug) {
            if ($this->totalProductsSeeded >= $this->targetProducts) break;

            $catData = $categories[$slug] ?? null;
            if (!$catData) continue;

            $targetCount = min($perNonVariantCat, $this->targetProducts - $this->totalProductsSeeded);
            $this->seedCategoryProducts($slug, $catData, $targetCount, $brands, false);

            $actualCount = DB::table('products')->where('child_cat_id', $catData['id'])->count();
            DB::table('categories')->where('id', $catData['id'])->update(['products_count' => $actualCount]);
        }

        $this->logInfo('Product seeding completed');
    }

    protected function seedCategoryProducts(string $slug, array $catData, int $targetCount, $brands, bool $hasVariants): void
    {
        $this->logInfo("Seeding category: {$slug} " . ($hasVariants ? 'with variants' : 'without variants'));

        $template = $this->productTemplates[$slug];
        $categoryBrands = $this->getCategoryBrands($slug, $brands);
        $variantTypes = $hasVariants ? ($this->categoryVariantTypes[$slug] ?? []) : [];

        $remaining = min($targetCount, $this->targetProducts - $this->totalProductsSeeded);
        $batches = (int)ceil($remaining / $this->batchSize);

        for ($b = 0; $b < $batches; $b++) {
            $batchSize = min($this->batchSize, $remaining - ($b * $this->batchSize));
            if ($batchSize <= 0) break;

            DB::beginTransaction();
            try {
                $this->seedProductBatch($slug, $catData, $template, $categoryBrands, $variantTypes, $batchSize, $b, $hasVariants);

                $progress = round(($this->totalProductsSeeded / $this->targetProducts) * 100, 2);
                $this->logInfo(sprintf(
                    "Progress: %s/%s (%s%%) - Batch %d/%d - %s",
                    number_format($this->totalProductsSeeded),
                    number_format($this->targetProducts),
                    $progress,
                    $b + 1,
                    $batches,
                    $slug
                ));

                DB::commit();
                if ($this->totalProductsSeeded >= $this->targetProducts) break;

            } catch (Exception $e) {
                DB::rollBack();
                $this->currentProductId += $batchSize;
                $this->logError("Batch {$b} failed for {$slug}: " . $e->getMessage());
                $this->logWarning("Skipping {$batchSize} product IDs. Next product ID: {$this->currentProductId}");
                continue;
            }
        }
    }

    protected function seedProductBatch(
        string $slug,
        array $catData,
        array $template,
        array $categoryBrands,
        array $variantTypes,
        int $batchSize,
        int $batchIndex,
        bool $hasVariants
    ): void {
        $now = Carbon::now();

        $productsData = [];
        $variantsData = [];
        $assignmentsData = [];
        $variantImagesData = [];
        $productImagesData = [];
        $typeSelectionsData = [];

        $variantCounter = 0;

        for ($i = 0; $i < $batchSize; $i++) {
            $productId = $this->currentProductId + $i;
            $globalIndex = $this->totalProductsSeeded + $i;

            $nameIdx = $globalIndex % count($template['names']);
            $summaryIdx = $globalIndex % count($template['summaries']);
            $brandIdx = $globalIndex % count($categoryBrands);
            $baseSlug = Str::slug($template['names'][$nameIdx]) . '-' . ($globalIndex + 1) . '-' . $slug;
            $slugSuffix = 1;
            $uniqueSlug = $baseSlug;
            
            while (DB::table('products')->where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = $baseSlug . '-' . $slugSuffix++;
            }

            $productEntry = [
                'id' => $productId,
                'title' => $template['names'][$nameIdx] . ' ' . ($globalIndex + 1),
                'slug' => $uniqueSlug,
                'summary' => $template['summaries'][$summaryIdx],
                'description' => $template['description'],
                'condition' => 'new',
                'status' => 'active',
                'is_featured' => $globalIndex % 10 === 0,
                'has_variants' => $hasVariants,
                'cat_id' => $catData['top_id'],
                'child_cat_id' => $catData['id'],
                'brand_id' => $categoryBrands[$brandIdx],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (!$hasVariants) {
                // Non-variant product (simple product)
                $price = random_int($template['price_range'][0], $template['price_range'][1]);
                $discount = random_int(0, 50);
                $stock = random_int(10, 100);
                $sku = $this->generateSKU($template['names'][$nameIdx], [], $globalIndex, $productId, $slug);

                $productEntry['base_price'] = $price;
                $productEntry['base_discount'] = $discount > 0 ? $discount : null;
                $productEntry['base_stock'] = $stock;
                $productEntry['base_sku'] = $sku;

                // Product images: 3 images per product
                $images = $this->getRandomImages(3);
                foreach ($images as $idx => $imageName) {
                    $productImagesData[] = [
                        'product_id' => $productId,
                        'image_path' => 'photos/1/Products/' . $imageName,
                        'thumbnail_path' => null,
                        'is_primary' => $idx === 0 ? 1 : 0,
                        'sort_order' => $idx,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            } else {
                // Variant product
                $productEntry['base_price'] = null;
                $productEntry['base_discount'] = null;
                $productEntry['base_stock'] = null;
                $productEntry['base_sku'] = null;

                // Type selections - link product to variant types
                foreach ($variantTypes as $typeName) {
                    if (isset($this->cachedVariantOptions[$typeName])) {
                        $typeSelectionsData[] = [
                            'product_id' => $productId,
                            'product_variant_type_id' => $this->cachedVariantOptions[$typeName]['type_id'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                // Generate variant combinations
                $combinations = $this->generateVariantCombinations($variantTypes, $this->variantsPerProduct);

                foreach ($combinations as $comboIndex => $combo) {
                    $variantId = $this->totalVariantsSeeded + $variantCounter + 1;
                    $variantCounter++;

                    $price = random_int($template['price_range'][0], $template['price_range'][1]);
                    $discount = random_int(0, 50);
                    $stock = random_int(10, 100);

                    $sku = $this->generateSKU($template['names'][$nameIdx], $combo, $globalIndex, $variantId, $slug);

                    $variantsData[] = [
                        'id' => $variantId,
                        'product_id' => $productId,
                        'sku' => $sku,
                        'price' => $price,
                        'discount' => $discount > 0 ? $discount : null,
                        'stock' => $stock,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    // Option assignments for variant
                    foreach ($combo as $typeName => $value) {
                        if (isset($this->cachedVariantOptions[$typeName]['options'][$value])) {
                            $assignmentsData[] = [
                                'product_variant_id' => $variantId,
                                'product_variant_option_id' => $this->cachedVariantOptions[$typeName]['options'][$value],
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    // Variant images: 3 images per variant
                    $images = $this->getRandomImages(3);
                    foreach ($images as $idx => $imageName) {
                        $variantImagesData[] = [
                            'product_variant_id' => $variantId,
                            'image_path' => 'photos/1/Products/' . $imageName,
                            'thumbnail_path' => null,
                            'is_primary' => $idx === 0 ? 1 : 0,
                            'sort_order' => $idx,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            $productsData[] = $productEntry;
        }

        // Insert products in chunks
        foreach (array_chunk($productsData, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        if ($hasVariants) {
            // Insert type selections
            foreach (array_chunk($typeSelectionsData, 500) as $chunk) {
                DB::table('product_variant_type_selections')->insert($chunk);
            }

            // Insert variants
            foreach (array_chunk($variantsData, 500) as $chunk) {
                DB::table('product_variants')->insert($chunk);
            }

            // Insert option assignments
            foreach (array_chunk($assignmentsData, 500) as $chunk) {
                DB::table('product_variant_option_assignments')->insert($chunk);
            }

            // Insert variant images
            foreach (array_chunk($variantImagesData, 500) as $chunk) {
                DB::table('variant_images')->insert($chunk);
            }
        }

        // Insert product images
        foreach (array_chunk($productImagesData, 500) as $chunk) {
            DB::table('product_images')->insert($chunk);
        }

        $this->totalProductsSeeded += $batchSize;
        $this->totalVariantsSeeded += $variantCounter;
        $this->currentProductId += $batchSize;
    }

    protected function getLeafCategories(): array
    {
        $leafSlugs = [
            'smartphones', 'laptops', 'audio', 'shoes', 'women', 'kids',
            'furniture', 'kitchen_appliances', 'gym_equipment', 'outdoor_sports',
            'skin_care', 'makeup'
        ];

        $allCategories = DB::table('categories')->select('id', 'parent_id')->get()->keyBy('id');

        $result = [];
        foreach ($leafSlugs as $slug) {
            $id = DB::table('categories')->where('slug', $slug)->value('id');
            if (!$id) continue;

            $topId = $this->getTopParentId($id, $allCategories);
            $result[$slug] = ['id' => $id, 'top_id' => $topId];
        }

        return $result;
    }

    protected function getTopParentId(int $catId, $categories): int
    {
        while (isset($categories[$catId]) && $categories[$catId]->parent_id) {
            $catId = $categories[$catId]->parent_id;
        }
        return $catId;
    }

    protected function getCategoryBrands(string $slug, $brands): array
    {
        $mappings = [
            'smartphones' => ['apple', 'samsung'],
            'laptops' => ['apple', 'dell'],
            'audio' => ['samsung', 'sony'],
            'shoes' => ['nike', 'adidas'],
            'women' => ['nike', 'adidas'],
            'kids' => ['nike', 'adidas'],
            'furniture' => ['ikea'],
            'kitchen_appliances' => ['prestige'],
            'gym_equipment' => ['decathlon', 'nike'],
            'outdoor_sports' => ['decathlon', 'adidas'],
            'skin_care' => ['loreal'],
            'makeup' => ['loreal', 'lakme'],
        ];

        $brandSlugs = $mappings[$slug] ?? ['nike'];
        return collect($brandSlugs)->map(fn($s) => $brands[$s]->id ?? null)->filter()->toArray();
    }

    protected function generateVariantCombinations(array $typeNames, int $maxCombinations): array
    {
        if (empty($typeNames)) {
            return [];
        }

        $typeOptions = [];
        foreach ($typeNames as $typeName) {
            $typeOptions[$typeName] = $this->variantTypes[$typeName]['options'];
        }

        $combinations = [];
        $this->buildCombinationsRecursive($typeOptions, array_keys($typeOptions), 0, [], $combinations);

        shuffle($combinations);
        return array_slice($combinations, 0, min($maxCombinations, count($combinations)));
    }

    protected function buildCombinationsRecursive(
        array $typeOptions,
        array $typeNames,
        int $index,
        array $current,
        array &$combinations
    ): void {
        if ($index >= count($typeNames)) {
            $combinations[] = $current;
            return;
        }

        $typeName = $typeNames[$index];
        foreach ($typeOptions[$typeName] as $option) {
            $current[$typeName] = $option;
            $this->buildCombinationsRecursive($typeOptions, $typeNames, $index + 1, $current, $combinations);
        }
    }

    protected function generateSKU(string $productName, array $combo, int $index, int $id, string $category): string
    {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $productName), 0, 3));
        $comboStr = implode('-', array_map(fn($v) => strtoupper(substr($v, 0, 2)), $combo));
        if (empty($comboStr)) {
            $comboStr = 'V1';
        }
        $categoryCode = strtoupper(substr($category, 0, 3));
        return "{$base}-{$categoryCode}-{$comboStr}-{$id}";
    }

    protected function getRandomImages(int $count = 3): array
    {
        $count = min($count, count($this->cachedImages));
        return collect($this->cachedImages)->random($count)->values()->toArray();
    }

    protected function generateUniqueCode(string $title, array &$existingCodes): string
    {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($title, 0, 3)));

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'X');
        }

        $code = $base;
        $counter = 1;

        while (in_array($code, $existingCodes)) {
            $code = $base . $counter;
            $counter++;
            if ($counter > 999) {
                $code = $base . '_' . time();
                break;
            }
        }

        $existingCodes[] = $code;
        return $code;
    }

    protected function logResults(): void
    {
        $elapsed = round(microtime(true) - $this->startTime, 2);
        $productsPerSecond = $elapsed > 0 ? round($this->totalProductsSeeded / $elapsed, 2) : 0;
        $variantsPerSecond = $elapsed > 0 ? round($this->totalVariantsSeeded / $elapsed, 2) : 0;

        $this->logInfo('=== SEEDING COMPLETED SUCCESSFULLY ===');
        $this->logInfo("Total Products: " . number_format($this->totalProductsSeeded));
        $this->logInfo("Total Variants: " . number_format($this->totalVariantsSeeded));
        $this->logInfo("Execution Time: {$elapsed}s");
        $this->logInfo("Speed: {$productsPerSecond} products/sec, {$variantsPerSecond} variants/sec");
        $this->logInfo("Memory Peak: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB");

        $this->validate();
    }

    protected function validate(): void
    {
        $this->logInfo('=== VALIDATION ===');

        $counts = [
            'products' => DB::table('products')->count(),
            'variants' => DB::table('product_variants')->count(),
            'product_images' => DB::table('product_images')->count(),
            'variant_images' => DB::table('variant_images')->count(),
            'assignments' => DB::table('product_variant_option_assignments')->count(),
        ];

        $this->logInfo("Products: " . number_format($counts['products']));
        $this->logInfo("Variants: " . number_format($counts['variants']));
        $this->logInfo("Product Images: " . number_format($counts['product_images']));
        $this->logInfo("Variant Images: " . number_format($counts['variant_images']));
        $this->logInfo("Assignments: " . number_format($counts['assignments']));

        // Check for orphaned records
        $orphanedVariants = DB::table('product_variants as pv')
            ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
            ->whereNull('p.id')
            ->count();

        $orphanedVariantImages = DB::table('variant_images as vi')
            ->leftJoin('product_variants as pv', 'vi.product_variant_id', '=', 'pv.id')
            ->whereNull('pv.id')
            ->count();

        $orphanedProductImages = DB::table('product_images as pi')
            ->leftJoin('products as p', 'pi.product_id', '=', 'p.id')
            ->whereNull('p.id')
            ->count();

        if ($orphanedVariants > 0) {
            $this->logWarning("Found {$orphanedVariants} orphaned variants");
        }

        if ($orphanedVariantImages > 0) {
            $this->logWarning("Found {$orphanedVariantImages} orphaned variant images");
        }

        if ($orphanedProductImages > 0) {
            $this->logWarning("Found {$orphanedProductImages} orphaned product images");
        }

        $variantProducts = DB::table('products')->where('has_variants', true)->count();
        $expectedVariantImages = $counts['variants'] * 3;
        if ($counts['variant_images'] < $expectedVariantImages) {
            $this->logWarning("Variant image count less than minimum expected: expected at least {$expectedVariantImages}, got {$counts['variant_images']}");
        }

        $nonVariantProducts = DB::table('products')->where('has_variants', false)->count();
        $expectedProductImages = $nonVariantProducts * 3;
        if ($counts['product_images'] < $expectedProductImages) {
            $this->logWarning("Product image count less than minimum expected: expected at least {$expectedProductImages}, got {$counts['product_images']}");
        }

        if ($variantProducts != $this->variantProducts) {
            $this->logWarning("Expected {$this->variantProducts} variant products, found {$variantProducts}");
        }

        $productsWithoutVariants = DB::table('products as p')
            ->leftJoin('product_variants as pv', 'p.id', '=', 'pv.product_id')
            ->where('p.has_variants', true)
            ->whereNull('pv.id')
            ->count();

        if ($productsWithoutVariants > 0) {
            $this->logWarning("Found {$productsWithoutVariants} variant products without variants");
        }

        $duplicateSKUs = DB::table('product_variants')
            ->select('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        $duplicateBaseSKUs = DB::table('products')
            ->select('base_sku')
            ->whereNotNull('base_sku')
            ->groupBy('base_sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('base_sku');

        if ($duplicateSKUs->isNotEmpty()) {
            $this->logWarning("Found duplicate variant SKUs: " . $duplicateSKUs->implode(', '));
        }

        if ($duplicateBaseSKUs->isNotEmpty()) {
            $this->logWarning("Found duplicate base SKUs: " . $duplicateBaseSKUs->implode(', '));
        }

        $this->logInfo('=== VALIDATION COMPLETED ===');
    }

    protected function logInfo(string $message): void
    {
        $this->command->info($message);
        Log::info('[SEEDER] ' . $message);
    }

    protected function logWarning(string $message): void
    {
        $this->command->warn($message);
        Log::warning('[SEEDER] ' . $message);
    }

    protected function logError(string $message): void
    {
        $this->command->error($message);
        Log::error('[SEEDER] ' . $message);
    }

    public function resume(): void
    {
        $current = DB::table('products')->count();

        if ($current >= $this->targetProducts) {
            $this->logInfo("Target already reached: " . number_format($current) . " products");
            return;
        }

        $this->totalProductsSeeded = $current;
        $this->totalVariantsSeeded = DB::table('product_variants')->count();

        $this->logInfo("Resuming from: " . number_format($this->totalProductsSeeded) . " products");
        $this->run();
    }

    public function cleanup(): void
    {
        $this->logInfo('Starting cleanup...');

        DB::beginTransaction();

        try {
            $deleted = DB::table('product_images as pi')
                ->leftJoin('products as p', 'pi.product_id', '=', 'p.id')
                ->whereNull('p.id')
                ->delete();

            if ($deleted > 0) {
                $this->logInfo("Deleted {$deleted} orphaned product images");
            }

            $deleted = DB::table('variant_images as vi')
                ->leftJoin('product_variants as pv', 'vi.product_variant_id', '=', 'pv.id')
                ->whereNull('pv.id')
                ->delete();

            if ($deleted > 0) {
                $this->logInfo("Deleted {$deleted} orphaned variant images");
            }

            $deleted = DB::table('product_variant_option_assignments as pvoa')
                ->leftJoin('product_variants as pv', 'pvoa.product_variant_id', '=', 'pv.id')
                ->whereNull('pv.id')
                ->delete();

            if ($deleted > 0) {
                $this->logInfo("Deleted {$deleted} orphaned assignments");
            }

            $deleted = DB::table('product_variants as pv')
                ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
                ->whereNull('p.id')
                ->delete();

            if ($deleted > 0) {
                $this->logInfo("Deleted {$deleted} orphaned variants");
            }

            DB::commit();
            $this->logInfo('Cleanup completed');

        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Cleanup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getStats(): array
    {
        return [
            'products' => DB::table('products')->count(),
            'variants' => DB::table('product_variants')->count(),
            'product_images' => DB::table('product_images')->count(),
            'variant_images' => DB::table('variant_images')->count(),
            'categories' => DB::table('categories')->count(),
            'brands' => DB::table('brands')->count(),
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'target' => $this->targetProducts,
        ];
    }
}