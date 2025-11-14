<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'variant_type_id', 'value', 'display_value',
        'hex_color', 'sort_order', 'status'
    ];

    public function variantType()
    {
        return $this->belongsTo(ProductVariantType::class, 'variant_type_id');
    }

    public function assignments()
    {
        return $this->hasMany(ProductVariantOptionAssignment::class, 'product_variant_option_id');
    }

    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_option_assignments',
            'product_variant_option_id',
            'product_variant_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
