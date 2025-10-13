<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\VariantImage;

class ProductVariant extends Model
{
    use HasFactory;

    protected $table = 'product_variants';

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'discount',
        'stock',
        'variant_values',
        'status'
    ];

    protected $casts = [
        'variant_values' => 'array',
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'stock' => 'integer'
    ];

    /**
     * Relationships
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variantCombinations()
    {
        return $this->hasMany(ProductVariantCombination::class, 'product_variant_id');
    }

    public function variantOptions()
    {
        return $this->belongsToMany(
            ProductVariantOption::class,
            'product_variant_combinations',
            'product_variant_id',
            'variant_option_id'
        )->with('variantType');
    }

    public function images()
    {
        return $this->hasMany(VariantImage::class, 'product_variant_id');
    }

    public function primaryImage()
    {
        return $this->hasOne(VariantImage::class, 'product_variant_id')->where('is_primary', true);
    }

    /**
     * Attributes
     */
    public function getDiscountedPriceAttribute()
    {
        if ($this->discount > 0) {
            return $this->price - ($this->price * $this->discount / 100);
        }
        return $this->price;
    }

    public function getDisplayNameAttribute()
    {
        $options = $this->variantOptions()->with('variantType')->get();
        return $options->map(fn($opt) => $opt->display_value)->join(', ');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInStock($query)
    {
        return $query->where('stock', '>', 0);
    }

    /**
     * Methods
     */
    public function isInStock()
    {
        return $this->stock > 0 && $this->status === 'active';
    }
}