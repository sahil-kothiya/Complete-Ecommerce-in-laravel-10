<?php
/**
 * Detailed Image Investigation Script
 * Check actual database content and file system
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\VariantImage;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n=== DETAILED IMAGE INVESTIGATION ===\n\n";

// 1. Check a specific product with and without variants
echo "1. Checking Product #8760863 (has real image in API):\n";
echo str_repeat('-', 70) . "\n";

$product = Product::with(['images', 'variants.images'])->find(8760863);
if ($product) {
    echo "Product: {$product->title}\n";
    echo "Has Variants: " . ($product->has_variants ? 'YES' : 'NO') . "\n";
    echo "Product Images Count: {$product->images->count()}\n";
    
    if ($product->images->isNotEmpty()) {
        foreach ($product->images as $img) {
            $rawPath = $img->image_path;
            $fullPath = storage_path('app/public/products/' . $rawPath);
            $fileExists = file_exists($fullPath);
            
            echo "\n  Image #{$img->id}:\n";
            echo "    DB Path: {$rawPath}\n";
            echo "    Full Path: {$fullPath}\n";
            echo "    File Exists: " . ($fileExists ? "✓ YES" : "✗ NO") . "\n";
            if ($fileExists) {
                echo "    File Size: " . filesize($fullPath) . " bytes\n";
            }
            echo "    Generated URL: {$img->url}\n";
        }
    } else {
        echo "  ⚠ No product images in database\n";
    }
    
    if ($product->has_variants && $product->variants->isNotEmpty()) {
        echo "\n  Variants: {$product->variants->count()}\n";
        foreach ($product->variants->take(2) as $variant) {
            echo "\n  Variant #{$variant->id}:\n";
            echo "    Images Count: {$variant->images->count()}\n";
            
            foreach ($variant->images as $img) {
                $rawPath = $img->image_path;
                $fullPath = storage_path('app/public/products/variants/' . $rawPath);
                $fileExists = file_exists($fullPath);
                
                echo "    - Image #{$img->id}:\n";
                echo "      DB Path: {$rawPath}\n";
                echo "      Full Path: {$fullPath}\n";
                echo "      File Exists: " . ($fileExists ? "✓ YES" : "✗ NO") . "\n";
                if ($fileExists) {
                    echo "      File Size: " . filesize($fullPath) . " bytes\n";
                }
            }
        }
    }
} else {
    echo "✗ Product not found\n";
}

// 2. Sample random products
echo "\n\n2. Checking 5 Random Products:\n";
echo str_repeat('-', 70) . "\n";

$randomProducts = Product::with(['images', 'variants.images'])
    ->inRandomOrder()
    ->limit(5)
    ->get();

foreach ($randomProducts as $product) {
    echo "\nProduct #{$product->id}: {$product->title}\n";
    echo "  Has Variants: " . ($product->has_variants ? 'YES' : 'NO') . "\n";
    echo "  Product Images: {$product->images->count()}\n";
    
    if ($product->images->isEmpty()) {
        echo "  ⚠ No product images\n";
        
        // Check if this product SHOULD have images by checking file system
        $productDir = storage_path('app/public/products/');
        $possibleFiles = glob($productDir . "product_*{$product->id}*.webp");
        
        if (!empty($possibleFiles)) {
            echo "  ⚠⚠⚠ ISSUE FOUND: Files exist but not in database!\n";
            foreach ($possibleFiles as $file) {
                echo "    - " . basename($file) . "\n";
            }
        }
    } else {
        foreach ($product->images->take(1) as $img) {
            $fullPath = storage_path('app/public/products/' . $img->image_path);
            echo "  - {$img->image_path} → " . (file_exists($fullPath) ? "✓" : "✗") . "\n";
        }
    }
    
    if ($product->has_variants) {
        echo "  Variants: {$product->variants->count()}\n";
        $variantsWithImages = $product->variants->filter(fn($v) => $v->images->isNotEmpty())->count();
        echo "  Variants with images: {$variantsWithImages}\n";
    }
}

// 3. Database statistics
echo "\n\n3. Database Statistics:\n";
echo str_repeat('-', 70) . "\n";

$totalProducts = DB::table('products')->count();
$productsWithImages = DB::table('product_images')->distinct('product_id')->count('product_id');
$totalProductImages = DB::table('product_images')->count();
$totalVariantImages = DB::table('variant_images')->count();

echo "Total Products: " . number_format($totalProducts) . "\n";
echo "Products with Images: " . number_format($productsWithImages) . " (" . number_format(($productsWithImages/$totalProducts)*100, 2) . "%)\n";
echo "Total Product Images: " . number_format($totalProductImages) . "\n";
echo "Total Variant Images: " . number_format($totalVariantImages) . "\n";

// 4. Check actual file system
echo "\n\n4. File System Check:\n";
echo str_repeat('-', 70) . "\n";

$productDir = storage_path('app/public/products/');
$variantDir = storage_path('app/public/products/variants/');

echo "Product Images Directory: {$productDir}\n";
echo "  Exists: " . (is_dir($productDir) ? "✓ YES" : "✗ NO") . "\n";
if (is_dir($productDir)) {
    $files = glob($productDir . "*.webp");
    echo "  Files found: " . count($files) . "\n";
    if (count($files) > 0) {
        echo "  Sample files:\n";
        foreach (array_slice($files, 0, 5) as $file) {
            echo "    - " . basename($file) . " (" . filesize($file) . " bytes)\n";
        }
    }
}

echo "\nVariant Images Directory: {$variantDir}\n";
echo "  Exists: " . (is_dir($variantDir) ? "✓ YES" : "✗ NO") . "\n";
if (is_dir($variantDir)) {
    $files = glob($variantDir . "*.webp");
    echo "  Files found: " . count($files) . "\n";
    if (count($files) > 0) {
        echo "  Sample files:\n";
        foreach (array_slice($files, 0, 5) as $file) {
            echo "    - " . basename($file) . " (" . filesize($file) . " bytes)\n";
        }
    }
}

// 5. Check for mismatches
echo "\n\n5. Checking for Database/FileSystem Mismatches:\n";
echo str_repeat('-', 70) . "\n";

// Check if files exist for DB entries
$imagesMissingFiles = 0;
$sampleMissing = [];

$dbImages = DB::table('product_images')->select('image_path')->limit(100)->get();
foreach ($dbImages as $img) {
    $fullPath = storage_path('app/public/products/' . $img->image_path);
    if (!file_exists($fullPath)) {
        $imagesMissingFiles++;
        if (count($sampleMissing) < 5) {
            $sampleMissing[] = $img->image_path;
        }
    }
}

echo "Images in DB but missing from filesystem: {$imagesMissingFiles} / 100 checked\n";
if (!empty($sampleMissing)) {
    echo "Sample missing files:\n";
    foreach ($sampleMissing as $path) {
        echo "  - {$path}\n";
    }
}

echo "\n=== INVESTIGATION COMPLETE ===\n\n";
