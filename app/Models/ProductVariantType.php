<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantType extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'display_name', 'sort_order', 'status'];

    public function options()
    {
        return $this->hasMany(ProductVariantOption::class, 'variant_type_id');
    }

    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'product_variant_type_selections',
            'product_variant_type_id',
            'product_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
