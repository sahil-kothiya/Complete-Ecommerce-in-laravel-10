<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;
use App\Models\Product;

echo "Identifying Products with No Images\n";
echo str_repeat('=', 80) . "\n\n";

$version = RedisCacheService::getVersion();
$productsWithFallback = [];

// Check Featured Products
$featuredKey = "cache:homepage:products:featured_v{$version}";
$featured = RedisCacheService::get($featuredKey);

if ($featured) {
    foreach ($featured as $product) {
        $isFallback = strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false;
        if ($isFallback) {
            $productsWithFallback[] = $product->id;
        }
    }
}

// Check Category Products
$categoryKey = "cache:homepage:category_products_v{$version}";
$categories = RedisCacheService::get($categoryKey);

if ($categories) {
    foreach ($categories as $category) {
        foreach ($category['products'] as $product) {
            $isFallback = strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false;
            if ($isFallback && !in_array($product->id, $productsWithFallback)) {
                $productsWithFallback[] = $product->id;
            }
        }
    }
}

echo "Found " . count($productsWithFallback) . " products with fallback images:\n";
echo "Product IDs: " . implode(', ', $productsWithFallback) . "\n\n";

echo str_repeat('=', 80) . "\n\n";

// Check database for these products
echo "Database Investigation:\n";
echo str_repeat('-', 80) . "\n\n";

foreach ($productsWithFallback as $productId) {
    $product = Product::with(['images', 'variants.images'])->find($productId);

    if (!$product) {
        echo "Product {$productId}: ❌ NOT FOUND in database\n\n";
        continue;
    }

    echo "Product {$productId}: {$product->title}\n";
    echo "  Status: {$product->status}\n";
    echo "  Featured: " . ($product->is_featured ? 'Yes' : 'No') . "\n";
    echo "  Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "  Product images: " . $product->images->count() . "\n";

    if ($product->images->count() > 0) {
        foreach ($product->images as $idx => $img) {
            echo "    [{$idx}] {$img->image_path}\n";
        }
    } else {
        echo "    ❌ NO IMAGES IN DATABASE\n";
    }

    echo "  Variants: " . $product->variants->count() . "\n";

    if ($product->has_variants && $product->variants->count() > 0) {
        foreach ($product->variants as $vIdx => $variant) {
            echo "    Variant {$vIdx}: {$variant->sku} (stock: {$variant->stock})\n";
            echo "      Images: " . $variant->images->count() . "\n";

            if ($variant->images->count() > 0) {
                foreach ($variant->images as $img) {
                    echo "        - {$img->image_path}\n";
                }
            } else {
                echo "        ❌ NO IMAGES\n";
            }
        }
    }

    // Recommendation
    echo "\n  💡 RECOMMENDATION: ";
    if ($product->images->count() == 0 && $product->variants->count() == 0) {
        echo "Add product images to product_images table\n";
    } elseif ($product->has_variants && $product->variants->count() > 0) {
        $hasVariantImages = false;
        foreach ($product->variants as $variant) {
            if ($variant->images->count() > 0) {
                $hasVariantImages = true;
                break;
            }
        }
        if (!$hasVariantImages) {
            echo "Add images to variant_images table for active variants\n";
        } else {
            echo "Check variant stock levels (may be out of stock)\n";
        }
    }

    echo "\n" . str_repeat('-', 80) . "\n\n";
}

echo str_repeat('=', 80) . "\n";
echo "\n✅ Investigation complete!\n";
echo "\nTo fix these products:\n";
echo "1. Add images to the database (product_images or variant_images table)\n";
echo "2. Run: php artisan cache:warmup\n";
echo "3. Refresh browser: Ctrl+F5\n";
