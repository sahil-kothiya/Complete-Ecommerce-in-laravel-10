<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Helpers\ImageHelper;

echo "Testing transformProductForDisplay logic...\n";
echo str_repeat('=', 80) . "\n\n";

// Load product exactly as warmup job does
$product = Product::where('id', 99064)
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
            $query->select(['id', 'product_id', 'image_path', 'is_primary'])
                ->limit(3);
        }
    ])
    ->first();

echo "Product ID: {$product->id}\n";
echo "Title: {$product->title}\n";
echo "Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
echo "\n";

echo "Loaded relationships:\n";
echo "- Variants count: " . $product->variants->count() . "\n";
echo "- Images count: " . $product->images->count() . "\n";
echo "\n";

if ($product->images->count() > 0) {
    echo "Product images:\n";
    foreach ($product->images as $idx => $img) {
        echo "  [{$idx}] {$img->image_path}\n";
        echo "      Full URL: " . ImageHelper::productImageUrl($img->image_path) . "\n";
    }
    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "Transform logic walkthrough:\n\n";

$images = [];
$variantsData = [];
$variantStockTotal = 0;
$variantMaxDiscount = 0;

echo "Step 1: Check has_variants ({$product->has_variants}) and variants count (" . $product->variants->count() . ")\n";
if ($product->has_variants && $product->variants && $product->variants->count() > 0) {
    echo "  → Processing variants branch\n";
    foreach ($product->variants as $variant) {
        if ($variant->status === 'active' && $variant->stock > 0) {
            $variantStockTotal += $variant->stock;
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

            $variantsData[] = [
                'id' => $variant->id,
                'price' => (float)$variant->price,
                'discount' => (float)($variant->discount ?? 0),
                'stock' => (int)$variant->stock,
                'status' => $variant->status,
                'images' => $variantImageArray,
            ];

            if (($variant->discount ?? 0) > $variantMaxDiscount) {
                $variantMaxDiscount = $variant->discount;
            }

            if (empty($images) && !empty($variantImageArray)) {
                $images = $variantImageArray;
            }
        }
    }
} else {
    echo "  → Skipping variants branch (has_variants=false or no variants)\n";
}

echo "\nStep 2: Images after variant processing: " . count($images) . " images\n";

echo "\nStep 3: Check simple product images (empty={" . (empty($images) ? 'true' : 'false') . "}, product->images->count()=" . $product->images->count() . ")\n";
if (empty($images) && $product->images && $product->images->count() > 0) {
    echo "  → Processing product images\n";
    foreach ($product->images->take(3) as $img) {
        echo "    - Processing image: {$img->image_path}\n";
        $imagePath = !empty($img->image_path)
            ? ImageHelper::productImageUrl($img->image_path)
            : asset('images/no-image.png');

        echo "      Generated URL: {$imagePath}\n";

        $images[] = [
            'image_path' => $imagePath,
            'alt_text' => $product->title,
        ];
    }
} else {
    echo "  → Skipping product images (images not empty or no product images)\n";
}

echo "\nStep 4: Images after product image processing: " . count($images) . " images\n";

if (empty($images)) {
    echo "\nStep 5: Using fallback placeholder\n";
    $images[] = [
        'image_path' => asset('images/no-image.png'),
        'alt_text' => $product->title,
    ];
}

echo "\nFinal images array:\n";
echo json_encode($images, JSON_PRETTY_PRINT) . "\n";
