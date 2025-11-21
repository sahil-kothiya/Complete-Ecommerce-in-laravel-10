<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use App\Services\RedisCacheService;
use App\Services\RedisCacheLogger;
use App\Helpers\ImageHelper;

echo "Deep investigation of problematic products...\n";
echo str_repeat('=', 80) . "\n\n";

$productIds = [97607, 99368];

foreach ($productIds as $productId) {
    echo "Product ID: {$productId}\n";
    echo str_repeat('-', 80) . "\n";

    // 1. Check database
    $product = Product::with(['images', 'variants.images'])->find($productId);

    if (!$product) {
        echo "  ❌ NOT FOUND in database!\n\n";
        continue;
    }

    echo "DATABASE STATUS:\n";
    echo "  Status: {$product->status}\n";
    echo "  Is Featured: " . ($product->is_featured ? 'Yes' : 'No') . "\n";
    echo "  Has Variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "  Product Images: " . $product->images->count() . "\n";

    if ($product->images->count() > 0) {
        foreach ($product->images as $idx => $img) {
            echo "    [{$idx}] {$img->image_path}\n";
        }
    }

    echo "  Variants: " . $product->variants->count() . "\n";

    // 2. Load with exact warmup query
    echo "\nWARMUP QUERY SIMULATION:\n";

    $productFromWarmup = Product::where('id', $productId)
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

    echo "  Loaded images: " . $productFromWarmup->images->count() . "\n";
    echo "  Loaded variants: " . $productFromWarmup->variants->count() . "\n";

    // 3. Transform like warmup job does
    echo "\nTRANSFORMATION:\n";

    $images = [];

    if ($productFromWarmup->has_variants && $productFromWarmup->variants && $productFromWarmup->variants->count() > 0) {
        foreach ($productFromWarmup->variants as $variant) {
            if ($variant->status === 'active' && $variant->stock > 0) {
                $variantImageArray = [];
                if ($variant->images && $variant->images->count() > 0) {
                    foreach ($variant->images->take(3) as $img) {
                        $imagePath = !empty($img->image_path)
                            ? ImageHelper::variantImageUrl($img->image_path)
                            : asset('images/no-image.png');

                        $variantImageArray[] = [
                            'image_path' => $imagePath,
                            'alt_text' => $productFromWarmup->title,
                        ];
                    }
                }

                if (empty($images) && !empty($variantImageArray)) {
                    $images = $variantImageArray;
                }
            }
        }
    }

    if (empty($images) && $productFromWarmup->images && $productFromWarmup->images->count() > 0) {
        foreach ($productFromWarmup->images->take(3) as $img) {
            $imagePath = !empty($img->image_path)
                ? ImageHelper::productImageUrl($img->image_path)
                : asset('images/no-image.png');

            $images[] = [
                'image_path' => $imagePath,
                'alt_text' => $productFromWarmup->title,
            ];
        }
    }

    if (empty($images)) {
        $images[] = [
            'image_path' => asset('images/no-image.png'),
            'alt_text' => $productFromWarmup->title,
        ];
    }

    echo "  Final images count: " . count($images) . "\n";
    echo "  First image: " . $images[0]['image_path'] . "\n";

    if (strpos($images[0]['image_path'], 'no-image.png') !== false) {
        echo "  ❌ FALLBACK IMAGE!\n";
    } else {
        echo "  ✅ PROPER IMAGE\n";
    }

    // 4. Check cache
    echo "\nCACHE STATUS:\n";
    $cardKey = "product:card:{$productId}";
    $cached = RedisCacheService::get($cardKey);

    if ($cached) {
        echo "  ✅ Found in cache\n";
        echo "  Cached images: " . count($cached->images) . "\n";
        echo "  First cached image: " . $cached->images[0]['image_path'] . "\n";

        if (strpos($cached->images[0]['image_path'], 'no-image.png') !== false) {
            echo "  ❌ CACHED WITH FALLBACK!\n";
        } else {
            echo "  ✅ CACHED WITH PROPER IMAGE\n";
        }
    } else {
        echo "  ℹ️  Not in cache\n";
    }

    echo "\n" . str_repeat('=', 80) . "\n\n";
}
