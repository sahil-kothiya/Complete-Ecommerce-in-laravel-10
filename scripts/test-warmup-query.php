<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Testing Warmup Query...\n";
echo str_repeat('=', 80) . "\n\n";

$productIds = [95093, 95543];

echo "Test 1: Exact warmup query\n";
echo str_repeat('-', 80) . "\n";

$products = Product::whereIn('id', $productIds)
    ->with([
        'brand' => function ($query) {
            $query->select(['id', 'title', 'slug']);
        },
        'variants' => function ($query) {
            $query->select(['id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status'])
                ->where('status', 'active')
                ->where('stock', '>', 0);
        },
        'variants.images' => function ($query) {
            $query->select(['id', 'product_variant_id', 'image_path', 'is_primary']);
        },
        'images' => function ($query) {
            $query->select(['id', 'product_id', 'image_path', 'is_primary', 'sort_order'])
                ->limit(3);
        }
    ])
    ->get();

foreach ($products as $product) {
    echo "Product {$product->id}:\n";
    echo "  Images loaded: " . ($product->relationLoaded('images') ? 'Yes' : 'No') . "\n";
    echo "  Images count: " . $product->images->count() . "\n";

    if ($product->images->count() > 0) {
        foreach ($product->images as $img) {
            echo "    - ID: {$img->id}, Path: {$img->image_path}\n";
        }
    }
    echo "\n";
}

echo "\nTest 2: Without select() constraints\n";
echo str_repeat('-', 80) . "\n";

$products2 = Product::whereIn('id', $productIds)->with('images')->get();

foreach ($products2 as $product) {
    echo "Product {$product->id}:\n";
    echo "  Images count: " . $product->images->count() . "\n";

    if ($product->images->count() > 0) {
        foreach ($product->images as $img) {
            echo "    - ID: {$img->id}, Path: {$img->image_path}, Sort: {$img->sort_order}\n";
        }
    }
    echo "\n";
}
