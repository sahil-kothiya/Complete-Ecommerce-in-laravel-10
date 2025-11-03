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
    // Configuration
    protected int $targetProducts = 10000;
    protected int $variantProducts = 9500; // 95% variants as requested
    protected int $variantsPerProduct = 4;
    protected int $batchSize = 500;
    protected bool $enableTruncate = false;

    // State tracking
    protected int $totalProductsSeeded = 0;
    protected int $totalVariantsSeeded = 0;
    protected int $currentProductId = 0;
    protected int $startingProductId;
    protected float $startTime;

    // Cached data
    protected array $cachedNormalImages;
    protected array $cachedVariantImages;
    protected array $cachedCategories = [];
    protected array $cachedBrands = [];
    protected array $cachedVariantOptions = [];
    protected array $existingCodes = [];

    // Product templates with dollar price ranges
    protected array $productTemplates = [
        'smartphones' => [
            'names' => ['Premium Smartphone', 'Flagship Phone', 'Pro Smartphone', 'Ultra Phone', 'Elite Smartphone'],
            'summaries' => ['Latest technology for seamless connectivity.', 'High-performance device for work and play.'],
            'description' => 'Designed for the tech-savvy user with cutting-edge features and superior performance.',
            'price_range' => [199, 1499] // Dollars
        ],
        'laptops' => [
            'names' => ['Gaming Laptop', 'Business Laptop', 'Ultrabook', 'Workstation', 'Pro Laptop'],
            'summaries' => ['Powerful computing for professionals.', 'Ideal for gaming and productivity.'],
            'description' => 'Crafted for professionals and gamers with high performance and reliability.',
            'price_range' => [299, 2499] // Dollars
        ],
        'audio' => [
            'names' => ['Wireless Headphones', 'Bluetooth Speaker', 'Gaming Headset', 'Studio Monitors', 'Earbuds'],
            'summaries' => ['Immersive sound for music lovers.', 'Crystal-clear audio for all your needs.'],
            'description' => 'Experience superior sound quality with advanced audio technology.',
            'price_range' => [19, 299] // Dollars
        ],
        'shoes' => [
            'names' => ['Running Shoes', 'Casual Sneakers', 'Formal Shoes', 'Sports Shoes', 'Canvas Shoes'],
            'summaries' => ['Classic design with modern comfort.', 'Durable footwear for all occasions.'],
            'description' => 'Crafted for the modern individual who values quality, style, and comfort.',
            'price_range' => [29, 199] // Dollars
        ],
        'women' => [
            'names' => ['Designer Dress', 'Casual Top', 'Formal Blouse', 'Summer Dress', 'Party Wear'],
            'summaries' => ['Elegant and comfortable for everyday wear.', 'Perfect for both casual and formal occasions.'],
            'description' => 'Designed specifically for women who appreciate style, comfort, and elegance.',
            'price_range' => [19, 99] // Dollars
        ],
        'kids' => [
            'names' => ['Kids T-Shirt', 'Shorts', 'Jacket', 'Dress', 'Casual Wear'],
            'summaries' => ['Fun and durable clothing for kids.', 'Comfortable fit for active children.'],
            'description' => 'Made for kids with comfort, durability, and fun designs in mind.',
            'price_range' => [9, 49] // Dollars
        ],
        'furniture' => [
            'names' => ['Modern Sofa', 'Dining Table', 'Office Chair', 'Bookshelf', 'Coffee Table'],
            'summaries' => ['Stylish addition to any home.', 'Durable and comfortable furniture.'],
            'description' => 'Transform your living space with elegant designs and premium materials.',
            'price_range' => [49, 999] // Dollars
        ],
        'kitchen_appliances' => [
            'names' => ['Mixer Grinder', 'Toaster', 'Blender', 'Coffee Maker', 'Food Processor'],
            'summaries' => ['Modern appliances for easy cooking.', 'Enhance your kitchen experience.'],
            'description' => 'Simplify your cooking with innovative appliances and modern technology.',
            'price_range' => [29, 299] // Dollars
        ],
        'gym_equipment' => [
            'names' => ['Treadmill', 'Dumbbell Set', 'Yoga Mat', 'Exercise Bike', 'Weight Bench'],
            'summaries' => ['Build your strength with quality gear.', 'Perfect for home workouts.'],
            'description' => 'Built for fitness enthusiasts to achieve their health and fitness goals.',
            'price_range' => [19, 999] // Dollars
        ],
        'outdoor_sports' => [
            'names' => ['Camping Tent', 'Hiking Backpack', 'Sports Shoes', 'Water Bottle', 'Sleeping Bag'],
            'summaries' => ['Gear for your next adventure.', 'Durable equipment for outdoor activities.'],
            'description' => 'Gear up for outdoor adventures with reliable and durable equipment.',
            'price_range' => [9, 299] // Dollars
        ],
        'skin_care' => [
            'names' => ['Face Moisturizer', 'Cleanser', 'Sunscreen', 'Face Mask', 'Serum'],
            'summaries' => ['Gentle care for all skin types.', 'Premium skincare for daily use.'],
            'description' => 'Nourish your skin with high-quality ingredients and advanced formulations.',
            'price_range' => [5, 49] // Dollars
        ],
        'makeup' => [
            'names' => ['Lipstick', 'Foundation', 'Eyeliner', 'Mascara', 'Blush'],
            'summaries' => ['Enhance your beauty with quality cosmetics.', 'Professional makeup for any occasion.'],
            'description' => 'Achieve a flawless look with professional-grade cosmetics and beauty products.',
            'price_range' => [5, 39] // Dollars
        ]
    ];

    // Variant types configuration
    protected array $variantTypes = [
        'color' => [
            'display_name' => 'Color',
            'options' => ['Red', 'Blue', 'Green', 'Black', 'White', 'Silver', 'Gold'],
            'hex_colors' => ['#FF0000', '#0000FF', '#00FF00', '#000000', '#FFFFFF', '#C0C0C0', '#FFD700']
        ],
        'size' => [
            'display_name' => 'Size',
            'options' => ['S', 'M', 'L', 'XL', 'XXL'],
            'hex_colors' => []
        ],
        'storage' => [
            'display_name' => 'Storage',
            'options' => ['64GB', '128GB', '256GB', '512GB', '1TB'],
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

    // Category to variant type mapping
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

    // Normal product images from provided list
    protected array $normalImages = [
        "product_6889f81c5163c_1.webp", "product_6889f81c59696_2.webp", "product_6889f81c616e8_3.webp",
        "product_6889f81c6a225_4.webp", "product_6889f9113eecd_0.webp", "product_6889f980ae308_0.webp",
        "product_6889fd3e6fc02_0.webp", "product_6889ff20a3003_0.webp", "product_6889ff20ab21f_1.webp",
        "product_6889ff20b31b8_2.webp", "product_688a005542712_0.webp", "product_688a0088c4999_0.webp",
        "product_688a01254a8de_0.webp", "product_688a02ff4c13c_0.webp", "product_688a03fbc0e23_0.webp",
        "product_688aefd6b5d92_0.webp", "product_688b3bcfd79b9_0.webp", "product_688b3d8672a03_0.webp",
        "product_688b4fa2cea02_0.webp", "product_688b4fa2d7cae_1.webp", "product_688b51b8ae4fd_0.webp",
        "product_688b5e59765d5_0.webp", "product_688b603325c06_0.webp", "product_688c3b3d8cd70_0.webp",
        "product_688c51f279b9c_0.webp", "product_688c9e1305a57_0.webp", "product_688c9e130e22d_1.webp",
        "product_68909d01452d4_0.webp", "product_68909d014e1e4_1.webp", "product_68909d015694f_2.webp",
        "product_68909d015f199_3.webp", "product_68909d0168d61_4.webp", "product_68909d0170766_5.webp",
        "product_68909d017985c_6.webp", "product_6892df81c7fe4_0.webp", "product_6892df81d0732_1.webp",
        "product_6892fe3b5293e_0.webp", "product_6892fe3b5ba1b_1.webp", "product_6892fea723299_0.webp",
        "product_6892fea72bb51_1.webp", "product_6892ffc4a0ae1_0.webp", "product_6892ffc4a8cea_1.webp",
        "product_6892ffc4afd6d_2.webp", "product_6893008bb8af3_0.webp", "product_6893008bc2ce2_1.webp",
        "product_6893008bcb95a_2.webp", "product_689300d5e7a6b_0.webp", "product_689300d5f0a7f_1.webp",
        "product_689300d604793_2.webp", "product_6893012f60da8_0.webp", "product_6893012f69029_1.webp",
        "product_6893012f70d3d_2.webp", "product_68e8d66387660_0.webp", "product_68e8d79a74aad_0.webp",
        "product_6901faebb109b_0.webp", "product_6901fe4b2da2d_0.webp", "product_69020083e3b75_0.webp",
        "product_690200b244587_0.webp", "product_690200b24efd9_1.webp", "product_690200b258067_2.webp",
        "product_690200b260592_3.webp", "product_690200b26771a_4.webp", "product_690200b270dd5_5.webp",
        "product_690200b27a849_6.webp", "product_690200b282a87_7.webp", "product_690200b28ab7d_8.webp",
        "product_690200b292edf_9.webp", "product_690200b29bf1e_10.webp", "product_690200b2a5137_11.webp",
        "product_690200b2add93_12.webp", "product_690200b2b62ac_13.webp", "product_690200b2be15d_14.webp",
        "product_690200b2c79a1_15.webp", "product_690200b2cfbe7_16.webp", "product_690200b2d807d_17.webp",
        "product_690200b2dfc85_18.webp", "product_690200b2e7ceb_19.webp", "product_690200b2f080e_20.webp",
        "product_690200b304657_21.webp", "product_690200b30c797_22.webp", "product_690200b3148d2_23.webp",
        "product_690200b31d457_24.webp", "product_6902e66def238_0.webp"
    ];

    // Variant images from provided list
    protected array $variantImagesList = [
        "variant_68e8c67d53c28_0.webp", "variant_68e8c73e05444_0.webp", "variant_68e8c824a9cf7_0.webp",
        "variant_68e8ca99af65f_0.webp", "variant_68e8cdbf8f7b7_0.webp", "variant_68e8cf3d223f7_0.webp",
        "variant_68e8cfdb27714_0.webp", "variant_68e8d02251456_0.webp", "variant_68e8d04846afc_0.webp",
        "variant_68e8d10d7ef24_0.webp", "variant_68e8d2a66ff27_0.webp", "variant_68e8d3848c5eb_0.webp",
        "variant_68e8d467679cf_0.webp", "variant_68e8d46771062_1.webp", "variant_68e8d46779626_2.webp",
        "variant_68e8d53a7c3af_0.webp", "variant_68ec8d7cd47f3_1.webp", "variant_68ececa9e9fb8_0.webp",
        "variant_68ececa9f2ee2_1.webp", "variant_68ececaa11db9_0.webp", "variant_68ececaa1abba_1.webp",
        "variant_68ececaa2565a_2.webp", "variant_68ececaa33d4d_0.webp", "variant_68ececaa42765_0.webp",
        "variant_68ececaa4bcec_1.webp", "variant_68edfabeda8af_0.webp", "variant_6901f4c839cad_0.webp",
        "variant_6901f4c841ab6_1.webp", "variant_6901f4c849376_2.webp", "variant_6901f4c85d109_0.webp",
        "variant_6901f4c864a76_1.webp", "variant_6901f4c86c824_2.webp", "variant_6901f4c876678_3.webp",
        "variant_6901f4c87e9c8_4.webp", "variant_6901f4c88c5aa_0.webp", "variant_6901f4c893147_1.webp",
        "variant_6901f4c89e529_0.webp", "variant_6901f4c8a616e_1.webp", "variant_6901f4c8addeb_2.webp",
        "variant_690201b061c8f_0.webp", "variant_690201d1267f3_0.webp", "variant_6902021a2cec0_0.webp",
        "variant_69020281e822d_0.webp", "variant_690202b9629f4_0.webp", "variant_690204516e31f_0.webp",
        "variant_6902e6f9d2fd1_0.webp", "variant_6902eba45bd69_0.webp", "variant_69031fe530b80_0.webp",
        "variant_69031fe53b9d9_1.webp", "variant_69031fe544ce7_2.webp", "variant_69031fe55ae68_0.webp",
        "variant_69031fe563f1f_1.webp", "variant_69031fe56d523_2.webp", "variant_69031fe578027_0.webp",
        "variant_69031fe58009a_1.webp", "variant_69031fe583d0a_2.webp", "variant_69031fe58a6f3_3.webp",
        "variant_69031fe59a6a3_0.webp", "variant_69031fe5a3ed3_1.webp", "variant_69031fe5ad104_2.webp",
        "variant_69031fe5b625a_3.webp", "variant_69031fe5bee4e_4.webp", "variant_69031fe5c8cde_5.webp",
        "variant_69031fe5da70e_0.webp", "variant_69031fe5e39e9_1.webp", "variant_69031fe5ed204_2.webp",
        "variant_69031fe6021ae_3.webp", "variant_69031fe60a53c_4.webp", "variant_69031fe62229e_0.webp",
        "variant_69031fe62bd58_1.webp", "variant_69031fe6372d0_0.webp", "variant_69031fe64056a_1.webp",
        "variant_69031fe64bbeb_2.webp", "variant_69031fe654739_3.webp", "variant_69031fe65dde7_4.webp",
        "variant_69031fe667054_5.webp"
    ];

    public function __construct()
    {
        $lastId = DB::table('products')->max('id');
        $this->startingProductId = $lastId ? $lastId + 1 : 1;
        $this->cachedNormalImages = $this->normalImages;
        $this->cachedVariantImages = $this->variantImagesList;
    }

    public function run(): void
    {
        $this->startTime = microtime(true);
        $this->logInfo('=== STARTING OPTIMIZED VARIANT SEEDER ===');
        $this->logInfo("Mode: " . ($this->enableTruncate ? 'FRESH' : 'INCREMENTAL'));
        $this->logInfo("Target: {$this->targetProducts} products ({$this->variantProducts} with variants)");
        $this->logInfo("Starting Product ID: {$this->startingProductId}");

        try {
            $this->initializeSeeding();
            $this->seedVariantTypesAndOptions();
            $this->seedProducts();
            $this->logResults();
        } catch (Exception $e) {
            $this->handleSeederException($e);
        }
    }

    /**
     * Initialize seeding based on mode
     */
    protected function initializeSeeding(): void
    {
        if ($this->enableTruncate) {
            $this->handleFreshMode();
        } else {
            $this->handleIncrementalMode();
        }
    }

    /**
     * Handle fresh mode - truncate and reseed
     */
    protected function handleFreshMode(): void
    {
        $this->logInfo('Truncating tables...');

        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $tables = [
            'variant_images',
            'product_variant_option_assignments',
            'product_variants',
            'product_variant_options',
            'product_variant_types',
            'product_variant_type_selections',
            'product_images',
            'products',
            'brand_category',
            'category_filter',
            'categories',
            'filters',
            'brands'
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

    /**
     * Handle incremental mode - continue from existing data
     */
    protected function handleIncrementalMode(): void
    {
        $maxProductId = DB::table('products')->max('id') ?? ($this->startingProductId - 1);
        $this->currentProductId = max($maxProductId + 1, $this->startingProductId);
        $this->totalProductsSeeded = DB::table('products')->count();
        $this->totalVariantsSeeded = DB::table('product_variants')->count();

        $this->logInfo("Current products: {$this->totalProductsSeeded}");
        $this->logInfo("Current variants: {$this->totalVariantsSeeded}");
        $this->logInfo("Next product ID: {$this->currentProductId}");

        $this->verifyBasicData();
    }

    /**
     * Seed basic data (categories, brands, filters)
     */
    protected function seedBasicData(): void
    {
        $this->logInfo('Seeding basic data...');

        $filters = $this->createFilters();
        $categories = $this->createCategories();
        $brands = $this->createBrands();

        $this->attachRelationships($categories, $filters, $brands);

        $this->logInfo('Basic data seeded successfully');
    }

    /**
     * Create filters
     */
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

    /**
     * Create category hierarchy
     */
    protected function createCategories()
    {
        if (DB::table('categories')->count() > 0) {
            return collect(DB::table('categories')->get())->keyBy('slug');
        }

        $now = Carbon::now();

        // Root categories
        $roots = [
            ['title' => 'Electronics', 'slug' => 'electronics', 'summary' => 'Latest electronics'],
            ['title' => 'Fashion', 'slug' => 'fashion', 'summary' => 'Trendy fashion'],
            ['title' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'summary' => 'Home essentials'],
            ['title' => 'Sports & Fitness', 'slug' => 'sports-fitness', 'summary' => 'Sports equipment'],
            ['title' => 'Beauty & Personal Care', 'slug' => 'beauty-personal-care', 'summary' => 'Beauty products'],
        ];

        $rootData = [];
        foreach ($roots as $idx => $cat) {
            $code = $this->generateUniqueCode($cat['title']);
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

        // Level 1 categories
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
                $code = $this->generateUniqueCode($cat['title']);
                $level1Data[] = [
                    'title' => $cat['title'],
                    'slug' => $cat['slug'],
                    'summary' => $cat['title'],
                    'photo' => null,
                    'parent_id' => $parent?->id,
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

        // Level 2 categories
        $level2Map = [
            'mobiles' => [['title' => 'Smartphones', 'slug' => 'smartphones']],
            'men' => [['title' => 'Shoes', 'slug' => 'shoes']],
        ];

        $level2Data = [];
        foreach ($level2Map as $parentSlug => $children) {
            $parent = $level1Cats[$parentSlug];
            foreach ($children as $cat) {
                $code = $this->generateUniqueCode($cat['title']);
                $level2Data[] = [
                    'title' => $cat['title'],
                    'slug' => $cat['slug'],
                    'summary' => $cat['title'],
                    'photo' => null,
                    'parent_id' => $parent?->id,
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

    /**
     * Create brands
     */
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

    /**
     * Attach category-filter and brand-category relationships
     */
    protected function attachRelationships($categories, $filters, $brands): void
    {
        // Category-Filter relationships
        if (DB::table('category_filter')->count() == 0) {
            $cfData = [];
            foreach ($categories as $cat) {
                foreach ($filters as $filter) {
                    $cfData[] = ['category_id' => $cat->id, 'filter_id' => $filter->id];
                }
            }
            if (!empty($cfData)) {
                DB::table('category_filter')->insert($cfData);
            }
        }

        // Brand-Category relationships
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
                $brand = $brands[$brandSlug] ?? null;
                if (!$brand) continue;

                foreach ($categorySlugs as $catSlug) {
                    $category = $categories[$catSlug] ?? null;
                    if ($category) {
                        $bcData[] = ['brand_id' => $brand->id, 'category_id' => $category->id];
                    }
                }
            }

            if (!empty($bcData)) {
                DB::table('brand_category')->insert($bcData);
            }
        }
    }

    /**
     * Verify basic data exists
     */
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

    /**
     * Seed variant types and options
     */
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

    /**
     * Cache variant options for faster access
     */
    protected function cacheVariantOptions(): void
    {
        $types = DB::table('product_variant_types')->get()->keyBy('name');
        $options = DB::table('product_variant_options')->get()->groupBy('variant_type_id');

        foreach ($types as $name => $type) {
            $typeOptions = $options[$type->id] ?? collect();
            $this->cachedVariantOptions[$name] = [
                'type_id' => $type->id,
                'options' => $typeOptions->keyBy('value')->map(fn($o) => $o->id)->toArray(),
            ];
        }
    }

    /**
     * Main product seeding logic
     */
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

        // Seed variant products first
        $this->seedVariantProducts($categories, $brands);

        // Seed non-variant products
        $this->seedNonVariantProducts($categories, $brands);

        $this->logInfo('Product seeding completed');
    }

    /**
     * Seed products with variants
     */
    protected function seedVariantProducts($categories, $brands): void
    {
        $variantCategories = ['smartphones', 'laptops', 'audio', 'shoes', 'women', 'kids', 'furniture', 'makeup'];
        $variantCats = array_intersect_key($categories, array_flip($variantCategories));
        
        $targetVariantProducts = min($this->variantProducts, $this->targetProducts - $this->totalProductsSeeded);
        $perCategory = (int)ceil($targetVariantProducts / count($variantCats));

        foreach ($variantCats as $slug => $catData) {
            if ($this->totalProductsSeeded >= $this->targetProducts) break;
            
            $targetCount = min($perCategory, $this->targetProducts - $this->totalProductsSeeded);
            $this->seedCategoryProducts($slug, $catData, $targetCount, $brands, true);

            $this->updateCategoryProductCount($catData['id']);
        }
    }

    /**
     * Seed products without variants
     */
    protected function seedNonVariantProducts($categories, $brands): void
    {
        $allCategories = array_keys($this->productTemplates);
        $targetNonVariant = $this->targetProducts - $this->variantProducts;
        $perCategory = (int)ceil($targetNonVariant / count($allCategories));

        foreach ($allCategories as $slug) {
            if ($this->totalProductsSeeded >= $this->targetProducts) break;

            $catData = $categories[$slug] ?? null;
            if (!$catData) continue;

            $targetCount = min($perCategory, $this->targetProducts - $this->totalProductsSeeded);
            $this->seedCategoryProducts($slug, $catData, $targetCount, $brands, false);

            $this->updateCategoryProductCount($catData['id']);
        }
    }

    /**
     * Seed products for a specific category
     */
    protected function seedCategoryProducts(
        string $slug,
        array $catData,
        int $targetCount,
        $brands,
        bool $hasVariants
    ): void {
        $this->logInfo("Seeding {$slug}: " . ($hasVariants ? 'with' : 'without') . " variants");

        $template = $this->productTemplates[$slug];
        $categoryBrands = $this->getCategoryBrands($slug, $brands);
        $variantTypes = $hasVariants ? ($this->categoryVariantTypes[$slug] ?? []) : [];

        $batches = (int)ceil($targetCount / $this->batchSize);

        for ($b = 0; $b < $batches; $b++) {
            $batchSize = min($this->batchSize, $targetCount - ($b * $this->batchSize));
            if ($batchSize <= 0) break;

            DB::beginTransaction();
            try {
                $this->seedProductBatch(
                    $slug,
                    $catData,
                    $template,
                    $categoryBrands,
                    $variantTypes,
                    $batchSize,
                    $b,
                    $hasVariants
                );

                $this->logProgress($b + 1, $batches, $slug);
                DB::commit();

                if ($this->totalProductsSeeded >= $this->targetProducts) break;
            } catch (Exception $e) {
                DB::rollBack();
                $this->handleBatchException($e, $batchSize, $slug, $b);
            }
        }
    }

    /**
     * Seed a batch of products
     */
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

            // Generate product data
            $productEntry = $this->buildProductEntry(
                $productId,
                $globalIndex,
                $template,
                $categoryBrands,
                $catData,
                $slug,
                $hasVariants,
                $now
            );

            // Always add product-level images (min 3 as requested)
            $images = $this->getRandomNormalImages(3);
            foreach ($images as $idx => $imageName) {
                $productImagesData[] = $this->buildImageEntry(
                    $productId,
                    $imageName,
                    $idx,
                    $now
                );
            }

            if (!$hasVariants) {
                // Simple product - no additional variant logic
            } else {
                // Variant product - create type selections and variants
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

                foreach ($combinations as $combo) {
                    $variantId = $this->totalVariantsSeeded + $variantCounter + 1;
                    $variantCounter++;

                    // Build variant data
                    $variantEntry = $this->buildVariantEntry(
                        $variantId,
                        $productId,
                        $template,
                        $combo,
                        $globalIndex,
                        $slug,
                        $now
                    );
                    $variantsData[] = $variantEntry;

                    // Build option assignments
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

                    // Build variant-specific images (min 3 as requested)
                    $variantImages = $this->getRandomVariantImages(3);
                    foreach ($variantImages as $idx => $imageName) {
                        $variantImagesData[] = $this->buildVariantImageEntry(
                            $variantId,
                            $imageName,
                            $idx,
                            $now
                        );
                    }
                }
            }

            $productsData[] = $productEntry;
        }

        // Insert all data
        $this->insertBatchData(
            $productsData,
            $variantsData,
            $assignmentsData,
            $variantImagesData,
            $productImagesData,
            $typeSelectionsData
        );

        // Update counters
        $this->totalProductsSeeded += $batchSize;
        $this->totalVariantsSeeded += $variantCounter;
        $this->currentProductId += $batchSize;
    }

    /**
     * Build product entry data
     */
    protected function buildProductEntry(
        int $productId,
        int $globalIndex,
        array $template,
        array $categoryBrands,
        array $catData,
        string $slug,
        bool $hasVariants,
        Carbon $now
    ): array {
        $nameIdx = $globalIndex % count($template['names']);
        $summaryIdx = $globalIndex % count($template['summaries']);
        $brandIdx = $globalIndex % count($categoryBrands);

        $title = $template['names'][$nameIdx] . ' ' . ($globalIndex + 1);
        $baseSlug = Str::slug($title) . '-' . $slug;
        $uniqueSlug = $this->ensureUniqueSlug($baseSlug);

        $entry = [
            'id' => $productId,
            'title' => $title,
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
            // Add base price/stock for non-variant products (in dollars)
            $price = random_int($template['price_range'][0], $template['price_range'][1]);
            $discount = random_int(0, 50);
            $stock = random_int(10, 100);
            $sku = $this->generateSKU($template['names'][$nameIdx], [], $globalIndex, $productId, $slug);

            $entry['base_price'] = $price;
            $entry['base_discount'] = $discount > 0 ? $discount : null;
            $entry['base_stock'] = $stock;
            $entry['base_sku'] = $sku;
        } else {
            $entry['base_price'] = null;
            $entry['base_discount'] = null;
            $entry['base_stock'] = null;
            $entry['base_sku'] = null;
        }

        return $entry;
    }

    /**
     * Build variant entry data
     */
    protected function buildVariantEntry(
        int $variantId,
        int $productId,
        array $template,
        array $combo,
        int $globalIndex,
        string $slug,
        Carbon $now
    ): array {
        $price = random_int($template['price_range'][0], $template['price_range'][1]);
        $discount = random_int(0, 50);
        $stock = random_int(10, 100);
        $sku = $this->generateSKU($template['names'][0], $combo, $globalIndex, $variantId, $slug);

        return [
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
    }

    /**
     * Build product image entry
     */
    protected function buildImageEntry(
        int $productId,
        string $imageName,
        int $index,
        Carbon $now
    ): array {
        return [
            'product_id' => $productId,
            'image_path' => 'products/' . $imageName,
            'thumbnail_path' => null,
            'is_primary' => $index === 0,
            'sort_order' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Build variant image entry
     */
    protected function buildVariantImageEntry(
        int $variantId,
        string $imageName,
        int $index,
        Carbon $now
    ): array {
        return [
            'product_variant_id' => $variantId,
            'image_path' => 'products/variants/' . $imageName,
            'thumbnail_path' => null,
            'is_primary' => $index === 0,
            'sort_order' => $index,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * Insert batch data into database
     */
    protected function insertBatchData(
        array $productsData,
        array $variantsData,
        array $assignmentsData,
        array $variantImagesData,
        array $productImagesData,
        array $typeSelectionsData
    ): void {
        // Insert products
        foreach (array_chunk($productsData, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        // Insert type selections
        if (!empty($typeSelectionsData)) {
            foreach (array_chunk($typeSelectionsData, 500) as $chunk) {
                DB::table('product_variant_type_selections')->insert($chunk);
            }
        }

        // Insert variants
        if (!empty($variantsData)) {
            foreach (array_chunk($variantsData, 500) as $chunk) {
                DB::table('product_variants')->insert($chunk);
            }
        }

        // Insert option assignments
        if (!empty($assignmentsData)) {
            foreach (array_chunk($assignmentsData, 500) as $chunk) {
                DB::table('product_variant_option_assignments')->insert($chunk);
            }
        }

        // Insert variant images
        if (!empty($variantImagesData)) {
            foreach (array_chunk($variantImagesData, 500) as $chunk) {
                DB::table('variant_images')->insert($chunk);
            }
        }

        // Insert product images
        if (!empty($productImagesData)) {
            foreach (array_chunk($productImagesData, 500) as $chunk) {
                DB::table('product_images')->insert($chunk);
            }
        }
    }

    /**
     * Get leaf categories with their top parent
     */
    protected function getLeafCategories(): array
    {
        $leafSlugs = array_keys($this->productTemplates);
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

    /**
     * Get top parent category ID
     */
    protected function getTopParentId(int $catId, $categories): int
    {
        while (isset($categories[$catId]) && $categories[$catId]->parent_id) {
            $catId = $categories[$catId]->parent_id;
        }
        return $catId;
    }

    /**
     * Get brands for a category
     */
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
        return collect($brandSlugs)
            ->map(fn($s) => $brands[$s]->id ?? null)
            ->filter()
            ->toArray();
    }

    /**
     * Generate variant combinations
     */
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

    /**
     * Build combinations recursively
     */
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

    /**
     * Generate SKU
     */
    protected function generateSKU(
        string $productName,
        array $combo,
        int $index,
        int $id,
        string $category
    ): string {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $productName), 0, 3));
        $comboStr = empty($combo) 
            ? 'STD' 
            : implode('-', array_map(fn($v) => strtoupper(substr($v, 0, 2)), $combo));
        $categoryCode = strtoupper(substr($category, 0, 3));
        
        return "{$base}-{$categoryCode}-{$comboStr}-{$id}";
    }

    /**
     * Get random normal images
     */
    protected function getRandomNormalImages(int $count = 3): array
    {
        $count = min($count, count($this->cachedNormalImages));
        return collect($this->cachedNormalImages)->random($count)->values()->toArray();
    }

    /**
     * Get random variant images
     */
    protected function getRandomVariantImages(int $count = 3): array
    {
        $count = min($count, count($this->cachedVariantImages));
        return collect($this->cachedVariantImages)->random($count)->values()->toArray();
    }

    /**
     * Generate unique code for category
     */
    protected function generateUniqueCode(string $title): string
    {
        $base = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($title, 0, 3)));

        if (strlen($base) < 3) {
            $base = str_pad($base, 3, 'X');
        }

        $code = $base;
        $counter = 1;

        while (in_array($code, $this->existingCodes)) {
            $code = $base . $counter;
            $counter++;
            if ($counter > 999) {
                $code = $base . '_' . time();
                break;
            }
        }

        $this->existingCodes[] = $code;
        return $code;
    }

    /**
     * Ensure unique slug
     */
    protected function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (DB::table('products')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Update category product count
     */
    protected function updateCategoryProductCount(int $categoryId): void
    {
        $count = DB::table('products')->where('child_cat_id', $categoryId)->count();
        DB::table('categories')->where('id', $categoryId)->update(['products_count' => $count]);
    }

    /**
     * Log progress
     */
    protected function logProgress(int $currentBatch, int $totalBatches, string $category): void
    {
        $progress = round(($this->totalProductsSeeded / $this->targetProducts) * 100, 2);
        $this->logInfo(sprintf(
            "Progress: %s/%s (%s%%) - Batch %d/%d - %s",
            number_format($this->totalProductsSeeded),
            number_format($this->targetProducts),
            $progress,
            $currentBatch,
            $totalBatches,
            $category
        ));
    }

    /**
     * Log results
     */
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

    /**
     * Validate seeded data
     */
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

        foreach ($counts as $key => $value) {
            $this->logInfo(ucfirst(str_replace('_', ' ', $key)) . ": " . number_format($value));
        }

        // Check for orphaned records
        $this->checkOrphanedRecords();

        // Check for duplicate SKUs
        $this->checkDuplicateSKUs();

        $this->logInfo('=== VALIDATION COMPLETED ===');
    }

    /**
     * Check for orphaned records
     */
    protected function checkOrphanedRecords(): void
    {
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
    }

    /**
     * Check for duplicate SKUs
     */
    protected function checkDuplicateSKUs(): void
    {
        $duplicateVariantSKUs = DB::table('product_variants')
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

        if ($duplicateVariantSKUs->isNotEmpty()) {
            $this->logWarning("Found {$duplicateVariantSKUs->count()} duplicate variant SKUs");
        }

        if ($duplicateBaseSKUs->isNotEmpty()) {
            $this->logWarning("Found {$duplicateBaseSKUs->count()} duplicate base SKUs");
        }
    }

    /**
     * Handle seeder exception
     */
    protected function handleSeederException(Exception $e): void
    {
        try {
            DB::rollBack();
        } catch (Exception $_) {
            // Ignore rollback errors
        }

        $this->logError("SEEDING FAILED: " . $e->getMessage());
        $this->logError("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }

    /**
     * Handle batch exception
     */
    protected function handleBatchException(Exception $e, int $batchSize, string $slug, int $batchIndex): void
    {
        $this->currentProductId += $batchSize;
        $this->logError("Batch {$batchIndex} failed for {$slug}: " . $e->getMessage());
        $this->logWarning("Skipping {$batchSize} product IDs. Next product ID: {$this->currentProductId}");
    }

    /**
     * Logging methods
     */
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

    /**
     * Cleanup orphaned records
     */
    public function cleanup(): void
    {
        $this->logInfo('Starting cleanup...');

        DB::beginTransaction();

        try {
            $tables = [
                ['table' => 'product_images', 'join' => 'products', 'fk' => 'product_id'],
                ['table' => 'variant_images', 'join' => 'product_variants', 'fk' => 'product_variant_id'],
                ['table' => 'product_variant_option_assignments', 'join' => 'product_variants', 'fk' => 'product_variant_id'],
                ['table' => 'product_variants', 'join' => 'products', 'fk' => 'product_id'],
            ];

            foreach ($tables as $config) {
                $deleted = DB::table($config['table'] . ' as t')
                    ->leftJoin($config['join'] . ' as j', 't.' . $config['fk'], '=', 'j.id')
                    ->whereNull('j.id')
                    ->delete();

                if ($deleted > 0) {
                    $this->logInfo("Deleted {$deleted} orphaned records from {$config['table']}");
                }
            }

            DB::commit();
            $this->logInfo('Cleanup completed');
        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Cleanup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get seeding statistics
     */
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