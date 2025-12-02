<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(App\Http\Controllers\FrontendController::class);
$method = new ReflectionMethod($controller, 'getHomepageProductsData');
$method->setAccessible(true);

$products = $method->invoke($controller, null, 3600, false);
foreach ($products as $product) {
	$productImages = App\Models\ProductImage::where('product_id', $product->id)
		->orderBy('sort_order', 'asc')
		->take(3)
		->get();
	echo "DB images for product {$product->id}: " . $productImages->count() . "\n";
	foreach ($productImages as $img) {
		echo "  - " . $img->url . "\n";
	}
}
print_r($products[0]);
