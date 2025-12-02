<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Brand;

$brands = Brand::where('status', 'active')
    ->select(['id','title','slug'])
    ->orderBy('id')
    ->limit(20)
    ->get();

foreach ($brands as $brand) {
    echo $brand->id . " | " . $brand->title . " | " . $brand->slug . "\n";
}
