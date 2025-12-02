<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$productIds = [95918, 99040, 97604, 97650];

foreach ($productIds as $pid) {
    echo "Product {$pid}:\n";

    $images = DB::table('product_images')->where('product_id', $pid)->get();

    echo "  Images: " . $images->count() . "\n";
    foreach ($images as $img) {
        echo "    - ID: {$img->id}, Path: {$img->image_path}, Sort: {$img->sort_order}\n";
    }
    echo "\n";
}
