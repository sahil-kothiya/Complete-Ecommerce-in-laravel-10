<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Brand;
use App\Models\Category;

$categorySlug = $argv[1] ?? 'electronics';
$brandSlug = $argv[2] ?? 'adidas';

$category = Category::where('slug', $categorySlug)->first();
if (!$category) {
    echo "No category\n";
    exit(1);
}

$brandId = Brand::where('slug', $brandSlug)->value('id');
if (!$brandId) {
    echo "No brand\n";
    exit(1);
}

$count = Product::where('status', 'active')
    ->where('cat_id', $category->id)
    ->where('brand_id', $brandId)
    ->count();

echo "Category {$categorySlug} brand {$brandSlug}: {$count} products\n";
