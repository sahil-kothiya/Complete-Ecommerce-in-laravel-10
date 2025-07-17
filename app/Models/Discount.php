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

    protected static function booted()
    {
        static::saved(function ($discount) {
            $discount->loadMissing('products', 'categories.products');

            foreach ($discount->products as $product) {
                Cache::forget("discount:product:{$product->id}");
            }

            foreach ($discount->categories as $category) {
                foreach ($category->products as $product) {
                    Cache::forget("discount:product:{$product->id}");
                }
            }
        });

        static::deleted(function ($discount) {
            $discount->loadMissing('products', 'categories.products');

            foreach ($discount->products as $product) {
                Cache::forget("discount:product:{$product->id}");
            }

            foreach ($discount->categories as $category) {
                foreach ($category->products as $product) {
                    Cache::forget("discount:product:{$product->id}");
                }
            }
        });
    }

    public static function countActiveDiscount()
    {
        $data = Discount::count();
        if ($data) {
            return $data;
        }
        return 0;
    }
}
