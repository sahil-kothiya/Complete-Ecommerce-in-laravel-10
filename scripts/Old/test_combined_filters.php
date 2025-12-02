<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Brand;
use Illuminate\Support\Facades\DB;

// Test discount filter
$brandSlug = 'hp';
$minDiscount = 50;
$minRating = 4;

$brandId = Brand::where('slug', $brandSlug)->value('id');

echo "Testing combined filters:\n";
echo "Brand: {$brandSlug} ({$brandId})\n";
echo "Min discount: {$minDiscount}%\n";
echo "Min rating: {$minRating}\n\n";

// Test 1: Brand only
$count1 = Product::where('status', 'active')
    ->where('brand_id', $brandId)
    ->count();
echo "Brand only: {$count1} products\n";

// Test 2: Brand + Discount
$count2 = Product::where('status', 'active')
    ->where('brand_id', $brandId)
    ->where(function ($q) use ($minDiscount) {
        $q->where(function ($subQ) use ($minDiscount) {
            $subQ->where('has_variants', false)
                 ->where('base_discount', '>=', $minDiscount);
        })
        ->orWhereIn('id', function ($subQ) use ($minDiscount) {
            $subQ->select('product_variants.product_id')
                 ->from('product_variants')
                 ->where('product_variants.status', 'active')
                 ->where('product_variants.discount', '>=', $minDiscount);
        });
    })
    ->count();
echo "Brand + Discount: {$count2} products\n";

// Test 3: Check if product_reviews table exists
$hasReviews = DB::select("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'product_reviews')")[0]->exists ?? false;
echo "Reviews table exists: " . ($hasReviews ? 'yes' : 'no') . "\n";

if ($hasReviews) {
    // Test with ratings
    $count3 = Product::where('status', 'active')
        ->where('brand_id', $brandId)
        ->where(function ($q) use ($minDiscount) {
            $q->where(function ($subQ) use ($minDiscount) {
                $subQ->where('has_variants', false)
                     ->where('base_discount', '>=', $minDiscount);
            })
            ->orWhereIn('id', function ($subQ) use ($minDiscount) {
                $subQ->select('product_variants.product_id')
                     ->from('product_variants')
                     ->where('product_variants.status', 'active')
                     ->where('product_variants.discount', '>=', $minDiscount);
            });
        })
        ->whereIn('id', function ($subQ) use ($minRating) {
            $subQ->select('product_id')
                 ->from('product_reviews')
                 ->groupBy('product_id')
                 ->havingRaw('AVG(CAST(rate AS DECIMAL(3,2))) >= ?', [$minRating]);
        })
        ->count();
    echo "Brand + Discount + Rating: {$count3} products\n";
}
