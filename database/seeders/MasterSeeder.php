<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Filter;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MasterSeeder extends Seeder
{
    public function run()
    {
        DB::statement('TRUNCATE TABLE brand_category RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE category_filter RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE categories RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE filters RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE brands RESTART IDENTITY CASCADE');

        $existingCodes = [];

        /** --------------------------
         *  Filters
         * -------------------------*/
        $priceFilter = Filter::create([
            'name' => 'price',
            'title' => 'Price Range',
            'description' => 'Filter products by price range',
            'status' => 'active'
        ]);

        $brandFilter = Filter::create([
            'name' => 'brand',
            'title' => 'Brands',
            'description' => 'Filter products by brand',
            'status' => 'active'
        ]);

        $ratingFilter = Filter::create([
            'name' => 'rating',
            'title' => 'Customer Ratings',
            'description' => 'Filter products by customer ratings',
            'status' => 'active'
        ]);

        $discountFilter = Filter::create([
            'name' => 'discount',
            'title' => 'Discounts',
            'description' => 'Filter products by discount percentage',
            'status' => 'active'
        ]);

        $discountFilter = Filter::create([
            'name' => 'recently-viewed',
            'title' => 'Recently Viewed',
            'description' => 'Recently Viewed',
            'status' => 'active'
        ]);

        /** --------------------------
         *  Categories (Amazon-Style) - Root Level
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Electronics', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $electronics->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Fashion', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $fashion->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Home & Kitchen', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $home->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Sports & Fitness', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $sports->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Beauty & Personal Care', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $beauty->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Electronics Subcategories - Level 1
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Mobiles & Accessories', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $mobiles->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Laptops & Accessories', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $laptops->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Audio', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'seo_title' => 'Audio Devices & Accessories',
            'seo_description' => 'High-quality audio devices for music and calls.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $audio->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Fashion Subcategories - Level 1
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Men', $existingCodes);
        $existingCodes[] = $generatedCode;
        $men = Category::create([
            'title' => 'Men',
            'slug' => 'men',
            'summary' => 'Fashion for men',
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
            'seo_title' => 'Men\'s Fashion',
            'seo_description' => 'Trendy clothing and accessories for men.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $men->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Women', $existingCodes);
        $existingCodes[] = $generatedCode;
        $women = Category::create([
            'title' => 'Women',
            'slug' => 'women',
            'summary' => 'Fashion for women',
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
            'seo_title' => 'Women\'s Fashion',
            'seo_description' => 'Stylish clothing and accessories for women.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $women->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Kids', $existingCodes);
        $existingCodes[] = $generatedCode;
        $kids = Category::create([
            'title' => 'Kids',
            'slug' => 'kids',
            'summary' => 'Fashion for kids',
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
            'seo_title' => 'Kids\' Fashion',
            'seo_description' => 'Cute and comfortable clothing for kids.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $kids->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Home Subcategories - Level 1
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Furniture', $existingCodes);
        $existingCodes[] = $generatedCode;
        $furniture = Category::create([
            'title' => 'Furniture',
            'slug' => 'furniture',
            'summary' => 'Home furniture and decor',
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
            'seo_title' => 'Home Furniture',
            'seo_description' => 'Quality furniture for your home.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $furniture->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Kitchen Appliances', $existingCodes);
        $existingCodes[] = $generatedCode;
        $kitchenAppliances = Category::create([
            'title' => 'Kitchen Appliances',
            'slug' => 'kitchen-appliances',
            'summary' => 'Kitchen appliances and utensils',
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
            'seo_title' => 'Kitchen Appliances',
            'seo_description' => 'Modern kitchen appliances for easy cooking.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $kitchenAppliances->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Sports Subcategories - Level 1
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Gym Equipment', $existingCodes);
        $existingCodes[] = $generatedCode;
        $gym = Category::create([
            'title' => 'Gym Equipment',
            'slug' => 'gym-equipment',
            'summary' => 'Equipment for home gym and fitness',
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
            'seo_description' => 'Build your home gym with our equipment.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $gym->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Outdoor Sports', $existingCodes);
        $existingCodes[] = $generatedCode;
        $outdoor = Category::create([
            'title' => 'Outdoor Sports',
            'slug' => 'outdoor-sports',
            'summary' => 'Gear for outdoor sports and adventures',
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
            'seo_title' => 'Outdoor Sports Gear',
            'seo_description' => 'Equipment for outdoor activities and sports.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $outdoor->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Beauty Subcategories - Level 1
         * -------------------------*/
        $generatedCode = $this->generateUniqueCode('Skin Care', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $skinCare->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        $generatedCode = $this->generateUniqueCode('Makeup', $existingCodes);
        $existingCodes[] = $generatedCode;
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
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $makeup->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        /** --------------------------
         *  Sub-subcategories - Level 2
         * -------------------------*/
        // Electronics > Mobiles > Smartphones
        $generatedCode = $this->generateUniqueCode('Smartphones', $existingCodes);
        $existingCodes[] = $generatedCode;
        $smartphones = Category::create([
            'title' => 'Smartphones',
            'slug' => 'smartphones',
            'summary' => 'Latest smartphones from top brands',
            'photo' => null,
            'parent_id' => $mobiles->id,
            'level' => 2,
            'path' => $mobiles->path . '/' . $mobiles->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Smartphones',
            'seo_description' => 'Buy latest smartphones online.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $smartphones->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

        // Fashion > Men > Shoes
        $generatedCode = $this->generateUniqueCode('Shoes', $existingCodes);
        $existingCodes[] = $generatedCode;
        $shoes = Category::create([
            'title' => 'Shoes',
            'slug' => 'shoes',
            'summary' => 'Men\'s footwear',
            'photo' => null,
            'parent_id' => $men->id,
            'level' => 2,
            'path' => $men->path . '/' . $men->id,
            'sort_order' => 1,
            'has_children' => false,
            'children_count' => 0,
            'products_count' => 0,
            'status' => 'active',
            'is_featured' => false,
            'seo_title' => 'Men\'s Shoes',
            'seo_description' => 'Comfortable and stylish shoes for men.',
            'added_by' => 1,
            'code' => $generatedCode,
            'code_generated_at' => Carbon::now(),
            'code_locked' => false
        ]);
        $shoes->filters()->attach([$priceFilter->id, $brandFilter->id, $ratingFilter->id, $discountFilter->id]);

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

    protected function generateUniqueCode($title, $existingCodes)
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
        }

        return $code;
    }
}
