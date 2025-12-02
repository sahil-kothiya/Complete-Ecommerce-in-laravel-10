<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;
use App\Models\Product;

echo "IDENTIFYING FALLBACK PRODUCTS IN CACHE\n";
echo str_repeat('=', 80) . "\n\n";

$version = RedisCacheService::getVersion();
$categoryKey = "cache:homepage:category_products_v{$version}";
$categories = RedisCacheService::get($categoryKey);

$fallbackProducts = [];

if ($categories) {
    foreach ($categories as $slug => $category) {
        echo "Checking category: {$category['title']}\n";

        foreach ($category['products'] as $product) {
            $isFallback = strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false;

            if ($isFallback) {
                $fallbackProducts[] = [
                    'category' => $category['title'],
                    'product_id' => $product->id,
                    'title' => $product->title,
                ];
            }
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n\n";

if (empty($fallbackProducts)) {
    echo "✅ No fallback products found!\n";
} else {
    echo "Found " . count($fallbackProducts) . " products with fallback:\n\n";

    foreach ($fallbackProducts as $item) {
        echo "Category: {$item['category']}\n";
        echo "Product ID: {$item['product_id']}\n";
        echo "Title: {$item['title']}\n";

        // Check database
        $product = Product::with(['images', 'variants.images'])->find($item['product_id']);

        if ($product) {
            echo "Database check:\n";
            echo "  Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
            echo "  Product images: " . $product->images->count() . "\n";

            if ($product->images->count() > 0) {
                foreach ($product->images as $img) {
                    echo "    - {$img->image_path}\n";
                }
            }

            echo "  Variants: " . $product->variants->count() . "\n";

            if ($product->has_variants && $product->variants->count() > 0) {
                $hasActiveVariants = false;
                foreach ($product->variants as $v) {
                    if ($v->status === 'active' && $v->stock > 0) {
                        $hasActiveVariants = true;
                        echo "    Variant {$v->id}: stock={$v->stock}, images={$v->images->count()}\n";
                    }
                }

                if (!$hasActiveVariants) {
                    echo "  ⚠️  NO ACTIVE VARIANTS WITH STOCK!\n";
                }
            }

            // Recommendation
            echo "\n  💡 SOLUTION: ";
            if ($product->images->count() == 0 && (!$product->has_variants || $product->variants->count() == 0)) {
                echo "Add product images OR unflag as featured\n";
            } elseif ($product->has_variants) {
                $hasActiveVariantWithImages = false;
                foreach ($product->variants as $v) {
                    if ($v->status === 'active' && $v->stock > 0 && $v->images->count() > 0) {
                        $hasActiveVariantWithImages = true;
                        break;
                    }
                }

                if (!$hasActiveVariantWithImages) {
                    echo "Add images to active variants with stock OR restock variants with images\n";
                } else {
                    echo "Issue with query - active variant has images but not being loaded\n";
                }
            }
        }

        echo "\n" . str_repeat('-', 80) . "\n\n";
    }
}

echo str_repeat('=', 80) . "\n";
