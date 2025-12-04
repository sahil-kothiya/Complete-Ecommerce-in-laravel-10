<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RedisKeyManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

echo "=== PRICE FILTER VERIFICATION ===\n\n";

// Check product 9999998 (should be $649.54, out of 0-500 range)
$testProductId = 9999998;

echo "Product ID: $testProductId\n";
echo "Expected price: ~$649.54 (OUT OF 0-500 RANGE)\n\n";

// Check Redis index membership
echo "Redis Index Membership:\n";
echo '  In ec:idx:price:0-100? '.(Redis::sismember('ec:idx:price:0-100', $testProductId) ? 'YES ❌' : 'NO ✓')."\n";
echo '  In ec:idx:price:100-500? '.(Redis::sismember('ec:idx:price:100-500', $testProductId) ? 'YES ❌' : 'NO ✓')."\n";
echo '  In ec:idx:price:500-1000? '.(Redis::sismember('ec:idx:price:500-1000', $testProductId) ? 'YES ✓' : 'NO ❌')."\n\n";

// Get actual price from database
$product = DB::table('products')->where('id', $testProductId)->first();

if ($product->has_variants) {
    $minPrice = DB::table('product_variants')
        ->where('product_id', $testProductId)
        ->where('status', 'active')
        ->min(DB::raw('price * (1 - COALESCE(discount, 0) / 100.0)'));
    echo 'Actual price from DB: $'.round($minPrice, 2)." (variant product)\n\n";
} else {
    $price = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);
    echo 'Actual price from DB: $'.round($price, 2)." (non-variant product)\n\n";
}

// Now test the filter intersection
echo "=== Testing Filter Intersection ===\n";
$catKey = RedisKeyManager::indexCategory(4);
$priceUnionKey = 'test:price:0-500:union';

// Create union of 0-100 + 100-500
Redis::sunionstore($priceUnionKey, 'ec:idx:price:0-100', 'ec:idx:price:100-500');
echo 'Created price union (0-500): '.Redis::scard($priceUnionKey)." products\n";

// Intersect with category
$finalKey = 'test:final:cat4:price0-500';
Redis::sinterstore($finalKey, $catKey, $priceUnionKey);
echo 'Final intersection (cat 4 + price 0-500): '.Redis::scard($finalKey)." products\n\n";

// Check if our test product is in the final set
$inFinalSet = Redis::sismember($finalKey, $testProductId);
echo "Product $testProductId in final filtered set? ".($inFinalSet ? 'YES ❌ (BUG!)' : 'NO ✓ (CORRECT)')."\n\n";

// Sample 20 random products from final set and check their prices
echo "=== Sampling 20 products from filtered set ===\n";
$sampleIds = Redis::srandmember($finalKey, 20);

foreach ($sampleIds as $id) {
    $p = DB::table('products')->where('id', $id)->first();

    if ($p->has_variants) {
        $price = DB::table('product_variants')
            ->where('product_id', $id)
            ->where('status', 'active')
            ->min(DB::raw('price * (1 - COALESCE(discount, 0) / 100.0)'));
    } else {
        $price = $p->base_price * (1 - ($p->base_discount ?? 0) / 100);
    }

    $status = $price <= 500 ? '✓' : '❌ OUT OF RANGE';
    echo "  Product $id: $".round($price, 2)." $status\n";
}

// Cleanup
Redis::del($priceUnionKey, $finalKey);

echo "\n=== CONCLUSION ===\n";
echo "If all sampled products are ≤ $500, Redis filtering works correctly.\n";
echo "If API still returns >$500 products, issue is in controller/cache layer.\n";
