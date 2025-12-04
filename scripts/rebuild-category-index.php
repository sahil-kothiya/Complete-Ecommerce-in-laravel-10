<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ProductIndexService;

$indexService = app(ProductIndexService::class);

echo "=== QUICK CATEGORY INDEX REBUILD ===\n\n";

try {
    $count = $indexService->buildCategoryIndex();
    echo "\n✓ Category index build completed!\n";
    echo "Categories indexed: $count\n";
} catch (\Exception $e) {
    echo "\n❌ Error building category index:\n";
    echo $e->getMessage()."\n";
}
