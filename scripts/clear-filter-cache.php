<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "=== CLEARING REDIS FILTER CACHE ===\n\n";

// Clear all filter result caches
$pattern = 'ec:cache:filter:*';
$keys = Redis::keys($pattern);
echo "Found " . count($keys) . " filter cache keys\n";

if (!empty($keys)) {
    $deleted = Redis::del(...$keys);
    echo "Deleted $deleted keys\n";
}

// Clear all temp filter keys
$tempPattern = 'ec:tmp:flt:*';
$tempKeys = Redis::keys($tempPattern);
echo "Found " . count($tempKeys) . " temp filter keys\n";

if (!empty($tempKeys)) {
    $deleted = Redis::del(...$tempKeys);
    echo "Deleted $deleted keys\n";
}

echo "\n✓ Cache cleared! API will now use fresh Redis indexes.\n";
