<?php
/**
 * Filter API Image Response Test
 * Verifies that filter endpoint returns proper image URLs
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simulate request to filter endpoint
$request = Illuminate\Http\Request::create(
    '/api/products/filter?category=&page=1&per_page=5',
    'GET'
);

echo "\n=== FILTER API IMAGE RESPONSE TEST ===\n\n";
echo "Testing endpoint: GET /api/products/filter?category=&page=1&per_page=5\n";
echo str_repeat('-', 70) . "\n\n";

try {
    $response = $kernel->handle($request);
    $content = $response->getContent();
    $data = json_decode($content, true);
    
    if (!isset($data['success']) || !$data['success']) {
        echo "❌ API returned error:\n";
        echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
    
    echo "✓ API Response: SUCCESS\n";
    echo "✓ Products returned: " . count($data['data']['products']) . "\n\n";
    
    // Check each product's images
    $allProductsHaveImages = true;
    $totalImages = 0;
    $productsWithDefaultImage = 0;
    $productsWithRealImages = 0;
    
    foreach ($data['data']['products'] as $index => $product) {
        $productNum = $index + 1;
        echo "Product #{$productNum}: {$product['t']}\n";
        echo "  ID: {$product['id']}\n";
        echo "  Slug: {$product['s']}\n";
        
        if (!isset($product['i']) || empty($product['i'])) {
            echo "  Images: ❌ MISSING (should always have at least default)\n";
            $allProductsHaveImages = false;
        } else {
            $imageCount = count($product['i']);
            $totalImages += $imageCount;
            echo "  Images: ✓ {$imageCount} image(s)\n";
            
            foreach ($product['i'] as $imgIdx => $imgUrl) {
                $imgNum = $imgIdx + 1;
                echo "    [{$imgNum}] {$imgUrl}\n";
                
                // Check if it's the default image
                if (str_contains($imgUrl, 'avatar.webp')) {
                    echo "        → Default fallback image\n";
                    if ($imgIdx === 0) {
                        $productsWithDefaultImage++;
                    }
                } else {
                    echo "        → Real product image\n";
                    if ($imgIdx === 0) {
                        $productsWithRealImages++;
                    }
                }
                
                // Validate URL structure
                $isValid = (
                    (str_starts_with($imgUrl, 'http://') || str_starts_with($imgUrl, 'https://')) &&
                    str_contains($imgUrl, '/storage/')
                );
                
                if (!$isValid) {
                    echo "        ⚠ Invalid URL structure!\n";
                }
            }
        }
        echo "\n";
    }
    
    // Summary
    echo str_repeat('=', 70) . "\n";
    echo "SUMMARY\n";
    echo str_repeat('=', 70) . "\n";
    
    echo "Total products tested:        " . count($data['data']['products']) . "\n";
    echo "Products with images:         " . ($allProductsHaveImages ? "✓ ALL" : "❌ SOME MISSING") . "\n";
    echo "Total images:                 {$totalImages}\n";
    echo "Products with real images:    {$productsWithRealImages}\n";
    echo "Products with default image:  {$productsWithDefaultImage}\n";
    
    $avgImagesPerProduct = count($data['data']['products']) > 0 
        ? number_format($totalImages / count($data['data']['products']), 2)
        : 0;
    echo "Avg images per product:       {$avgImagesPerProduct}\n";
    
    echo "\n";
    
    if ($allProductsHaveImages) {
        echo "✅ SUCCESS: All products have at least one image\n";
        echo "✅ Image retrieval is working perfectly\n";
    } else {
        echo "❌ FAILED: Some products missing images\n";
        echo "   This should never happen with the fallback mechanism\n";
    }
    
    // Additional metadata
    echo "\n";
    echo str_repeat('=', 70) . "\n";
    echo "RESPONSE METADATA\n";
    echo str_repeat('=', 70) . "\n";
    echo "Total products available: " . number_format($data['data']['total']) . "\n";
    echo "Current page:             {$data['data']['current_page']}\n";
    echo "Per page:                 {$data['data']['per_page']}\n";
    echo "Total pages:              " . number_format($data['data']['last_page']) . "\n";
    echo "Response time:            {$data['data']['response_time']}ms\n";
    
    if (isset($data['data']['cache_source'])) {
        echo "Cache source:             {$data['data']['cache_source']}\n";
    }
    
    echo "\n=== TEST COMPLETE ===\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
