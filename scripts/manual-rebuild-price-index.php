<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ProductIndexService;

$indexService = app(ProductIndexService::class);

echo "=== MANUAL PRICE INDEX BUILD ===\n\n";

try {
    $count = $indexService->buildPriceIndex();
    echo "\n✓ Price index build completed!\n";
    echo "Ranges indexed: $count\n";
} catch (\Exception $e) {
    echo "\n❌ Error building price index:\n";
    echo $e->getMessage()."\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString()."\n";
}
