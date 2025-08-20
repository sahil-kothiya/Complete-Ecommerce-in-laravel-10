<?php

namespace App\Models;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'status',
        'code',
        'code_locked',
        'code_generated_at'
    ];

    /**
     * Relationship: Brand → Products (One-to-Many)
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'brand_id', 'id')
                    ->where('status', 'active');
    }

    /**
     * Relationship: Brand ↔ Categories (Many-to-Many)
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'brand_category', 'brand_id', 'category_id')
                    ->withTimestamps();
    }

    /**
     * Static helper: Get products by brand slug
     */
    public static function getProductByBrand($slug)
    {
        return Product::with('brand:id,title,slug')
            ->whereHas('brand', function ($q) use ($slug) {
                $q->where('slug', $slug);
            })
            ->where('status', 'active')
            ->paginate(9)
            ->appends(request()->query()); // Keeps filters in pagination links
    }
}
