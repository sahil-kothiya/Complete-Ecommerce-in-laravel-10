<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'type', 'value', 'starts_at', 'ends_at', 'is_active'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'is_active' => 'boolean',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_discount');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_discount');
    }

    public function scopeActive($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now);
    }

    // protected static function booted()
    // {
    //     static::saved(function ($discount) {
    //         // Avoid memory explosion by lazy-loading product IDs only
    //         $productIds = $discount->products()->pluck('id')->toArray();

    //         $categoryProductIds = \App\Models\Product::whereIn('category_id', function ($query) use ($discount) {
    //             $query->select('category_id')
    //                 ->from('category_discount')
    //                 ->where('discount_id', $discount->id);
    //         })->pluck('id')->toArray();

    //         $allProductIds = array_unique(array_merge($productIds, $categoryProductIds));

    //         foreach ($allProductIds as $productId) {
    //             Cache::forget("discount:product:$productId");
    //         }
    //     });

    //     static::deleted(function ($discount) {
    //         // Same logic as above
    //         $productIds = $discount->products()->pluck('id')->toArray();

    //         $categoryProductIds = \App\Models\Product::whereIn('category_id', function ($query) use ($discount) {
    //             $query->select('category_id')
    //                 ->from('category_discount')
    //                 ->where('discount_id', $discount->id);
    //         })->pluck('id')->toArray();

    //         $allProductIds = array_unique(array_merge($productIds, $categoryProductIds));

    //         foreach ($allProductIds as $productId) {
    //             Cache::forget("discount:product:$productId");
    //         }
    //     });
    // }


    public static function countActiveDiscount()
    {
        $data = Discount::count();
        if ($data) {
            return $data;
        }
        return 0;
    }
}
