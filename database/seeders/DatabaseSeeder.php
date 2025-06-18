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
        $this->call(SettingTableSeeder::class);
        $this->call(CouponSeeder::class);
        $this->call(BrandsTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(BulkUsersSeeder::class);
        $this->call(BulkProductsTableSeeder::class);
    }
}
