<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\\Contracts\\Console\\Kernel')->bootstrap();

use App\Models\Product;

$count = Product::where('status', 'active')
	->where(function($priceQuery) {
		$minPrice = 100;
		$maxPrice = 1000;

		$priceQuery->whereBetween('base_price', [$minPrice, $maxPrice])
			->orWhereIn('id', function($sub) use ($minPrice, $maxPrice) {
				$sub->select('product_variants.product_id')
					->from('product_variants')
					->whereBetween('product_variants.price', [$minPrice, $maxPrice]);
			});
	})
	->count();
echo "Count: {$count}\n";
