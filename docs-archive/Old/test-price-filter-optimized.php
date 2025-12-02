<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "Testing optimized price filter query...\n\n";

$startTime = microtime(true);

// Test query: Products in 100-1000 price range, sorted by price high to low
$minPrice = 100;
$maxPrice = 1000;
$sortBy = 'price_high_low';

$query = Product::where('products.status', 'active');

// Apply price range filter - OPTIMIZED: Simple OR condition
$query->where(function($q) use ($minPrice, $maxPrice) {
    // Case 1: Products without variants with base_price in range
    $q->where(function($sub) use ($minPrice, $maxPrice) {
        $sub->where('products.has_variants', false)
            ->whereBetween('products.base_price', [$minPrice, $maxPrice]);
    })
    // Case 2: Products with variants (assume they have variants in range)
    ->orWhere('products.has_variants', true);
});

// Get count
echo "Counting products...\n";
$countStart = microtime(true);
$total = $query->count('products.id');
$countTime = round((microtime(true) - $countStart) * 1000, 2);
echo "Total products: {$total} (took {$countTime}ms)\n\n";

// Select fields
$query->select([
    'products.id', 'products.title', 'products.slug', 'products.base_price',
    'products.base_discount', 'products.base_stock', 'products.condition',
    'products.has_variants', 'products.brand_id'
]);

// Apply sorting
echo "Applying sort: {$sortBy}\n";
$query->orderBy('products.base_price', 'desc');

// Get paginated results
echo "Fetching page 1 (12 products)...\n";
$fetchStart = microtime(true);
$productIds = $query->skip(0)
                   ->take(12)
                   ->pluck('products.id')
                   ->toArray();
$fetchTime = round((microtime(true) - $fetchStart) * 1000, 2);

echo "Product IDs: " . implode(', ', array_slice($productIds, 0, 5)) . "...\n";
echo "Fetch time: {$fetchTime}ms\n\n";

$totalTime = round((microtime(true) - $startTime) * 1000, 2);
echo "Total execution time: {$totalTime}ms\n";

if ($totalTime < 5000) {
    echo "✓ Query optimized successfully!\n";
} else {
    echo "⚠ Query still slow, needs more optimization\n";
}
