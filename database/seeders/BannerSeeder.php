<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('banners')->insert([
            [
                'title'       => 'Lorem Ipsum is',
                'slug'        => 'lorem-ipsum-is',
                'photo'       => '/storage/photos/1/Banner/banner-01.jpg',
                'description' => '<h2><span style="font-weight: bold; color: rgb(99, 99, 99);">Up to 10%</span></h2>',
                'status'      => 'active',
                'created_at'  => '2020-08-14 07:17:38',
                'updated_at'  => '2020-08-14 07:18:21',
            ],
            [
                'title'       => 'Lorem Ipsum',
                'slug'        => 'lorem-ipsum',
                'photo'       => '/storage/photos/1/Banner/banner-07.jpg',
                'description' => '<p>Up to 90%</p>',
                'status'      => 'active',
                'created_at'  => '2020-08-14 07:20:23',
                'updated_at'  => '2020-08-14 07:20:23',
            ],
            [
                'title'       => 'Banner',
                'slug'        => 'banner',
                'photo'       => '/storage/photos/1/Banner/banner-06.jpg',
                'description' => '<h2><span style="color: rgb(156, 0, 255); font-size: 2rem; font-weight: bold;">Up to 40%</span><br></h2><h2><span style="color: rgb(156, 0, 255);"></span></h2>',
                'status'      => 'active',
                'created_at'  => '2020-08-18 02:16:59',
                'updated_at'  => '2020-08-18 02:16:59',
            ],
        ]);
    }
}
