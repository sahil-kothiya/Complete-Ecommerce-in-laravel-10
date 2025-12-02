<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;

echo "Product Count Analysis:\n";
echo "======================\n\n";

$total = Product::where('status', 'active')->count();
echo "Total active products: {$total}\n\n";

$ranges = [
    [0, 50],
    [50, 100],
    [100, 200],
    [139, 1000],
    [200, 500],
    [500, 1000],
    [1000, 2000],
];

foreach ($ranges as [$min, $max]) {
    $count = Product::where('status', 'active')
        ->where(function ($q) use ($min, $max) {
            $q->where(function($subQ) use ($min, $max) {
                $subQ->where('base_discount', '=', 0)
                     ->whereBetween('base_price', [$min, $max]);
            })
            ->orWhere(function($subQ) use ($min, $max) {
                $subQ->where('base_discount', '>', 0)
                     ->whereRaw('(base_price - (base_price * base_discount / 100)) BETWEEN ? AND ?', [$min, $max]);
            })
            ->orWhereBetween('base_price', [$min, $max]);
        })
        ->count();
    
    echo "Range \${$min}-\${$max}: {$count} products\n";
}

echo "\nSample products with their prices:\n";
$samples = Product::where('status', 'active')
    ->limit(20)
    ->get(['id', 'title', 'base_price', 'base_discount']);

foreach ($samples as $p) {
    $finalPrice = $p->base_price - ($p->base_price * $p->base_discount / 100);
    echo "  ID: {$p->id}, Base: \${$p->base_price}, Discount: {$p->base_discount}%, Final: \${$finalPrice}\n";
}
