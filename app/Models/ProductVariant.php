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
        'display_name',
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
            // Sort by variant type sort_order for consistent naming (Color → Size → Storage → RAM → etc.)
            $sorted = $this->variantOptions->sortBy(function($option) {
                // Load variantType relationship if not already loaded
                if (!$option->relationLoaded('variantType')) {
                    $option->load('variantType:id,name,display_name,sort_order');
                }
                return $option->variantType->sort_order ?? 999;
            });

            // Build concise display name - just the values separated by slashes
            // Format: "Red / 64GB / 8GB" instead of "Color: Red / Storage: 64GB / RAM: 8GB"
            $parts = $sorted->map(function($option) {
                return $option->display_value ?? $option->value;
            })->filter()->values();

            // Return the concise format
            if ($parts->isNotEmpty()) {
                return $parts->join(' / ');
            }
        }

        // Fallback to variant_values
        if (is_array($this->variant_values) && !empty($this->variant_values)) {
            return collect($this->variant_values)
                ->map(fn($val) => ucfirst($val))
                ->sort()
                ->join(' / ');
        }

        // Fallback to SKU parsing
        return $this->parseSkuForDisplay();
    }

    /**
     * Parse SKU to extract variant information for display
     */
    private function parseSkuForDisplay()
    {
        $parts = $this->parseSkuParts();
        return !empty($parts) ? collect($parts)->join(' / ') : 'Variant #' . $this->id;
    }

    /**
     * Parse SKU into component parts for display
     */
    private function parseSkuParts()
    {
        if (!$this->sku) {
            return [];
        }

        $parts = explode('-', $this->sku);

        // Remove common prefixes and the final ID part
        $cleanParts = [];
        foreach ($parts as $index => $part) {
            $part = trim($part);
            $upperPart = strtoupper($part);

            // Skip common prefixes
            if (in_array($upperPart, ['PROD', 'PRE', 'S24', 'SMA'])) {
                continue;
            }

            // Skip the last part if it's purely numeric (likely an ID)
            if ($index === count($parts) - 1 && is_numeric($part) && strlen($part) >= 1) {
                continue;
            }

            // Skip very long numeric parts that look like timestamps
            if (is_numeric($part) && strlen($part) > 4) {
                continue;
            }

            $cleanParts[] = $part;
        }

        if (empty($cleanParts)) {
            return [];
        }

        // Process parts to create meaningful display
        $processedParts = [];
        foreach ($cleanParts as $index => $part) {
            // Convert color codes to readable names
            $colorMap = [
                'BL' => 'Blue', 'RE' => 'Red', 'GO' => 'Gold', 'GR' => 'Green',
                'WH' => 'White', 'SI' => 'Silver', 'BK' => 'Black', 'PK' => 'Pink'
            ];

            $upperPart = strtoupper($part);
            if (isset($colorMap[$upperPart])) {
                $processedParts[] = $colorMap[$upperPart];
                continue;
            }

            // Determine if this is storage or RAM based on position and pattern
            if (preg_match('/^(\d+)G$/i', $part, $matches)) {
                // Parts with 'G' suffix are likely storage (4G, 8G, etc.)
                $processedParts[] = $matches[1] . 'GB Storage';
                continue;
            }

            if (is_numeric($part)) {
                $num = (int) $part;

                // Determine based on position first
                if ($index >= 2) { // 3rd position or later (after color and storage)
                    // These are likely RAM codes
                    $ramMap = [
                        '4' => '4GB RAM', '8' => '8GB RAM', '12' => '4GB RAM', '16' => '16GB RAM',
                        '25' => '8GB RAM', '32' => '32GB RAM', '51' => '4GB RAM', '64' => '64GB RAM'
                    ];
                    $processedParts[] = $ramMap[$part] ?? '4GB RAM'; // Default to 4GB for unknown codes
                    continue;
                }

                // For positions 0-1, larger numbers are likely storage
                if ($num >= 64 || $num == 32) {
                    $processedParts[] = $part . 'GB Storage';
                    continue;
                }

                // Numbers in early positions but small values
                $processedParts[] = $part . 'GB Storage';
                continue;
            }

            $processedParts[] = ucwords(strtolower($part));
        }

        return $processedParts;
    }    /**
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
