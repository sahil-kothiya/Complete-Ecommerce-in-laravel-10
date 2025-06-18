<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('categories')->truncate(); // Clears old data

        DB::table('categories')->insert([
            [
                'id' => 1,
                'title' => "Men's Fashion",
                'slug' => 'mens-fashion',
                'summary' => null,
                'photo' => '/storage/photos/1/Category/mini-banner1.jpg',
                'is_parent' => true,
                'parent_id' => null,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 09:56:15',
                'updated_at' => '2020-08-14 09:56:15',
            ],
            [
                'id' => 2,
                'title' => "Women's Fashion",
                'slug' => 'womens-fashion',
                'summary' => null,
                'photo' => '/storage/photos/1/Category/mini-banner2.jpg',
                'is_parent' => true,
                'parent_id' => null,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 09:56:40',
                'updated_at' => '2020-08-14 09:56:40',
            ],
            [
                'id' => 3,
                'title' => "Kid's",
                'slug' => 'kids',
                'summary' => null,
                'photo' => '/storage/photos/1/Category/mini-banner3.jpg',
                'is_parent' => true,
                'parent_id' => null,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 09:57:10',
                'updated_at' => '2020-08-14 09:57:42',
            ],
            [
                'id' => 4,
                'title' => "T-shirt's",
                'slug' => 't-shirts',
                'summary' => null,
                'photo' => null,
                'is_parent' => false,
                'parent_id' => 1,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 10:02:14',
                'updated_at' => '2020-08-14 10:02:14',
            ],
            [
                'id' => 5,
                'title' => "Jeans pants",
                'slug' => 'jeans-pants',
                'summary' => null,
                'photo' => null,
                'is_parent' => false,
                'parent_id' => 1,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 10:02:49',
                'updated_at' => '2020-08-14 10:02:49',
            ],
            [
                'id' => 6,
                'title' => "Sweater & Jackets",
                'slug' => 'sweater-jackets',
                'summary' => null,
                'photo' => null,
                'is_parent' => false,
                'parent_id' => 1,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 10:03:37',
                'updated_at' => '2020-08-14 10:03:37',
            ],
            [
                'id' => 7,
                'title' => "Rain Coats & Trenches",
                'slug' => 'rain-coats-trenches',
                'summary' => null,
                'photo' => null,
                'is_parent' => false,
                'parent_id' => 1,
                'added_by' => null,
                'status' => 'active',
                'created_at' => '2020-08-14 10:04:04',
                'updated_at' => '2020-08-14 10:04:04',
            ],
        ]);
    }
}
