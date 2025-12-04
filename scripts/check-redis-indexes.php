<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "=== Redis Index Health Check ===\n\n";

// Check indexes
$keys = Redis::connection()->keys('ec:idx:*');
echo "Total indexes: " . count($keys) . "\n\n";

if (count($keys) > 0) {
    echo "Sample indexes:\n";
    foreach (array_slice($keys, 0, 10) as $key) {
        $count = Redis::connection()->scard($key);
        echo "  - $key => $count members\n";
    }
    
    echo "\n\nIndex breakdown:\n";
    $categories = count(array_filter($keys, fn($k) => str_contains($k, 'ec:idx:cat:')));
    $brands = count(array_filter($keys, fn($k) => str_contains($k, 'ec:idx:br:')));
    $prices = count(array_filter($keys, fn($k) => str_contains($k, 'ec:idx:price:')));
    $ratings = count(array_filter($keys, fn($k) => str_contains($k, 'ec:idx:rating:')));
    
    echo "  Categories: $categories\n";
    echo "  Brands: $brands\n";
    echo "  Prices: $prices\n";
    echo "  Ratings: $ratings\n";
} else {
    echo "❌ NO INDEXES FOUND!\n";
    echo "Run: php artisan indexes:manage build --force\n";
}
