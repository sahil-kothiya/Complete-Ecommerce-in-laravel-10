<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Http\Controllers\UltraFastFilterController;
use App\Models\ProductImage;
use App\Services\FastFilterService;
use Illuminate\Http\Request;

$filterService = app(FastFilterService::class);
$controller = new UltraFastFilterController($filterService);

$path = 'ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9';

$params = [
    'show' => 12,
    'page' => 1,
    'sortBy' => 'price_low_high',
];

parse_str($argv[1] ?? '', $cliParams);
$params = array_merge($params, $cliParams);

$request = Request::create('/api/filters/'.$path, 'GET', $params);

$response = $controller->getFilterData($request, $path);
$data = json_decode($response->getContent(), true);

echo 'Applied params: '.json_encode($params)."\n";
echo 'Total products: '.($data['m']['tot'] ?? 0)."\n";
echo 'Source: '.($data['m']['src'] ?? 'unknown')."\n";
echo 'Similar flag: '.json_encode($data['m']['sim'] ?? null)."\n\n";

foreach (array_slice($data['p'] ?? [], 0, 12) as $product) {
    echo "Product {$product['id']} - {$product['t']}\n";
    echo 'Images: '.json_encode($product['i'] ?? [])."\n";

    $rawImages = ProductImage::where('product_id', $product['id'])->pluck('image_path')->toArray();
    echo 'Raw paths: '.json_encode($rawImages)."\n\n";
}
