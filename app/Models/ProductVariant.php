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
            // Sort by display_value alphabetically for consistent naming across the system
            return $this->variantOptions
                ->pluck('display_value')
                ->filter()
                ->sort()
                ->values()
                ->join(' / '); // Match backend and frontend format
        }

        // Fallback to variant_values
        if (is_array($this->variant_values) && !empty($this->variant_values)) {
            return collect($this->variant_values)
                ->map(fn($val) => ucfirst($val))
                ->sort()
                ->join(' / ');
        }

        // Fallback: Parse from SKU (for old variants without option assignments)
        // Expected SKU format: PREFIX-VALUE1-VALUE2-VALUE3 or similar
        if ($this->sku) {
            $parts = explode('-', $this->sku);
            // Remove common prefixes (PROD, PRE, product slug, etc.)
            $parts = array_filter($parts, function($part) {
                $part = strtoupper($part);
                return !in_array($part, ['PROD', 'PRE', 'S24'])
                    && !is_numeric($part) // Skip numeric parts like timestamps
                    && strlen($part) > 1; // Skip single character parts
            });

            if (!empty($parts)) {
                // Capitalize and join with " / "
                return collect($parts)
                    ->map(fn($part) => ucwords(strtolower($part)))
                    ->join(' / ');
            }
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
