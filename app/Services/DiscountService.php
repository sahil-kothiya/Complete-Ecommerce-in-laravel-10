<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use Carbon\Carbon;

class DiscountService
{
    public function getEffectiveDiscount(Product $product): ?array
    {
        $now = Carbon::now();

        $category = $product->cat_info; // Uses cat_id from product

        if (!$category) return null;

        $activeDiscount = $category->discounts()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->first();

        if ($activeDiscount) {
            return [
                'type' => $activeDiscount->type,
                'value' => $activeDiscount->value,
            ];
        }

        return null;
    }

    public function calculateDiscountedPrice($original, $discount): float
    {
        if ($discount['type'] === 'percentage') {
            return round($original - ($original * $discount['value'] / 100), 2);
        }

        return round(max($original - $discount['value'], 0), 2);
    }

    public function getAllActiveCategoryDiscounts(): array
    {
        $now = now();

        $discounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('categories')
            ->with('categories:id,slug,title')
            ->get();

        $results = [];

        foreach ($discounts as $discount) {
            $category = $discount->categories->first(); // pick first category for link
            if ($category) {
                $results[] = [
                    'title' => $discount->title,
                    'value' => $discount->value,
                    'type' => $discount->type,
                    'category_slug' => $category->slug,
                    'category_title' => $category->title,
                ];
            }
        }

        return $results;
    }

    public function getEffectiveDiscounts(Product $product): array
    {
        $discounts = [];

        // 1. Product-level discount
        if ($product->discount && $product->discount > 0) {
            $discounts[] = [
                'type'   => 'percentage',
                'value'  => $product->discount,
                'source' => 'product',
            ];
        }

        // 2. Category-level discounts
        $now = now();
        $categoryDiscounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->whereHas('categories', function ($q) use ($product) {
                $q->where('categories.id', $product->cat_id);
            })
            ->with('categories')
            ->get();

        foreach ($categoryDiscounts as $discount) {
            $discounts[] = [
                'type'   => $discount->type,
                'value'  => $discount->value,
                'source' => 'category',
                'title'  => $discount->title,
            ];
        }

        return $discounts;
    }

    public function applyAllDiscounts(float $basePrice, array $discounts): float
    {
        $price = $basePrice;

        foreach ($discounts as $discount) {
            if ($discount['type'] === 'percentage') {
                $price -= ($price * $discount['value'] / 100);
            } elseif ($discount['type'] === 'amount') {
                $price -= $discount['value'];
            }
        }

        return max($price, 0); // Prevent negative price
    }
}
