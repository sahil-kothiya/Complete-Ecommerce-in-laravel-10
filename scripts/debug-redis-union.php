<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "=== REDIS INDEX DEBUG ===\n\n";

echo "Price index counts:\n";
echo "  ec:idx:price:0-100: " . Redis::scard('ec:idx:price:0-100') . "\n";
echo "  ec:idx:price:100-500: " . Redis::scard('ec:idx:price:100-500') . "\n";
echo "  ec:idx:price:500-1000: " . Redis::scard('ec:idx:price:500-1000') . "\n\n";

echo "Testing SUNIONSTORE manually:\n";
$tempKey = 'test:manual:union:' . time();
$result = Redis::sunionstore($tempKey, 'ec:idx:price:0-100', 'ec:idx:price:100-500');
echo "  SUNIONSTORE returned: $result\n";
echo "  Union set count: " . Redis::scard($tempKey) . "\n";

// Sample a few IDs
$sampleIds = Redis::srandmember($tempKey, 5);
echo "  Sample IDs: " . implode(', ', $sampleIds ?: []) . "\n";

Redis::del($tempKey);

// Check key types
echo "\nKey types:\n";
echo "  ec:idx:price:0-100 type: " . Redis::type('ec:idx:price:0-100') . "\n";
echo "  ec:idx:price:100-500 type: " . Redis::type('ec:idx:price:100-500') . "\n";

// Try KEYS pattern to see what exists
echo "\nAll price-related keys:\n";
$allPriceKeys = Redis::keys('ec:idx:price:*');
foreach ($allPriceKeys as $key) {
    echo "  $key: " . Redis::scard($key) . " members\n";
}
