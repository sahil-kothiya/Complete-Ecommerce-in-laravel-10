<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;

echo "GET TIME VERIFICATION - Redis → Retrieval\n";
echo str_repeat('=', 80) . "\n\n";

$version = RedisCacheService::getVersion();

// Test 1: Featured Products
echo "Test 1: Featured Products Cache\n";
echo str_repeat('-', 80) . "\n";

$featuredKey = "cache:homepage:products:featured_v{$version}";
$featured = RedisCacheService::get($featuredKey);

if (!$featured) {
    echo "❌ Featured products not cached! Run: php artisan cache:warmup\n";
    exit(1);
}

echo "Retrieved: " . count($featured) . " products\n";

$corruptionIssues = [];
$totalProducts = 0;

foreach ($featured as $product) {
    $totalProducts++;

    // Verify data integrity
    if (!isset($product->id)) {
        $corruptionIssues[] = "Product missing ID field";
        continue;
    }

    if (!isset($product->images) || !is_array($product->images)) {
        $corruptionIssues[] = "Product {$product->id}: images not an array";
        continue;
    }

    if (empty($product->images)) {
        $corruptionIssues[] = "Product {$product->id}: images array is empty";
        continue;
    }

    foreach ($product->images as $idx => $image) {
        if (!isset($image['image_path'])) {
            $corruptionIssues[] = "Product {$product->id}: image[{$idx}] missing image_path";
        }
        if (!isset($image['alt_text'])) {
            $corruptionIssues[] = "Product {$product->id}: image[{$idx}] missing alt_text";
        }
    }
}

if (empty($corruptionIssues)) {
    echo "✅ All data retrieved correctly - no corruption\n";
} else {
    echo "❌ Found " . count($corruptionIssues) . " corruption issues:\n";
    foreach ($corruptionIssues as $issue) {
        echo "  - {$issue}\n";
    }
}

echo "\n";

// Test 2: Category Products
echo "Test 2: Category Products Cache\n";
echo str_repeat('-', 80) . "\n";

$categoryKey = "cache:homepage:category_products_v{$version}";
$categories = RedisCacheService::get($categoryKey);

if (!$categories) {
    echo "❌ Category products not cached!\n";
} else {
    echo "Retrieved: " . count($categories) . " categories\n";

    $categoryCorruption = [];

    foreach ($categories as $slug => $category) {
        if (!isset($category['products']) || !is_array($category['products'])) {
            $categoryCorruption[] = "Category {$slug}: products not an array";
            continue;
        }

        foreach ($category['products'] as $product) {
            $totalProducts++;

            if (!isset($product->images) || !is_array($product->images)) {
                $categoryCorruption[] = "Category {$slug}, Product {$product->id}: images not an array";
            }
        }
    }

    if (empty($categoryCorruption)) {
        echo "✅ All category data retrieved correctly\n";
    } else {
        echo "❌ Found " . count($categoryCorruption) . " issues:\n";
        foreach ($categoryCorruption as $issue) {
            echo "  - {$issue}\n";
        }
    }
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// Test 3: Serialization Round-Trip
echo "Test 3: Serialization Round-Trip Test\n";
echo str_repeat('-', 80) . "\n";

$testData = (object)[
    'id' => 99999,
    'title' => 'Test Product',
    'images' => [
        ['image_path' => '/storage/products/test.webp', 'alt_text' => 'Test'],
    ],
];

// Store
RedisCacheService::put('test:serialization', $testData, 60);

// Retrieve
$retrieved = RedisCacheService::get('test:serialization');

// Verify
$roundTripSuccess = true;
if ($retrieved->id !== $testData->id) {
    echo "❌ ID mismatch\n";
    $roundTripSuccess = false;
}
if ($retrieved->title !== $testData->title) {
    echo "❌ Title mismatch\n";
    $roundTripSuccess = false;
}
if ($retrieved->images[0]['image_path'] !== $testData->images[0]['image_path']) {
    echo "❌ Image path mismatch\n";
    $roundTripSuccess = false;
}

if ($roundTripSuccess) {
    echo "✅ Serialization round-trip successful - no data loss\n";
}

// Cleanup
RedisCacheService::forget('test:serialization');

echo "\n" . str_repeat('=', 80) . "\n\n";

// Summary
echo "SUMMARY:\n";
echo "  Total products checked: {$totalProducts}\n";
echo "  Corruption issues: " . (count($corruptionIssues) + count($categoryCorruption ?? [])) . "\n";
echo "  Round-trip test: " . ($roundTripSuccess ? 'PASS' : 'FAIL') . "\n\n";

if (empty($corruptionIssues) && empty($categoryCorruption ?? []) && $roundTripSuccess) {
    echo "✅ 100% SUCCESS - GET operations working perfectly!\n";
    echo "   No data corruption during retrieval\n";
    echo "   Serialization/deserialization intact\n";
} else {
    echo "⚠️  Issues found - check details above\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
