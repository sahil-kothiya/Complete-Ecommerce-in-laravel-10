<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Redis;
use App\Services\ElasticsearchService;

echo "=== SYSTEM STATUS CHECK ===\n\n";

// Check Redis
try {
    $indexCount = count(Redis::keys('index:*'));
    echo "✅ Redis: CONNECTED\n";
    echo "   - Index keys: {$indexCount}\n";

    if ($indexCount > 0) {
        // Sample some indexes
        $categoryKeys = count(Redis::keys('index:category:*'));
        $brandKeys = count(Redis::keys('index:brand:*'));
        $priceKeys = count(Redis::keys('index:price:*'));

        echo "   - Category indexes: {$categoryKeys}\n";
        echo "   - Brand indexes: {$brandKeys}\n";
        echo "   - Price indexes: {$priceKeys}\n";
    } else {
        echo "   ⚠️  NO INDEXES BUILT! Run: php artisan indexes:manage build\n";
    }
} catch (Exception $e) {
    echo "❌ Redis: FAILED - {$e->getMessage()}\n";
}

echo "\n";

// Check Elasticsearch
try {
    $es = app(ElasticsearchService::class);

    if ($es->isAvailable()) {
        echo "✅ Elasticsearch: ONLINE\n";

        $stats = $es->getIndexStats();
        $docCount = $stats['document_count'] ?? 0;

        echo "   - Indexed products: " . number_format($docCount) . "\n";
        echo "   - Index size: " . ($stats['index_size'] ?? 'Unknown') . "\n";

        if ($docCount == 0) {
            echo "   ⚠️  NO PRODUCTS INDEXED! Run: php artisan elasticsearch:index-all --chunk=2000\n";
        }
    } else {
        echo "❌ Elasticsearch: OFFLINE\n";
        echo "   Start it: cd D:\\elasticsearch-9.0.2\\bin; .\\elasticsearch.bat\n";
    }
} catch (Exception $e) {
    echo "❌ Elasticsearch: ERROR - {$e->getMessage()}\n";
}

echo "\n";

// Check database product count
try {
    $productCount = DB::table('products')->where('status', 'active')->count();
    echo "✅ Database: CONNECTED\n";
    echo "   - Active products: " . number_format($productCount) . "\n";

    if ($productCount >= 1000000) {
        echo "   - Scale: " . round($productCount / 1000000, 1) . "M products (LARGE SCALE)\n";
        echo "   ⚠️  Note: Redis index build takes 3-8 minutes\n";
        echo "   ⚠️  Note: Elasticsearch indexing takes 15-25 HOURS\n";
        echo "   💡 Tip: Use partial indexing for quick testing\n";
    }
} catch (Exception $e) {
    echo "❌ Database: FAILED - {$e->getMessage()}\n";
}

echo "\n=== END STATUS CHECK ===\n";
