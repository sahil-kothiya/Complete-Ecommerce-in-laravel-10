<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\File;

echo "Extracting product data from Redis cache log...\n";
echo str_repeat('=', 80) . "\n\n";

$logPath = base_path('storage/logs/redis-cache-operations.log');

if (!File::exists($logPath)) {
    echo "❌ Log file not found!\n";
    echo "Please run: php artisan cache:warmup\n";
    exit(1);
}

$content = File::get($logPath);
$entries = explode(str_repeat('-', 80), $content);

$products = [];
$putOperations = [];
$getOperations = [];

foreach ($entries as $entry) {
    $entry = trim($entry);
    if (empty($entry) || strpos($entry, 'Redis Cache Operations Log') !== false) {
        continue;
    }

    $data = json_decode($entry, true);
    if (!$data || !isset($data['operation'])) {
        continue;
    }

    // Track TRANSFORM operations
    if ($data['operation'] === 'TRANSFORM') {
        $productId = $data['product_id'];

        if (!isset($products[$productId])) {
            $products[$productId] = [];
        }

        $products[$productId][] = [
            'timestamp' => $data['timestamp'],
            'stage' => $data['stage'],
            'has_variants' => $data['has_variants'],
            'product_images_count' => $data['product_images_count'],
            'variants_count' => $data['variants_count'],
            'transformed_images_count' => $data['transformed_images_count'],
            'images' => $data['transformed_images'],
            'is_fallback' => $data['is_fallback'],
        ];
    }

    // Track PUT operations
    if ($data['operation'] === 'PUT') {
        $putOperations[] = [
            'timestamp' => $data['timestamp'],
            'key' => $data['key'],
            'context' => $data['context'],
            'data_structure' => $data['data_structure'],
            'serialized_size' => $data['serialized_size'],
        ];
    }

    // Track GET operations
    if ($data['operation'] === 'GET') {
        $getOperations[] = [
            'timestamp' => $data['timestamp'],
            'key' => $data['key'],
            'context' => $data['context'],
            'found' => $data['found'],
        ];
    }
}

// Export to JSON files
$productsFile = 'storage/logs/redis-products-data.json';
$putFile = 'storage/logs/redis-put-operations.json';
$getFile = 'storage/logs/redis-get-operations.json';

File::put(base_path($productsFile), json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
File::put(base_path($putFile), json_encode($putOperations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
File::put(base_path($getFile), json_encode($getOperations, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Data extracted successfully!\n\n";
echo "Products data:\n";
echo "  Total products: " . count($products) . "\n";
echo "  File: {$productsFile}\n\n";

echo "PUT operations:\n";
echo "  Total: " . count($putOperations) . "\n";
echo "  File: {$putFile}\n\n";

echo "GET operations:\n";
echo "  Total: " . count($getOperations) . "\n";
echo "  File: {$getFile}\n\n";

// Show products with fallback images
$fallbackProducts = array_filter($products, function($transforms) {
    return isset($transforms[0]['is_fallback']) && $transforms[0]['is_fallback'];
});

if (!empty($fallbackProducts)) {
    echo "⚠️  Products with fallback images:\n";
    foreach ($fallbackProducts as $productId => $transforms) {
        $transform = $transforms[0];
        echo "  - Product {$productId}: has_variants={$transform['has_variants']}, ";
        echo "product_images={$transform['product_images_count']}, ";
        echo "variants={$transform['variants_count']}\n";
        echo "    First image: {$transform['images'][0]['image_path']}\n";
    }
} else {
    echo "✅ No fallback images found!\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Check the JSON files for detailed data.\n";
