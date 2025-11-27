<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

echo "\n=== IMAGE STORAGE VERIFICATION ===\n\n";

// 1. Check product_images table structure
echo "1. PRODUCT_IMAGES TABLE STRUCTURE:\n";
echo str_repeat("-", 70) . "\n";
$columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'product_images'");
foreach ($columns as $col) {
    echo "{$col->column_name}: {$col->data_type}\n";
}

// 2. Sample product images from database
echo "\n2. SAMPLE PRODUCT IMAGES FROM DATABASE (First 10):\n";
echo str_repeat("-", 70) . "\n";
$productImages = DB::table('product_images')->limit(10)->get(['id', 'product_id', 'image_path', 'is_primary']);
foreach ($productImages as $img) {
    $fullPath = storage_path('app/public/products/' . $img->image_path);
    $exists = file_exists($fullPath);
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo "ID: {$img->id} | Product: {$img->product_id} | File: {$img->image_path} | {$status}\n";
}

// 3. Check variant_images table structure
echo "\n3. VARIANT_IMAGES TABLE STRUCTURE:\n";
echo str_repeat("-", 70) . "\n";
$columns = DB::select("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'variant_images'");
foreach ($columns as $col) {
    echo "{$col->column_name}: {$col->data_type}\n";
}

// 4. Sample variant images from database
echo "\n4. SAMPLE VARIANT IMAGES FROM DATABASE (First 10):\n";
echo str_repeat("-", 70) . "\n";
$variantImages = DB::table('variant_images')->limit(10)->get(['id', 'product_variant_id', 'image_path']);
foreach ($variantImages as $img) {
    $fullPath = storage_path('app/public/products/variants/' . $img->image_path);
    $exists = file_exists($fullPath);
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    echo "ID: {$img->id} | Variant: {$img->product_variant_id} | File: {$img->image_path} | {$status}\n";
}

// 5. Check files on disk that are NOT in database
echo "\n5. FILES ON DISK NOT IN DATABASE:\n";
echo str_repeat("-", 70) . "\n";

// Get all filenames from database
$dbProductImages = DB::table('product_images')->pluck('image_path')->toArray();
$dbVariantImages = DB::table('variant_images')->pluck('image_path')->toArray();

// Get all files from disk
$diskProductFiles = [];
$productPath = storage_path('app/public/products');
if (is_dir($productPath)) {
    $files = scandir($productPath);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
            $diskProductFiles[] = $file;
        }
    }
}

$diskVariantFiles = [];
$variantPath = storage_path('app/public/products/variants');
if (is_dir($variantPath)) {
    $files = scandir($variantPath);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
            $diskVariantFiles[] = $file;
        }
    }
}

// Files on disk but not in DB
$orphanProductFiles = array_diff($diskProductFiles, $dbProductImages);
$orphanVariantFiles = array_diff($diskVariantFiles, $dbVariantImages);

echo "Product images on disk but NOT in database: " . count($orphanProductFiles) . "\n";
if (count($orphanProductFiles) > 0 && count($orphanProductFiles) <= 10) {
    foreach ($orphanProductFiles as $file) {
        echo "  - {$file}\n";
    }
} elseif (count($orphanProductFiles) > 10) {
    echo "  First 10:\n";
    foreach (array_slice($orphanProductFiles, 0, 10) as $file) {
        echo "  - {$file}\n";
    }
}

echo "\nVariant images on disk but NOT in database: " . count($orphanVariantFiles) . "\n";
if (count($orphanVariantFiles) > 0 && count($orphanVariantFiles) <= 10) {
    foreach ($orphanVariantFiles as $file) {
        echo "  - {$file}\n";
    }
} elseif (count($orphanVariantFiles) > 10) {
    echo "  First 10:\n";
    foreach (array_slice($orphanVariantFiles, 0, 10) as $file) {
        echo "  - {$file}\n";
    }
}

// 6. Check the photos/1/ directory structure
echo "\n6. CHECKING photos/1/ DIRECTORY:\n";
echo str_repeat("-", 70) . "\n";
$photosPath = storage_path('app/public/photos/1');
if (is_dir($photosPath)) {
    echo "Photos directory exists: {$photosPath}\n";
    
    // Count files in NewProducts
    $newProductsPath = $photosPath . '/NewProducts';
    if (is_dir($newProductsPath)) {
        $count = count(glob($newProductsPath . '/*.webp'));
        echo "  - NewProducts: {$count} files\n";
        
        // Sample filenames
        $samples = array_slice(scandir($newProductsPath), 2, 5);
        echo "    Sample files:\n";
        foreach ($samples as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'webp') {
                echo "      * {$file}\n";
            }
        }
    }
    
    // Count files in Products
    $productsPath = $photosPath . '/Products';
    if (is_dir($productsPath)) {
        $count = count(glob($productsPath . '/*.webp'));
        echo "  - Products: {$count} files\n";
    }
} else {
    echo "Photos directory does NOT exist\n";
}

// 7. Check ImageHelper current configuration
echo "\n7. IMAGEHELPER CONFIGURATION:\n";
echo str_repeat("-", 70) . "\n";
$helperPath = app_path('Helpers/ImageHelper.php');
if (file_exists($helperPath)) {
    $content = file_get_contents($helperPath);
    
    // Extract productImageUrl method
    if (preg_match('/public static function productImageUrl.*?(?=public static function|$)/s', $content, $matches)) {
        echo "productImageUrl method found:\n";
        $lines = explode("\n", $matches[0]);
        foreach (array_slice($lines, 0, 15) as $line) {
            echo "  " . $line . "\n";
        }
    }
    
    echo "\n";
    
    // Extract variantImageUrl method
    if (preg_match('/public static function variantImageUrl.*?(?=public static function|$)/s', $content, $matches)) {
        echo "variantImageUrl method found:\n";
        $lines = explode("\n", $matches[0]);
        foreach (array_slice($lines, 0, 15) as $line) {
            echo "  " . $line . "\n";
        }
    }
} else {
    echo "ImageHelper.php not found\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
