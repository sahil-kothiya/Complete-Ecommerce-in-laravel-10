<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Http\Controllers\UltraFastFilterController;
use Illuminate\Http\Request;
use App\Services\FastFilterService;

echo "Testing Filter API...\n\n";

// Create controller instance
$filterService = app(FastFilterService::class);
$controller = new UltraFastFilterController($filterService);

// Test 1: Simple category filter
echo "Test 1: Category filter (no filters)\n";
$request = Request::create('/api/filters/ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9', 'GET', [
    'show' => 12,
    'page' => 1
]);

$response = $controller->getFilterData($request, 'ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9');

$content = $response->getContent();
$data = json_decode($content, true);

if ($data && isset($data['ok']) && $data['ok']) {
    echo "✅ SUCCESS!\n";
    echo "  Products returned: " . count($data['p']) . "\n";
    echo "  Total products: " . ($data['m']['tot'] ?? 0) . "\n";
    echo "  Response time: " . ($data['m']['ms'] ?? 0) . "ms\n";
    echo "  Source: " . ($data['m']['src'] ?? 'unknown') . "\n";
    echo "  Filters available: " . (isset($data['f']) ? 'Yes' : 'No') . "\n";

    if (isset($data['f']['br']) && is_array($data['f']['br'])) {
        echo "  Brands available: " . count($data['f']['br']) . "\n";
    }

    if (isset($data['f']['pr'])) {
        echo "  Price range: " . ($data['f']['pr']['mn'] ?? 0) . " - " . ($data['f']['pr']['mx'] ?? 0) . "\n";
    }
} else {
    echo "❌ FAILED!\n";
    echo "Response: " . $content . "\n";
}

echo "\n";

// Test 2: With brand filter
echo "Test 2: With brand filter\n";
$request = Request::create('/api/filters/ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9', 'GET', [
    'show' => 12,
    'page' => 1,
    'brands' => 'brand-1'
]);

$response = $controller->getFilterData($request, 'ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9');

$content = $response->getContent();
$data = json_decode($content, true);

if ($data && isset($data['ok']) && $data['ok']) {
    echo "✅ SUCCESS!\n";
    echo "  Products returned: " . count($data['p']) . "\n";
    echo "  Total products: " . ($data['m']['tot'] ?? 0) . "\n";
    echo "  Response time: " . ($data['m']['ms'] ?? 0) . "ms\n";
} else {
    echo "❌ FAILED!\n";
    echo "Response: " . $content . "\n";
}

echo "\n";

// Test 3: Performance check - multiple requests
echo "Test 3: Performance test (5 requests)\n";
$times = [];

for ($i = 1; $i <= 5; $i++) {
    $start = microtime(true);
    $request = Request::create('/api/filters/ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9', 'GET', [
        'show' => 12,
        'page' => 1
    ]);

    $response = $controller->getFilterData($request, 'ZXlKcGRpSTZJbXhJZDBsNE56TmxXRGxOZHpCYWExVjBPRXRXZDJjOVBTSXNJblpoYkhWbElqb2lkelp0Wm1GUWNUVnlOVlJXWWxoRU1FMVZiME5RTmxVMVZWSXpOemt6TjJSRU1XZHdaRGRYVVM5cFdUMGlMQ0p0WVdNaU9pSTNNRGxrTnpZelpXVmpaRE0zTUdOall6STVaRFU0WTJVek1EVmhOVE01TmpRM09UTTFPRE5tT1RWbU4yWTBNekExWldRNVl6QTBZMk01TXpBM01ETmlJaXdpZEdGbklqb2lJbjA9');

    $end = microtime(true);
    $times[] = round(($end - $start) * 1000, 2);
}

echo "  Request times: " . implode('ms, ', $times) . "ms\n";
echo "  Average: " . round(array_sum($times) / count($times), 2) . "ms\n";
echo "  Min: " . min($times) . "ms\n";
echo "  Max: " . max($times) . "ms\n";

echo "\n";
echo "All tests completed!\n";
