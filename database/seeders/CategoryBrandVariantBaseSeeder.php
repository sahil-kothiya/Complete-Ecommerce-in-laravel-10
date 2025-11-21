<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Category, Brand, and Variant Base Seeder
 *
 * Seeds all foundational data for the e-commerce system:
 * - Filters (price, brand, rating, discount, recently-viewed)
 * - Categories (with SEO data and codes)
 * - Brands
 * - Brand-Category mappings
 * - Filter-Category mappings
 * - Variant Types (color, size, storage, RAM)
 * - Variant Options
 *
 * This seeder is idempotent and can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class CategoryBrandVariantBaseSeeder extends Seeder
{
    /**
     * Tracking unique codes for categories/brands
     *
     * @var array<string>
     */
    private array $existingCodes = [];

    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->newLine();
        $this->command->info('╔════════════════════════════════════════════════════════════════╗');
        $this->command->info('║     CATEGORY, BRAND & VARIANT BASE SEEDER                     ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════╝');
        $this->command->newLine();

        DB::beginTransaction();

        try {
            $this->seedFilters();
            $this->seedCategories();
            $this->seedBrands();
            $this->assignBrandsToCategories();
            $this->assignFiltersToCategories();
            $this->seedVariantTypes();
            $this->seedVariantOptions();

            DB::commit();

            $this->command->newLine();
            $this->command->info('╔════════════════════════════════════════════════════════════════╗');
            $this->command->info('║            ✅ ALL BASE DATA SEEDED SUCCESSFULLY                ║');
            $this->command->info('╚════════════════════════════════════════════════════════════════╝');
            $this->command->newLine();
        } catch (Throwable $e) {
            DB::rollBack();

            $this->command->error('❌ Failed to seed base data: ' . $e->getMessage());
            Log::error('Base data seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Seed filter records
     *
     * Inserts predefined filters used for product filtering:
     * - Price Range
     * - Brands
     * - Customer Ratings
     * - Discounts
     * - Recently Viewed
     *
     * @return void
     */
    protected function seedFilters(): void
    {
        DB::statement('TRUNCATE TABLE filters RESTART IDENTITY CASCADE');

        $this->command->info('🔄 Seeding filters...');

        DB::table('filters')->insert([
            [
                'id' => 1,
                'name' => 'price',
                'title' => 'Price Range',
                'description' => 'Filter products by price range',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'brand',
                'title' => 'Brands',
                'description' => 'Filter products by brand',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'rating',
                'title' => 'Customer Ratings',
                'description' => 'Filter products by customer ratings',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'discount',
                'title' => 'Discounts',
                'description' => 'Filter products by discount percentage',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'recently-viewed',
                'title' => 'Recently Viewed',
                'description' => 'Recently Viewed',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('filters', 'id'), COALESCE((SELECT MAX(id) FROM filters), 0))");

        $this->command->info('✅ Filters seeded successfully!');
    }

    protected function seedCategories(): void
    {
        // Truncate categories and related pivot tables
        DB::statement('TRUNCATE TABLE brand_category RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE category_filter RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE categories RESTART IDENTITY CASCADE');

        $this->command->info('🔄 Seeding categories...');

        $categories = [
            // Parent categories
            [
                'id' => 1,
                'title' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'summary' => "Men's clothing and accessories",
                'photo' => 'categories/mens-fashion.webp',
                'parent_id' => null,
                'level' => 0,
                'path' => '1',
                'sort_order' => 1,
                'has_children' => true,
                'children_count' => 4,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => true,
                'seo_title' => "Men's Fashion - Latest Trends & Styles",
                'seo_description' => "Shop the latest men's fashion trends including shirts, jeans, jackets, and shoes. Quality clothing and accessories for modern men.",
            ],
            [
                'id' => 2,
                'title' => "Women's Fashion",
                'slug' => 'womens-fashion',
                'summary' => "Women's clothing and accessories",
                'photo' => 'categories/womens-fashion.webp',
                'parent_id' => null,
                'level' => 0,
                'path' => '2',
                'sort_order' => 2,
                'has_children' => true,
                'children_count' => 4,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => true,
                'seo_title' => "Women's Fashion - Trendy Clothing & Accessories",
                'seo_description' => "Discover stylish women's fashion including dresses, tops, jeans, and shoes. Latest trends and timeless classics for every occasion.",
            ],
            [
                'id' => 3,
                'title' => "Kids Fashion",
                'slug' => 'kids-fashion',
                'summary' => "Kids clothing and accessories",
                'photo' => 'categories/kids-fashion.webp',
                'parent_id' => null,
                'level' => 0,
                'path' => '3',
                'sort_order' => 3,
                'has_children' => true,
                'children_count' => 4,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Kids Fashion - Comfortable & Stylish Children's Clothing",
                'seo_description' => "Explore our kids fashion collection with clothing for boys and girls. Comfortable, durable, and stylish options for all ages.",
            ],
            [
                'id' => 4,
                'title' => 'Electronics',
                'slug' => 'electronics',
                'summary' => 'Electronic devices and gadgets',
                'photo' => 'categories/electronics.webp',
                'parent_id' => null,
                'level' => 0,
                'path' => '4',
                'sort_order' => 4,
                'has_children' => true,
                'children_count' => 3,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => true,
                'seo_title' => 'Electronics - Latest Gadgets & Technology',
                'seo_description' => 'Shop the latest electronics including smartphones, laptops, headphones, and more. Top brands and cutting-edge technology.',
            ],
            // Men's subcategories
            [
                'id' => 5,
                'title' => "Men's Shirts",
                'slug' => 'mens-shirts',
                'summary' => "Casual and formal shirts for men",
                'photo' => null,
                'parent_id' => 1,
                'level' => 1,
                'path' => '1/5',
                'sort_order' => 1,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Men's Shirts - Casual & Formal Styles",
                'seo_description' => "Browse our collection of men's shirts including formal dress shirts, casual button-downs, and trendy styles.",
            ],
            [
                'id' => 6,
                'title' => "Men's Jeans",
                'slug' => 'mens-jeans',
                'summary' => "Denim jeans for every style",
                'photo' => null,
                'parent_id' => 1,
                'level' => 1,
                'path' => '1/6',
                'sort_order' => 2,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Men's Jeans - Slim Fit, Straight & Relaxed",
                'seo_description' => "Shop premium quality men's jeans in various fits and washes. Find the perfect pair for any occasion.",
            ],
            [
                'id' => 7,
                'title' => "Men's Jackets",
                'slug' => 'mens-jackets',
                'summary' => "Jackets and outerwear for men",
                'photo' => null,
                'parent_id' => 1,
                'level' => 1,
                'path' => '1/7',
                'sort_order' => 3,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Men's Jackets - Leather, Denim & Winter Coats",
                'seo_description' => "Stay warm and stylish with our men's jacket collection. From leather to denim and winter coats.",
            ],
            [
                'id' => 8,
                'title' => "Men's Shoes",
                'slug' => 'mens-shoes',
                'summary' => "Footwear for every occasion",
                'photo' => null,
                'parent_id' => 1,
                'level' => 1,
                'path' => '1/8',
                'sort_order' => 4,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Men's Shoes - Sneakers, Formal & Casual Footwear",
                'seo_description' => "Discover comfortable and stylish men's shoes including sneakers, formal dress shoes, and casual footwear.",
            ],
            // Women's subcategories
            [
                'id' => 9,
                'title' => "Women's Dresses",
                'slug' => 'womens-dresses',
                'summary' => "Elegant dresses for women",
                'photo' => null,
                'parent_id' => 2,
                'level' => 1,
                'path' => '2/9',
                'sort_order' => 1,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Women's Dresses - Casual, Formal & Party Wear",
                'seo_description' => "Explore beautiful women's dresses for every occasion. From casual sundresses to elegant evening gowns.",
            ],
            [
                'id' => 10,
                'title' => "Women's Tops",
                'slug' => 'womens-tops',
                'summary' => "Tops and blouses for women",
                'photo' => null,
                'parent_id' => 2,
                'level' => 1,
                'path' => '2/10',
                'sort_order' => 2,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Women's Tops - Blouses, T-Shirts & Tank Tops",
                'seo_description' => "Shop trendy women's tops including blouses, t-shirts, tank tops, and more in various styles and colors.",
            ],
            [
                'id' => 11,
                'title' => "Women's Jeans",
                'slug' => 'womens-jeans',
                'summary' => "Stylish denim for women",
                'photo' => null,
                'parent_id' => 2,
                'level' => 1,
                'path' => '2/11',
                'sort_order' => 3,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Women's Jeans - Skinny, Boyfriend & High-Waisted",
                'seo_description' => "Find the perfect fit with our women's jeans collection. Skinny, boyfriend, high-waisted, and more styles.",
            ],
            [
                'id' => 12,
                'title' => "Women's Shoes",
                'slug' => 'womens-shoes',
                'summary' => "Footwear collection for women",
                'photo' => null,
                'parent_id' => 2,
                'level' => 1,
                'path' => '2/12',
                'sort_order' => 4,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Women's Shoes - Heels, Flats, Boots & Sneakers",
                'seo_description' => "Step out in style with our women's shoe collection. Heels, flats, boots, sneakers, and sandals.",
            ],
            // Kids subcategories
            [
                'id' => 13,
                'title' => 'Boys Clothing',
                'slug' => 'boys-clothing',
                'summary' => "Clothing for boys of all ages",
                'photo' => null,
                'parent_id' => 3,
                'level' => 1,
                'path' => '3/13',
                'sort_order' => 1,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Boys Clothing - Shirts, Pants, Shorts & More",
                'seo_description' => "Shop comfortable and stylish clothing for boys including shirts, pants, shorts, and jackets.",
            ],
            [
                'id' => 14,
                'title' => 'Girls Clothing',
                'slug' => 'girls-clothing',
                'summary' => "Clothing for girls of all ages",
                'photo' => null,
                'parent_id' => 3,
                'level' => 1,
                'path' => '3/14',
                'sort_order' => 2,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Girls Clothing - Dresses, Tops, Bottoms & More",
                'seo_description' => "Discover cute and comfortable girls clothing including dresses, tops, skirts, and pants.",
            ],
            [
                'id' => 15,
                'title' => 'Kids Shoes',
                'slug' => 'kids-shoes',
                'summary' => "Comfortable footwear for kids",
                'photo' => null,
                'parent_id' => 3,
                'level' => 1,
                'path' => '3/15',
                'sort_order' => 3,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Kids Shoes - Sneakers, Sandals & School Shoes",
                'seo_description' => "Quality footwear for kids including sneakers, sandals, school shoes, and boots.",
            ],
            [
                'id' => 16,
                'title' => 'Kids Accessories',
                'slug' => 'kids-accessories',
                'summary' => "Accessories for kids",
                'photo' => null,
                'parent_id' => 3,
                'level' => 1,
                'path' => '3/16',
                'sort_order' => 4,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => "Kids Accessories - Bags, Hats, Belts & More",
                'seo_description' => "Complete your kids outfits with our accessories collection including bags, hats, belts, and more.",
            ],
            // Electronics subcategories
            [
                'id' => 17,
                'title' => 'Smartphones',
                'slug' => 'smartphones',
                'summary' => "Latest smartphones and mobile phones",
                'photo' => null,
                'parent_id' => 4,
                'level' => 1,
                'path' => '4/17',
                'sort_order' => 1,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => 'Smartphones - Latest Models & Best Deals',
                'seo_description' => 'Shop the latest smartphones from top brands including Apple, Samsung, and more.',
            ],
            [
                'id' => 18,
                'title' => 'Laptops',
                'slug' => 'laptops',
                'summary' => "Powerful laptops for work and gaming",
                'photo' => null,
                'parent_id' => 4,
                'level' => 1,
                'path' => '4/18',
                'sort_order' => 2,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => 'Laptops - For Work, Gaming & Students',
                'seo_description' => 'Browse our laptop collection featuring the latest models for work, gaming, and education.',
            ],
            [
                'id' => 19,
                'title' => 'Headphones',
                'slug' => 'headphones',
                'summary' => "Headphones and earphones",
                'photo' => null,
                'parent_id' => 4,
                'level' => 1,
                'path' => '4/19',
                'sort_order' => 3,
                'has_children' => false,
                'children_count' => 0,
                'products_count' => 0,
                'status' => 'active',
                'is_featured' => false,
                'seo_title' => 'Headphones - Wireless, Noise Cancelling & Gaming',
                'seo_description' => 'High-quality headphones and earphones including wireless, noise-cancelling, and gaming headsets.',
            ],
        ];

        foreach ($categories as $category) {
            $code = $this->generateUniqueCode($category['title']);

            DB::table('categories')->insert([
                'id' => $category['id'],
                'title' => $category['title'],
                'slug' => $category['slug'],
                'code' => $code,
                'summary' => $category['summary'],
                'photo' => $category['photo'],
                'parent_id' => $category['parent_id'],
                'level' => $category['level'],
                'path' => $category['path'],
                'sort_order' => $category['sort_order'],
                'has_children' => $category['has_children'],
                'children_count' => $category['children_count'],
                'products_count' => $category['products_count'],
                'status' => $category['status'],
                'is_featured' => $category['is_featured'],
                'seo_title' => $category['seo_title'],
                'seo_description' => $category['seo_description'],
                'code_generated_at' => Carbon::now(),
                'code_locked' => false,
                'added_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('categories', 'id'), COALESCE((SELECT MAX(id) FROM categories), 0))");

        $this->command->info('✅ Categories seeded successfully!');
    }

    protected function seedBrands(): void
    {
        DB::statement('TRUNCATE TABLE brands RESTART IDENTITY CASCADE');

        $this->command->info('🔄 Seeding brands...');

        $brands = [
            ['title' => 'Nike', 'slug' => 'nike', 'status' => 'active'],
            ['title' => 'Adidas', 'slug' => 'adidas', 'status' => 'active'],
            ['title' => 'Puma', 'slug' => 'puma', 'status' => 'active'],
            ['title' => 'Zara', 'slug' => 'zara', 'status' => 'active'],
            ['title' => 'H&M', 'slug' => 'hm', 'status' => 'active'],
            ['title' => 'Samsung', 'slug' => 'samsung', 'status' => 'active'],
            ['title' => 'Apple', 'slug' => 'apple', 'status' => 'active'],
            ['title' => 'Sony', 'slug' => 'sony', 'status' => 'active'],
            ['title' => 'Dell', 'slug' => 'dell', 'status' => 'active'],
            ['title' => 'HP', 'slug' => 'hp', 'status' => 'active'],
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->insert([
                'title' => $brand['title'],
                'slug' => $brand['slug'],
                'status' => $brand['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('brands', 'id'), COALESCE((SELECT MAX(id) FROM brands), 0))");

        $this->command->info('✅ Brands seeded successfully!');
    }

    protected function assignBrandsToCategories(): void
    {
        $this->command->info('🔄 Assigning brands to categories...');

        // Get all brands
        $allBrands = DB::table('brands')->pluck('id')->toArray();

        // Get all parent categories (level = 0)
        $parentCategories = DB::table('categories')->where('parent_id', null)->pluck('id')->toArray();

        // Get all child categories (level > 0)
        $childCategories = DB::table('categories')->whereNotNull('parent_id')->get();

        // Assign ALL brands to parent categories
        foreach ($parentCategories as $parentId) {
            foreach ($allBrands as $brandId) {
                DB::table('brand_category')->insert([
                    'brand_id' => $brandId,
                    'category_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ All brands assigned to parent categories');

        // Assign random brands to child categories (2-5 brands per child)
        foreach ($childCategories as $child) {
            $numBrands = rand(2, 5);
            $randomBrands = array_rand(array_flip($allBrands), $numBrands);

            if (!is_array($randomBrands)) {
                $randomBrands = [$randomBrands];
            }

            foreach ($randomBrands as $brandId) {
                DB::table('brand_category')->insert([
                    'brand_id' => $brandId,
                    'category_id' => $child->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Random brands assigned to child categories');
    }

    protected function assignFiltersToCategories(): void
    {
        $this->command->info('🔄 Assigning filters to categories...');

        // Get all filters
        $allFilters = DB::table('filters')->pluck('id')->toArray();

        // Get all parent categories (level = 0)
        $parentCategories = DB::table('categories')->where('parent_id', null)->pluck('id')->toArray();

        // Get all child categories (level > 0)
        $childCategories = DB::table('categories')->whereNotNull('parent_id')->get();

        // Assign ALL filters to parent categories
        foreach ($parentCategories as $parentId) {
            foreach ($allFilters as $filterId) {
                DB::table('category_filter')->insert([
                    'filter_id' => $filterId,
                    'category_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ All filters assigned to parent categories');

        // Assign random filters to child categories (2-4 filters per child)
        foreach ($childCategories as $child) {
            $numFilters = rand(2, 4);
            $randomFilters = array_rand(array_flip($allFilters), $numFilters);

            if (!is_array($randomFilters)) {
                $randomFilters = [$randomFilters];
            }

            foreach ($randomFilters as $filterId) {
                DB::table('category_filter')->insert([
                    'filter_id' => $filterId,
                    'category_id' => $child->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('✅ Random filters assigned to child categories');
    }

    protected function seedVariantTypes(): void
    {
        DB::statement('TRUNCATE TABLE product_variant_type_selections RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE product_variant_options RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE product_variant_types RESTART IDENTITY CASCADE');

        $this->command->info('🔄 Seeding variant types...');

        DB::table('product_variant_types')->insert([
            [
                'id' => 1,
                'name' => 'color',
                'display_name' => 'Color',
                'sort_order' => 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'size',
                'display_name' => 'Size',
                'sort_order' => 2,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'storage',
                'display_name' => 'Storage',
                'sort_order' => 3,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'ram',
                'display_name' => 'RAM',
                'sort_order' => 4,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::statement("SELECT setval(pg_get_serial_sequence('product_variant_types', 'id'), COALESCE((SELECT MAX(id) FROM product_variant_types), 0))");

        $this->command->info('✅ Variant types seeded successfully!');
    }

    protected function seedVariantOptions(): void
    {
        // Note: product_variant_options already truncated in seedVariantTypes()
        $this->command->info('🔄 Seeding variant options...');

        $options = [
            // Colors (type_id = 1)
            ['variant_type_id' => 1, 'value' => 'red', 'display_value' => 'Red', 'hex_color' => '#FF0000', 'sort_order' => 1, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'blue', 'display_value' => 'Blue', 'hex_color' => '#0000FF', 'sort_order' => 2, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'green', 'display_value' => 'Green', 'hex_color' => '#00FF00', 'sort_order' => 3, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'black', 'display_value' => 'Black', 'hex_color' => '#000000', 'sort_order' => 4, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'white', 'display_value' => 'White', 'hex_color' => '#FFFFFF', 'sort_order' => 5, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'yellow', 'display_value' => 'Yellow', 'hex_color' => '#FFFF00', 'sort_order' => 6, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'orange', 'display_value' => 'Orange', 'hex_color' => '#FFA500', 'sort_order' => 7, 'status' => 'active'],
            ['variant_type_id' => 1, 'value' => 'purple', 'display_value' => 'Purple', 'hex_color' => '#800080', 'sort_order' => 8, 'status' => 'active'],

            // Sizes (type_id = 2)
            ['variant_type_id' => 2, 'value' => 'xs', 'display_value' => 'XS', 'hex_color' => null, 'sort_order' => 1, 'status' => 'active'],
            ['variant_type_id' => 2, 'value' => 's', 'display_value' => 'S', 'hex_color' => null, 'sort_order' => 2, 'status' => 'active'],
            ['variant_type_id' => 2, 'value' => 'm', 'display_value' => 'M', 'hex_color' => null, 'sort_order' => 3, 'status' => 'active'],
            ['variant_type_id' => 2, 'value' => 'l', 'display_value' => 'L', 'hex_color' => null, 'sort_order' => 4, 'status' => 'active'],
            ['variant_type_id' => 2, 'value' => 'xl', 'display_value' => 'XL', 'hex_color' => null, 'sort_order' => 5, 'status' => 'active'],
            ['variant_type_id' => 2, 'value' => 'xxl', 'display_value' => 'XXL', 'hex_color' => null, 'sort_order' => 6, 'status' => 'active'],

            // Storage (type_id = 3)
            ['variant_type_id' => 3, 'value' => '64gb', 'display_value' => '64GB', 'hex_color' => null, 'sort_order' => 1, 'status' => 'active'],
            ['variant_type_id' => 3, 'value' => '128gb', 'display_value' => '128GB', 'hex_color' => null, 'sort_order' => 2, 'status' => 'active'],
            ['variant_type_id' => 3, 'value' => '256gb', 'display_value' => '256GB', 'hex_color' => null, 'sort_order' => 3, 'status' => 'active'],
            ['variant_type_id' => 3, 'value' => '512gb', 'display_value' => '512GB', 'hex_color' => null, 'sort_order' => 4, 'status' => 'active'],
            ['variant_type_id' => 3, 'value' => '1tb', 'display_value' => '1TB', 'hex_color' => null, 'sort_order' => 5, 'status' => 'active'],

            // RAM (type_id = 4)
            ['variant_type_id' => 4, 'value' => '4gb', 'display_value' => '4GB', 'hex_color' => null, 'sort_order' => 1, 'status' => 'active'],
            ['variant_type_id' => 4, 'value' => '8gb', 'display_value' => '8GB', 'hex_color' => null, 'sort_order' => 2, 'status' => 'active'],
            ['variant_type_id' => 4, 'value' => '16gb', 'display_value' => '16GB', 'hex_color' => null, 'sort_order' => 3, 'status' => 'active'],
            ['variant_type_id' => 4, 'value' => '32gb', 'display_value' => '32GB', 'hex_color' => null, 'sort_order' => 4, 'status' => 'active'],
        ];

        foreach ($options as $option) {
            DB::table('product_variant_options')->insert([
                'variant_type_id' => $option['variant_type_id'],
                'value' => $option['value'],
                'display_value' => $option['display_value'],
                'hex_color' => $option['hex_color'],
                'sort_order' => $option['sort_order'],
                'status' => $option['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('product_variant_options', 'id'), COALESCE((SELECT MAX(id) FROM product_variant_options), 0))");

        $this->command->info('✅ Variant options seeded successfully!');
    }

    protected function generateUniqueCode(string $title): string
    {
        $baseCode = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', substr($title, 0, 3)));

        if (strlen($baseCode) < 3) {
            $baseCode = str_pad($baseCode, 3, 'X');
        }

        $code = $baseCode;
        $counter = 1;
        while (in_array($code, $this->existingCodes)) {
            $code = substr($baseCode, 0, 2) . $counter;
            $counter++;
        }

        $this->existingCodes[] = $code;
        return $code;
    }
}
