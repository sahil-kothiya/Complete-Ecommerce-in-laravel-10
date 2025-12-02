<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = app(App\Http\Controllers\FrontendController::class);

$product = App\Models\Product::select(['id','title','slug','base_price','base_discount','base_stock','has_variants','cat_id','condition'])
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
        }
    ])
    ->find(10000001);

$method = new ReflectionMethod($controller, 'transformProductForDisplay');
$method->setAccessible(true);

$card = $method->invoke($controller, $product);

print_r($card->images);
