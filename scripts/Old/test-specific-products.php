<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Helpers\ImageHelper;
use App\Services\RedisCacheService;

echo "Testing specific products...\n";
echo str_repeat('=', 80) . "\n\n";

$testProductIds = [99064, 99150, 95989, 95507, 97350];

foreach ($testProductIds as $productId) {
    echo "Product ID: {$productId}\n";

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
                $query->select(['id', 'product_id', 'image_path', 'is_primary'])
                    ->limit(3);
            }
        ])
        ->first();

    if (!$product) {
        echo "  ❌ Not found\n\n";
        continue;
    }

    // Transform using same logic as warmup job
    $images = [];

    if ($product->has_variants && $product->variants && $product->variants->count() > 0) {
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

    if (empty($images) && $product->images && $product->images->count() > 0) {
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

    echo "  Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "  Product images count: " . $product->images->count() . "\n";
    echo "  Transformed images count: " . count($images) . "\n";
    echo "  First image: " . $images[0]['image_path'] . "\n";

    if (strpos($images[0]['image_path'], 'no-image.png') !== false) {
        echo "  ⚠️ USING FALLBACK!\n";
    } else {
        echo "  ✅ Has proper image\n";
    }

    echo "\n";
}

echo str_repeat('=', 80) . "\n";
echo "✅ Test complete\n";
