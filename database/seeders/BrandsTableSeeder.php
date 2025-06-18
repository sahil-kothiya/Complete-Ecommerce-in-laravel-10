<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $brands = [
            ['title' => 'Adidas', 'slug' => 'adidas'],
            ['title' => 'Nike', 'slug' => 'nike'],
            ['title' => 'Kappa', 'slug' => 'kappa'],
            ['title' => 'Prada', 'slug' => 'prada'],
            ['title' => 'Brand', 'slug' => 'brand'],
        ];

        foreach ($brands as $brand) {
            DB::table('brands')->insert([
                'title' => $brand['title'],
                'slug' => $brand['slug'],
                'status' => 'active',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
