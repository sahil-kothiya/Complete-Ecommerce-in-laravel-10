<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantType;
use App\Models\ProductVariantOption;
use App\Models\ProductVariantCombination;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    public function run()
    {
        // Create Variant Types (idempotent—skip if exist)
        $colorType = ProductVariantType::firstOrCreate(
            ['name' => 'color'],
            ['display_name' => 'Color', 'sort_order' => 1, 'status' => 'active']
        );

        $sizeType = ProductVariantType::firstOrCreate(
            ['name' => 'size'],
            ['display_name' => 'Size', 'sort_order' => 2, 'status' => 'active']
        );

        $ramType = ProductVariantType::firstOrCreate(
            ['name' => 'ram'],
            ['display_name' => 'RAM', 'sort_order' => 3, 'status' => 'active']
        );

        $storageType = ProductVariantType::firstOrCreate(
            ['name' => 'storage'],
            ['display_name' => 'Storage', 'sort_order' => 4, 'status' => 'active']
        );

        // Create Color Options (idempotent)
        $colors = [
            ['value' => 'black', 'display_value' => 'Black', 'hex_color' => '#000000'],
            ['value' => 'white', 'display_value' => 'White', 'hex_color' => '#FFFFFF'],
            ['value' => 'red', 'display_value' => 'Red', 'hex_color' => '#FF0000'],
            ['value' => 'blue', 'display_value' => 'Blue', 'hex_color' => '#0000FF'],
            ['value' => 'green', 'display_value' => 'Green', 'hex_color' => '#00FF00'],
        ];

        $colorOptions = [];
        foreach ($colors as $index => $color) {
            $colorOptions[$color['value']] = ProductVariantOption::firstOrCreate(
                ['variant_type_id' => $colorType->id, 'value' => $color['value']],
                [
                    'display_value' => $color['display_value'],
                    'hex_color' => $color['hex_color'],
                    'sort_order' => $index + 1,
                    'status' => 'active'
                ]
            );
        }

        // Create Size Options (for clothing/shoes—idempotent)
        $clothingSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL'];
        $sizeOptions = [];
        foreach ($clothingSizes as $index => $size) {
            $sizeOptions[$size] = ProductVariantOption::firstOrCreate(
                ['variant_type_id' => $sizeType->id, 'value' => strtolower($size)],
                [
                    'display_value' => $size,
                    'sort_order' => $index + 1,
                    'status' => 'active'
                ]
            );
        }

        // Create Shoe Size Options (separate for numeric sizes—idempotent)
        $shoeSizes = ['7', '8', '9', '10', '11', '12'];
        $shoeSizeOptions = [];
        foreach ($shoeSizes as $index => $size) {
            $shoeSizeOptions[$size] = ProductVariantOption::firstOrCreate(
                ['variant_type_id' => $sizeType->id, 'value' => 'size_' . $size],
                [
                    'display_value' => 'Size ' . $size,
                    'sort_order' => 100 + $index,
                    'status' => 'active'
                ]
            );
        }

        // Create RAM Options (idempotent)
        $rams = ['4GB', '6GB', '8GB', '12GB', '16GB'];
        $ramOptions = [];
        foreach ($rams as $index => $ram) {
            $ramOptions[$ram] = ProductVariantOption::firstOrCreate(
                ['variant_type_id' => $ramType->id, 'value' => strtolower(str_replace('GB', 'gb', $ram))],
                [
                    'display_value' => $ram . ' RAM',
                    'sort_order' => $index + 1,
                    'status' => 'active'
                ]
            );
        }

        // Create Storage Options (idempotent)
        $storages = ['64GB', '128GB', '256GB', '512GB', '1TB'];
        $storageOptions = [];
        foreach ($storages as $index => $storage) {
            $storageOptions[$storage] = ProductVariantOption::firstOrCreate(
                ['variant_type_id' => $storageType->id, 'value' => strtolower($storage)],
                [
                    'display_value' => $storage . ' Storage',
                    'sort_order' => $index + 1,
                    'status' => 'active'
                ]
            );
        }

        // Dynamic product selection: Pick real products from your seeded data
        $mobileProduct = Product::where('child_cat_id', function ($query) {
            $query->select('id')->from('categories')->where('slug', 'smartphones')->first()->id ?? 0;
        })->inRandomOrder()->first(); // Pick a random smartphone

        $tshirtProduct = Product::where('child_cat_id', function ($query) {
            $query->select('id')->from('categories')->where('slug', 'women')->first()->id ?? 0; // Assuming women's category for t-shirts
        })->inRandomOrder()->first(); // Pick a random women's product (adapt if needed)

        $shoesProduct = Product::where('child_cat_id', function ($query) {
            $query->select('id')->from('categories')->where('slug', 'shoes')->first()->id ?? 0;
        })->inRandomOrder()->first(); // Pick a random shoe

        // Example 1: Mobile variants (if product exists)
        if ($mobileProduct) {
            $mobileProduct->update(['has_variants' => true, 'base_price' => 999.00]);

            $mobileVariants = [
                ['color' => 'black', 'ram' => '8GB', 'storage' => '128GB', 'price' => 999.00, 'stock' => 50],
                ['color' => 'black', 'ram' => '8GB', 'storage' => '256GB', 'price' => 1099.00, 'stock' => 40],
                ['color' => 'white', 'ram' => '8GB', 'storage' => '128GB', 'price' => 999.00, 'stock' => 45],
                ['color' => 'blue', 'ram' => '8GB', 'storage' => '256GB', 'price' => 1099.00, 'stock' => 50],
            ];

            foreach ($mobileVariants as $variantData) {
                $variant = ProductVariant::create([
                    'product_id' => $mobileProduct->id,
                    'sku' => 'MOB-' . strtoupper($variantData['color']) . '-' . $variantData['ram'] . '-' . $variantData['storage'],
                    'price' => $variantData['price'],
                    'discount' => 10,
                    'stock' => $variantData['stock'],
                    'variant_values' => [
                        'color_id' => $colorOptions[$variantData['color']]->id,
                        'ram_id' => $ramOptions[$variantData['ram']]->id,
                        'storage_id' => $storageOptions[$variantData['storage']]->id,
                    ],
                    'status' => 'active'
                ]);

                // Variant combinations
                ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $colorOptions[$variantData['color']]->id]);
                ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $ramOptions[$variantData['ram']]->id]);
                ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $storageOptions[$variantData['storage']]->id]);
            }
        }

        // Example 2: T-Shirt variants (if product exists)
        if ($tshirtProduct) {
            $tshirtProduct->update(['has_variants' => true, 'base_price' => 29.99]);

            $tshirtColors = ['black', 'white', 'red', 'blue'];
            $tshirtSizes = ['S', 'M', 'L', 'XL'];

            foreach ($tshirtColors as $color) {
                foreach ($tshirtSizes as $size) {
                    $variant = ProductVariant::create([
                        'product_id' => $tshirtProduct->id,
                        'sku' => 'TSH-' . strtoupper($color) . '-' . $size,
                        'price' => 29.99,
                        'discount' => 15,
                        'stock' => rand(20, 100),
                        'variant_values' => [
                            'color_id' => $colorOptions[$color]->id,
                            'size_id' => $sizeOptions[$size]->id,
                        ],
                        'status' => 'active'
                    ]);

                    ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $colorOptions[$color]->id]);
                    ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $sizeOptions[$size]->id]);
                }
            }
        }

        // Example 3: Shoes variants (if product exists)
        if ($shoesProduct) {
            $shoesProduct->update(['has_variants' => true, 'base_price' => 89.99]);

            $shoeColors = ['black', 'white', 'blue'];

            foreach ($shoeColors as $color) {
                foreach ($shoeSizes as $size) {
                    $variant = ProductVariant::create([
                        'product_id' => $shoesProduct->id,
                        'sku' => 'SHOE-' . strtoupper($color) . '-' . $size,
                        'price' => 89.99,
                        'discount' => 20,
                        'stock' => rand(10, 50),
                        'variant_values' => [
                            'color_id' => $colorOptions[$color]->id,
                            'size_id' => $shoeSizeOptions[$size]->id,
                        ],
                        'status' => 'active'
                    ]);

                    ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $colorOptions[$color]->id]);
                    ProductVariantCombination::create(['product_variant_id' => $variant->id, 'variant_option_id' => $shoeSizeOptions[$size]->id]);
                }
            }
        }

        $this->command->info('Product variants seeded successfully! Check `product_variants` table.');
    }
}