<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Category;

$categorySlug = $argv[1] ?? 'electronics';
$discount = (int) ($argv[2] ?? 50);

$category = Category::where('slug', $categorySlug)->firstOrFail();

$count = Product::where('status','active')
    ->where('cat_id',$category->id)
    ->where('base_discount','>=',$discount)
    ->count();

echo "Category {$category->slug} products with discount >= {$discount}: {$count}\n";
