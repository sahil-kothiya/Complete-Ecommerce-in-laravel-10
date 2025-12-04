<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

echo "=== CLEARING ALL CACHES ===\n\n";

// Clear Laravel cache
echo "1. Clearing Laravel application cache...\n";
Cache::flush();
echo "   ✓ Done\n\n";

// Clear Redis cache (all keys matching filter patterns)
echo "2. Clearing Redis filter caches...\n";
$patterns = [
    'ec:cache:*',
    'ec:tmp:*',
    'laravel_cache:*',
];

$totalDeleted = 0;
foreach ($patterns as $pattern) {
    $keys = Redis::keys($pattern);
    if (!empty($keys)) {
        $deleted = Redis::del(...$keys);
        echo "   Deleted $deleted keys matching '$pattern'\n";
        $totalDeleted += $deleted;
    }
}

echo "   Total deleted: $totalDeleted\n\n";

echo "✓ All caches cleared!\n";
echo "Next API request will use fresh Redis indexes.\n";
