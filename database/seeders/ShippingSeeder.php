<?php

namespace Database\Seeders;

use App\Models\Shipping;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run()
    {
        Shipping::updateOrCreate(
            ['type' => 'Free Shipping'],
            [
                'price'  => 0.00,
                'status' => 'active',
            ]
        );

        Shipping::updateOrCreate(
            ['type' => 'Standard Delivery'],
            [
                'price'  => 5.99,
                'status' => 'active',
            ]
        );

        Shipping::updateOrCreate(
            ['type' => 'Express Delivery'],
            [
                'price'  => 12.99,
                'status' => 'active',
            ]
        );
    }
}