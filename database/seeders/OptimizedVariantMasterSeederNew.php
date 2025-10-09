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
    protected int $startingProductId;
    protected bool $enableTruncate = false;
    protected int $targetProducts = 10000;
    protected int $variantsPerProduct = 5;
    protected int $batchSize = 500; // Changed to 500 as per request
    protected int $imageBatchSize = 500;

    // Tracking
    protected int $totalProductsSeeded = 0;
    protected int $totalVariantsSeeded = 0;
    protected int $currentProductId = 0;
    protected float $startTime;

    // Cache
    protected array $cachedImages;
    protected array $cachedCategories = [];
    protected array $cachedBrands = [];
    protected array $cachedVariantOptions = [];

    // Product templates configuration
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
        'smartphones' => ['color', 'ram'],
        'laptops' => ['ram', 'screen_size'],
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

    protected array $images = [
        // Same image list as provided previously (omitted for brevity, but should include all 200+ image names)
        "00497a4a-3fc5-47c9-93ba-842393d35f46.webp",
        "00846c49-7137-4af6-8317-f1a7d578d8c2.webp",
        // ... (include all images from the original seeder)
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
                // Ignore rollback errors
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

        // Get inserted roots
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

        // Level 2 (leaf nodes)
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

        $perCategory = (int)ceil($remaining / count($categories));

        foreach ($categories as $slug => $catData) {
            if ($this->totalProductsSeeded >= $this->targetProducts) break;

            $this->seedCategoryProducts($slug, $catData, $perCategory, $brands);

            $actualCount = DB::table('products')->where('child_cat_id', $catData['id'])->count();
            DB::table('categories')->where('id', $catData['id'])->update(['products_count' => $actualCount]);
        }

        $this->logInfo('Product seeding completed');
    }

    protected function seedCategoryProducts(string $slug, array $catData, int $targetCount, $brands): void
    {
        $this->logInfo("Seeding category: {$slug}");

        $template = $this->productTemplates[$slug];
        $categoryBrands = $this->getCategoryBrands($slug, $brands);
        $variantTypes = $this->categoryVariantTypes[$slug] ?? [];

        $remaining = min($targetCount, $this->targetProducts - $this->totalProductsSeeded);
        $batches = (int)ceil($remaining / $this->batchSize);

        for ($b = 0; $b < $batches; $b++) {
            $batchSize = min($this->batchSize, $remaining - ($b * $this->batchSize));
            if ($batchSize <= 0) break;

            DB::beginTransaction();
            try {
                $this->seedProductBatch($slug, $catData, $template, $categoryBrands, $variantTypes, $batchSize, $b);

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
                $this->logWarning("Skipping {$batchSize} product IDs to avoid duplicates. Next product ID: {$this->currentProductId}");
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
        int $batchIndex
    ): void {
        $now = Carbon::now();

        $productsData = [];
        $variantsData = [];
        $assignmentsData = [];
        $imagesData = [];
        $typeSelectionsData = [];

        $variantCounter = 0;

        for ($i = 0; $i < $batchSize; $i++) {
            $productId = $this->currentProductId + $i;
            $globalIndex = $this->totalProductsSeeded + $i;

            // Generate unique slug
            $nameIdx = $globalIndex % count($template['names']);
            $summaryIdx = $globalIndex % count($template['summaries']);
            $brandIdx = $globalIndex % count($categoryBrands);
            $baseSlug = Str::slug($template['names'][$nameIdx]) . '-' . ($globalIndex + 1) . '-' . $slug;
            $slugSuffix = 1;
            $uniqueSlug = $baseSlug;
            while (DB::table('products')->where('slug', $uniqueSlug)->exists()) {
                $uniqueSlug = $baseSlug . '-' . $slugSuffix++;
            }

            $productsData[] = [
                'id' => $productId,
                'title' => $template['names'][$nameIdx] . ' ' . ($globalIndex + 1),
                'slug' => $uniqueSlug,
                'summary' => $template['summaries'][$summaryIdx],
                'description' => $template['description'],
                'condition' => 'new',
                'status' => 'active',
                'is_featured' => $globalIndex % 10 === 0,
                'cat_id' => $catData['top_id'],
                'child_cat_id' => $catData['id'],
                'brand_id' => $categoryBrands[$brandIdx],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Type selections
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

            // Generate variants (ensure at least one variant)
            $combinations = $this->generateVariantCombinations($variantTypes, $this->variantsPerProduct);
            if (empty($combinations) || (count($combinations) === 1 && empty($combinations[0]))) {
                $combinations = array_fill(0, max(1, $this->variantsPerProduct), []);
            }

            foreach ($combinations as $comboIndex => $combo) {
                $variantId = $this->totalVariantsSeeded + $variantCounter + 1;
                $variantCounter++;

                $price = random_int($template['price_range'][0], $template['price_range'][1]);
                $discount = random_int(0, 50);
                $stock = random_int(10, 100);

                // Ensure unique SKU by incorporating variant ID
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

                // Option assignments
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

                // Images (3 to 5 per variant)
                $imageCount = random_int(3, 5);
                $images = $this->getRandomImages($imageCount);
                foreach ($images as $idx => $imageName) {
                    $imagesData[] = [
                        'product_variant_id' => $variantId,
                        'image_path' => 'storage/photos/1/Products/' . $imageName,
                        'is_primary' => $idx === 0,
                        'sort_order' => $idx,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        // Insert in chunks of 500
        foreach (array_chunk($productsData, 500) as $chunk) {
            DB::table('products')->insert($chunk);
        }

        foreach (array_chunk($typeSelectionsData, 500) as $chunk) {
            DB::table('product_variant_type_selections')->insert($chunk);
        }

        foreach (array_chunk($variantsData, 500) as $chunk) {
            DB::table('product_variants')->insert($chunk);
        }

        foreach (array_chunk($assignmentsData, 500) as $chunk) {
            DB::table('product_variant_option_assignments')->insert($chunk);
        }

        foreach (array_chunk($imagesData, 500) as $chunk) {
            DB::table('variant_images')->insert($chunk);
        }

        // Update counters
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
            return [[]]; // Handled in seedProductBatch to ensure at least one variant
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

    protected function generateSKU(string $productName, array $combo, int $index, int $variantId, string $category): string
    {
        $base = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $productName), 0, 3));
        $comboStr = implode('-', array_map(fn($v) => strtoupper(substr($v, 0, 2)), $combo));
        if (empty($comboStr)) {
            $comboStr = 'V1';
        }
        $categoryCode = strtoupper(substr($category, 0, 3));
        // Use variantId to ensure SKU uniqueness
        return "{$base}-{$categoryCode}-{$comboStr}-{$variantId}";
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
            'images' => DB::table('variant_images')->count(),
            'assignments' => DB::table('product_variant_option_assignments')->count(),
        ];

        $this->logInfo("Products: " . number_format($counts['products']));
        $this->logInfo("Variants: " . number_format($counts['variants']));
        $this->logInfo("Images: " . number_format($counts['images']));
        $this->logInfo("Assignments: " . number_format($counts['assignments']));

        // Check for orphaned records
        $orphanedVariants = DB::table('product_variants as pv')
            ->leftJoin('products as p', 'pv.product_id', '=', 'p.id')
            ->whereNull('p.id')
            ->count();

        $orphanedImages = DB::table('variant_images as vi')
            ->leftJoin('product_variants as pv', 'vi.product_variant_id', '=', 'pv.id')
            ->whereNull('pv.id')
            ->count();

        if ($orphanedVariants > 0) {
            $this->logWarning("Found {$orphanedVariants} orphaned variants");
        }

        if ($orphanedImages > 0) {
            $this->logWarning("Found {$orphanedImages} orphaned images");
        }

        // Validate minimum requirements
        $expectedImages = $counts['variants'] * 3;
        if ($counts['images'] < $expectedImages) {
            $this->logWarning("Image count less than minimum expected: expected at least {$expectedImages}, got {$counts['images']}");
        }

        // Check each product has at least one variant
        $productsWithoutVariants = DB::table('products as p')
            ->leftJoin('product_variants as pv', 'p.id', '=', 'pv.product_id')
            ->whereNull('pv.id')
            ->count();

        if ($productsWithoutVariants > 0) {
            $this->logWarning("Found {$productsWithoutVariants} products without variants");
        }

        // Check for duplicate SKUs
        $duplicateSKUs = DB::table('product_variants')
            ->select('sku')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        if ($duplicateSKUs->isNotEmpty()) {
            $this->logWarning("Found duplicate SKUs: " . $duplicateSKUs->implode(', '));
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
            $deleted = DB::table('variant_images as vi')
                ->leftJoin('product_variants as pv', 'vi.product_variant_id', '=', 'pv.id')
                ->whereNull('pv.id')
                ->delete();

            if ($deleted > 0) {
                $this->logInfo("Deleted {$deleted} orphaned images");
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
            'images' => DB::table('variant_images')->count(),
            'categories' => DB::table('categories')->count(),
            'brands' => DB::table('brands')->count(),
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'target' => $this->targetProducts,
        ];
    }
}