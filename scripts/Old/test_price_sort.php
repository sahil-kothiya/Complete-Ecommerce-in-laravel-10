<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Http\Controllers\UltraFastFilterController;
use App\Services\FastFilterService;
use Illuminate\Http\Request;

try {
    $filterService = app(FastFilterService::class);
    $controller = new UltraFastFilterController($filterService);

    $path = 'ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9';

    $request = Request::create('/api/filters/' . $path, 'GET', [
        'show' => 12,
        'page' => 1,
        'sortBy' => 'price_low_high'
    ]);

    $response = $controller->getFilterData($request, $path);
    $data = json_decode($response->getContent(), true);

    if (!$data || !isset($data['ok'])) {
        echo "Error in response\n";
        print_r($data);
        exit(1);
    }

    echo "Total: " . ($data['m']['tot'] ?? 0) . "\n";
    echo "Products returned: " . count($data['p'] ?? []) . "\n\n";

    foreach (array_slice($data['p'] ?? [], 0, 12) as $product) {
        $price = $product['pr']['f'] ?? $product['pr']['o'] ?? 0;
        echo "Product {$product['id']}: ${price}\n";
    }

} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
