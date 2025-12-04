<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

echo "=== DEBUG PRODUCT 9999998 PRICE ===\n\n";

$productId = 9999998;

// Get product info
$product = DB::table('products')->where('id', $productId)->first();
echo "Product ID: $productId\n";
echo 'Has variants: '.($product->has_variants ? 'YES' : 'NO')."\n";
echo 'Base price: $'.($product->base_price ?? 'null')."\n";
echo 'Base discount: '.($product->base_discount ?? 'null')."%\n\n";

if ($product->has_variants) {
    echo "=== VARIANTS ===\n";
    $variants = DB::table('product_variants')
        ->where('product_id', $productId)
        ->where('status', 'active')
        ->get();

    echo 'Total variants: '.count($variants)."\n\n";

    foreach ($variants as $variant) {
        $originalPrice = $variant->price;
        $discount = $variant->discount ?? 0;
        $discountedPrice = $originalPrice * (1 - $discount / 100);

        echo "Variant #{$variant->id}:\n";
        echo '  Original: $'.number_format($originalPrice, 2)."\n";
        echo "  Discount: {$discount}%\n";
        echo '  Final: $'.number_format($discountedPrice, 2)."\n";
        echo "  Stock: {$variant->stock}\n";

        // Check which price range this should be in
        if ($discountedPrice <= 100) {
            echo "  Should be in: 0-100 ✓\n";
        } elseif ($discountedPrice <= 500) {
            echo "  Should be in: 100-500 ✓\n";
        } elseif ($discountedPrice <= 1000) {
            echo "  Should be in: 500-1000\n";
        } else {
            echo "  Should be in: 1000+\n";
        }
        echo "\n";
    }

    // Get MINIMUM discounted price
    $minDiscountedPrice = DB::table('product_variants')
        ->where('product_id', $productId)
        ->where('status', 'active')
        ->selectRaw('MIN(price * (1 - COALESCE(discount, 0) / 100.0)) as min_price')
        ->value('min_price');

    echo 'MINIMUM discounted price across all variants: $'.number_format($minDiscountedPrice, 2)."\n";
    echo 'This product SHOULD be indexed in: ';
    if ($minDiscountedPrice <= 100) {
        echo "0-100\n";
    } elseif ($minDiscountedPrice <= 500) {
        echo "100-500\n";
    } elseif ($minDiscountedPrice <= 1000) {
        echo "500-1000\n";
    } else {
        echo "1000+\n";
    }
} else {
    $discountedPrice = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);
    echo 'Final price: $'.number_format($discountedPrice, 2)."\n";
}

echo "\n=== REDIS INDEX MEMBERSHIP ===\n";
echo 'In ec:idx:price:0-100? '.(Redis::sismember('ec:idx:price:0-100', $productId) ? 'YES' : 'NO')."\n";
echo 'In ec:idx:price:100-500? '.(Redis::sismember('ec:idx:price:100-500', $productId) ? 'YES' : 'NO')."\n";
echo 'In ec:idx:price:500-1000? '.(Redis::sismember('ec:idx:price:500-1000', $productId) ? 'YES' : 'NO')."\n";
echo 'In ec:idx:price:1000-5000? '.(Redis::sismember('ec:idx:price:1000-5000', $productId) ? 'YES' : 'NO')."\n";

echo "\n=== CONCLUSION ===\n";
echo "If minimum variant price is ≤ $500, product should appear in 0-500 filter.\n";
echo "If Redis index shows it's in 500+ range, index rebuild didn't work correctly.\n";
