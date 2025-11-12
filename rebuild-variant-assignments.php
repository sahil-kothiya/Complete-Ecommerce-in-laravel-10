<?php

/**
 * Rebuild Variant Option Assignments from SKU
 *
 * This script rebuilds variant option assignments by parsing the SKU
 * and matching it against the product's selected variant options.
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantTypeSelection;
use App\Models\ProductVariantOption;
use Illuminate\Support\Facades\DB;

// Specify the product ID from the screenshot (or process all)
$productId = 9841; // "test" product from cleanup output

$product = Product::with('variants')->find($productId);

if (!$product) {
    echo "❌ Product not found\n";
    exit(1);
}

echo "Product: {$product->title} (ID: {$product->id})\n\n";

// Load variant types with options
$product->loadVariantTypes();

// Get all variant types and options for this product
$typeOptions = [];
foreach ($product->variantTypes as $type) {
    $typeId = $type->id;
    $typeName = $type->display_name;
    $typeOptions[$typeId] = [
        'name' => $typeName,
        'options' => $type->options->keyBy('id')->toArray()
    ];
}

echo "Available Types:\n";
foreach ($typeOptions as $typeId => $typeData) {
    echo "  {$typeData['name']} (Type ID: {$typeId}): " . count($typeData['options']) . " options\n";
}
echo "\n";

$fixed = 0;

foreach ($product->variants as $variant) {
    echo "Variant {$variant->id} (SKU: {$variant->sku})\n";

    // Parse SKU to find matching options
    $skuParts = explode('-', strtoupper($variant->sku));
    echo "  SKU parts: " . implode(', ', $skuParts) . "\n";

    $matchedOptions = [];

    // For each variant type, find the best matching option
    foreach ($typeOptions as $typeId => $typeData) {
        $bestMatch = null;
        $bestScore = 0;

        foreach ($typeData['options'] as $optionId => $option) {
            $optionValue = strtoupper($option['display_value']);
            $score = 0;

            // Check if ANY SKU part matches this option value
            foreach ($skuParts as $part) {
                // Exact match - highest priority
                if ($part === $optionValue) {
                    $score = 10000 + strlen($optionValue) * 100; // Exact match gets huge boost
                    break;
                }
                // Exact match without spaces
                elseif ($part === str_replace(' ', '', $optionValue)) {
                    $score = 9000 + strlen($optionValue) * 90;
                    break;
                }
                // Substring matches - but only if lengths are similar to avoid false matches
                // e.g., "4GB" should not match "64GB"
                elseif (strpos($part, $optionValue) !== false && abs(strlen($part) - strlen($optionValue)) <= 2) {
                    $score = strlen($optionValue) * 50;
                }
                elseif (strpos($optionValue, $part) !== false && abs(strlen($part) - strlen($optionValue)) <= 2) {
                    $score = strlen($optionValue) * 40;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $option;
            }
        }        if ($bestMatch && $bestScore > 0) {
            $matchedOptions[$typeId] = $bestMatch;
            echo "  ✓ {$typeData['name']}: {$bestMatch['display_value']} (score: {$bestScore})\n";
        } else {
            echo "  ✗ {$typeData['name']}: No match found\n";
        }
    }

    if (count($matchedOptions) > 0) {
        try {
            DB::beginTransaction();

            // Delete existing assignments
            $variant->optionAssignments()->delete();

            // Create new assignments
            foreach ($matchedOptions as $typeId => $option) {
                $variant->optionAssignments()->create([
                    'product_variant_option_id' => $option['id']
                ]);
            }

            DB::commit();
            $fixed++;

            // Reload and show new display name
            $variant->load('variantOptions.variantType');
            echo "  ✅ Fixed! New display name: {$variant->display_name}\n";

        } catch (\Exception $e) {
            DB::rollBack();
            echo "  ❌ Error: {$e->getMessage()}\n";
        }
    }

    echo "\n";
}

echo "Summary: Fixed {$fixed} / " . $product->variants->count() . " variants\n";
