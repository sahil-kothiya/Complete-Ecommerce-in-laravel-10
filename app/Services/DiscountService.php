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
        if ($product->base_discount && $product->base_discount > 0) {
            $discounts[] = [
                'type'   => 'percentage',
                'value'  => $product->base_discount,
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

    public function applyAllDiscounts(float $basePrice = null, array $discounts): float
    {
        // If basePrice is null, return 0.0
        $prix = $basePrice ?? 0.0;

        // Apply product-level discount first
        foreach ($discounts as $discount) {
            if ($discount['source'] === 'product') {
                if ($discount['type'] === 'percentage') {
                    $prix -= ($prix * $discount['value'] / 100);
                } elseif ($discount['type'] === 'amount') {
                    $prix -= $discount['value'];
                }
            }
        }

        // Then apply category-level discounts
        foreach ($discounts as $discount) {
            if ($discount['source'] === 'category') {
                if ($discount['type'] === 'percentage') {
                    $prix -= ($prix * $discount['value'] / 100);
                } elseif ($discount['type'] === 'amount') {
                    $prix -= $discount['value'];
                }
            }
        }

        return max($prix, 0);
    }

    public function getCachedDiscountedPrice(Product $product): float
    {
        // Use base_price and base_discount, default to 0.0 if base_price is null
        $basePrice = $product->base_price ?? 0.0;
        return $product->base_discount > 0
            ? $basePrice - ($basePrice * $product->base_discount / 100)
            : $basePrice;
    }
}