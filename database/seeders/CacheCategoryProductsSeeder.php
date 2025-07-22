<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Facades\Redis;
use Illuminate\Pagination\LengthAwarePaginator;

class CacheCategoryProductsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("🚀 Starting product pagination caching process...");

        $categoryConfigs = [
            ['cat_id' => 1, 'child_cat_id' => 4, 'limit' => 5000, 'per_page' => 21, 'description' => 'Main Category Products'],
            // Add more configurations as needed
            // ['cat_id' => 2, 'child_cat_id' => 5, 'limit' => 3000, 'per_page' => 21, 'description' => 'Electronics'],
        ];

        foreach ($categoryConfigs as $config) {
            $this->cachePaginatedProducts(
                $config['cat_id'],
                $config['child_cat_id'],
                $config['limit'],
                $config['per_page'],
                $config['description']
            );
        }

        $this->command->info("✅ All pagination caching operations completed!");
    }

    private function cachePaginatedProducts(int $catId, int $childCatId, int $totalLimit, int $perPage, string $description): void
    {
        $ttlSeconds = 60 * 60 * 24 * 30; // 30 days
        $this->command->info("🔄 Caching paginated {$totalLimit} products for {$description} (cat_id={$catId}, child_cat_id={$childCatId})");

        // Get products from database
        $products = Product::where('cat_id', $catId)
            ->where('child_cat_id', $childCatId)
            ->select(['id', 'title', 'slug', 'price', 'discount', 'stock', 'condition', 'cat_id', 'child_cat_id', 'size', 'summary', 'created_at'])
            ->with([
                'images' => function ($q) {
                    $q->select(['id', 'image_path', 'product_id', 'is_primary', 'sort_order'])
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order');
                },
                'cat_info:id,title',
                'sub_cat_info:id,title'
            ])
            ->latest() // Default sorting
            ->limit($totalLimit)
            ->get();

        if ($products->isEmpty()) {
            $this->command->warn("⚠️ No products found for cat_id={$catId}, child_cat_id={$childCatId}");
            return;
        }

        // Transform products data
        $transformed = $products->map(function ($product) {
            $arr = $product->toArray();

            // Add calculated fields
            $arr['discounted_price'] = $product->discount > 0
                ? $product->price - ($product->price * $product->discount / 100)
                : $product->price;
            $arr['in_wishlist'] = false;

            // Handle images
            if (empty($arr['images'])) {
                $arr['images'] = [[
                    'id' => null,
                    'image_path' => 'images/no-image.png',
                    'product_id' => $product->id,
                    'is_primary' => 1,
                    'sort_order' => 0
                ]];
            }

            return $arr;
        });

        $totalCount = $transformed->count();
        $totalPages = (int) ceil($totalCount / $perPage);

        // Get recent products for all pages (only 3)
        $recentProducts = $transformed->sortByDesc('created_at')->take(3)->values()->toArray();

        // Cache different sorting variations
        $sortVariations = [
            'default' => $transformed,
            'price' => $transformed->sortBy('price')->values(),
            'price_desc' => $transformed->sortByDesc('price')->values(),
        ];

        foreach ($sortVariations as $sortType => $sortedProducts) {
            $this->command->info("📦 Caching {$sortType} sorted products...");

            // Cache each page for current sort
            for ($page = 1; $page <= $totalPages; $page++) {
                $pageData = $sortedProducts->forPage($page, $perPage)->values();

                // Create paginator
                $paginator = new LengthAwarePaginator(
                    $pageData,
                    $totalCount,
                    $perPage,
                    $page,
                    ['path' => url('/')]
                );

                // Build cache key to match controller format
                $cacheKey = "cached_products_cat{$catId}_childcat{$childCatId}_page{$page}_limit{$perPage}_sort{$sortType}_min_max";

                // Cache data structure matching controller expectations
                $cacheData = [
                    'products' => $paginator->toArray(),
                    'recent_products' => $recentProducts,
                    'limit' => $perPage,
                    'sort' => $sortType,
                    'minPrice' => null,
                    'maxPrice' => null,
                ];

                // Store in Redis using the same helper as controller
                Redis::setex($cacheKey, $ttlSeconds, serialize($cacheData));

                $this->command->info("✅ Cached {$sortType} page {$page}/{$totalPages} with key: {$cacheKey}");
            }
        }

        // Also cache price range variations (you can expand this based on common price ranges)
        $priceRanges = [
            ['min' => 0, 'max' => 100],
            ['min' => 100, 'max' => 500],
            ['min' => 500, 'max' => 1000],
            // Add more price ranges as needed
        ];

        foreach ($priceRanges as $priceRange) {
            $filteredProducts = $transformed->filter(function ($product) use ($priceRange) {
                return $product['price'] >= $priceRange['min'] && $product['price'] <= $priceRange['max'];
            })->values();

            if ($filteredProducts->isNotEmpty()) {
                $filteredTotal = $filteredProducts->count();
                $filteredPages = (int) ceil($filteredTotal / $perPage);

                for ($page = 1; $page <= $filteredPages; $page++) {
                    $pageData = $filteredProducts->forPage($page, $perPage)->values();

                    $paginator = new LengthAwarePaginator(
                        $pageData,
                        $filteredTotal,
                        $perPage,
                        $page,
                        ['path' => url('/')]
                    );

                    $cacheKey = "cached_products_cat{$catId}_childcat{$childCatId}_page{$page}_limit{$perPage}_sortdefault_min{$priceRange['min']}_max{$priceRange['max']}";

                    $cacheData = [
                        'products' => $paginator->toArray(),
                        'recent_products' => $recentProducts,
                        'limit' => $perPage,
                        'sort' => 'default',
                        'minPrice' => $priceRange['min'],
                        'maxPrice' => $priceRange['max'],
                    ];

                    Redis::setex($cacheKey, $ttlSeconds, serialize($cacheData));

                    $this->command->info("✅ Cached price range {$priceRange['min']}-{$priceRange['max']} page {$page}/{$filteredPages}");
                }
            }
        }

        $this->command->info("🎯 Completed caching for {$description}: {$totalCount} products across {$totalPages} pages");
    }
}
