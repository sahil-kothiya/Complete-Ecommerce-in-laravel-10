<?php

namespace App\Services;

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
}
