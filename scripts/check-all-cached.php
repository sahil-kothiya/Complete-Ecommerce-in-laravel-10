<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;

echo "Checking all cached homepage data...\n";
echo str_repeat('=', 80) . "\n\n";

$version = RedisCacheService::getVersion();

// Check category products
$categoryKey = "cache:homepage:category_products_v{$version}";
$categoryProducts = RedisCacheService::get($categoryKey);

if ($categoryProducts) {
    echo "Category Products Cache:\n";
    echo "  Categories: " . count($categoryProducts) . "\n\n";

    $productIds = [97607, 99368];

    foreach ($categoryProducts as $slug => $categoryData) {
        echo "  Category: {$categoryData['title']} ({$categoryData['count']} products)\n";

        foreach ($productIds as $searchId) {
            foreach ($categoryData['products'] as $product) {
                if ($product->id == $searchId) {
                    echo "    🔍 Found Product {$searchId}:\n";
                    echo "       Images: " . json_encode($product->images[0] ?? 'none') . "\n";
                }
            }
        }
    }
} else {
    echo "❌ Category products cache is empty!\n";
}

echo "\n" . str_repeat('=', 80) . "\n";

// Check featured products
$featuredKey = "cache:homepage:products:featured_v{$version}";
$featuredProducts = RedisCacheService::get($featuredKey);

if ($featuredProducts) {
    echo "Featured Products Cache: " . count($featuredProducts) . " products\n";

    foreach ($featuredProducts as $product) {
        echo "  Product {$product->id}: " . (strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false ? '❌ FALLBACK' : '✅ OK') . "\n";
    }
}
