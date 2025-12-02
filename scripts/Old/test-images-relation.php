<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

// Enable query logging
DB::enableQueryLog();

$product = Product::where('id', 99064)
    ->with([
        'images' => function ($query) {
            $query->select(['id', 'product_id', 'image_path', 'is_primary'])
                ->limit(3);
        }
    ])
    ->first();

// Show executed queries
$queries = DB::getQueryLog();
echo "Executed Queries:\n";
echo str_repeat('=', 80) . "\n";
foreach ($queries as $idx => $query) {
    echo "[{$idx}] " . $query['query'] . "\n";
    echo "    Bindings: " . json_encode($query['bindings']) . "\n";
    echo "    Time: {$query['time']}ms\n\n";
}

echo str_repeat('=', 80) . "\n";
echo "Product Info:\n";
echo "ID: {$product->id}\n";
echo "Title: {$product->title}\n";
echo "Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
echo "\n";

echo "Images loaded:\n";
if ($product->images->isEmpty()) {
    echo "  ❌ NO IMAGES!\n";
} else {
    echo "  Count: " . $product->images->count() . "\n";
    foreach ($product->images as $idx => $img) {
        echo "  [{$idx}] ID: {$img->id}\n";
        echo "      Product ID: {$img->product_id}\n";
        echo "      Path: {$img->image_path}\n";
        echo "      Is Primary: {$img->is_primary}\n";
    }
}
