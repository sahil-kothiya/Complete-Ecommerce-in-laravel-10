<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheLogger;

echo "Analyzing Redis Cache Operations Log...\n";
echo str_repeat('=', 80) . "\n\n";

$analysis = RedisCacheLogger::analyzeLog();

if (isset($analysis['error'])) {
    echo "❌ Error: {$analysis['error']}\n";
    echo "Please run cache warmup first: php artisan cache:warmup\n";
    exit(1);
}

echo "📊 Summary:\n";
echo "  Total operations: {$analysis['total_operations']}\n";
echo "  - PUT operations: {$analysis['put_operations']}\n";
echo "  - GET operations: {$analysis['get_operations']}\n";
echo "  - TRANSFORM operations: {$analysis['transform_operations']}\n";
echo "\n";

if (!empty($analysis['fallback_images'])) {
    echo "⚠️  Products with FALLBACK images:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($analysis['fallback_images'] as $item) {
        echo "  Product ID: {$item['product_id']}\n";
        echo "    Stage: {$item['stage']}\n";
        echo "    Has variants: " . ($item['has_variants'] ? 'Yes' : 'No') . "\n";
        echo "    Product images count: {$item['product_images_count']}\n";
        echo "    Variants count: {$item['variants_count']}\n";
        echo "\n";
    }

    echo "\n❌ Found " . count($analysis['fallback_images']) . " products with fallback images!\n";
    echo "These products should be investigated in the database.\n";
} else {
    echo "✅ No fallback images found - all products have proper images!\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "To see detailed logs, check: storage/logs/redis-cache-operations.log\n";
