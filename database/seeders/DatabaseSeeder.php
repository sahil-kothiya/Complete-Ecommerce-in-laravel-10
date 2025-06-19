<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class);
        $this->call(BannerSeeder::class);
        $this->call(SettingsTableSeeder::class);
        $this->call(CouponSeeder::class);
        $this->call(BrandsTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(BulkUsersSeeder::class); // 1 Million
        // $this->call(BulkProductsTableSeeder::class); // 10 Million
        $this->call(BulkProductsTableSeederNew::class); // 10 Million
    }
}
