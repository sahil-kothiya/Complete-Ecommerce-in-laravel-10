#!/usr/bin/env php
<?php

/**
 * Filter System Health Check Script
 * Tests the optimized filter system performance
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Category;
use App\Models\Brand;
use App\Services\FastFilterService;

echo "=== FILTER SYSTEM HEALTH CHECK ===" . PHP_EOL . PHP_EOL;

// 1. Check Redis connection
echo "1. Redis Connection..." . PHP_EOL;
try {
    Redis::ping();
    echo "   SUCCESS: Redis Connected" . PHP_EOL;
} catch (Exception $e) {
    echo "   FAILED: Redis Not Connected - " . $e->getMessage() . PHP_EOL;
    echo PHP_EOL . "Please start Redis and try again." . PHP_EOL;
    exit(1);
}

// 2. Check Redis indexes
echo PHP_EOL . "2. Redis Indexes..." . PHP_EOL;
$categoryCount = count(Redis::keys('index:category:*'));
$brandCount = count(Redis::keys('index:brand:*'));
$priceCount = count(Redis::keys('index:price:*'));
$ratingCount = count(Redis::keys('index:rating:*'));
$discountCount = count(Redis::keys('index:discount:*'));

echo "   Category indexes: " . $categoryCount . PHP_EOL;
echo "   Brand indexes: " . $brandCount . PHP_EOL;
echo "   Price indexes: " . $priceCount . PHP_EOL;
echo "   Rating indexes: " . $ratingCount . PHP_EOL;
echo "   Discount indexes: " . $discountCount . PHP_EOL;

$totalIndexes = $categoryCount + $brandCount + $priceCount + $ratingCount + $discountCount;

if ($totalIndexes === 0) {
    echo PHP_EOL . "   WARNING: No indexes found!" . PHP_EOL;
    echo "   Run: php artisan indexes:manage build --force" . PHP_EOL;
} else {
    echo "   SUCCESS: Total indexes = " . $totalIndexes . PHP_EOL;
}

// 3. Check database
echo PHP_EOL . "3. Database..." . PHP_EOL;
$productCount = DB::table('products')->where('status', 'active')->count();
$categoryDbCount = Category::where('status', 'active')->count();
$brandDbCount = Brand::where('status', 'active')->count();

echo "   Active products: " . number_format($productCount) . PHP_EOL;
echo "   Active categories: " . $categoryDbCount . PHP_EOL;
echo "   Active brands: " . $brandDbCount . PHP_EOL;

// 4. Test filter performance
echo PHP_EOL . "4. Filter Performance Test..." . PHP_EOL;

if ($totalIndexes === 0) {
    echo "   Skipping performance test (no indexes)" . PHP_EOL;
} else {
    try {
        $filterService = app(FastFilterService::class);
        
        // Get first active category
        $category = Category::where('status', 'active')->first();
        
        if ($category) {
            $filters = ['category_id' => $category->id];
            
            echo "   Testing category filter: " . $category->slug . PHP_EOL;
            
            $startTime = microtime(true);
            $result = $filterService->getFilteredProductIds($filters);
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            
            echo "   Products found: " . ($result['count'] ?? 0) . PHP_EOL;
            echo "   Response time: " . $elapsed . "ms" . PHP_EOL;
            
            if ($elapsed < 300) {
                echo "   SUCCESS: Performance EXCELLENT (<300ms)" . PHP_EOL;
            } elseif ($elapsed < 1000) {
                echo "   SUCCESS: Performance GOOD (<1s)" . PHP_EOL;
            } elseif ($elapsed < 3000) {
                echo "   WARNING: Performance ACCEPTABLE (<3s)" . PHP_EOL;
            } else {
                echo "   FAILED: Performance SLOW (>" . $elapsed . "ms)" . PHP_EOL;
                echo "   Consider rebuilding indexes" . PHP_EOL;
            }
        } else {
            echo "   WARNING: No active categories found" . PHP_EOL;
        }
    } catch (Exception $e) {
        echo "   FAILED: Filter test error - " . $e->getMessage() . PHP_EOL;
    }
}

// 5. Cache status
echo PHP_EOL . "5. Cache Status..." . PHP_EOL;
$cacheDriver = config('cache.default');
echo "   Cache driver: " . $cacheDriver . PHP_EOL;

try {
    Cache::put('health_check_test', 'ok', 60);
    $test = Cache::get('health_check_test');
    if ($test === 'ok') {
        echo "   SUCCESS: Cache Working" . PHP_EOL;
    } else {
        echo "   FAILED: Cache Not Working" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "   FAILED: Cache Error - " . $e->getMessage() . PHP_EOL;
}

echo PHP_EOL . "=== HEALTH CHECK COMPLETE ===" . PHP_EOL;

// Summary
echo PHP_EOL . "SUMMARY:" . PHP_EOL;
echo "--------" . PHP_EOL;

$status = "HEALTHY";
$issues = [];

if ($totalIndexes === 0) {
    $status = "NEEDS SETUP";
    $issues[] = "Redis indexes need to be built";
}

if ($productCount === 0) {
    $status = "NO DATA";
    $issues[] = "No products in database";
}

echo "System Status: " . $status . PHP_EOL;

if (!empty($issues)) {
    echo PHP_EOL . "Issues Found:" . PHP_EOL;
    foreach ($issues as $issue) {
        echo "  - " . $issue . PHP_EOL;
    }
    echo PHP_EOL . "NEXT STEPS:" . PHP_EOL;
    echo "  1. Build indexes: php artisan indexes:manage build --force" . PHP_EOL;
    echo "  2. Test again: php test-filter-health.php" . PHP_EOL;
} else {
    echo PHP_EOL . "SUCCESS: System is ready for optimal performance!" . PHP_EOL;
    echo "  Expected filter response time: 1-3 seconds" . PHP_EOL;
}

echo PHP_EOL;
