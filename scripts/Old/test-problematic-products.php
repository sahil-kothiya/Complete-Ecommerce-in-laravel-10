<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Helpers\ImageHelper;

echo "TESTING PROBLEMATIC PRODUCTS WITH EXACT WARMUP QUERY\n";
echo str_repeat('=', 80) . "\n\n";

$productIds = [95093, 95543];

foreach ($productIds as $productId) {
    echo "Product {$productId}:\n";
    echo str_repeat('-', 80) . "\n";

    // Exact query from warmup
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

    echo "Loaded:\n";
    echo "  has_variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "  images relation loaded: " . ($product->relationLoaded('images') ? 'Yes' : 'No') . "\n";
    echo "  images count: " . $product->images->count() . "\n";

    if ($product->images->count() > 0) {
        foreach ($product->images as $idx => $img) {
            echo "    [{$idx}] id={$img->id}, path={$img->image_path}, primary={$img->is_primary}, sort={$img->sort_order}\n";
        }
    }

    // Now transform
    echo "\nTransformation:\n";

    $images = [];

    if ($product->has_variants && $product->variants && $product->variants->count() > 0) {
        echo "  Branch: HAS VARIANTS (skipped)\n";
    }

    if (empty($images) && $product->images && $product->images->count() > 0) {
        echo "  Branch: SIMPLE PRODUCT IMAGES\n";
        foreach ($product->images->take(3) as $img) {
            $imagePath = !empty($img->image_path)
                ? ImageHelper::productImageUrl($img->image_path)
                : asset('images/no-image.png');

            $images[] = [
                'image_path' => $imagePath,
                'alt_text' => $product->title,
            ];

            echo "    Generated: {$imagePath}\n";
        }
    }

    if (empty($images)) {
        echo "  Branch: FALLBACK\n";
        $images[] = [
            'image_path' => asset('images/no-image.png'),
            'alt_text' => $product->title,
        ];
    }

    echo "\nFinal result:\n";
    echo "  Images count: " . count($images) . "\n";
    echo "  First image: {$images[0]['image_path']}\n";
    echo "  Is fallback: " . (strpos($images[0]['image_path'], 'no-image.png') !== false ? 'YES' : 'NO') . "\n";

    echo "\n" . str_repeat('=', 80) . "\n\n";
}
