<?php

namespace App\Models;

use App\Services\RedisCacheService;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\ProductVariantOption;
use App\Models\ProductVariantType;
use App\Models\ProductVariantTypeSelection;

class Product extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'description',
        'cat_id',
        'child_cat_id',
        'base_price',
        'brand_id',
        'base_discount',
        'status',
        'base_sku',
        'base_stock',
        'is_featured',
        'condition',
        'has_variants',
        'size'
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'base_discount' => 'decimal:2',
        'base_stock' => 'integer',
        'has_variants' => 'boolean',
        'is_featured' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithVariants($query)
    {
        return $query->where('has_variants', true);
    }

    public function scopeWithoutVariants($query)
    {
        return $query->where('has_variants', false);
    }

    /**
     * Relationships
     */
    public function images()
    {
        return $this->hasMany(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variantTypeSelections()
    {
        return $this->hasMany(ProductVariantTypeSelection::class);
    }

    public function variantTypePivot()
    {
        return $this->belongsToMany(
            ProductVariantType::class,
            'product_variant_type_selections',
            'product_id',
            'product_variant_type_id'
        )->withTimestamps();
    }

    public function cat_info()
    {
        return $this->belongsTo(Category::class, 'cat_id');
    }

    public function sub_cat_info()
    {
        return $this->belongsTo(Category::class, 'child_cat_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->where('status', 'active');
    }

    public function inStockVariants()
    {
        return $this->hasMany(ProductVariant::class)
            ->where('status', 'active')
            ->where('stock', '>', 0);
    }

    public function rel_prods()
    {
        return $this->hasMany(Product::class, 'cat_id', 'cat_id')
            ->where('status', 'active')
            ->where('id', '!=', $this->id)
            ->orderBy('id', 'DESC')
            ->limit(12);
    }

    public function getReview()
    {
        return $this->hasMany(ProductReview::class, 'product_id')
            ->with('user_info')
            ->where('status', 'active')
            ->orderBy('id', 'DESC');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function carts()
    {
        return $this->hasMany(Cart::class)->whereNotNull('order_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class)->whereNotNull('cart_id');
    }

    /**
     * Attributes
     */
    public function getRatingAttribute()
    {
        return Cache::remember(
            "product_rating:{$this->id}",
            3600,
            function () {
                $rating = $this->reviews()
                    ->selectRaw('AVG(CAST(rate AS DECIMAL(3,2))) as avg_rating, COUNT(*) as total')
                    ->first();

                if (!$rating || !$rating->avg_rating) {
                    return ['average' => 0, 'total' => 0];
                }

                return [
                    'average' => round($rating->avg_rating, 1),
                    'total' => (int) $rating->total
                ];
            }
        );
    }

    public function getPriceRangeAttribute()
    {
        if (!$this->has_variants) {
            return null;
        }

        $variants = $this->inStockVariants;

        if ($variants->isEmpty()) {
            return null;
        }

        $prices = $variants->map(fn($v) => $v->discounted_price);
        $min = $prices->min();
        $max = $prices->max();

        if ($min === $max) {
            return '$' . number_format($min, 2);
        }

        return '$' . number_format($min, 2) . ' - $' . number_format($max, 2);
    }

    public function loadVariantTypes(): void
    {
        // 1. Try to load variant types from product_variant_type_selections first
        $typeIds = DB::table('product_variant_type_selections')
            ->where('product_id', $this->id)
            ->pluck('product_variant_type_id');

        // Fallback: If no selections saved, load ALL variant types (for backward compatibility)
        if ($typeIds->isEmpty()) {
            $typeIds = ProductVariantType::query()
                ->orderBy('sort_order')
                ->pluck('id');
        }

        // 2. Load all options for those types
        $options = ProductVariantOption::whereIn('variant_type_id', $typeIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('variant_type_id');

        // 3. Get IDs of options already used in any variant of this product
        $selectedOptionIds = $this->variants()
            ->with('variantOptions')
            ->get()
            ->pluck('variantOptions.*.id')
            ->flatten()
            ->unique()
            ->all();

        // 4. FALLBACK: If no option IDs found (old variants without assignments),
        //    try to extract from SKUs or variant display names
        if (empty($selectedOptionIds) && $this->variants->isNotEmpty()) {
            // Extract option values from existing variant SKUs or names
            $allOptions = ProductVariantOption::whereIn('variant_type_id', $typeIds)->get();

            foreach ($this->variants as $variant) {
                // Check variant SKU for option values
                $sku = strtoupper($variant->sku);

                foreach ($allOptions as $option) {
                    $optionValue = strtoupper($option->display_value);

                    // Direct match: full value or without spaces/with dashes
                    if (strpos($sku, $optionValue) !== false ||
                        strpos($sku, str_replace(' ', '', $optionValue)) !== false ||
                        strpos($sku, str_replace(' ', '-', $optionValue)) !== false) {
                        $selectedOptionIds[] = $option->id;
                        continue;
                    }

                    // Partial match: check if SKU contains first 3+ chars of option value
                    // This handles abbreviations like "GOL" for "GOLD", "SIL" for "SILVER"
                    if (strlen($optionValue) >= 3) {
                        $prefix = substr($optionValue, 0, 3);
                        if (strpos($sku, $prefix) !== false) {
                            $selectedOptionIds[] = $option->id;
                        }
                    }
                }
            }

            $selectedOptionIds = array_unique($selectedOptionIds);
        }

        // 4. Build final collection with 'selected' flag
        $types = ProductVariantType::whereIn('id', $typeIds)
            ->orderBy('sort_order')
            ->get()
            ->map(function ($type) use ($options, $selectedOptionIds) {
                $typeOptions = $options->get($type->id, collect());

                $type->options = $typeOptions->map(function ($opt) use ($selectedOptionIds) {
                    $opt->selected = in_array($opt->id, $selectedOptionIds);
                    return $opt;
                })->values();

                return $type;
            });

        $this->setRelation('variantTypes', $types);
    }

    /**
     * ----------------------------------------------------------------------
     * 2. ACCESSOR – use `$product->variantTypes` in Blade / API
     * ----------------------------------------------------------------------
     */
    public function getVariantTypesAttribute(): \Illuminate\Support\Collection
    {
        // If we already loaded them via loadVariantTypes() just return
        if ($this->relationLoaded('variantTypes')) {
            return $this->getRelation('variantTypes');
        }

        // Fallback – load on-the-fly (still only 2 queries)
        $this->loadVariantTypes();
        return $this->getRelation('variantTypes');
    }

    /**
     * ----------------------------------------------------------------------
     * 3. (Optional) Old method – kept for backward compatibility
     * ----------------------------------------------------------------------
     */
    public function getVariantTypesOptimized()
    {
        // Just delegate to the new accessor
        return $this->variantTypes;
    }



    /**
     * Get cheapest in-stock variant
     */
    public function getCheapestVariantAttribute()
    {
        if (!$this->has_variants) {
            return null;
        }

        return $this->inStockVariants()
            ->orderByRaw('price * (1 - COALESCE(discount, 0)/100) ASC')
            ->first();
    }

    /**
     * Static Methods
     */
    public static function getAllProduct()
    {
        return Product::with(['cat_info', 'sub_cat_info'])
            ->orderBy('id', 'desc')
            ->paginate(10);
    }

    public static function getProductBySlug($slug)
    {
        return Product::with(['cat_info', 'rel_prods.images', 'getReview'])
            ->where('slug', $slug)
            ->first();
    }

    public static function countActiveProduct()
    {
        return Product::where('status', 'active')->count();
    }

    /**
     * Search Integration
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'base_price' => $this->base_price,
            'base_discount' => $this->base_discount,
            'base_stock' => $this->base_stock,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function searchableAs()
    {
        return config('elasticsearch.index');
    }

    /**
     * Cache Helpers
     */
    public function getVariantsJsonAttribute()
    {
        $key = "product_variants:{$this->id}";

        if (RedisCacheService::has($key)) {
            return RedisCacheService::get($key);
        }

        $variants = $this->activeVariants()
            ->with('variantOptions.variantType')
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'sku' => $v->sku,
                'price' => $v->price,
                'stock' => $v->stock,
                'display' => $v->display_name,
                'values' => $v->variant_values
            ]);

        $json = $variants->toJson();
        RedisCacheService::put($key, $json, 3600);

        return $json;
    }

    /**
     * Clear cache when product is updated
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function ($product) {
            Cache::forget("product_rating:{$product->id}");
            RedisCacheService::forget("product_variants:{$product->id}");
        });

        static::deleted(function ($product) {
            Cache::forget("product_rating:{$product->id}");
            RedisCacheService::forget("product_variants:{$product->id}");
        });
    }

    /**
     * Resolve everything that the carousel needs:
     *   – price (discounted)
     *   – original price (if discount >0)
     *   – discount %
     *   – stock status (0 = out-of-stock)
     *   – primary image URL
     *   – display name (variant display or product title)
     *
     * @return object
     */
    public function resolveDisplayData()
    {
        // -----------------------------------------------------------------
        // 1. Products WITHOUT variants – use base fields
        // -----------------------------------------------------------------
        if (! $this->has_variants) {
            $discount = $this->base_discount ?? 0;
            $originalPrice = $this->base_price;
            $discountedPrice = $discount > 0
                ? $originalPrice * (1 - $discount / 100)
                : $originalPrice;

            return (object) [
                'price'          => $discountedPrice,
                'original_price' => $originalPrice,
                'discount'       => $discount,
                'stock'          => $this->base_stock,
                'image_url'      => $this->primaryImage?->url ?? asset('images/no-image.png'),
                'display_name'   => $this->title,
            ];
        }

        // -----------------------------------------------------------------
        // 2. Products WITH variants – cheapest *in-stock* variant wins
        // -----------------------------------------------------------------
        $variant = $this->inStockVariants()
            ->with(['primaryImage', 'variantOptions'])
            ->orderByRaw('price * (1 - COALESCE(discount,0)/100) ASC')
            ->first();

        // If every variant is out of stock → show out-of-stock UI
        if (! $variant) {
            $variant = $this->activeVariants()
                ->with(['primaryImage', 'variantOptions'])
                ->orderBy('price')
                ->first(); // fallback to any active variant (still show “out of stock”)
        }

        $discounted = $variant?->discounted_price ?? $variant?->price ?? 0;
        $original   = $variant?->price ?? 0;
        $discount   = $variant?->discount ?? 0;
        $stock      = $variant?->stock ?? 0;

        return (object) [
            'price'          => $discounted,
            'original_price' => $original,
            'discount'       => $discount,
            'stock'          => $stock,
            'image_url'      => $variant?->primaryImage?->url
                            ?? $variant?->images->first()?->url
                            ?? $this->primaryImage?->url
                            ?? asset('images/no-image.png'),
            'display_name'   => $variant?->display_name
                            ? $this->title . ' – ' . $variant->display_name
                            : $this->title,
        ];
    }
}
