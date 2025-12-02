<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;

$productIds = [95357, 99447];

foreach ($productIds as $id) {
    echo "Product {$id}:\n";
    $p = Product::with('images')->find($id);

    if (!$p) {
        echo "  NOT FOUND\n\n";
        continue;
    }

    echo "  Title: {$p->title}\n";
    echo "  Has variants: " . ($p->has_variants ? 'Yes' : 'No') . "\n";
    echo "  Images: " . $p->images->count() . "\n";

    if ($p->images->count() > 0) {
        foreach ($p->images as $img) {
            echo "    - {$img->image_path} (primary: {$img->is_primary}, sort: {$img->sort_order})\n";
        }
    } else {
        echo "    ❌ NO IMAGES IN DATABASE\n";
    }

    echo "\n";
}
