<?php

/**
 * Direct test of filter logic without HTTP
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\FastFilterService;
use App\Services\RedisKeyManager;
use App\Models\Category;
use App\Models\Brand;
use Illuminate\Support\Facades\Redis;

echo "=== DIRECT FILTER LOGIC TEST ===\n\n";

// Test 1: Category filter only (Electronics, ID=4)
echo "TEST 1: Category Filter (Electronics)\n";
echo str_repeat('-', 50) . "\n";

$filterService = app(FastFilterService::class);

$filters = [
    'category_id' => 4, // Electronics
];

echo "Filters: " . json_encode($filters) . "\n";

$result = $filterService->getFilteredProductIds($filters);
echo "Result Count: {$result['count']}\n";
echo "Redis Key: " . ($result['key'] ?? 'NULL') . "\n";

if ($result['count'] > 0 && $result['key']) {
    // Get first 12 IDs
    $productIds = $filterService->getPaginatedIds($result['key'], 0, 12);
    echo "Sample Product IDs: " . implode(', ', $productIds) . "\n";
} else {
    echo "❌ No products found!\n";
}

echo "\n";

// Test 2: Category + Brand (HP ID=3)
echo "TEST 2: Category + Brand (Electronics + HP)\n";
echo str_repeat('-', 50) . "\n";

// First, find HP brand ID
$hp = Brand::where('slug', 'hp')->first();
if (!$hp) {
    echo "❌ HP brand not found!\n";
    exit(1);
}

echo "HP Brand ID: {$hp->id}\n";

$filters2 = [
    'category_id' => 4,
    'brands' => [$hp->id],
];

echo "Filters: " . json_encode($filters2) . "\n";

$result2 = $filterService->getFilteredProductIds($filters2);
echo "Result Count: {$result2['count']}\n";
echo "Redis Key: " . ($result2['key'] ?? 'NULL') . "\n";

if ($result2['count'] > 0 && $result2['key']) {
    $productIds2 = $filterService->getPaginatedIds($result2['key'], 0, 12);
    echo "Sample Product IDs: " . implode(', ', $productIds2) . "\n";
} else {
    echo "❌ No products found!\n";
}

echo "\n";

// Test 3: Category + Brand + Price
echo "TEST 3: Category + Brand + Price (Electronics + HP + 0-500)\n";
echo str_repeat('-', 50) . "\n";

$filters3 = [
    'category_id' => 4,
    'brands' => [$hp->id],
    'price_range' => '0-100', // Try the smallest range first
];

echo "Filters: " . json_encode($filters3) . "\n";

$result3 = $filterService->getFilteredProductIds($filters3);
echo "Result Count: {$result3['count']}\n";
echo "Redis Key: " . ($result3['key'] ?? 'NULL') . "\n";

if ($result3['count'] > 0 && $result3['key']) {
    $productIds3 = $filterService->getPaginatedIds($result3['key'], 0, 12);
    echo "Sample Product IDs: " . implode(', ', $productIds3) . "\n";
} else {
    echo "❌ No products found!\n";
    
    echo "\nDEBUG: Check individual indexes:\n";
    $catKey = RedisKeyManager::indexCategory(4);
    $brandKey = RedisKeyManager::indexBrand($hp->id);
    $priceKey = RedisKeyManager::indexPriceRange('0-100');
    
    echo "  Category Index ({$catKey}): " . Redis::connection()->scard($catKey) . " products\n";
    echo "  Brand Index ({$brandKey}): " . Redis::connection()->scard($brandKey) . " products\n";
    echo "  Price Index ({$priceKey}): " . Redis::connection()->scard($priceKey) . " products\n";
    
    // Try intersection manually
    $tempKey = 'temp:test:intersect';
    Redis::connection()->sinterstore($tempKey, $catKey, $brandKey, $priceKey);
    $intersectCount = Redis::connection()->scard($tempKey);
    echo "  Manual Intersection: {$intersectCount} products\n";
    Redis::connection()->del($tempKey);
}

echo "\n=== END TESTS ===\n";
