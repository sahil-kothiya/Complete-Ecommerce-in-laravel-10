<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Helpers\ImageHelper;
use Illuminate\Support\Facades\DB;

echo "STORE TIME VERIFICATION - Database → Transformation\n";
echo str_repeat('=', 80) . "\n\n";

// Get all featured products that will be cached
$productIds = DB::table('products')
    ->where('status', 'active')
    ->where('is_featured', 1)
    ->inRandomOrder()
    ->limit(20)
    ->pluck('id')
    ->toArray();

echo "Testing " . count($productIds) . " featured products...\n\n";

$issues = [];
$success = 0;

foreach ($productIds as $productId) {
    // Load product exactly as warmup does
    $product = Product::where('id', $productId)
        ->with([
            'brand' => function ($query) {
                $query->select(['id', 'title', 'slug']);
            },
            'variants' => function ($query) {
                $query->select(['id', 'product_id', 'sku', 'price', 'discount', 'stock', 'status'])
                    ->where('status', 'active')
                    ->where('stock', '>', 0);
            },
            'variants.images' => function ($query) {
                $query->select(['id', 'product_variant_id', 'image_path', 'is_primary']);
            },
            'images' => function ($query) {
                $query->select(['id', 'product_id', 'image_path', 'is_primary', 'sort_order'])
                    ->limit(3);
            }
        ])
        ->first();

    if (!$product) {
        continue;
    }

    // Check what we got from database
    $hasVariants = $product->has_variants;
    $dbImagesCount = $product->images->count();
    $dbVariantsCount = $product->variants->count();

    // Simulate transformation
    $images = [];

    if ($hasVariants && $dbVariantsCount > 0) {
        foreach ($product->variants as $variant) {
            if ($variant->status === 'active' && $variant->stock > 0) {
                $variantImageArray = [];
                if ($variant->images && $variant->images->count() > 0) {
                    foreach ($variant->images->take(3) as $img) {
                        $imagePath = !empty($img->image_path)
                            ? ImageHelper::variantImageUrl($img->image_path)
                            : asset('images/no-image.png');

                        $variantImageArray[] = [
                            'image_path' => $imagePath,
                            'alt_text' => $product->title,
                        ];
                    }
                }

                if (empty($images) && !empty($variantImageArray)) {
                    $images = $variantImageArray;
                }
            }
        }
    }

    if (empty($images) && $dbImagesCount > 0) {
        foreach ($product->images->take(3) as $img) {
            $imagePath = !empty($img->image_path)
                ? ImageHelper::productImageUrl($img->image_path)
                : asset('images/no-image.png');

            $images[] = [
                'image_path' => $imagePath,
                'alt_text' => $product->title,
            ];
        }
    }

    if (empty($images)) {
        $images[] = [
            'image_path' => asset('images/no-image.png'),
            'alt_text' => $product->title,
        ];
    }

    // Verify transformation
    $isFallback = strpos($images[0]['image_path'] ?? '', 'no-image.png') !== false;

    if ($isFallback && $dbImagesCount > 0) {
        $issues[] = [
            'product_id' => $productId,
            'problem' => 'Has DB images but transformed to fallback',
            'has_variants' => $hasVariants,
            'db_images_count' => $dbImagesCount,
            'db_variants_count' => $dbVariantsCount,
            'transformed_image' => $images[0]['image_path'],
            'db_images' => $product->images->pluck('image_path')->toArray(),
        ];
    } elseif (!$isFallback || $dbImagesCount == 0) {
        $success++;
    }
}

echo str_repeat('=', 80) . "\n\n";
echo "RESULTS:\n";
echo "  Total tested: " . count($productIds) . "\n";
echo "  Success: {$success}\n";
echo "  Issues: " . count($issues) . "\n";
echo "  Success rate: " . ($success / count($productIds) * 100) . "%\n\n";

if (!empty($issues)) {
    echo "ISSUES FOUND:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($issues as $issue) {
        echo "\nProduct {$issue['product_id']}:\n";
        echo "  Problem: {$issue['problem']}\n";
        echo "  Has variants: " . ($issue['has_variants'] ? 'Yes' : 'No') . "\n";
        echo "  DB images count: {$issue['db_images_count']}\n";
        echo "  DB variants count: {$issue['db_variants_count']}\n";
        echo "  Transformed to: {$issue['transformed_image']}\n";
        echo "  DB images:\n";
        foreach ($issue['db_images'] as $img) {
            echo "    - {$img}\n";
        }
    }
} else {
    echo "✅ 100% SUCCESS - All products transformed correctly!\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
