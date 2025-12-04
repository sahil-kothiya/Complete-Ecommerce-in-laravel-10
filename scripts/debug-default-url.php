<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use App\Services\RedisKeyManager;

echo "=== DEBUG DEFAULT CATEGORY URL ===\n\n";

// Category 4 is Electronics
$categoryId = 4;

echo "Category ID: $categoryId\n\n";

// Check Redis category index
$catKey = RedisKeyManager::indexCategory($categoryId);
echo "Redis Key: $catKey\n";
echo "Redis exists: " . (Redis::exists($catKey) ? 'YES' : 'NO') . "\n";
echo "Redis count: " . number_format(Redis::scard($catKey)) . "\n\n";

// Check database
$dbCount = DB::table('products')
    ->where('status', 'active')
    ->where('cat_id', $categoryId)
    ->count();

echo "Database count: " . number_format($dbCount) . "\n\n";

if (Redis::scard($catKey) === 0) {
    echo "❌ PROBLEM: Redis category index is EMPTY!\n";
    echo "API will return 0 results and fallback to similar products.\n\n";
    
    echo "SOLUTION: Rebuild category indexes\n";
} elseif (Redis::scard($catKey) !== $dbCount) {
    echo "⚠️  WARNING: Redis count doesn't match database!\n";
    echo "Difference: " . number_format(abs(Redis::scard($catKey) - $dbCount)) . "\n\n";
} else {
    echo "✓ Redis index is correct!\n";
    echo "Issue must be in controller logic.\n";
}

// Sample some product IDs from Redis
if (Redis::scard($catKey) > 0) {
    echo "\nSample product IDs from Redis:\n";
    $sampleIds = Redis::srandmember($catKey, 5);
    print_r($sampleIds);
}
