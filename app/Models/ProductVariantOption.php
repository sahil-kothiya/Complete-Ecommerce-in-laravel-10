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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
