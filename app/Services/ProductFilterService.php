<?php

// app/Services/ProductFilterService.php
namespace App\Services;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

class ProductFilterService
{
    public function getProducts(array $filters, int $perPage = 12, int $page = 1)
    {
        $query = Product::with(['brand', 'category'])
            ->where('status', 'active');

        // Category slug
        if (!empty($filters['category_slug'])) {
            $category = Category::where('slug', $filters['category_slug'])
                ->where('status', 'active')
                ->first();
            if ($category) {
                $query->where('cat_id', $category->id);
            }
        }

        // Brand filter
        if (!empty($filters['brand'])) {
            $query->whereHas('brand', function ($q) use ($filters) {
                $q->whereIn('slug', $filters['brand'])
                   ->where('status', 'active');
            });
        }

        // Search
        if (!empty($filters['query'])) {
            $searchTerm = $filters['query'];
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('summary', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Price range
        if (!empty($filters['price_range'])) {
            $range = explode('-', $filters['price_range']);
            if (count($range) === 2) {
                $query->whereBetween('price', [(float) $range[0], (float) $range[1]]);
            }
        }

        // Rating filter
        if (!empty($filters['min_rating'])) {
            $minRating = min(array_map('intval', $filters['min_rating']));
            $query->whereExists(function ($q) use ($minRating) {
                $q->select(DB::raw(1))
                  ->from('product_reviews')
                  ->whereColumn('products.id', 'product_reviews.product_id')
                  ->groupBy('product_id')
                  ->havingRaw('AVG(rate) >= ?', [$minRating]);
            });
        }

        // Discount filter
        if (!empty($filters['min_discount'])) {
            $minDiscount = min(array_map('intval', $filters['min_discount']));
            $query->where('discount', '>=', $minDiscount);
        }

        // Sorting
        switch ($filters['sortBy'] ?? '') {
            case 'title':
                $query->orderBy('title', 'asc');
                break;
            case 'price':
                $query->orderBy('price', 'asc');
                break;
            case 'priceDesc':
                $query->orderBy('price', 'desc');
                break;
            case 'date':
                $query->orderBy('created_at', 'desc');
                break;
            case 'rating':
                $query->leftJoin(DB::raw('(
                    SELECT product_id, AVG(rate) as avg_rating
                    FROM product_reviews
                    GROUP BY product_id
                ) as reviews_avg'), 'products.id', '=', 'reviews_avg.product_id')
                      ->orderByDesc('reviews_avg.avg_rating');
                break;
            case 'discount':
                $query->orderBy('discount', 'desc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }
}
