<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;

$productIds = [97607, 99368];

echo "Checking cached data for problem products...\n";
echo str_repeat('=', 80) . "\n\n";

// Check individual product cards
foreach ($productIds as $productId) {
    $key = "product:card:{$productId}";
    $cached = RedisCacheService::get($key);

    echo "Product ID: {$productId}\n";

    if (!$cached) {
        echo "  ❌ NOT in cache (individual card)\n";
    } else {
        echo "  ✅ Found in individual cache\n";
        echo "  Title: {$cached->title}\n";
        echo "  Has variants: " . ($cached->has_variants ? 'Yes' : 'No') . "\n";
        echo "  Images count: " . count($cached->images) . "\n";
        echo "  First image: " . json_encode($cached->images[0]) . "\n";
    }
    echo "\n";
}

echo str_repeat('=', 80) . "\n";

// Check featured products cache
$version = RedisCacheService::getVersion();
$key = "cache:homepage:products:featured_v{$version}";
$featuredProducts = RedisCacheService::get($key);

if ($featuredProducts) {
    echo "Featured products cache: " . count($featuredProducts) . " products\n\n";

    foreach ($productIds as $productId) {
        $found = false;
        foreach ($featuredProducts as $product) {
            if ($product->id == $productId) {
                $found = true;
                echo "Product {$productId} in featured cache:\n";
                echo "  Images: " . json_encode($product->images) . "\n\n";
                break;
            }
        }

        if (!$found) {
            echo "Product {$productId}: NOT in featured cache\n\n";
        }
    }
} else {
    echo "❌ Featured products cache is empty!\n";
}
