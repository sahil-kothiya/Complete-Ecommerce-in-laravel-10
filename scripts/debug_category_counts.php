<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;

$categorySlug = $argv[1] ?? 'electronics';

$category = Category::where('slug', $categorySlug)->first();
if (!$category) {
    echo "No category\n";
    exit(1);
}

$total = Product::where('status','active')->where('cat_id',$category->id)->count();

$withBrands = Product::where('status','active')->where('cat_id',$category->id)->whereNotNull('brand_id')->count();

$topBrands = Product::where('status','active')
    ->where('cat_id', $category->id)
    ->selectRaw('brand_id, COUNT(*) as cnt')
    ->groupBy('brand_id')
    ->orderByDesc('cnt')
    ->limit(5)
    ->get();

echo "Category {$categorySlug}: total {$total}, with brand {$withBrands}\n";

foreach ($topBrands as $row) {
    echo " - brand {$row->brand_id}: {$row->cnt}\n";
}
