<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;
use Illuminate\Support\Facades\DB;

echo "FIXING PRODUCTS WITH NO IMAGES\n";
echo str_repeat('=', 80) . "\n\n";

// Find all featured products with no images
$productsWithNoImages = DB::table('products as p')
    ->leftJoin('product_images as pi', 'p.id', '=', 'pi.product_id')
    ->leftJoin('product_variants as pv', function($join) {
        $join->on('p.id', '=', 'pv.product_id')
             ->where('pv.status', '=', 'active')
             ->where('pv.stock', '>', 0);
    })
    ->leftJoin('variant_images as vi', 'pv.id', '=', 'vi.product_variant_id')
    ->where('p.status', 'active')
    ->where('p.is_featured', 1)
    ->whereNull('pi.id')
    ->whereNull('vi.id')
    ->select('p.id', 'p.title', 'p.has_variants')
    ->distinct()
    ->get();

echo "Found " . count($productsWithNoImages) . " featured products with NO images\n\n";

if ($productsWithNoImages->isEmpty()) {
    echo "✅ All featured products have images!\n";
} else {
    echo "Products needing images:\n";
    echo str_repeat('-', 80) . "\n";

    foreach ($productsWithNoImages as $product) {
        echo "Product {$product->id}: {$product->title}\n";
        echo "  Has variants: " . ($product->has_variants ? 'Yes' : 'No') . "\n";
        echo "  Recommendation: ";

        if ($product->has_variants) {
            echo "Add images to variant_images table for this product's variants\n";
        } else {
            echo "Add images to product_images table for product_id = {$product->id}\n";
        }

        echo "\n";
    }

    echo "\nTo fix automatically, you can:\n";
    echo "1. Upload images for these products via admin panel\n";
    echo "2. Or set is_featured = 0 for products without images\n";
    echo "3. Run: php artisan cache:warmup after fixing\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
