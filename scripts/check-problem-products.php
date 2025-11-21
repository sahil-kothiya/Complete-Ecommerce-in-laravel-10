<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;

echo "Checking problematic products...\n";
echo str_repeat('=', 80) . "\n\n";

$productIds = [97607, 99368];

foreach ($productIds as $productId) {
    echo "Product ID: {$productId}\n";

    $product = Product::with(['images', 'variants.images'])->find($productId);

    if (!$product) {
        echo "  ❌ Product NOT FOUND in database!\n\n";
        continue;
    }

    echo "  Title: {$product->title}\n";
    echo "  Status: {$product->status}\n";
    echo "  Featured: " . ($product->is_featured ? 'Yes' : 'No') . "\n";
    echo "  Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "\n";

    echo "  Product Images:\n";
    if ($product->images->isEmpty()) {
        echo "    ❌ NO product images found!\n";
    } else {
        foreach ($product->images as $idx => $img) {
            echo "    [{$idx}] ID: {$img->id}, Path: {$img->image_path}, Primary: {$img->is_primary}\n";
        }
    }
    echo "\n";

    echo "  Variants:\n";
    if ($product->variants->isEmpty()) {
        echo "    ℹ️  No variants (simple product)\n";
    } else {
        foreach ($product->variants as $vIdx => $variant) {
            echo "    [{$vIdx}] Variant ID: {$variant->id}\n";
            echo "        Status: {$variant->status}, Stock: {$variant->stock}\n";
            echo "        Images:\n";

            if ($variant->images->isEmpty()) {
                echo "          ❌ NO variant images!\n";
            } else {
                foreach ($variant->images as $iIdx => $img) {
                    echo "          [{$iIdx}] ID: {$img->id}, Path: {$img->image_path}\n";
                }
            }
        }
    }

    echo "\n" . str_repeat('-', 80) . "\n\n";
}
