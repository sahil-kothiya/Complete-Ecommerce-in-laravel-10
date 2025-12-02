<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Checking Product Price Structure:\n";
echo "==================================\n\n";

// Check a specific product from the API response
$productId = 10000000;
$product = Product::find($productId);

if ($product) {
    echo "Product ID: {$product->id}\n";
    echo "Title: {$product->title}\n";
    echo "Base Price: \${$product->base_price}\n";
    echo "Base Discount: {$product->base_discount}%\n";
    echo "Has Variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    
    if ($product->has_variants) {
        echo "\nVariants:\n";
        $variants = DB::table('product_variants')
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->limit(5)
            ->get(['id', 'price', 'discount', 'stock']);
        
        foreach ($variants as $v) {
            $finalPrice = $v->price - ($v->price * ($v->discount ?? 0) / 100);
            echo "  Variant {$v->id}: Price: \${$v->price}, Discount: {$v->discount}%, Final: \${$finalPrice}, Stock: {$v->stock}\n";
        }
    }
    
    $finalPrice = $product->base_price - ($product->base_price * $product->base_discount / 100);
    echo "\nCalculated Final Price: \${$finalPrice}\n";
}

// Check how the API query actually works
echo "\n\nTesting API Query Logic:\n";
echo "========================\n";

$minPrice = 139;
$maxPrice = 1000;

// This is what the UltraFastFilterController should be doing
$count = Product::where('status', 'active')
    ->where(function ($q) use ($minPrice, $maxPrice) {
        $q->where(function($subQ) use ($minPrice, $maxPrice) {
            $subQ->where('base_discount', '=', 0)
                 ->whereBetween('base_price', [$minPrice, $maxPrice]);
        })
        ->orWhere(function($subQ) use ($minPrice, $maxPrice) {
            $subQ->where('base_discount', '>', 0)
                 ->whereRaw('(base_price - (base_price * base_discount / 100)) BETWEEN ? AND ?', [$minPrice, $maxPrice]);
        })
        ->orWhereBetween('base_price', [$minPrice, $maxPrice]);
    })
    ->count();

echo "Products matching {$minPrice}-{$maxPrice}: {$count}\n";
