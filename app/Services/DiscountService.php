<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class DiscountService
{
    public function getEffectiveDiscount(Product $product): ?array
    {
        // Priority 1: Product-specific active discount
        $productDiscount = $product->discounts()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        if ($productDiscount) {
            return [
                'type' => $productDiscount->type,
                'value' => $productDiscount->value,
            ];
        }

        // Priority 2: Category or sub-category discounts
        $categoryDiscount = $product->cat_info?->discounts()
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();

        if (!$categoryDiscount && $product->sub_cat_info) {
            $categoryDiscount = $product->sub_cat_info->discounts()
                ->where('is_active', true)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->first();
        }

        if ($categoryDiscount) {
            return [
                'type' => $categoryDiscount->type,
                'value' => $categoryDiscount->value,
            ];
        }

        return null;
    }

    public function calculateDiscountedPrice(Product $product): float
    {
        $basePrice = $product->price;
        $discount = $this->getEffectiveDiscount($product);

        if (!$discount) {
            return $basePrice;
        }

        return match ($discount['type']) {
            'amount' => max(0, $basePrice - $discount['value']),
            'percentage' => max(0, $basePrice * (1 - ($discount['value'] / 100))),
            default => $basePrice,
        };
    }

    public function getCachedDiscountedPrice(Product $product): float
    {
        $cacheKey = "discount:product:{$product->id}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($product) {
            return $this->calculateDiscountedPrice($product);
        });
    }
}
