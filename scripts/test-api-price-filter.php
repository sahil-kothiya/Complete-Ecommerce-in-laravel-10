<?php
require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\FastFilterService;
use Illuminate\Support\Facades\DB;

$filterService = app(FastFilterService::class);

echo "=== TEST PRICE FILTER 0-500 ===\n\n";

// Simulate exact API request
$filters = [
    'category_id' => 4,
    'price_range' => '0-500'
];

// Get filtered IDs
$result = $filterService->getFilteredProductIds($filters);
echo "Total matching products: " . number_format($result['count']) . "\n";
echo "Redis key: " . $result['key'] . "\n\n";

// Get first 12 product IDs (page 1)
$productIds = $filterService->getPaginatedIds($result['key'], 0, 12);
echo "First 12 product IDs:\n";
print_r($productIds);
echo "\n";

// Check prices for these products
echo "Checking actual prices:\n";
foreach ($productIds as $id) {
    $product = DB::table('products')->where('id', $id)->first();
    
    if ($product->has_variants) {
        $minPrice = DB::table('product_variants')
            ->where('product_id', $id)
            ->where('status', 'active')
            ->selectRaw('MIN(price * (1 - COALESCE(discount, 0) / 100.0)) as min_price')
            ->value('min_price');
        
        $status = $minPrice <= 500 ? '✓' : '❌ OUT OF RANGE';
        echo "  Product $id: $" . number_format($minPrice, 2) . " (variant) $status\n";
    } else {
        $price = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);
        $status = $price <= 500 ? '✓' : '❌ OUT OF RANGE';
        echo "  Product $id: $" . number_format($price, 2) . " (base) $status\n";
    }
}

echo "\nIf any products show ❌ OUT OF RANGE, Redis indexes are incorrect.\n";
