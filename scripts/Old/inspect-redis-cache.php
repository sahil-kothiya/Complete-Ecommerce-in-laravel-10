<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;

$version = RedisCacheService::getVersion();
$key = 'cache:homepage:products:featured_v' . $version;

echo "Cache key: {$key}\n";
echo str_repeat('=', 80) . "\n\n";

$data = RedisCacheService::get($key);

if (!$data) {
    echo "❌ Cache not found!\n";
    exit(1);
}

echo "✅ Found " . count($data) . " products\n\n";

// Check first 3 products
for ($i = 0; $i < min(3, count($data)); $i++) {
    $product = $data[$i];

    echo "Product #{$i}: ID={$product->id}, Title={$product->title}\n";
    echo "  - Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
    echo "  - Images count: " . count($product->images) . "\n";

    if (!empty($product->images)) {
        echo "  - First image: " . json_encode($product->images[0]) . "\n";
    }

    if ($product->has_variants && !empty($product->variants)) {
        echo "  - Variants count: " . count($product->variants) . "\n";
        $firstVariant = $product->variants[0];
        echo "  - Variant 0 ID: {$firstVariant['id']}\n";
        echo "  - Variant 0 images: " . (isset($firstVariant['images']) ? count($firstVariant['images']) : 'NOT SET') . "\n";

        if (isset($firstVariant['images']) && !empty($firstVariant['images'])) {
            echo "  - Variant 0 first image: " . json_encode($firstVariant['images'][0]) . "\n";
        } else {
            echo "  - ⚠️ Variant has NO images array!\n";
        }
    }

    echo "\n";
}

// Check the specific problematic products
$problemIds = [99064, 99150, 95989, 95507, 97350];
echo str_repeat('=', 80) . "\n";
echo "Checking problematic products...\n\n";

foreach ($data as $product) {
    if (in_array($product->id, $problemIds)) {
        echo "🔍 Product ID={$product->id}, Title={$product->title}\n";
        echo "  - Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
        echo "  - Product images count: " . count($product->images) . "\n";

        if (!empty($product->images)) {
            foreach ($product->images as $idx => $img) {
                echo "    [{$idx}] " . json_encode($img) . "\n";
            }
        }

        if ($product->has_variants && !empty($product->variants)) {
            echo "  - Variants: " . count($product->variants) . "\n";
            foreach ($product->variants as $vIdx => $variant) {
                echo "    Variant [{$vIdx}] ID={$variant['id']}\n";
                if (isset($variant['images'])) {
                    echo "      - Images: " . count($variant['images']) . "\n";
                    foreach ($variant['images'] as $iIdx => $img) {
                        echo "        [{$iIdx}] " . json_encode($img) . "\n";
                    }
                } else {
                    echo "      - ❌ NO images array!\n";
                }
            }
        }
        echo "\n";
    }
}
