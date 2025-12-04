<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;
use App\Services\RedisKeyManager;

echo "=== DEBUG PRICE UNION ===\n\n";

// Check if price indexes exist
echo "Price index counts:\n";
$ranges = ['0-100', '100-500', '500-1000', '1000-5000', '5000-10000', '10000+'];
foreach ($ranges as $range) {
    $key = RedisKeyManager::indexPriceRange($range);
    $count = Redis::scard($key);
    $exists = Redis::exists($key);
    echo "  $key: " . number_format($count) . " products (exists: " . ($exists ? 'YES' : 'NO') . ")\n";
}

echo "\n\nManual SUNIONSTORE test:\n";
$key1 = RedisKeyManager::indexPriceRange('0-100');
$key2 = RedisKeyManager::indexPriceRange('100-500');
$unionKey = 'test:price:union:' . time();

echo "  Key 1: $key1 (" . Redis::scard($key1) . " items)\n";
echo "  Key 2: $key2 (" . Redis::scard($key2) . " items)\n";

$result = Redis::sunionstore($unionKey, $key1, $key2);
echo "  SUNIONSTORE result: $result\n";
echo "  Union count: " . Redis::scard($unionKey) . "\n";

// Sample some IDs
$sampleIds = Redis::srandmember($unionKey, 5);
echo "  Sample IDs: " . implode(', ', $sampleIds ?: ['none']) . "\n";

Redis::del($unionKey);

echo "\n\nConclusion:\n";
if ($result > 0) {
    echo "✓ SUNIONSTORE works! Issue is in FastFilterService logic.\n";
} else {
    echo "❌ SUNIONSTORE returns 0! Price indexes are empty or don't exist.\n";
}
