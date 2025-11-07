<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Session Test Results:\n";
echo "====================\n\n";

// Test Laravel session
use Illuminate\Support\Facades\Session;

echo "Laravel Session Started: " . (Session::isStarted() ? 'Yes' : 'No') . "\n";

// Force start session if not started
if (!Session::isStarted()) {
    Session::start();
}

echo "Laravel Session ID: " . Session::getId() . "\n";
echo "Laravel Session Started: " . (Session::isStarted() ? 'Yes' : 'No') . "\n\n";

// Test adding a recent product using the service
echo "Testing RecentProductService...\n";

use App\Services\RecentProductService;
use App\Models\Product;

$service = new RecentProductService();

// Get a test product
$product = Product::where('status', 'active')->first();

if ($product) {
    echo "Test Product ID: " . $product->id . "\n";
    echo "Test Product Title: " . $product->title . "\n\n";

    echo "Tracking product view...\n";
    $result = $service->trackProductView($product->id);
    echo "Track Result: " . ($result ? 'Success' : 'Failed') . "\n\n";

    echo "Getting recent products...\n";
    $recentProducts = $service->getRecentProducts();
    echo "Recent Products Count: " . $recentProducts->count() . "\n";

    if ($recentProducts->count() > 0) {
        echo "\nRecent Products:\n";
        foreach ($recentProducts as $rp) {
            echo "  - {$rp->title} (ID: {$rp->id})\n";
        }
    }
} else {
    echo "No active products found!\n";
}

