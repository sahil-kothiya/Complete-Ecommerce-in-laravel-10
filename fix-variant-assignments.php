<?php

/**
 * Fix Variant Option Assignments
 *
 * This script identifies and fixes variants that have incorrect option assignments
 * (e.g., multiple RAM options assigned to one variant)
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantType;
use Illuminate\Support\Facades\DB;

echo "=== Fixing Variant Option Assignments ===\n\n";

// Find all product IDs with variants (no eager loading)
$productIds = Product::where('has_variants', true)->pluck('id');

if ($productIds->isEmpty()) {
    echo "❌ No products with variants found\n";
    exit(0);
}

echo "Found " . $productIds->count() . " products with variants\n\n";

$totalVariants = 0;
$fixedVariants = 0;
$errors = [];

// Process one product at a time to avoid memory issues
foreach ($productIds as $productId) {
    $product = Product::with(['variants.variantOptions.variantType', 'variants.optionAssignments'])
        ->find($productId);
    echo "Product: {$product->title} (ID: {$product->id})\n";

    foreach ($product->variants as $variant) {
        $totalVariants++;

        // Group current option assignments by type
        $optionsByType = $variant->variantOptions->groupBy('product_variant_type_id');

        $hasDuplicates = false;
        $typesWithDuplicates = [];

        foreach ($optionsByType as $typeId => $options) {
            if ($options->count() > 1) {
                $hasDuplicates = true;
                $type = $options->first()->variantType;
                $typeName = $type->display_name ?? "Type {$typeId}";
                $optionValues = $options->pluck('display_value')->toArray();
                $typesWithDuplicates[] = "{$typeName}: " . implode(', ', $optionValues);
            }
        }

        if ($hasDuplicates) {
            echo "  ⚠️ Variant {$variant->id} (SKU: {$variant->sku}): ";
            echo "Has duplicate types: " . implode(' | ', $typesWithDuplicates) . "\n";

            // Try to fix by keeping only the first option of each type
            // (the one that best matches the SKU)
            try {
                DB::beginTransaction();

                $keptOptions = [];
                foreach ($optionsByType as $typeId => $options) {
                    if ($options->count() > 1) {
                        // Find best matching option based on SKU
                        $skuUpper = strtoupper($variant->sku);
                        $bestMatch = null;
                        $bestScore = 0;

                        foreach ($options as $option) {
                            $optionValue = strtoupper($option->display_value);
                            $score = 0;

                            // Exact match
                            if (strpos($skuUpper, $optionValue) !== false) {
                                $score = strlen($optionValue) * 10;
                            }
                            // Match without spaces
                            elseif (strpos($skuUpper, str_replace(' ', '', $optionValue)) !== false) {
                                $score = strlen($optionValue) * 8;
                            }

                            if ($score > $bestScore) {
                                $bestScore = $score;
                                $bestMatch = $option;
                            }
                        }

                        if ($bestMatch) {
                            $keptOptions[] = $bestMatch->id;
                            echo "    ✓ Keeping: {$bestMatch->display_value} (score: {$bestScore})\n";
                        } else {
                            // If no match, keep the first one
                            $keptOptions[] = $options->first()->id;
                            echo "    ⚠️ No match found, keeping first: {$options->first()->display_value}\n";
                        }
                    } else {
                        // Only one option, keep it
                        $keptOptions[] = $options->first()->id;
                    }
                }

                // Delete all assignments
                $variant->optionAssignments()->delete();

                // Re-create with only the kept options
                foreach ($keptOptions as $optionId) {
                    $variant->optionAssignments()->create([
                        'product_variant_option_id' => $optionId
                    ]);
                }

                DB::commit();
                $fixedVariants++;
                echo "    ✅ Fixed!\n";

            } catch (\Exception $e) {
                DB::rollBack();
                $errorMsg = "Failed to fix variant {$variant->id}: {$e->getMessage()}";
                echo "    ❌ {$errorMsg}\n";
                $errors[] = $errorMsg;
            }

            echo "\n";
        }
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "Summary:\n";
echo "  Total Variants: {$totalVariants}\n";
echo "  Fixed Variants: {$fixedVariants}\n";
echo "  Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
}

echo "\n✅ Cleanup completed!\n";
