<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariantCombination extends Model
{
    use HasFactory;

    protected $fillable = ['product_variant_id', 'variant_option_id'];

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function option()
    {
        return $this->belongsTo(ProductVariantOption::class, 'variant_option_id');
    }
}
