<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;

echo "Testing variant display names and SKU sequence:\n\n";

$product = Product::with('variants.variantOptions.variantType')->find(3842);

if (!$product) {
    echo "Product 3842 not found\n";
    exit(1);
}

echo "Product: {$product->title}\n";
echo "Has Variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
echo "\nExisting Variants:\n";
echo str_repeat('-', 80) . "\n";

foreach ($product->variants as $variant) {
    printf("%-30s => %-30s\n", $variant->display_name, $variant->sku);
}

echo str_repeat('-', 80) . "\n";
echo "\nTotal Variants: " . $product->variants->count() . "\n";

// Extract max index from SKUs
$maxIndex = -1;
foreach ($product->variants as $variant) {
    $skuParts = explode('-', $variant->sku);
    $lastPart = end($skuParts);
    if (is_numeric($lastPart)) {
        $maxIndex = max($maxIndex, (int)$lastPart);
    }
}

echo "Max SKU Index: $maxIndex\n";
echo "Next Variant Index: " . ($maxIndex + 1) . "\n";

echo "\n✅ Test complete!\n";
