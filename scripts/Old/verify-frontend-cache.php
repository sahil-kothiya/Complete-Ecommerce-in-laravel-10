<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\RedisCacheService;

echo "Frontend Cache Verification\n";
echo str_repeat('=', 80) . "\n\n";

$version = RedisCacheService::getVersion();

// 1. Check Featured Products
echo "1. FEATURED PRODUCTS (All Products Section)\n";
echo str_repeat('-', 80) . "\n";

$featuredKey = "cache:homepage:products:featured_v{$version}";
$featured = RedisCacheService::get($featuredKey);

if ($featured) {
    echo "✅ Featured products cached: " . count($featured) . " products\n\n";

    $fallbackCount = 0;
    $properCount = 0;

    echo "Product Details:\n";
    foreach ($featured as $product) {
        $isFallback = strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false;

        if ($isFallback) {
            $fallbackCount++;
            echo "  ❌ Product {$product->id}: FALLBACK\n";
        } else {
            $properCount++;
            echo "  ✅ Product {$product->id}: {$product->images[0]['image_path']}\n";
        }
    }

    echo "\nSummary:\n";
    echo "  Proper images: {$properCount}\n";
    echo "  Fallback images: {$fallbackCount}\n";
} else {
    echo "❌ Featured products NOT cached!\n";
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// 2. Check Category Products
echo "2. CATEGORY PRODUCTS\n";
echo str_repeat('-', 80) . "\n";

$categoryKey = "cache:homepage:category_products_v{$version}";
$categories = RedisCacheService::get($categoryKey);

if ($categories) {
    echo "✅ Category products cached: " . count($categories) . " categories\n\n";

    $totalProducts = 0;
    $totalFallbacks = 0;
    $totalProper = 0;

    foreach ($categories as $slug => $category) {
        echo "Category: {$category['title']}\n";

        $catFallback = 0;
        $catProper = 0;

        foreach ($category['products'] as $product) {
            $totalProducts++;
            $isFallback = strpos($product->images[0]['image_path'] ?? '', 'no-image.png') !== false;

            if ($isFallback) {
                $catFallback++;
                $totalFallbacks++;
            } else {
                $catProper++;
                $totalProper++;
            }
        }

        echo "  Products: {$category['count']} (✅ {$catProper} proper, ";
        if ($catFallback > 0) {
            echo "❌ {$catFallback} fallback)\n";
        } else {
            echo "🎉 0 fallback)\n";
        }
    }

    echo "\nTotal Summary:\n";
    echo "  Total products: {$totalProducts}\n";
    echo "  Proper images: {$totalProper}\n";
    echo "  Fallback images: {$totalFallbacks}\n";
} else {
    echo "❌ Category products NOT cached!\n";
}

echo "\n" . str_repeat('=', 80) . "\n\n";

// 3. Overall Health Check
echo "3. OVERALL SYSTEM HEALTH\n";
echo str_repeat('-', 80) . "\n";

$totalCached = ($featured ? count($featured) : 0) + ($categories ? array_sum(array_column($categories, 'count')) : 0);
$totalFallbacksOverall = ($fallbackCount ?? 0) + ($totalFallbacks ?? 0);

echo "Total cached products: {$totalCached}\n";
echo "Total with fallback: {$totalFallbacksOverall}\n";
echo "Success rate: " . ($totalCached > 0 ? round((($totalCached - $totalFallbacksOverall) / $totalCached) * 100, 2) : 0) . "%\n\n";

if ($totalFallbacksOverall == 0) {
    echo "🎉 PERFECT! All cached products have proper images!\n";
} elseif ($totalFallbacksOverall <= 2) {
    echo "✅ GOOD! Only {$totalFallbacksOverall} products with fallback (likely have no images in DB)\n";
} else {
    echo "⚠️  WARNING! {$totalFallbacksOverall} products with fallback - investigate further\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "\n💡 TIP: Refresh your browser with Ctrl+F5 to see the latest cached data\n";
echo "📊 For detailed logs, run: php scripts/analyze-redis-log.php\n";
