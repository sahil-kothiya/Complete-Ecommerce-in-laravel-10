<?php

/**
 * Test Variant Display Names Fix
 *
 * This script checks if variant display names are generated correctly
 * after fixing the syncVariantOptionAssignments issue.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;

echo "=== Variant Display Names Test ===\n\n";

// Find a product with variants
$product = Product::where('has_variants', true)
    ->with(['variants.variantOptions.variantType', 'variants.optionAssignments'])
    ->first();

if (!$product) {
    echo "❌ No products with variants found in database\n";
    exit(1);
}

echo "Testing Product: {$product->title} (ID: {$product->id})\n";
echo "Variants Count: " . $product->variants->count() . "\n\n";

foreach ($product->variants as $variant) {
    echo "Variant ID: {$variant->id}\n";
    echo "SKU: {$variant->sku}\n";
    echo "Display Name: {$variant->display_name}\n";
    echo "Option Assignments: " . $variant->optionAssignments->count() . "\n";

    if ($variant->variantOptions->isNotEmpty()) {
        echo "Options:\n";
        foreach ($variant->variantOptions->sortBy(fn($o) => $o->variantType->sort_order ?? 999) as $option) {
            $typeName = $option->variantType->display_name ?? 'Unknown';
            echo "  - {$typeName}: {$option->display_value} (ID: {$option->id})\n";
        }
    } else {
        echo "⚠️ No variant options loaded!\n";
    }

    echo "\n" . str_repeat('-', 60) . "\n\n";
}

echo "✅ Test completed!\n";
echo "\nExpected Format: Color / Storage / RAM (e.g., 'Red / 64GB / 4GB')\n";
echo "If you see mixed values or duplicates, there's still an issue.\n";
