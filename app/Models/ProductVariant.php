<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'sku', 'price', 'discount', 
        'stock', 'variant_values', 'images', 'status'
    ];

    protected $casts = [
        'variant_values' => 'array',
        'images' => 'array',
        'price' => 'decimal:2',
        'discount' => 'decimal:2'
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variantCombinations()
    {
        return $this->hasMany(ProductVariantCombination::class);
    }

    public function variantOptions()
    {
        return $this->belongsToMany(
            ProductVariantOption::class, 
            'product_variant_combinations',
            'product_variant_id',
            'variant_option_id'
        );
    }

    // Get discounted price
    public function getDiscountedPriceAttribute()
    {
        if ($this->discount > 0) {
            return $this->price - ($this->price * $this->discount / 100);
        }
        return $this->price;
    }

    // Check if in stock
    public function isInStock()
    {
        return $this->stock > 0 && $this->status === 'active';
    }

    // Get variant display string (e.g., "Red, 8GB RAM, 128GB Storage")
    public function getDisplayNameAttribute()
    {
        $options = $this->variantOptions()->with('variantType')->get();
        return $options->map(fn($opt) => $opt->display_value)->join(', ');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }
}
