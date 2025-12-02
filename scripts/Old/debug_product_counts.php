<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;
use App\Models\Brand;

$slug = $argv[1] ?? 'adidas';
$brandId = Brand::where('slug', $slug)->value('id');

if (!$brandId) {
    echo "No brand found for {$slug}\n";
    exit(1);
}

$count = Product::where('status', 'active')->where('brand_id', $brandId)->count();

echo "Brand {$slug} ({$brandId}) has {$count} active products\n";
