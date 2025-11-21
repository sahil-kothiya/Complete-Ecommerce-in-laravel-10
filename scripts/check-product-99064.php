<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;

$productId = 99064;

echo "Checking Product {$productId}...\n";
echo str_repeat('=', 80) . "\n";

$product = Product::with(['images', 'variants.images'])->find($productId);

if (!$product) {
    echo "❌ Product not found!\n";
    exit(1);
}

echo "Product: {$product->title}\n";
echo "Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
echo "Featured: " . ($product->is_featured ? 'Yes' : 'No') . "\n";
echo "\n";

echo "Product Images (from product_images table):\n";
if ($product->images->isEmpty()) {
    echo "  ❌ NO product images found!\n";
} else {
    foreach ($product->images as $idx => $img) {
        echo "  [{$idx}] ID: {$img->id}\n";
        echo "      Path: {$img->image_path}\n";
        echo "      Product ID: {$img->product_id}\n";
    }
}

echo "\n";

echo "Variants:\n";
if ($product->variants->isEmpty()) {
    echo "  ❌ NO variants found!\n";
} else {
    foreach ($product->variants as $vIdx => $variant) {
        echo "  [{$vIdx}] Variant ID: {$variant->id}\n";
        echo "      SKU: {$variant->sku}\n";
        echo "      Images:\n";

        if ($variant->images->isEmpty()) {
            echo "        ❌ NO variant images!\n";
        } else {
            foreach ($variant->images as $iIdx => $img) {
                echo "        [{$iIdx}] ID: {$img->id}\n";
                echo "            Path: {$img->image_path}\n";
                echo "            Variant ID: {$img->product_variant_id}\n";
            }
        }
    }
}
