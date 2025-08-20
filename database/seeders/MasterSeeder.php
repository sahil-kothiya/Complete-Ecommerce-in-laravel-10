<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

class MasterSeeder extends Seeder
{
    public function run()
    {
        DB::statement('TRUNCATE TABLE brand_category RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE categories RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE brands RESTART IDENTITY CASCADE');

        /** --------------------------
         *  Categories (Amazon-Style) - Root Level
         * -------------------------*/
        $electronics = Category::create([
            'title' => 'Electronics',
            'slug' => 'electronics',
            'summary' => 'Latest electronics including mobiles, laptops, and audio devices',
            'photo' => '/storage/photos/1/Category/mini-banner1.webp',
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'sort_order' => 1,
            'has_children' => true,
            'children_count' => 0, // Will be updated after creating children
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => true,
            'seo_title' => 'Electronics - Latest Gadgets & Devices',
            'seo_description' => 'Shop latest electronics including smartphones, laptops, audio devices and more.',
            'added_by' => 1
        ]);

        $fashion = Category::create([
            'title' => 'Fashion',
            'slug' => 'fashion',
            'summary' => 'Trendy fashion for men, women, and kids',
            'photo' => '/storage/photos/1/Category/mini-banner2.webp',
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'sort_order' => 2,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => true,
            'seo_title' => 'Fashion - Clothing & Accessories',
            'seo_description' => 'Discover latest fashion trends for men, women, and kids.',
            'added_by' => 1
        ]);

        $home = Category::create([
            'title' => 'Home & Kitchen',
            'slug' => 'home-kitchen',
            'summary' => 'Home essentials, furniture, and kitchen appliances',
            'photo' => '/storage/photos/1/Category/mini-banner3.webp',
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'sort_order' => 3,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => true,
            'seo_title' => 'Home & Kitchen - Furniture & Appliances',
            'seo_description' => 'Transform your home with our furniture and kitchen appliances.',
            'added_by' => 1
        ]);

        $sports = Category::create([
            'title' => 'Sports & Fitness',
            'slug' => 'sports-fitness',
            'summary' => 'Sports equipment and fitness gear for active lifestyle',
            'photo' => '/storage/photos/1/Category/mini-banner2.webp',
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'sort_order' => 4,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Sports & Fitness - Equipment & Gear',
            'seo_description' => 'Get fit with our range of sports and fitness equipment.',
            'added_by' => 1
        ]);

        $beauty = Category::create([
            'title' => 'Beauty & Personal Care',
            'slug' => 'beauty-personal-care',
            'summary' => 'Beauty products, skincare, and personal care essentials',
            'photo' => '/storage/photos/1/Category/mini-banner2.webp',
            'parent_id' => null,
            'level' => 0,
            'path' => null,
            'sort_order' => 5,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Beauty & Personal Care Products',
            'seo_description' => 'Discover beauty and personal care products for your daily routine.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Electronics Subcategories - Level 1
         * -------------------------*/
        $mobiles = Category::create([
            'title' => 'Mobiles & Accessories',
            'slug' => 'mobiles',
            'summary' => 'Smartphones and mobile accessories',
            'photo' => null,
            'parent_id' => $electronics->id,
            'level' => 1,
            'path' => (string)$electronics->id,
            'sort_order' => 1,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Mobile Phones & Accessories',
            'seo_description' => 'Latest smartphones and mobile accessories.',
            'added_by' => 1
        ]);

        $laptops = Category::create([
            'title' => 'Laptops & Accessories',
            'slug' => 'laptops',
            'summary' => 'Laptops, notebooks, and computer accessories',
            'photo' => null,
            'parent_id' => $electronics->id,
            'level' => 1,
            'path' => (string)$electronics->id,
            'sort_order' => 2,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Laptops & Computer Accessories',
            'seo_description' => 'Find the perfect laptop for work, gaming, or study.',
            'added_by' => 1
        ]);

        $audio = Category::create([
            'title' => 'Audio',
            'slug' => 'audio',
            'summary' => 'Headphones, speakers, and audio equipment',
            'photo' => null,
            'parent_id' => $electronics->id,
            'level' => 1,
            'path' => (string)$electronics->id,
            'sort_order' => 3,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Audio Equipment - Headphones & Speakers',
            'seo_description' => 'Premium audio equipment for music lovers.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Smartphones - Level 2 (under Mobiles)
         * -------------------------*/
        $smartphones = Category::create([
            'title' => 'Smartphones',
            'slug' => 'smartphones',
            'summary' => 'Latest smartphones from top brands',
            'photo' => null,
            'parent_id' => $mobiles->id,
            'level' => 2,
            'path' => $electronics->id . '/' . $mobiles->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => true,
            'seo_title' => 'Latest Smartphones',
            'seo_description' => 'Shop latest smartphones with best features and prices.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Fashion Subcategories - Level 1
         * -------------------------*/
        $men = Category::create([
            'title' => 'Men',
            'slug' => 'men',
            'summary' => 'Men\'s fashion and accessories',
            'photo' => null,
            'parent_id' => $fashion->id,
            'level' => 1,
            'path' => (string)$fashion->id,
            'sort_order' => 1,
            'has_children' => true,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Men\'s Fashion & Clothing',
            'seo_description' => 'Stylish clothing and accessories for men.',
            'added_by' => 1
        ]);

        $women = Category::create([
            'title' => 'Women',
            'slug' => 'women',
            'summary' => 'Women\'s fashion and accessories',
            'photo' => null,
            'parent_id' => $fashion->id,
            'level' => 1,
            'path' => (string)$fashion->id,
            'sort_order' => 2,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Women\'s Fashion & Clothing',
            'seo_description' => 'Trendy clothing and accessories for women.',
            'added_by' => 1
        ]);

        $kids = Category::create([
            'title' => 'Kids',
            'slug' => 'kids',
            'summary' => 'Kids\' fashion and accessories',
            'photo' => null,
            'parent_id' => $fashion->id,
            'level' => 1,
            'path' => (string)$fashion->id,
            'sort_order' => 3,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Kids Fashion & Clothing',
            'seo_description' => 'Comfortable and stylish clothing for kids.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Shoes - Level 2 (under Men)
         * -------------------------*/
        $shoes = Category::create([
            'title' => 'Shoes',
            'slug' => 'shoes',
            'summary' => 'Men\'s shoes for all occasions',
            'photo' => null,
            'parent_id' => $men->id,
            'level' => 2,
            'path' => $fashion->id . '/' . $men->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Men\'s Shoes',
            'seo_description' => 'Comfortable and stylish shoes for men.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Home Subcategories - Level 1
         * -------------------------*/
        $furniture = Category::create([
            'title' => 'Furniture',
            'slug' => 'furniture',
            'summary' => 'Home and office furniture',
            'photo' => null,
            'parent_id' => $home->id,
            'level' => 1,
            'path' => (string)$home->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Home & Office Furniture',
            'seo_description' => 'Quality furniture for your home and office.',
            'added_by' => 1
        ]);

        $kitchenAppliances = Category::create([
            'title' => 'Kitchen Appliances',
            'slug' => 'kitchen-appliances',
            'summary' => 'Kitchen appliances and cookware',
            'photo' => null,
            'parent_id' => $home->id,
            'level' => 1,
            'path' => (string)$home->id,
            'sort_order' => 2,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Kitchen Appliances & Cookware',
            'seo_description' => 'Modern kitchen appliances for efficient cooking.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Sports Subcategories - Level 1
         * -------------------------*/
        $gym = Category::create([
            'title' => 'Gym Equipment',
            'slug' => 'gym-equipment',
            'summary' => 'Home gym and fitness equipment',
            'photo' => null,
            'parent_id' => $sports->id,
            'level' => 1,
            'path' => (string)$sports->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Gym & Fitness Equipment',
            'seo_description' => 'Build your home gym with our fitness equipment.',
            'added_by' => 1
        ]);

        $outdoor = Category::create([
            'title' => 'Outdoor',
            'slug' => 'outdoor',
            'summary' => 'Outdoor sports and adventure gear',
            'photo' => null,
            'parent_id' => $sports->id,
            'level' => 1,
            'path' => (string)$sports->id,
            'sort_order' => 2,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Outdoor Sports & Adventure Gear',
            'seo_description' => 'Gear up for outdoor adventures and sports.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Beauty Subcategories - Level 1
         * -------------------------*/
        $skinCare = Category::create([
            'title' => 'Skin Care',
            'slug' => 'skin-care',
            'summary' => 'Skincare products for all skin types',
            'photo' => null,
            'parent_id' => $beauty->id,
            'level' => 1,
            'path' => (string)$beauty->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Skin Care Products',
            'seo_description' => 'Premium skincare products for healthy skin.',
            'added_by' => 1
        ]);

        $makeup = Category::create([
            'title' => 'Makeup',
            'slug' => 'makeup',
            'summary' => 'Makeup and cosmetic products',
            'photo' => null,
            'parent_id' => $beauty->id,
            'level' => 1,
            'path' => (string)$beauty->id,
            'sort_order' => 2,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Makeup & Cosmetics',
            'seo_description' => 'Professional makeup and cosmetic products.',
            'added_by' => 1
        ]);

        /** --------------------------
         *  Update children_count for parent categories
         * -------------------------*/
        // Update Electronics children count
        $electronics->update(['children_count' => 3]); // mobiles, laptops, audio

        // Update Fashion children count
        $fashion->update(['children_count' => 3]); // men, women, kids

        // Update Home children count
        $home->update(['children_count' => 2]); // furniture, kitchen appliances

        // Update Sports children count
        $sports->update(['children_count' => 2]); // gym, outdoor

        // Update Beauty children count
        $beauty->update(['children_count' => 2]); // skincare, makeup

        // Update Mobiles children count
        $mobiles->update(['children_count' => 1]); // smartphones

        // Update Men children count
        $men->update(['children_count' => 1]); // shoes

        /** --------------------------
         *  Brands
         * -------------------------*/
        $apple = Brand::create([
            'title' => 'Apple',
            'slug' => 'apple',
            'status' => 'active'
        ]);
        $samsung = Brand::create([
            'title' => 'Samsung',
            'slug' => 'samsung',
            'status' => 'active'
        ]);
        $dell = Brand::create([
            'title' => 'Dell',
            'slug' => 'dell',
            'status' => 'active'
        ]);
        $sony = Brand::create([
            'title' => 'Sony',
            'slug' => 'sony',
            'status' => 'active'
        ]);
        $nike = Brand::create([
            'title' => 'Nike',
            'slug' => 'nike',
            'status' => 'active'
        ]);
        $adidas = Brand::create([
            'title' => 'Adidas',
            'slug' => 'adidas',
            'status' => 'active'
        ]);
        $ikea = Brand::create([
            'title' => 'Ikea',
            'slug' => 'ikea',
            'status' => 'active'
        ]);
        $prestige = Brand::create([
            'title' => 'Prestige',
            'slug' => 'prestige',
            'status' => 'active'
        ]);
        $decathlon = Brand::create([
            'title' => 'Decathlon',
            'slug' => 'decathlon',
            'status' => 'active'
        ]);
        $loreal = Brand::create([
            'title' => 'L\'Oreal',
            'slug' => 'loreal',
            'status' => 'active'
        ]);
        $lakme = Brand::create([
            'title' => 'Lakme',
            'slug' => 'lakme',
            'status' => 'active'
        ]);

        /** --------------------------
         *  Brand ↔ Category Mapping
         * -------------------------*/
        $apple->categories()->attach([$smartphones->id, $laptops->id]);
        $samsung->categories()->attach([$smartphones->id, $audio->id]);
        $dell->categories()->attach([$laptops->id]);
        $sony->categories()->attach([$audio->id]);

        $nike->categories()->attach([$shoes->id, $gym->id]);
        $adidas->categories()->attach([$shoes->id, $outdoor->id]);

        $ikea->categories()->attach([$furniture->id]);
        $prestige->categories()->attach([$kitchenAppliances->id]);

        $decathlon->categories()->attach([$gym->id, $outdoor->id]);

        $loreal->categories()->attach([$skinCare->id, $makeup->id]);
        $lakme->categories()->attach([$makeup->id]);

        $this->command->info('Categories and brands seeded successfully!');
    }
}