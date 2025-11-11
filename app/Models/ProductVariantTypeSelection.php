<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantTypeSelection extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_type_id',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variantType(): BelongsTo
    {
        return $this->belongsTo(ProductVariantType::class, 'product_variant_type_id');
    }
}
