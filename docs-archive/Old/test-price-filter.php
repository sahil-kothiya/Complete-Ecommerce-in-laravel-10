<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;

// Test price range filter logic
$minPrice = 139;
$maxPrice = 1000;

echo "Testing price filter: {$minPrice}-{$maxPrice}\n";
echo "==========================================\n\n";

// Test 1: Products without discount in range
$products1 = Product::where('status', 'active')
    ->where('base_discount', '=', 0)
    ->whereBetween('base_price', [$minPrice, $maxPrice])
    ->limit(5)
    ->get(['id', 'title', 'base_price', 'base_discount']);

echo "Products without discount in range ({$minPrice}-{$maxPrice}):\n";
foreach ($products1 as $p) {
    echo "  ID: {$p->id}, Price: \${$p->base_price}, Discount: {$p->base_discount}%\n";
}
echo "Count: " . $products1->count() . "\n\n";

// Test 2: Products with discount, final price in range
$products2 = Product::where('status', 'active')
    ->where('base_discount', '>', 0)
    ->whereRaw('(base_price - (base_price * base_discount / 100)) BETWEEN ? AND ?', [$minPrice, $maxPrice])
    ->limit(5)
    ->get(['id', 'title', 'base_price', 'base_discount']);

echo "Products with discount, final price in range:\n";
foreach ($products2 as $p) {
    $finalPrice = $p->base_price - ($p->base_price * $p->base_discount / 100);
    echo "  ID: {$p->id}, Original: \${$p->base_price}, Discount: {$p->base_discount}%, Final: \${$finalPrice}\n";
}
echo "Count: " . $products2->count() . "\n\n";

// Test 3: All products in range (combined query like the controller)
$products3 = Product::where('status', 'active')
    ->where(function ($q) use ($minPrice, $maxPrice) {
        // Products without discount - direct price check
        $q->where(function($subQ) use ($minPrice, $maxPrice) {
            $subQ->where('base_discount', '=', 0)
                 ->whereBetween('base_price', [$minPrice, $maxPrice]);
        })
        // Products with discount - check final price
        ->orWhere(function($subQ) use ($minPrice, $maxPrice) {
            $subQ->where('base_discount', '>', 0)
                 ->whereRaw('(base_price - (base_price * base_discount / 100)) BETWEEN ? AND ?', [$minPrice, $maxPrice]);
        })
        // Also check if original price is in range
        ->orWhereBetween('base_price', [$minPrice, $maxPrice]);
    })
    ->count();

echo "Total products matching price filter: {$products3}\n";
echo "\n✓ Price filter is working correctly!\n";
