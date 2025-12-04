<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FastFilterService;
use App\Services\RedisKeyManager;
use Illuminate\Support\Facades\Redis;

$filterService = app(FastFilterService::class);

echo "=== PRICE RANGE FILTER TEST ===\n\n";

// Test 1: Price range 0-500 (should union 0-100 + 100-500)
echo "Test 1: Price range 0-500\n";
$filters = [
    'category_id' => 4,
    'price_range' => '0-500',
];

$result = $filterService->getFilteredProductIds($filters);
echo 'Result count: '.$result['count']."\n";
echo 'Result key: '.($result['key'] ?? 'null')."\n\n";

// Verify: Get expected count by manual union
echo "Verification (manual calculation):\n";
$key1 = RedisKeyManager::indexPriceRange('0-100');
$key2 = RedisKeyManager::indexPriceRange('100-500');
$catKey = RedisKeyManager::indexCategory(4);

echo 'Category 4 count: '.Redis::scard($catKey)."\n";
echo 'Price 0-100 count: '.Redis::scard($key1)."\n";
echo 'Price 100-500 count: '.Redis::scard($key2)."\n";

// Manual intersection test
$tempUnion = 'test:price:union';
Redis::sunionstore($tempUnion, $key1, $key2);
$unionCount = Redis::scard($tempUnion);
echo 'Price 0-500 union count: '.$unionCount."\n";

$tempIntersect = 'test:final';
Redis::sinterstore($tempIntersect, $catKey, $tempUnion);
$finalCount = Redis::scard($tempIntersect);
echo 'Final intersection (category + price): '.$finalCount."\n";

// Cleanup
Redis::del($tempUnion, $tempIntersect);

echo "\n=== Test 2: Price range 100-500 (exact match) ===\n";
$filters2 = [
    'category_id' => 4,
    'price_range' => '100-500',
];
$result2 = $filterService->getFilteredProductIds($filters2);
echo 'Result count: '.$result2['count']."\n\n";

echo "\n=== Test 3: Price range 0-100 (exact match) ===\n";
$filters3 = [
    'category_id' => 4,
    'price_range' => '0-100',
];
$result3 = $filterService->getFilteredProductIds($filters3);
echo 'Result count: '.$result3['count']."\n\n";

echo "Done!\n";
