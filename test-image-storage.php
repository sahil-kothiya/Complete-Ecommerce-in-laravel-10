<?php
/**
 * Image Storage & Retrieval Test
 * Tests that images are stored and retrieved properly using ImageHelper
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Helpers\ImageHelper;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VariantImage;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n=== IMAGE STORAGE & RETRIEVAL TEST ===\n\n";

// 1. Test ProductImage storage format
echo "1. Testing ProductImage Database Storage:\n";
echo str_repeat('-', 60) . "\n";

$productImages = ProductImage::with('product:id,title')
    ->take(5)
    ->get();

if ($productImages->isEmpty()) {
    echo "❌ No product images found in database\n\n";
} else {
    foreach ($productImages as $img) {
        $rawPath = $img->image_path;
        $generatedUrl = $img->url; // Uses ImageHelper via getUrlAttribute()
        $manualUrl = ImageHelper::productImageUrl($rawPath);
        
        echo "Product: {$img->product->title}\n";
        echo "  Raw Path (DB): {$rawPath}\n";
        echo "  Model URL:     {$generatedUrl}\n";
        echo "  Manual URL:    {$manualUrl}\n";
        echo "  Match: " . ($generatedUrl === $manualUrl ? "✓" : "✗") . "\n";
        
        // Check if path format is correct (filename only or with prefix)
        $hasStoragePrefix = strpos($rawPath, 'storage/') === 0;
        $hasProductsPrefix = strpos($rawPath, 'products/') === 0;
        $isFilenameOnly = !$hasStoragePrefix && !$hasProductsPrefix && !str_contains($rawPath, '/');
        
        echo "  Format: ";
        if ($isFilenameOnly) {
            echo "✓ Filename only (optimal)\n";
        } elseif ($hasProductsPrefix && !$hasStoragePrefix) {
            echo "⚠ Relative path (products/...)\n";
        } elseif ($hasStoragePrefix) {
            echo "⚠ Full path (storage/...)\n";
        } else {
            echo "❌ Unknown format\n";
        }
        echo "\n";
    }
}

// 2. Test VariantImage storage format
echo "\n2. Testing VariantImage Database Storage:\n";
echo str_repeat('-', 60) . "\n";

$variantImages = VariantImage::with('variant.product:id,title')
    ->take(5)
    ->get();

if ($variantImages->isEmpty()) {
    echo "❌ No variant images found in database\n\n";
} else {
    foreach ($variantImages as $img) {
        $rawPath = $img->image_path;
        $generatedUrl = $img->url; // Uses ImageHelper via getUrlAttribute()
        $manualUrl = ImageHelper::variantImageUrl($rawPath);
        
        echo "Variant: {$img->variant->product->title} - Variant #{$img->variant->id}\n";
        echo "  Raw Path (DB): {$rawPath}\n";
        echo "  Model URL:     {$generatedUrl}\n";
        echo "  Manual URL:    {$manualUrl}\n";
        echo "  Match: " . ($generatedUrl === $manualUrl ? "✓" : "✗") . "\n";
        
        // Check if path format is correct
        $hasStoragePrefix = strpos($rawPath, 'storage/') === 0;
        $hasVariantsPrefix = strpos($rawPath, 'products/variants/') === 0;
        $isFilenameOnly = !$hasStoragePrefix && !$hasVariantsPrefix && !str_contains($rawPath, '/');
        
        echo "  Format: ";
        if ($isFilenameOnly) {
            echo "✓ Filename only (optimal)\n";
        } elseif ($hasVariantsPrefix && !$hasStoragePrefix) {
            echo "⚠ Relative path (products/variants/...)\n";
        } elseif ($hasStoragePrefix) {
            echo "⚠ Full path (storage/...)\n";
        } else {
            echo "❌ Unknown format\n";
        }
        echo "\n";
    }
}

// 3. Test ImageHelper URL generation patterns
echo "\n3. Testing ImageHelper URL Generation:\n";
echo str_repeat('-', 60) . "\n";

$testCases = [
    // Optimal: Filename only
    ['product_12345.webp', 'product'],
    ['variant_67890.webp', 'variant'],
    
    // Legacy: Relative paths
    ['products/product_12345.webp', 'product'],
    ['products/variants/variant_67890.webp', 'variant'],
    ['photos/product_12345.jpg', 'product'],
    
    // Full paths (shouldn't happen but should handle)
    ['storage/products/product_12345.webp', 'product'],
    ['storage/products/variants/variant_67890.webp', 'variant'],
    
    // Edge cases
    [null, 'product'],
    ['', 'product'],
];

foreach ($testCases as [$filename, $type]) {
    $url = $type === 'variant' 
        ? ImageHelper::variantImageUrl($filename) 
        : ImageHelper::productImageUrl($filename);
    
    $filenameDisplay = $filename ?: '(null/empty)';
    echo "Input: {$filenameDisplay} [{$type}]\n";
    echo "  Output: {$url}\n";
    
    // Validate output
    if (empty($filename)) {
        $isDefault = str_contains($url, 'avatar.webp');
        echo "  Status: " . ($isDefault ? "✓ Default fallback" : "❌ Should use default") . "\n";
    } else {
        $hasHttp = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
        $hasStorage = str_contains($url, '/storage/');
        $hasProducts = str_contains($url, '/products/');
        
        echo "  Status: " . ($hasHttp && $hasStorage && $hasProducts ? "✓ Valid URL" : "❌ Invalid URL") . "\n";
    }
    echo "\n";
}

// 4. Check for images with incorrect paths in database
echo "\n4. Checking for Problematic Image Paths:\n";
echo str_repeat('-', 60) . "\n";

$problematicProducts = DB::table('product_images')
    ->where(function($q) {
        $q->whereNull('image_path')
          ->orWhere('image_path', '')
          ->orWhere('image_path', 'LIKE', 'http://%')
          ->orWhere('image_path', 'LIKE', 'https://%');
    })
    ->count();

$problematicVariants = DB::table('variant_images')
    ->where(function($q) {
        $q->whereNull('image_path')
          ->orWhere('image_path', '')
          ->orWhere('image_path', 'LIKE', 'http://%')
          ->orWhere('image_path', 'LIKE', 'https://%');
    })
    ->count();

echo "Product images with issues: " . ($problematicProducts > 0 ? "❌ {$problematicProducts}" : "✓ 0") . "\n";
echo "Variant images with issues: " . ($problematicVariants > 0 ? "❌ {$problematicVariants}" : "✓ 0") . "\n";

if ($problematicProducts > 0 || $problematicVariants > 0) {
    echo "\n⚠ Found problematic paths. Run cleanup script to fix.\n";
}

// 5. Test filter controller image retrieval (simulate)
echo "\n5. Testing Filter Controller Image Logic:\n";
echo str_repeat('-', 60) . "\n";

$testProduct = Product::with(['images' => function($q) {
        $q->orderByDesc('is_primary')
          ->orderBy('sort_order')
          ->limit(2);
    }])
    ->has('images')
    ->first();

if ($testProduct) {
    echo "Product: {$testProduct->title}\n";
    echo "Image Count: {$testProduct->images->count()}\n\n";
    
    // Simulate filter controller logic (fixed version)
    $images = $testProduct->images
        ->map(fn($img) => $img->url ?? null)
        ->filter()
        ->values()
        ->toArray();
    
    echo "Extracted URLs:\n";
    foreach ($images as $idx => $url) {
        echo "  [" . ($idx + 1) . "] {$url}\n";
    }
    
    if (empty($images)) {
        echo "  ⚠ No images extracted - would use default fallback\n";
    } else {
        echo "  ✓ Images extracted successfully\n";
    }
} else {
    echo "❌ No products with images found for testing\n";
}

// 6. Statistics
echo "\n\n=== STATISTICS ===\n";
echo str_repeat('-', 60) . "\n";

$stats = [
    'Total Products' => DB::table('products')->count(),
    'Products with Images' => DB::table('product_images')->distinct('product_id')->count('product_id'),
    'Total Product Images' => DB::table('product_images')->count(),
    'Total Variant Images' => DB::table('variant_images')->count(),
];

foreach ($stats as $label => $value) {
    echo str_pad($label . ':', 30) . number_format($value) . "\n";
}

// Calculate image coverage
$productsWithImages = $stats['Products with Images'];
$totalProducts = $stats['Total Products'];
$coverage = $totalProducts > 0 ? ($productsWithImages / $totalProducts * 100) : 0;

echo "\nImage Coverage: " . number_format($coverage, 2) . "%\n";

if ($coverage < 50) {
    echo "⚠ Low image coverage - many products missing images\n";
} elseif ($coverage < 90) {
    echo "⚠ Moderate image coverage - some products missing images\n";
} else {
    echo "✓ Good image coverage\n";
}

echo "\n=== TEST COMPLETE ===\n\n";
