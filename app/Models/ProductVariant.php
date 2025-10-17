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

    public function optionAssignments()
    {
        return $this->hasMany(ProductVariantOptionAssignment::class, 'product_variant_id');
    }

    public function variantOptions()
    {
        return $this->belongsToMany(
            ProductVariantOption::class,
            'product_variant_option_assignments',
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
     * Accessor: resolve primary image with fallbacks
     * - If relation is already loaded and present, return it
     * - Attempt to fetch the primary image from DB
     * - Fallback to the first image from images() relationship
     */
    public function getPrimaryImageAttribute()
    {
        // If relation was eager loaded and not null, return it
        if ($this->relationLoaded('primaryImage') && $this->getRelation('primaryImage')) {
            return $this->getRelation('primaryImage');
        }

        // Try to fetch the primary image via the relation query
        $primary = $this->primaryImage()->first();
        if ($primary) {
            return $primary;
        }

        // Fallback: return first image from images() if available
        $first = $this->images()->orderByDesc('is_primary')->orderBy('sort_order')->first();
        return $first;
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