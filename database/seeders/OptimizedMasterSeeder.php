<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Filter;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

class OptimizedMasterSeeder extends Seeder
{
    // Configuration constants
    protected $startingProductId;

    public function __construct()
    {
        // Check if products table has data
        $lastId = DB::table('products')->max('id');
        if ($lastId) {
            $this->startingProductId = $lastId + 1;
        } else {
            // Fresh table, start from desired initial value
            $this->startingProductId = 2564002;
        }
    }
    protected $enableTruncate = false; // Set to false for existing data insert
    protected $targetProducts = 10000000;
    
    protected $menImages = [
        '00497a4a-3fc5-47c9-93ba-842393d35f46.webp',
        '00846c49-7137-4af6-8317-f1a7d578d8c2.webp',
        '0156bece-7587-4bb4-bf9c-00d0168d6656.webp',
        '021a2a91-7595-4999-9fd7-22465f160c43.webp',
        '0237a1ac-b56e-4c15-9789-8ddc9bd7f064.webp',
        '04cd210b-b297-48df-8813-bb4b1ff5c6c8.webp',
        '05331b5f-664e-42ee-9e50-877bf1377ecc.webp',
        '059926d6-36fc-4a13-8f0c-b3b45e1233de.webp',
        '0707c8a3-4490-47cd-9cda-fcfde0f06cef.webp',
        '07a0fd0b-a61f-4b2f-bc7a-94de73e74837.webp',
        '08e639f0-fbd4-4fdd-858d-97028cacb2e6.webp',
        '14cb9bfb-418c-4d22-857b-65d9da9cb0df.webp',
        '15408c36-b7df-4f2a-815a-fc3bc09358b6.webp',
        '17151bee-8464-4457-9f79-9a060665e86e.webp',
        '180de963-d17b-4a22-8857-401e90f1b963.webp',
        '181b0b1e-8e21-4327-91d5-52252d40e39d.webp',
        '1956ec81-3ad2-4faa-be22-b88b0d63f50d.webp',
        '206ff910-bbf7-4ed1-9a70-f300205815b5.webp',
        '20ba8fb4-f3b6-4f27-8b9a-341b4053c32a.webp',
        '20d1ef96-0cb8-421d-a556-dfda6d72f4d9.webp',
        '21ed957a-df01-42b7-b2ce-3441a20af592.webp',
        '22af1205-ead5-474b-91b6-de89553b039a.webp',
        '2339fe8a-4e2b-4ff9-9cdf-dca5c2279ea6.webp',
        '2501e267-fbbe-4ade-9b77-7d4304ba6b27.webp',
        '254e6ade-127b-4224-bf9e-a7419b87b57d.webp',
        '276010a7-5506-4481-875e-3cd04a8ee25c.webp',
        '3335010e-f606-4a2b-83b5-49ff03ee46ad.webp',
        '3c812305-91bf-40d5-8301-b5c4827bb68f.webp',
        '4480e6e9-51dd-4f11-a094-06c2d0f69108.webp',
        '449dd908-0025-4450-b14d-f8de30a38e4e.webp',
        '44dfed28-ef5e-4b15-bf9f-889effd593f9.webp',
        '4b7773db-2610-435e-89ea-ace6e2554881.webp',
        '4eb38d66-aa49-42ef-b66e-d703c0c2b554.webp',
        '506280b8-ffb7-4a54-b42c-8b82c40cbd65.webp',
        '5190f28a-35e0-461b-aa75-55a0ad911dbf.webp',
        '52a5c396-7d47-425a-bdc5-d76d1c82fad4.webp',
        '53cc2ba3-561d-46f7-8fcf-3b8525c59b76.webp',
        '54aea5d4-b8d9-4bd1-9516-5bd2c4922feb.webp',
        '54b03731-8d2b-4519-8b20-bcd1f2a5dc06.webp',
        '54b931f5-692a-4df5-9337-929ee022b313.webp',
        '54f09718-56a6-49f5-8bee-dda0c230ef85.webp',
        '56e68d4f-2c12-42da-b145-9e3f08199814.webp',
        '576b3068-3192-4f77-8f32-a772599b91c7.webp',
        '577d68e0-d03b-4558-b362-11edf71e08e6.webp',
        '5833f3e4-f43f-477c-a817-31825a20cc8b.webp',
        '59a4f8b5-afac-41ba-85ff-d551a6c80573.webp',
        '59bce5f3-b433-4af5-bd18-9703fd277536.webp',
        '5b9d2198-ddbe-424e-98ac-83eda7f537ea.webp',
        '5c57fdd5-7a65-422d-829f-10decbe81e34.webp',
        '5d42d176-1b52-43b8-9656-5f8df44910ce.webp',
        '5e5158e8-c2ca-4d15-a1fe-fb161a718ad8.webp',
        '5f40c65b-f3fc-4187-a43a-f3a518bfd0e5.webp',
        '5fa42928-ade8-4982-b3e4-4f7e37639b41.webp',
        '5fab7109-9d6a-47ee-9b9f-646c9779d42a.webp',
        '61e2898e-617b-493a-a6d5-286529618c58.webp',
        '6294d963-fbe8-4c6b-81cc-0d0247b166bf.webp',
        '6466e73f-fe9b-47f2-baa1-99c7e0812d58.webp',
        '656ee23d-bcd2-4dc1-a18f-14f3e4285267.webp',
        '6626de1e-0bad-445e-9c74-084e43c69b95.webp',
        '69da07c2-42f7-479c-b01f-536a8082cbe4.webp',
        '6b1da216-7072-4918-9077-c2f5aa0b75ca.webp',
        '6bd798b8-bde7-4809-a624-963eda7ffe30.webp',
        '6d15cc45-63b0-4458-9d3c-fbad0f1459c0.webp',
        '6e096ebf-0908-47f6-84f7-0d9f2566a501.webp',
        '6e2b528a-f307-4600-8eaf-b79b7f2512c8.webp',
        '6f7c39b4-0803-40d0-b7b9-a3eb094afd37.webp',
        '7190d555-af5a-4049-8e82-a879f88127b3.webp',
        '73cb08ba-f144-4c8c-826c-f101d9216586.webp',
        '776aab22-ec00-4a2c-bad4-ea01aba9017e.webp',
        '79207a1d-8f93-49ba-9948-847d9b0a9821.webp',
        '797bc909-60b3-4847-9398-67a86ac93ebb.webp',
        '7b0c951a-68dd-4554-a64d-35a710fb1fa6.webp',
        '7b49c5d5-c0a6-4ef5-8d42-9c464389ec3a.webp',
        '7c0506b7-755f-423b-9979-f110470cb4a4.webp',
        '7c26316a-20cd-4827-a6f4-435a62c246cd.webp',
        '7cf946ec-ae9c-415f-9923-b785a719ffa0.webp',
        '7f493e57-c09e-4c88-ac7a-9341b4c788ca.webp',
        '82ff762c-2272-4997-9d6c-cb1e32662ddf.webp',
        '8388df12-e701-4a23-9f4c-a2b3b3c8780d.webp',
        '83bea5ee-2820-421d-80be-c9d5449f9ed3.webp',
        '85558907-feb6-4b54-870c-4f3970f3ceac.webp',
        '85cea55f-dc8c-4896-a1f0-5d05599e779c.webp',
        '88dddfeb-afdc-43f6-ae1b-9f9c71cc2bcf.webp',
        '89d08686-9cbf-4f2e-9f99-fc7cda2005e5.webp',
        '90ac8e43-ac74-47d9-b5f3-02258b07f90b.webp',
        '93c282a1-28d9-46ab-a5fe-7d6dbdeac83c.webp',
        '97f79b5a-8365-424b-9718-cdd909075f92.webp',
        '9a0d992e-5c64-4ac2-80b8-76b3f9c56862.webp',
        '9dd4f960-4f15-429f-a081-370c1b54e2c3.webp',
        '9ee186fc-1227-4acc-8197-7740a0886e3d.webp',
        '9ef7d602-dedb-4a01-85e6-68bfd1e5eacd.webp',
        '9f31e5d1-33e3-45ad-a403-608a0a40b6b8.webp',
        'a1823ab5-0a2b-44b5-aa93-9e402710240d.webp',
        'a259d402-cb17-476f-9109-f120621ed3c0.webp',
        'a29ca1fe-40c8-4e21-be74-e281108d8cc0.webp',
        'a2b411b3-356a-488f-a0f7-ada9a9a55bee.webp',
        'a59f8ae2-a237-4973-94f9-4f8db862afac.webp',
        'a70896e0-5de2-46bb-9866-ea6a7322092e.webp',
        'a79eead7-5743-41cc-8d4c-a7443f630683.webp',
        'a8769d80-2a3e-4b53-bf34-b342b0f236ab.webp',
        'a8a8ba0b-d30c-46cc-8b1c-5ae8b05fee84.webp',
        'a9d1961b-796f-4456-94bf-f26bd6c36d22.webp',
        'abb49a25-3dce-47ad-b6ad-cd8e5743fa61.webp',
        'ad735a6f-b735-4d51-bffd-27d15f369b91.webp',
        'afb0e35f-3668-42f9-bfa8-7ae9dbe53fdb.webp',
        'b30f4a96-fad1-4f21-840c-6b38475fc74b.webp',
        'b44b6426-c880-4036-9b65-7a778cdddb5f.webp',
        'b71c8398-ed9e-4aea-9d09-fd2f63129444.webp',
        'b966be47-8f16-4c94-86fc-d95242a2ab1a.webp',
        'bb1d1619-3338-418f-824c-4b59a436213e.webp',
        'bd7a2-pms000t.webp',
        'f675d915-6630-46a8-98f8-33006ed1575c.webp',
        'fa0855ea-1b4f-4de1-8af8-6f76a14023b3.webp',
        'fa687950-ee02-433f-8500-e15d26e59344.webp',
        'fc5876f1-ba63-497d-867e-db0d1e2f38d2.webp',
        'fdc37297-08d4-4b2f-ad75-a561d358536f.webp',
        'fe7dee33-7015-4d9c-bdf6-e4bc7f60234b.webp'
    ];

    protected $womenImages = [
        '095e4164-fc6a-496a-82be-1a4329dde539.webp',
        '1409e8df-fafa-4aee-b2f6-7219ff79f065.webp',
        '1417dd87-781e-4a1e-95db-4e8c1211c79d.webp',
        '1508966f-1038-476f-919d-4b5dd1666d90.webp',
        '2563e59a-d467-412c-ab3c-9294d559fc70.webp',
        '2627a0e5-6cd9-487b-b75b-0025658adcbe.webp',
        '3199ab14-3c02-4b80-bb07-1d676d1b7da0.webp',
        '33d651c7-eb24-4c88-9bf6-970c701a225f.webp',
        '346d999f-2947-4bb0-835f-92b05fdd8317.webp',
        '34fd23d1-9447-4b08-b237-e84b208221ec.webp',
        '37a54979-07ee-4cca-a773-038c97030919.webp',
        '392dc0a3-9e6c-4095-a577-1be927dbc491.webp',
        '3fb6a907-39a3-4df8-af85-255c10bf2637.webp',
        '42ceb321-8679-4bed-adcf-2b48b050f4ad.webp',
        '43763d10-71a3-4f84-b298-8947ffb17bbf.webp',
        '490ba931-e182-4f4a-9187-1eda9c738bdf.webp',
        '4b2a18d4-f26e-4507-9257-4719d5b94f9f.webp',
        '5bfc4a24-0603-45ac-ae76-6cb4d9547750.webp',
        '6097495c-eb0a-42c2-a7d0-6ea9c8fdbe6f.webp',
        '61d48963-ee82-4817-aef1-cee40b04797a.webp',
        '62ca90cc-77a8-4e6f-8d04-01bb0118eab1.webp',
        '6a3a8272-43fd-4bfc-83ca-f5eddf910d97.webp',
        '6b866d5c-1049-4d7c-b563-9de8e37ecd2f.webp',
        '6d28eef2-998f-4de8-bb74-87bba6dd0146.webp',
        '6e53dc58-85e8-4f4f-a688-6c3889cb3bee.webp',
        '7205ad2f-705d-43b2-a9ac-2a7a8b6237db.webp',
        '7360f2e9-bcfc-41e5-9d09-9d0d5d0a2b37.webp',
        '744306f6-4a9d-4e63-b277-23c017085237.webp',
        '775f2205-848b-40c4-b48d-ab6dcd785554.webp',
        '77dbc725-aed6-4e3f-a449-77bafe60b697.webp',
        '7c02bc19-6371-4d88-bb8f-bfb023c280dd.webp',
        '8122c116-6b27-497d-a6a2-9ff138405121.webp',
        '818097b1-03fa-49f8-b98e-73b932bbeb1b.webp',
        '83730e92-bbea-4417-b1c7-e966d1566477.webp',
        '892d83be-be16-4b16-90a6-fab508ee2ed9.webp',
        '8cdea5b9-17fb-4a65-bbd1-078064d3b593.webp',
        '8fd5a6ab-c3c5-494d-9212-846b2f34ba5a.webp',
        '91a17b49-8ef2-4d25-95b2-dcae1487ffce.webp',
        '961de4a3-8263-45c3-9cad-d2cd08976e53.webp',
        '97637309-d1af-4376-beba-c7d6970637c7.webp',
        '9c1b2d04-3d33-4884-9ace-25935ec220f4.webp',
        '9fd6b31f-6437-40f8-aa5d-30275289e08d.webp',
        'a0b447cb-a774-4e28-92ab-e32debdec19b.webp',
        'a15d9fea-1c86-4b98-aeae-13dc2d3a2025.webp',
        'a336c637-5260-43b0-a91e-06b5172a5b87.webp',
        'a48e9cfa-fdf4-434e-bea3-966c12f1794b.webp',
        'ac253524-e830-4d0f-a546-75f6bac6f4ec.webp',
        'ad2f2568-a254-42ec-95ac-6ee1cd0bfd81.webp',
        'b1d850c3-31ab-4ca0-8439-419601ada299.webp',
        'b3262b3a-b9ad-4c6f-b46e-c98c2d256e01.webp',
        'b327d5d2-fa75-41a8-bacd-0ad165b7b14c.webp',
        'b73f3892-108e-4b9f-b76d-43d7c443bf63.webp',
        'b8099a8b-a23a-46f3-8861-90affe952cf7.webp',
        'bb4652aa-fc4a-4d53-a3d3-03719244c171.webp',
        'bc43c-image1xxl.webp',
        'bca2e060-942a-4cec-afeb-260ea7c190c4.webp',
        'd3fdb-image2xxl-4-.webp',
        'f305b29f-7afc-4ba8-b9a0-56dcf9b273e8.webp',
        'f7042e18-5bd6-45a4-8da0-8b60ccbbf587.webp',
        'f85033ca-32c5-46ac-a2dd-530d2bf3abe1.webp',
        'fcbe7abb-650c-4712-ae24-6c3340f3236b.webp',
        'ff3310a1-c17a-40e6-a454-afdb689629f4.webp',
        'ff3310a1-c17a-40e6-a454-afdb689629f5.webp'
    ];

    // Pre-computed product data to reduce runtime generation
    protected $productTemplates = [
        'smartphones' => [
            'names' => ['Premium Smartphone', 'Flagship Phone', 'Pro Smartphone', 'Ultra Phone', 'Elite Smartphone'],
            'summaries' => ['Latest technology for seamless connectivity.', 'High-performance device for work and play.'],
            'description' => 'Designed for the tech-savvy user with cutting-edge features and superior performance.',
            'sizes' => ['N/A'],
            'price_range' => [20000, 100000]
        ],
        'laptops' => [
            'names' => ['Gaming Laptop', 'Business Laptop', 'Ultrabook', 'Workstation', 'Pro Laptop'],
            'summaries' => ['Powerful computing for professionals.', 'Ideal for gaming and productivity.'],
            'description' => 'Crafted for professionals and gamers with high performance and reliability.',
            'sizes' => ['N/A'],
            'price_range' => [30000, 150000]
        ],
        'audio' => [
            'names' => ['Wireless Headphones', 'Bluetooth Speaker', 'Gaming Headset', 'Studio Monitors', 'Earbuds'],
            'summaries' => ['Immersive sound for music lovers.', 'Crystal-clear audio for all your needs.'],
            'description' => 'Experience superior sound quality with advanced audio technology.',
            'sizes' => ['N/A'],
            'price_range' => [1000, 20000]
        ],
        'shoes' => [
            'names' => ['Running Shoes', 'Casual Sneakers', 'Formal Shoes', 'Sports Shoes', 'Canvas Shoes'],
            'summaries' => ['Classic design with modern comfort.', 'Durable footwear for all occasions.'],
            'description' => 'Crafted for the modern man who values quality, style, and comfort.',
            'sizes' => ['7', '8', '9', '10', '11'],
            'price_range' => [2000, 15000]
        ],
        'women' => [
            'names' => ['Designer Dress', 'Casual Top', 'Formal Blouse', 'Summer Dress', 'Party Wear'],
            'summaries' => ['Elegant and comfortable for everyday wear.', 'Perfect for both casual and formal occasions.'],
            'description' => 'Designed specifically for women who appreciate style, comfort, and elegance.',
            'sizes' => ['XS', 'S', 'M', 'L', 'XL'],
            'price_range' => [1500, 8000]
        ],
        'kids' => [
            'names' => ['Kids T-Shirt', 'Shorts', 'Jacket', 'Dress', 'Casual Wear'],
            'summaries' => ['Fun and durable clothing for kids.', 'Comfortable fit for active children.'],
            'description' => 'Made for kids with comfort, durability, and fun designs in mind.',
            'sizes' => ['2-3Y', '4-5Y', '6-7Y', '8-9Y', '10-11Y'],
            'price_range' => [500, 3000]
        ],
        'furniture' => [
            'names' => ['Modern Sofa', 'Dining Table', 'Office Chair', 'Bookshelf', 'Coffee Table'],
            'summaries' => ['Stylish addition to any home.', 'Durable and comfortable furniture.'],
            'description' => 'Transform your living space with elegant designs and premium materials.',
            'sizes' => ['Small', 'Medium', 'Large'],
            'price_range' => [5000, 50000]
        ],
        'kitchen_appliances' => [
            'names' => ['Mixer Grinder', 'Toaster', 'Blender', 'Coffee Maker', 'Food Processor'],
            'summaries' => ['Modern appliances for easy cooking.', 'Enhance your kitchen experience.'],
            'description' => 'Simplify your cooking with innovative appliances and modern technology.',
            'sizes' => ['Standard'],
            'price_range' => [2000, 25000]
        ],
        'gym_equipment' => [
            'names' => ['Treadmill', 'Dumbbell Set', 'Yoga Mat', 'Exercise Bike', 'Weight Bench'],
            'summaries' => ['Build your strength with quality gear.', 'Perfect for home workouts.'],
            'description' => 'Built for fitness enthusiasts to achieve their health and fitness goals.',
            'sizes' => ['One Size', 'Adjustable'],
            'price_range' => [1000, 100000]
        ],
        'outdoor_sports' => [
            'names' => ['Camping Tent', 'Hiking Backpack', 'Sports Shoes', 'Water Bottle', 'Sleeping Bag'],
            'summaries' => ['Gear for your next adventure.', 'Durable equipment for outdoor activities.'],
            'description' => 'Gear up for outdoor adventures with reliable and durable equipment.',
            'sizes' => ['One Size', 'Adjustable'],
            'price_range' => [500, 30000]
        ],
        'skin_care' => [
            'names' => ['Face Moisturizer', 'Cleanser', 'Sunscreen', 'Face Mask', 'Serum'],
            'summaries' => ['Gentle care for all skin types.', 'Premium skincare for daily use.'],
            'description' => 'Nourish your skin with high-quality ingredients and advanced formulations.',
            'sizes' => ['50ml', '100ml', '200ml'],
            'price_range' => [300, 5000]
        ],
        'makeup' => [
            'names' => ['Lipstick', 'Foundation', 'Eyeliner', 'Mascara', 'Blush'],
            'summaries' => ['Enhance your beauty with quality cosmetics.', 'Professional makeup for any occasion.'],
            'description' => 'Achieve a flawless look with professional-grade cosmetics and beauty products.',
            'sizes' => ['Standard'],
            'price_range' => [200, 3000]
        ]
    ];

    protected $totalProductsSeeded = 0;
    protected $currentProductId = 0;

    public function run()
    {
        $this->command->info('Starting enhanced seeder with dynamic IDs...');
        $this->command->info("Truncate mode: " . ($this->enableTruncate ? 'ENABLED' : 'DISABLED'));
        $this->command->info("Starting Product ID: " . number_format($this->startingProductId));
        
        $startTime = microtime(true);
        
        try {
            // Configure PostgreSQL for bulk operations
            $this->optimizeDatabaseSettings();
            
            // Handle truncate or existing data mode
            if ($this->enableTruncate) {
                $this->handleFreshDataMode();
            } else {
                $this->handleExistingDataMode();
            }
            
            // Seed products with maximum performance
            $this->seedProductsBulk();
            
            // Restore normal database settings
            $this->restoreDatabaseSettings();
            
            $endTime = microtime(true);
            $executionTime = round($endTime - $startTime, 2);
            
            $this->command->info("Seeding completed successfully!");
            $this->command->info("Total products seeded: " . number_format($this->totalProductsSeeded));
            $this->command->info("Execution time: {$executionTime} seconds");
            
        } catch (Exception $e) {
            $this->command->error("Seeding failed: " . $e->getMessage());
            throw $e;
        }
    }

    protected function handleFreshDataMode()
    {
        $this->command->info('Fresh data mode: Truncating tables and seeding basic data...');
        
        // Truncate tables in correct order
        DB::statement('TRUNCATE TABLE brand_category, category_filter, categories, filters, brands, product_images, products RESTART IDENTITY CASCADE');
        
        // Reset sequences to start from our desired IDs
        DB::statement("ALTER SEQUENCE products_id_seq RESTART WITH {$this->startingProductId}");
        
        // Seed basic data
        $this->seedBasicData();
        
        // Set current product ID
        $this->currentProductId = $this->startingProductId;
        
        $this->command->info('Fresh data setup completed.');
    }

    protected function handleExistingDataMode()
    {
        $this->command->info('Existing data mode: Working with current data...');
        
        // Get current max product ID and set next ID
        $maxProductId = DB::table('products')->max('id') ?? ($this->startingProductId - 1);
        $this->currentProductId = max($maxProductId + 1, $this->startingProductId);
        
        // Get current product count
        $this->totalProductsSeeded = DB::table('products')->count();
        
        $this->command->info("Current max product ID: " . number_format($maxProductId));
        $this->command->info("Next product ID will be: " . number_format($this->currentProductId));
        $this->command->info("Current total products: " . number_format($this->totalProductsSeeded));
        
        // Verify that basic data exists (categories, brands, filters)
        $this->verifyBasicDataExists();
        
        $this->command->info('Existing data mode setup completed.');
    }

    protected function verifyBasicDataExists()
    {
        $categoriesCount = DB::table('categories')->count();
        $brandsCount = DB::table('brands')->count();
        $filtersCount = DB::table('filters')->count();
        
        if ($categoriesCount == 0 || $brandsCount == 0 || $filtersCount == 0) {
            $this->command->warn('Basic data (categories, brands, filters) not found. Creating...');
            $this->seedBasicData();
        } else {
            $this->command->info("Found {$categoriesCount} categories, {$brandsCount} brands, {$filtersCount} filters");
        }
    }

    protected function optimizeDatabaseSettings()
    {
        $this->command->info('Optimizing database settings...');
        
        try {
            // Only set parameters that can be changed at runtime
            DB::statement("SET work_mem = '256MB'");
            DB::statement("SET maintenance_work_mem = '1GB'");
            DB::statement("SET synchronous_commit = OFF");
            DB::statement("SET commit_delay = 0");
            DB::statement("SET commit_siblings = 5");
            
            // Disable autovacuum during bulk insert
            DB::statement("ALTER TABLE products SET (autovacuum_enabled = false)");
            DB::statement("ALTER TABLE product_images SET (autovacuum_enabled = false)");
            
            $this->command->info('Database settings optimized successfully.');
        } catch (Exception $e) {
            $this->command->warn("Some database optimizations failed: " . $e->getMessage());
            $this->command->info("Continuing with default settings...");
        }
    }

    protected function restoreDatabaseSettings()
    {
        $this->command->info('Restoring database settings...');
        
        try {
            // Re-enable autovacuum
            DB::statement("ALTER TABLE products SET (autovacuum_enabled = true)");
            DB::statement("ALTER TABLE product_images SET (autovacuum_enabled = true)");
            
            // Restore synchronous commit
            DB::statement("SET synchronous_commit = ON");
            
            // Run VACUUM ANALYZE to update statistics
            $this->command->info('Running VACUUM ANALYZE...');
            DB::statement("VACUUM ANALYZE products");
            DB::statement("VACUUM ANALYZE product_images");
            
            $this->command->info('Database settings restored.');
        } catch (Exception $e) {
            $this->command->warn("Error restoring database settings: " . $e->getMessage());
        }
    }

    protected function seedBasicData()
    {
        $this->command->info('Seeding filters, categories, and brands...');
        
        $existingCodes = [];

        // Create filters
        $filters = $this->createFilters();
        
        // Create categories
        $categories = $this->createCategories($existingCodes);
        
        // Create brands
        $brands = $this->createBrands();
        
        // Attach relationships
        $this->attachFiltersToCategoriesOptimized($categories, $filters);
        $this->attachBrandsToCategories($brands, $categories);
        
        $this->command->info('Basic data seeding completed.');
    }

    protected function createFilters()
    {
        // Check if filters already exist
        if (DB::table('filters')->count() > 0) {
            $this->command->info('Filters already exist, skipping creation.');
            return collect(DB::table('filters')->get())->keyBy('name');
        }

        $filtersData = [
            ['name' => 'price', 'title' => 'Price Range', 'description' => 'Filter products by price range', 'status' => 'active'],
            ['name' => 'brand', 'title' => 'Brands', 'description' => 'Filter products by brand', 'status' => 'active'],
            ['name' => 'rating', 'title' => 'Customer Ratings', 'description' => 'Filter products by customer ratings', 'status' => 'active'],
            ['name' => 'discount', 'title' => 'Discounts', 'description' => 'Filter products by discount percentage', 'status' => 'active'],
            ['name' => 'recently-viewed', 'title' => 'Recently Viewed', 'description' => 'Recently Viewed', 'status' => 'active']
        ];

        DB::table('filters')->insert($filtersData);
        return collect(DB::table('filters')->get())->keyBy('name');
    }

    protected function createCategories(&$existingCodes)
    {
        // Check if categories already exist
        if (DB::table('categories')->count() > 0) {
            $this->command->info('Categories already exist, skipping creation.');
            return collect(DB::table('categories')->get())->keyBy('slug');
        }

        $now = Carbon::now();
        
        // Root categories
        $rootCategories = [
            ['title' => 'Electronics', 'slug' => 'electronics', 'summary' => 'Latest electronics including mobiles, laptops, and audio devices', 'photo' => '/storage/photos/1/Category/mini-banner1.webp', 'sort_order' => 1, 'is_featured' => true],
            ['title' => 'Fashion', 'slug' => 'fashion', 'summary' => 'Trendy fashion for men, women, and kids', 'photo' => '/storage/photos/1/Category/mini-banner2.webp', 'sort_order' => 2, 'is_featured' => true],
            ['title' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'summary' => 'Home essentials, furniture, and kitchen appliances', 'photo' => '/storage/photos/1/Category/mini-banner3.webp', 'sort_order' => 3, 'is_featured' => true],
            ['title' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'summary' => 'Sports equipment and fitness gear for active lifestyle', 'photo' => '/storage/photos/1/Category/mini-banner2.webp', 'sort_order' => 4, 'is_featured' => false],
            ['title' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'summary' => 'Beauty products, skincare, and personal care essentials', 'photo' => '/storage/photos/1/Category/mini-banner2.webp', 'sort_order' => 5, 'is_featured' => false]
        ];

        $rootCategoriesData = [];
        foreach ($rootCategories as $cat) {
            $code = $this->generateUniqueCode($cat['title'], $existingCodes);
            $existingCodes[] = $code;
            $rootCategoriesData[] = array_merge($cat, [
                'parent_id' => null,
                'level' => 0,
                'path' => null,
                'has_children' => true,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'seo_title' => $cat['title'] . ' - Shop Online',
                'seo_description' => $cat['summary'],
                'added_by' => 1,
                'code' => $code,
                'code_generated_at' => $now,
                'code_locked' => false,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }

        DB::table('categories')->insert($rootCategoriesData);
        $rootCats = collect(DB::table('categories')->where('level', 0)->get())->keyBy('slug');

        // Level 1 categories
        $level1Categories = [
            // Electronics children
            ['title' => 'Mobiles & Accessories', 'slug' => 'mobiles', 'parent_slug' => 'electronics'],
            ['title' => 'Laptops & Accessories', 'slug' => 'laptops', 'parent_slug' => 'electronics'],
            ['title' => 'Audio', 'slug' => 'audio', 'parent_slug' => 'electronics'],
            // Fashion children
            ['title' => 'Men', 'slug' => 'men', 'parent_slug' => 'fashion'],
            ['title' => 'Women', 'slug' => 'women', 'parent_slug' => 'fashion'],
            ['title' => 'Kids', 'slug' => 'kids', 'parent_slug' => 'fashion'],
            // Home children
            ['title' => 'Furniture', 'slug' => 'furniture', 'parent_slug' => 'home-kitchen'],
            ['title' => 'Kitchen Appliances', 'slug' => 'kitchen-appliances', 'parent_slug' => 'home-kitchen'],
            // Sports children
            ['title' => 'Gym Equipment', 'slug' => 'gym-equipment', 'parent_slug' => 'sports-fitness'],
            ['title' => 'Outdoor Sports', 'slug' => 'outdoor-sports', 'parent_slug' => 'sports-fitness'],
            // Beauty children
            ['title' => 'Skin Care', 'slug' => 'skin-care', 'parent_slug' => 'beauty-personal-care'],
            ['title' => 'Makeup', 'slug' => 'makeup', 'parent_slug' => 'beauty-personal-care']
        ];

        $level1CategoriesData = [];
        foreach ($level1Categories as $cat) {
            $parentCat = $rootCats[$cat['parent_slug']];
            // Ensure $parentCat is an object
            if (is_array($parentCat)) {
                $parentCat = (object)$parentCat;
            }
            $code = $this->generateUniqueCode($cat['title'], $existingCodes);
            $existingCodes[] = $code;
            $level1CategoriesData[] = [
                'title' => $cat['title'],
                'slug' => $cat['slug'],
                'summary' => $cat['title'],
                'photo' => null,
                'parent_id' => $parentCat->id,
                'level' => 1,
                'path' => (string)$parentCat->id,
                'sort_order' => 1,
                'has_children' => in_array($cat['slug'], ['mobiles', 'men']) ? true : false,
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
                'updated_at' => $now
            ];
        }

        DB::table('categories')->insert($level1CategoriesData);
        $level1Cats = collect(DB::table('categories')->where('level', 1)->get())->keyBy('slug');

        // Level 2 categories (leaf nodes for products)
        $level2Categories = [
            ['title' => 'Smartphones', 'slug' => 'smartphones', 'parent_slug' => 'mobiles'],
            ['title' => 'Shoes', 'slug' => 'shoes', 'parent_slug' => 'men']
        ];

        $level2CategoriesData = [];
        foreach ($level2Categories as $cat) {
            $parentCat = $level1Cats[$cat['parent_slug']];
            if (is_array($parentCat)) {
                $parentCat = (object)$parentCat;
            }
            $code = $this->generateUniqueCode($cat['title'], $existingCodes);
            $existingCodes[] = $code;
            $level2CategoriesData[] = [
                'title' => $cat['title'],
                'slug' => $cat['slug'],
                'summary' => $cat['title'],
                'photo' => null,
                'parent_id' => $parentCat->id,
                'level' => 2,
                'path' => $parentCat->path . '/' . $parentCat->id,
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
                'updated_at' => $now
            ];
        }

        DB::table('categories')->insert($level2CategoriesData);

        // Update parent children counts
        DB::table('categories')->where('slug', 'electronics')->update(['children_count' => 3]);
        DB::table('categories')->where('slug', 'fashion')->update(['children_count' => 3]);
        DB::table('categories')->where('slug', 'home-kitchen')->update(['children_count' => 2]);
        DB::table('categories')->where('slug', 'sports-fitness')->update(['children_count' => 2]);
        DB::table('categories')->where('slug', 'beauty-personal-care')->update(['children_count' => 2]);
        DB::table('categories')->where('slug', 'mobiles')->update(['children_count' => 1]);
        DB::table('categories')->where('slug', 'men')->update(['children_count' => 1]);

        return collect(DB::table('categories')->get())->keyBy('slug');
    }

    protected function createBrands()
    {
        // Check if brands already exist
        if (DB::table('brands')->count() > 0) {
            $this->command->info('Brands already exist, skipping creation.');
            return collect(DB::table('brands')->get())->keyBy('slug');
        }

        $brandsData = [
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
            ['title' => 'Lakme', 'slug' => 'lakme', 'status' => 'active']
        ];

        DB::table('brands')->insert($brandsData);
        return collect(DB::table('brands')->get())->keyBy('slug');
    }

    protected function attachFiltersToCategoriesOptimized($categories, $filters)
    {
        // Check if relationships already exist
        if (DB::table('category_filter')->count() > 0) {
            $this->command->info('Category-Filter relationships already exist, skipping.');
            return;
        }

        $filterIds = $filters->pluck('id')->toArray();
        $categoryFilterData = [];

        foreach ($categories as $category) {
            foreach ($filterIds as $filterId) {
                $categoryFilterData[] = [
                    'category_id' => $category->id,
                    'filter_id' => $filterId
                ];
            }
        }

        // Insert in chunks for better performance
        collect($categoryFilterData)->chunk(100)->each(function ($chunk) {
            DB::table('category_filter')->insert($chunk->toArray());
        });
    }

    protected function attachBrandsToCategories($brands, $categories)
    {
        // Check if relationships already exist
        if (DB::table('brand_category')->count() > 0) {
            $this->command->info('Brand-Category relationships already exist, skipping.');
            return;
        }

        $brandCategoryMappings = [
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
            'lakme' => ['makeup']
        ];

        $brandCategoryData = [];
        foreach ($brandCategoryMappings as $brandSlug => $categorySlugs) {
            $brandId = $brands[$brandSlug]->id;
            foreach ($categorySlugs as $categorySlug) {
                if (isset($categories[$categorySlug])) {
                    $brandCategoryData[] = [
                        'brand_id' => $brandId,
                        'category_id' => $categories[$categorySlug]->id
                    ];
                }
            }
        }

        DB::table('brand_category')->insert($brandCategoryData);
    }

    protected function seedProductsBulk()
    {
        $this->command->info('Starting bulk product seeding...');
        
        $batchSize = 100; // Optimized batch size for PostgreSQL
        $imageBatchSize = 9000; // 3 images per product
        
        // Get all necessary data upfront
        $categories = $this->getLeafCategories();
        $brands = collect(DB::table('brands')->get())->keyBy('slug');
        $now = Carbon::now();
        
        $categoriesCount = count($categories);
        $remainingProducts = $this->targetProducts - $this->totalProductsSeeded;
        
        if ($remainingProducts <= 0) {
            $this->command->info("Target already reached! Current: " . number_format($this->totalProductsSeeded));
            return;
        }
        
        $productsPerCategory = (int) ceil($remainingProducts / $categoriesCount);
        
        $menImagesCount = count($this->menImages);
        $womenImagesCount = count($this->womenImages);
        
        foreach ($categories as $categoryKey => $categoryData) {
            if ($this->totalProductsSeeded >= $this->targetProducts) {
                break;
            }
            
            $this->command->info("Seeding category: {$categoryKey}");
            
            $template = $this->productTemplates[$categoryKey];
            $categoryBrands = $this->getCategoryBrands($categoryKey, $brands);
            
            // Calculate remaining products needed
            $remainingProducts = $this->targetProducts - $this->totalProductsSeeded;
            if ($remainingProducts <= 0) {
                break;
            }
            
            $productsForCategory = min($productsPerCategory, $remainingProducts);
            $batches = (int) ceil($productsForCategory / $batchSize);
            
            $menImageIndex = 0;
            $womenImageIndex = 0;
            
            for ($batchIndex = 0; $batchIndex < $batches; $batchIndex++) {
                $batchStart = $batchIndex * $batchSize;
                $batchEnd = min($batchStart + $batchSize, $productsForCategory);
                $actualBatchSize = $batchEnd - $batchStart;
                
                if ($actualBatchSize <= 0) break;
                
                try {
                    // Use database transaction for better performance and data integrity
                    DB::transaction(function () use (
                        $template, $categoryData, $categoryBrands, $categoryKey,
                        $batchStart, $actualBatchSize, $now,
                        &$menImageIndex, &$womenImageIndex, $imageBatchSize,
                        $menImagesCount, $womenImagesCount
                    ) {
                        // Generate products for this batch
                        $productsData = [];
                        $productImagesData = [];
                        
                        for ($i = 0; $i < $actualBatchSize; $i++) {
                            $productIndex = $batchStart + $i;
                            $productId = $this->currentProductId + $i;
                            
                            // Generate product data
                            $nameIndex = $productIndex % count($template['names']);
                            $summaryIndex = $productIndex % count($template['summaries']);
                            $sizeIndex = $productIndex % count($template['sizes']);
                            $brandIndex = $productIndex % count($categoryBrands);
                            
                            $price = random_int($template['price_range'][0], $template['price_range'][1]);
                            $discount = random_int(0, 50);
                            
                            $productsData[] = [
                                'id' => $productId,
                                'title' => $template['names'][$nameIndex] . ' ' . ($this->totalProductsSeeded + $i + 1),
                                'slug' => Str::slug($template['names'][$nameIndex]) . '-' . ($this->totalProductsSeeded + $i + 1) . '-' . $categoryKey,
                                'summary' => $template['summaries'][$summaryIndex],
                                'description' => $template['description'],
                                'stock' => random_int(10, 100),
                                'size' => $template['sizes'][$sizeIndex],
                                'condition' => 'new',
                                'status' => 'active',
                                'price' => $price,
                                'discount' => $discount,
                                'is_featured' => $productIndex % 10 === 0 ? 1 : 0, // 10% featured
                                'cat_id' => $categoryData['id'],
                                'child_cat_id' => null,
                                'brand_id' => $categoryBrands[$brandIndex],
                                'created_at' => $now,
                                'updated_at' => $now
                            ];
                            
                            // Generate images for this product
                            $images = $this->getProductImages($categoryKey, $menImageIndex, $womenImageIndex);
                            foreach ($images as $imgIndex => $imageName) {
                                $productImagesData[] = [
                                    'product_id' => $productId,
                                    'image_path' => 'storage/photos/1/Products/' . $imageName,
                                    'is_primary' => $imgIndex === 0,
                                    'sort_order' => $imgIndex
                                ];
                            }
                            
                            // Update image indices
                            if ($categoryKey === 'shoes' && $menImagesCount > 0) {
                                $menImageIndex = ($menImageIndex + 3) % $menImagesCount;
                            } elseif ($categoryKey === 'women' && $womenImagesCount > 0) {
                                $womenImageIndex = ($womenImageIndex + 3) % $womenImagesCount;
                            }
                        }
                        
                        // Bulk insert products with explicit IDs
                        DB::table('products')->insert($productsData);
                        
                        // Bulk insert product images in chunks
                        collect($productImagesData)->chunk($imageBatchSize)->each(function ($chunk) {
                            DB::table('product_images')->insert($chunk->toArray());
                        });
                    });
                    
                    $this->totalProductsSeeded += $actualBatchSize;
                    $this->currentProductId += $actualBatchSize;
                    
                    // Progress update
                    $percentage = round(($this->totalProductsSeeded / $this->targetProducts) * 100, 2);
                    $this->command->info("Progress: " . number_format($this->totalProductsSeeded) . "/" . number_format($this->targetProducts) . " (" . $percentage . "%) - Batch " . ($batchIndex + 1) . "/" . $batches . " for " . $categoryKey);
                    
                    // Memory cleanup
                    if ($batchIndex % 10 === 0) {
                        $this->command->info("Memory usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB");
                    }
                    
                    // Check if we've reached our target
                    if ($this->totalProductsSeeded >= $this->targetProducts) {
                        $this->command->info("Target of " . number_format($this->targetProducts) . " products reached!");
                        break 2; // Break out of both loops
                    }
                    
                } catch (Exception $e) {
                    $this->command->error("Batch {$batchIndex} failed for category {$categoryKey}: " . $e->getMessage());
                    $this->command->info("Continuing with next batch...");
                    continue; // Continue with next batch instead of stopping
                }
            }
            
            // Update category products count
            $actualProductsInCategory = DB::table('products')
                ->where('cat_id', $categoryData['id'])
                ->count();
            
            DB::table('categories')
                ->where('id', $categoryData['id'])
                ->update(['products_count' => $actualProductsInCategory]);
        }
        
        // Update sequence to continue from the last used ID
        if ($this->currentProductId > $this->startingProductId) {
            $nextSeqValue = $this->currentProductId;
            DB::statement("ALTER SEQUENCE products_id_seq RESTART WITH {$nextSeqValue}");
            $this->command->info("Updated sequence to start from: " . number_format($nextSeqValue));
        }
        
        $this->command->info("Bulk seeding completed. Total products seeded: " . number_format($this->totalProductsSeeded));
    }

    protected function getLeafCategories()
    {
        return [
            'smartphones' => ['id' => DB::table('categories')->where('slug', 'smartphones')->value('id')],
            'laptops' => ['id' => DB::table('categories')->where('slug', 'laptops')->value('id')],
            'audio' => ['id' => DB::table('categories')->where('slug', 'audio')->value('id')],
            'shoes' => ['id' => DB::table('categories')->where('slug', 'shoes')->value('id')],
            'women' => ['id' => DB::table('categories')->where('slug', 'women')->value('id')],
            'kids' => ['id' => DB::table('categories')->where('slug', 'kids')->value('id')],
            'furniture' => ['id' => DB::table('categories')->where('slug', 'furniture')->value('id')],
            'kitchen_appliances' => ['id' => DB::table('categories')->where('slug', 'kitchen-appliances')->value('id')],
            'gym_equipment' => ['id' => DB::table('categories')->where('slug', 'gym-equipment')->value('id')],
            'outdoor_sports' => ['id' => DB::table('categories')->where('slug', 'outdoor-sports')->value('id')],
            'skin_care' => ['id' => DB::table('categories')->where('slug', 'skin-care')->value('id')],
            'makeup' => ['id' => DB::table('categories')->where('slug', 'makeup')->value('id')]
        ];
    }

    protected function getCategoryBrands($categoryKey, $brands)
    {
        $brandMappings = [
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
            'makeup' => ['loreal', 'lakme']
        ];

        $categoryBrandSlugs = $brandMappings[$categoryKey] ?? ['nike'];
        return collect($categoryBrandSlugs)->map(function ($brandSlug) use ($brands) {
            return $brands[$brandSlug]->id;
        })->toArray();
    }

    protected function getProductImages($categoryKey, $menImageIndex, $womenImageIndex)
    {
        if ($categoryKey === 'shoes') {
            return [
                $this->menImages[$menImageIndex % count($this->menImages)],
                $this->menImages[($menImageIndex + 1) % count($this->menImages)],
                $this->menImages[($menImageIndex + 2) % count($this->menImages)]
            ];
        } elseif ($categoryKey === 'women') {
            return [
                $this->womenImages[$womenImageIndex % count($this->womenImages)],
                $this->womenImages[($womenImageIndex + 1) % count($this->womenImages)],
                $this->womenImages[($womenImageIndex + 2) % count($this->womenImages)]
            ];
        } else {
            // Generic images for other categories
            return [
                'generic-' . $categoryKey . '-1.webp',
                'generic-' . $categoryKey . '-2.webp',
                'generic-' . $categoryKey . '-3.webp'
            ];
        }
    }

    protected function generateUniqueCode($title, &$existingCodes)
    {
        $baseCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($title, 0, 3)));
        
        if (strlen($baseCode) < 3) {
            $baseCode = str_pad($baseCode, 3, 'X');
        }
        
        $code = $baseCode;
        $counter = 1;
        while (in_array($code, $existingCodes)) {
            $code = substr($baseCode, 0, 2) . $counter;
            $counter++;
            
            // Prevent infinite loop
            if ($counter > 999) {
                $code = $baseCode . '_' . time();
                break;
            }
        }
        
        $existingCodes[] = $code;
        return $code;
    }

    /**
     * Handle memory cleanup and progress tracking
     */
    protected function performMemoryCleanup()
    {
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        if ($memoryUsage > ($memoryLimit * 0.8)) {
            $this->command->warn("High memory usage detected: " . round($memoryUsage / 1024 / 1024, 2) . " MB");
        }
    }

    /**
     * Get memory limit in bytes
     */
    protected function getMemoryLimit()
    {
        $limit = ini_get('memory_limit');
        if ($limit == -1) {
            return PHP_INT_MAX;
        }
        
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit)-1]);
        $limit = (int) $limit;
        
        switch($last) {
            case 'g':
                $limit *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $limit *= 1024 * 1024;
                break;
            case 'k':
                $limit *= 1024;
                break;
        }
        
        return $limit;
    }

    /**
     * Resume seeding from where it left off (useful for interrupted runs)
     */
    public function resumeSeeding()
    {
        $currentProductCount = DB::table('products')->count();
        
        if ($currentProductCount >= $this->targetProducts) {
            $this->command->info("Already have " . number_format($currentProductCount) . " products. No need to resume.");
            return;
        }
        
        $this->totalProductsSeeded = $currentProductCount;
        $this->command->info("Resuming seeding from " . number_format($this->totalProductsSeeded) . " products...");
        
        // Continue seeding
        $this->seedProductsBulk();
    }

    /**
     * Validate seeding results
     */
    public function validateSeeding()
    {
        $this->command->info("Validating seeding results...");
        
        $productCount = DB::table('products')->count();
        $imageCount = DB::table('product_images')->count();
        
        $this->command->info("Products: " . number_format($productCount));
        $this->command->info("Images: " . number_format($imageCount));
        $this->command->info("Expected images: " . number_format($productCount * 3));
        
        if ($imageCount != $productCount * 3) {
            $this->command->warn("Image count mismatch! Expected " . ($productCount * 3) . " but got " . $imageCount);
        }
        
        // Check for orphaned images
        $orphanedImages = DB::table('product_images as pi')
            ->leftJoin('products as p', 'pi.product_id', '=', 'p.id')
            ->whereNull('p.id')
            ->count();
            
        if ($orphanedImages > 0) {
            $this->command->warn("Found " . $orphanedImages . " orphaned images");
        }
        
        // Check category product counts
        $categories = DB::table('categories')->get();
        foreach ($categories as $category) {
            $actualCount = DB::table('products')->where('cat_id', $category->id)->count();
            if ($actualCount != $category->products_count) {
                $this->command->warn("Category {$category->title} count mismatch: stored={$category->products_count}, actual={$actualCount}");
                
                // Fix the count
                DB::table('categories')
                    ->where('id', $category->id)
                    ->update(['products_count' => $actualCount]);
            }
        }
        
        $this->command->info("Validation completed.");
    }
}