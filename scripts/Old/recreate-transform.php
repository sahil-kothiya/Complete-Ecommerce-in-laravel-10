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
                ->select(['id','product_id','image_path','thumbnail_path','is_primary','sort_order','alt_text']);
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
        }
    ])
    ->first();

$images = [];
if ($product->has_variants && $product->variants->count() > 0) {
    foreach ($product->variants as $variant) {
        foreach ($variant->images->take(3) as $img) {
            $images[] = [
                'image_path' => $img->url,
                'thumbnail_path' => $img->thumbnail_url ?? $img->url,
                'alt_text' => $img->alt_text ?? $product->title,
            ];
        }
    }
}

if (empty($images) && $product->images->count() > 0) {
    foreach ($product->images->take(3) as $img) {
        $images[] = [
            'image_path' => $img->url,
            'thumbnail_path' => $img->thumbnail_url ?? $img->url,
            'alt_text' => $img->alt_text ?? $product->title,
        ];
    }
}

if (empty($images)) {
    $images[] = [
        'image_path' => asset('images/no-image.png'),
        'thumbnail_path' => asset('images/no-image.png'),
        'alt_text' => $product->title,
    ];
}

echo "Images generated:\n";
foreach ($images as $img) {
    echo '- ' . $img['image_path'] . "\n";
}
