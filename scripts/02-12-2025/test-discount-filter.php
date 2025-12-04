<?php

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Redis;
use App\Models\Brand;

echo "=== Discount Filter Debug ===\n\n";

// 1. Check category index
$categoryCount = Redis::scard('ec:idx:cat:15');
echo "Category 15 products: {$categoryCount}\n";

// 2. Check discount index
$discount50Count = Redis::scard('ec:idx:discount:50');
echo "Discount 50% products: {$discount50Count}\n";
echo "Discount 50 exists: " . (Redis::exists('ec:idx:discount:50') ? 'YES' : 'NO') . "\n\n";

// 3. Check brand slugs
$brands = ['hm', 'hp'];
$brandIds = Brand::whereIn('slug', $brands)->where('status', 'active')->pluck('id', 'slug')->toArray();
echo "Brand IDs:\n";
foreach ($brandIds as $slug => $id) {
    $count = Redis::scard("ec:idx:br:{$id}");
    echo "  - {$slug} (ID: {$id}): {$count} products\n";
}

// 4. Test intersection
echo "\n=== Testing Intersection ===\n";
$sets = ['ec:idx:cat:15'];
if (!empty($brandIds)) {
    foreach ($brandIds as $id) {
        $sets[] = "ec:idx:br:{$id}";
    }
}
echo "Sets to intersect: " . implode(', ', $sets) . "\n";

$tempKey = 'temp:test_intersection';
Redis::del($tempKey);

if (count($sets) === 1) {
    echo "Only one set, no intersection needed\n";
    $resultCount = Redis::scard($sets[0]);
} else {
    Redis::sinterstore($tempKey, ...$sets);
    $resultCount = Redis::scard($tempKey);
    Redis::del($tempKey);
}

echo "Result count WITHOUT discount: {$resultCount}\n\n";

// 5. Test WITH discount filter
$sets[] = 'ec:idx:discount:50';
echo "Adding discount filter...\n";
echo "Sets to intersect: " . implode(', ', $sets) . "\n";

Redis::del($tempKey);
Redis::sinterstore($tempKey, ...$sets);
$resultCountWithDiscount = Redis::scard($tempKey);
Redis::del($tempKey);

echo "Result count WITH discount 50%: {$resultCountWithDiscount}\n";

if ($resultCountWithDiscount === 0) {
    echo "\n✅ CORRECT: Discount filter properly returns 0 products\n";
} else {
    echo "\n❌ ERROR: Should be 0 but got {$resultCountWithDiscount}\n";
}
