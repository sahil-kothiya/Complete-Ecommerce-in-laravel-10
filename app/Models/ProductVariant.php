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

    /**
     * FIXED: Use correct column name from migration
     */
    public function variantOptions()
    {
        return $this->belongsToMany(
            ProductVariantOption::class,
            'product_variant_option_assignments',
            'product_variant_id',
            'product_variant_option_id' // Corrected from 'variant_option_id'
        );
    }

    public function images()
    {
        return $this->hasMany(VariantImage::class, 'product_variant_id')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(VariantImage::class, 'product_variant_id')
            ->where('is_primary', true);
    }

    /**
     * Accessor: resolve primary image with fallbacks
     */
    public function getPrimaryImageAttribute()
    {
        if ($this->relationLoaded('primaryImage') && $this->getRelation('primaryImage')) {
            return $this->getRelation('primaryImage');
        }

        if ($this->relationLoaded('images') && $this->images->isNotEmpty()) {
            return $this->images->first();
        }

        return $this->primaryImage()->first() ?? $this->images()->first();
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
        if ($this->relationLoaded('variantOptions') && $this->variantOptions->isNotEmpty()) {
            return $this->variantOptions
                ->sortBy(fn($opt) => optional($opt->variantType)->sort_order ?? 999)
                ->pluck('display_value')
                ->filter()
                ->join(', ');
        }

        // Fallback to variant_values
        if (is_array($this->variant_values) && !empty($this->variant_values)) {
            return collect($this->variant_values)
                ->map(fn($val) => ucfirst($val))
                ->join(', ');
        }

        return 'Variant #' . $this->id;
    }

    /**
     * Get variant option values as associative array
     */
    public function getVariantValuesArrayAttribute()
    {
        if ($this->relationLoaded('variantOptions') && $this->variantOptions->isNotEmpty()) {
            return $this->variantOptions->mapWithKeys(function ($opt) {
                $typeName = optional($opt->variantType)->name ?? 'unknown';
                return [$typeName => $opt->value];
            })->toArray();
        }

        return is_array($this->variant_values) ? $this->variant_values : [];
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

    public function scopeWithVariantData($query)
    {
        return $query->with([
            'images' => fn($q) => $q->select(['id', 'product_variant_id', 'image_path', 'thumbnail_path', 'is_primary', 'sort_order']),
            'variantOptions.variantType' => fn($q) => $q->select(['id', 'name', 'display_name', 'sort_order'])
        ]);
    }

    /**
     * Methods
     */
    public function isInStock()
    {
        return $this->stock > 0 && $this->status === 'active';
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute()
    {
        return '$' . number_format($this->discounted_price, 2);
    }

    /**
     * Get stock status
     */
    public function getStockStatusAttribute()
    {
        if ($this->stock > 10) {
            return 'in_stock';
        } elseif ($this->stock > 0) {
            return 'low_stock';
        }
        return 'out_of_stock';
    }
}