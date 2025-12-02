<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$images = App\Models\ProductImage::where('product_id', 10000001)
    ->orderBy('sort_order', 'asc')
    ->take(3)
    ->get();

echo "Query count: " . $images->count() . "\n";
foreach ($images as $img) {
    echo $img->image_path . ' => ' . $img->url . "\n";
}
