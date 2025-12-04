<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FastFilterService;
use App\Services\RedisKeyManager;
use Illuminate\Support\Facades\Redis;

echo "=== Test Discount Filter Logic ===\n\n";

$filterService = app(FastFilterService::class);

// Test 1: Category + Discount 50
$filters = [
    'category_id' => 4,
    'min_discount' => 50,
];

echo "Test 1: Category 4 + Discount 50%\n";
echo "Filters: " . json_encode($filters) . "\n";

$result = $filterService->getFilteredProductIds($filters);
echo "Result count: {$result['count']}\n";
echo "Result key: {$result['key']}\n\n";

if ($result['count'] === 0) {
    echo "✅ CORRECT: Returns 0 products (no 50% discounts exist)\n\n";
} else {
    echo "❌ ERROR: Should return 0 but got {$result['count']}\n\n";
}

// Test 2: Category + Brands + Discount 50
$filters2 = [
    'category_id' => 4,
    'brands' => [5, 10],  // HM, HP
    'min_discount' => 50,
];

echo "Test 2: Category 4 + Brands [HM, HP] + Discount 50%\n";
echo "Filters: " . json_encode($filters2) . "\n";

$result2 = $filterService->getFilteredProductIds($filters2);
echo "Result count: {$result2['count']}\n";
echo "Result key: {$result2['key']}\n\n";

if ($result2['count'] === 0) {
    echo "✅ CORRECT: Returns 0 products (no products match all filters)\n\n";
} else {
    echo "❌ ERROR: Should return 0 but got {$result2['count']}\n\n";
}

// Test 3: Category only (should have products)
$filters3 = [
    'category_id' => 4,
];

echo "Test 3: Category 4 only (control test)\n";
echo "Filters: " . json_encode($filters3) . "\n";

$result3 = $filterService->getFilteredProductIds($filters3);
echo "Result count: {$result3['count']}\n";

if ($result3['count'] > 0) {
    echo "✅ CORRECT: Category has products\n";
} else {
    echo "❌ ERROR: Category should have products\n";
}
