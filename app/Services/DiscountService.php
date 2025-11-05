<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Carbon\Carbon;

class DiscountService
{
    /**
     * Get **all** active discounts for a product (product + variant + category).
     *
     * @param Product $product
     * @param ProductVariant|null $variant
     * @return array
     */
    public function getEffectiveDiscounts(Product $product, ?ProductVariant $variant = null): array
    {
        $discounts = [];
        $now       = Carbon::now();

        // 1. Variant discount (highest priority)
        if ($variant && $variant->discount > 0) {
            $discounts[] = [
                'type'   => 'percentage',
                'value'  => $variant->discount,
                'source' => 'variant',
                'title'  => 'Variant Discount',
            ];
        }

        // 2. Product base discount
        if ($product->base_discount > 0) {
            $discounts[] = [
                'type'   => 'percentage',
                'value'  => $product->base_discount,
                'source' => 'product',
                'title'  => 'Product Discount',
            ];
        }

        // 3. Category discounts (active now)
        $categoryDiscounts = Discount::where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at',   '>=', $now)
            ->whereHas('categories', fn($q) => $q->where('categories.id', $product->cat_id))
            ->get();

        foreach ($categoryDiscounts as $d) {
            $discounts[] = [
                'type'   => $d->type,
                'value'  => $d->value,
                'source' => 'category',
                'title'  => $d->title,
            ];
        }

        return $discounts;
    }

    /**
     * Apply an array of discounts **in order** to a base price.
     *
     * @param float $basePrice
     * @param array $discounts  [{type, value, source, title}]
     * @return float
     */
    public function applyAllDiscounts(float $basePrice, array $discounts): float
    {
        $price = $basePrice;

        foreach ($discounts as $discount) {
            if ($discount['type'] === 'percentage') {
                $price -= $price * ($discount['value'] / 100);
            } elseif ($discount['type'] === 'amount') {
                $price -= $discount['value'];
            }
            // never go below 0
            $price = max($price, 0.0);
        }

        return round($price, 2);
    }

    /**
     * Helper – single discount calculation (kept for backward compatibility)
     */
    public function calculateDiscountedPrice(float $original, array $discount): float
    {
        return $this->applyAllDiscounts($original, [$discount]);
    }

    /**
     * Cached price for product (base + product discount only)
     */
    public function getCachedDiscountedPrice(Product $product): float
    {
        $base = $product->base_price ?? 0.0;
        if ($product->base_discount > 0) {
            return $this->applyAllDiscounts($base, [[
                'type'  => 'percentage',
                'value' => $product->base_discount,
                'source'=> 'product',
            ]]);
        }
        return round($base, 2);
    }

    /**
     * All active category discounts (for homepage banners etc.)
     */
    public function getAllActiveCategoryDiscounts(): array
    {
        $now = Carbon::now();

        return Discount::where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at',   '>=', $now)
            ->whereHas('categories')
            ->with('categories:id,slug,title')
            ->get()
            ->map(function ($d) {
                $cat = $d->categories->first();
                return [
                    'title'           => $d->title,
                    'value'           => $d->value,
                    'type'            => $d->type,
                    'category_slug'   => $cat?->slug,
                    'category_title'  => $cat?->title,
                ];
            })
            ->toArray();
    }
}