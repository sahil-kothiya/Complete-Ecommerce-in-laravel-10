<?php
/**
 * Test Filter API with Detailed Logging
 * This script simulates a filter request and tracks image retrieval
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Http\Controllers\UltraFastFilterController;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n=== FILTER API TEST WITH IMAGE LOGGING ===\n\n";

// Clear previous logs for this test
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    file_put_contents($logFile, '');
    echo "✓ Cleared previous logs\n\n";
}

// Test 1: Simple category filter
echo "TEST 1: Category Filter (First 5 Products)\n";
echo str_repeat('-', 70) . "\n";

try {
    $request = Request::create('/api/filters', 'GET', [
        'category' => 'electronics',
        'per_page' => 5,
        'page' => 1
    ]);
    
    $controller = new UltraFastFilterController(
        app(\App\Services\FastFilterService::class),
        app(\App\Services\IndexHealthService::class)
    );
    
    echo "Making request: /api/filters?category=electronics&per_page=5&page=1\n\n";
    
    $response = $controller->getFilterData($request);
    $data = json_decode($response->getContent(), true);
    
    echo "Response Status: " . $response->getStatusCode() . "\n";
    echo "Products Returned: " . count($data['products'] ?? []) . "\n";
    echo "Total Found: " . ($data['pagination']['total'] ?? 0) . "\n\n";
    
    if (!empty($data['products'])) {
        echo "Product Details:\n";
        foreach ($data['products'] as $idx => $product) {
            echo "\n" . ($idx + 1) . ". Product #{$product['id']} - {$product['t']}\n";
            echo "   Has Variants: " . ($product['hv'] ? 'Yes' : 'No') . "\n";
            echo "   Images: " . count($product['i'] ?? []) . "\n";
            
            if (!empty($product['i'])) {
                foreach ($product['i'] as $imgIdx => $url) {
                    echo "     - Image " . ($imgIdx + 1) . ": " . $url . "\n";
                    
                    // Check if it's a default image
                    if (strpos($url, 'avatar.webp') !== false) {
                        echo "       ⚠ WARNING: Using default fallback image!\n";
                    }
                }
            } else {
                echo "     ⚠ WARNING: No images in response!\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n\n" . str_repeat('=', 70) . "\n";
echo "CHECKING LOGS\n";
echo str_repeat('=', 70) . "\n\n";

// Read and display relevant log entries
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    
    $relevantLogs = array_filter($lines, function($line) {
        return strpos($line, 'Image') !== false 
            || strpos($line, 'BATCH FETCH') !== false
            || strpos($line, 'image') !== false
            || strpos($line, 'ImageHelper') !== false;
    });
    
    if (empty($relevantLogs)) {
        echo "⚠ No image-related logs found\n";
        echo "\nShowing last 50 lines of log:\n";
        echo str_repeat('-', 70) . "\n";
        echo implode("\n", array_slice($lines, -50));
    } else {
        echo "Found " . count($relevantLogs) . " image-related log entries:\n\n";
        foreach ($relevantLogs as $log) {
            echo $log . "\n";
        }
    }
} else {
    echo "❌ Log file not found: {$logFile}\n";
}

echo "\n\n=== TEST COMPLETE ===\n\n";
echo "For full logs, check: {$logFile}\n\n";
