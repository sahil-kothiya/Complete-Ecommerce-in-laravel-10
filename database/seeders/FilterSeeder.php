<?php

namespace Database\Seeders;

use App\Models\Filter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FilterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('TRUNCATE TABLE category_filter RESTART IDENTITY CASCADE');
        DB::statement('TRUNCATE TABLE filters RESTART IDENTITY CASCADE');

        $this->command->info('🔄 Seeding filters...');

        // Price Range Filter
        Filter::create([
            'name' => 'price',
            'title' => 'Price Range',
            'description' => 'Filter products by price range',
            'status' => 'active'
        ]);

        // Brand Filter
        Filter::create([
            'name' => 'brand',
            'title' => 'Brands',
            'description' => 'Filter products by brand',
            'status' => 'active'
        ]);

        // Rating Filter
        Filter::create([
            'name' => 'rating',
            'title' => 'Customer Ratings',
            'description' => 'Filter products by customer ratings',
            'status' => 'active'
        ]);

        // Discount Filter
        Filter::create([
            'name' => 'discount',
            'title' => 'Discounts',
            'description' => 'Filter products by discount percentage',
            'status' => 'active'
        ]);

        // Recently Viewed Filter
        Filter::create([
            'name' => 'recently-viewed',
            'title' => 'Recently Viewed',
            'description' => 'Recently Viewed',
            'status' => 'active'
        ]);

        $this->command->info('✅ Filters seeded successfully!');
    }
}
