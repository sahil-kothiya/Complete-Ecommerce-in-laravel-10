<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$slug = 'mens-shirts-1';
$product = App\Models\Product::where('slug', $slug)
    ->where('status','active')
    ->with(['images','variants.images'])
    ->first();
if(!$product){
    echo "Product not found\n";
    exit(1);
}

echo "Product images:\n";
foreach($product->images as $img){
    echo "- " . $img->url . "\n";
}

echo "\nVariant images:\n";
foreach($product->variants as $variant){
    foreach($variant->images as $img){
        echo "- Variant {$variant->id}: " . $img->url . "\n";
    }
}
