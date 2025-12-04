<?php
/**
 * Test Sort Variations
 * 
 * Verifies that different sortBy parameters return different product orders
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "🧪 Testing Sort Variations\n";
echo str_repeat("=", 60) . "\n\n";

// Get category 4 products
$categoryId = 4;
$limit = 12;

echo "📊 Category $categoryId - First 12 Products\n\n";

// Test 1: Latest (ID DESC)
echo "1. Latest (id DESC):\n";
$latestIds = DB::table('products')
    ->where('status', 'active')
    ->where('cat_id', $categoryId)
    ->orderByDesc('id')
    ->limit($limit)
    ->pluck('id')
    ->toArray();
echo "   IDs: " . implode(', ', array_slice($latestIds, 0, 6)) . "...\n";
echo "   First ID: {$latestIds[0]}, Last ID: {$latestIds[11]}\n\n";

// Test 2: Price Low to High
echo "2. Price Low to High:\n";
$priceLowIds = DB::table('products')
    ->where('status', 'active')
    ->where('cat_id', $categoryId)
    ->orderByRaw('base_price * (1 - COALESCE(base_discount, 0) / 100.0) ASC')
    ->limit($limit)
    ->pluck('id')
    ->toArray();
echo "   IDs: " . implode(', ', array_slice($priceLowIds, 0, 6)) . "...\n";
echo "   First ID: {$priceLowIds[0]}, Last ID: {$priceLowIds[11]}\n\n";

// Test 3: Price High to Low
echo "3. Price High to Low:\n";
$priceHighIds = DB::table('products')
    ->where('status', 'active')
    ->where('cat_id', $categoryId)
    ->orderByRaw('base_price * (1 - COALESCE(base_discount, 0) / 100.0) DESC')
    ->limit($limit)
    ->pluck('id')
    ->toArray();
echo "   IDs: " . implode(', ', array_slice($priceHighIds, 0, 6)) . "...\n";
echo "   First ID: {$priceHighIds[0]}, Last ID: {$priceHighIds[11]}\n\n";

// Verification
echo "✓ Verification:\n";
echo "  - Latest vs Price Low: " . ($latestIds !== $priceLowIds ? "✓ DIFFERENT" : "❌ SAME") . "\n";
echo "  - Latest vs Price High: " . ($latestIds !== $priceHighIds ? "✓ DIFFERENT" : "❌ SAME") . "\n";
echo "  - Price Low vs Price High: " . ($priceLowIds !== $priceHighIds ? "✓ DIFFERENT" : "❌ SAME") . "\n\n";

if ($latestIds === $priceLowIds || $latestIds === $priceHighIds) {
    echo "❌ WARNING: Different sort orders returning SAME products!\n";
    echo "   This indicates the sorting logic is not working correctly.\n";
} else {
    echo "✓ SUCCESS: Different sort orders return different products as expected!\n";
}

echo "\n" . str_repeat("=", 60) . "\n";
