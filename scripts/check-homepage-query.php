<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$product = App\Models\Product::select(['id','title','slug','base_price','base_discount','base_stock','has_variants','cat_id','condition'])
    ->where('id', 10000001)
    ->with([
        'images' => function($q) {
            return $q->orderBy('sort_order', 'asc')
                ->take(3)
                ->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order']);
        },
        'variants' => function($q) {
            return $q->where('status', 'active')
                ->select(['id','product_id','price','discount','stock','status'])
                ->with([
                    'images' => function($q) {
                        return $q->orderBy('sort_order', 'asc')
                            ->take(3)
                            ->select(['id','product_variant_id','image_path','thumbnail_path','is_primary','sort_order']);
                    }
                ]);
        },
        'brand' => function($q) {
            return $q->select(['id','title','slug']);
        }
    ])
    ->first();

echo "Image count: " . $product->images->count() . "\n";
foreach ($product->images as $img) {
    echo "- " . $img->url . "\n";
}
