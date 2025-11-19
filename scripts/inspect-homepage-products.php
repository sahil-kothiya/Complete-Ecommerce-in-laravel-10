<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(App\Http\Controllers\FrontendController::class);

$method = new ReflectionMethod($controller, 'getHomepageProductsData');
$method->setAccessible(true);
$products = $method->invoke($controller, null, 3600, false);

foreach (array_slice($products, 0, 3) as $product) {
    echo "Product ID: {$product->id}\n";
    echo "Images:\n";
    foreach ($product->images as $img) {
        echo " - {$img['image_path']}\n";
    }
    echo "Variants images:\n";
    foreach ($product->variants as $variant) {
        echo "   Variant {$variant['id']} stock {$variant['stock']}\n";
    }
    echo "----\n";
}
