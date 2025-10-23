<?php

namespace App\Models;

use App\Helpers\RedisHelper;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'description', 'cat_id', 'child_cat_id', 
        'base_price', 'brand_id', 'base_discount', 'status', 'base_sku', 
        'base_stock', 'is_featured', 'condition', 'has_variants'
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

    /**
     * Get variant types efficiently (use in controller with eager loading)
     */
    public function getVariantTypesOptimized()
    {
        if (!$this->has_variants) {
            return collect();
        }

        // Requires variants.variantOptions.variantType to be eager loaded
        if (!$this->relationLoaded('variants')) {
            return collect();
        }

        $typeMap = [];
        
        foreach ($this->variants as $variant) {
            if (!$variant->relationLoaded('variantOptions')) {
                continue;
            }

            foreach ($variant->variantOptions as $option) {
                $type = $option->variantType;
                
                if (!isset($typeMap[$type->id])) {
                    $typeMap[$type->id] = [
                        'id' => $type->id,
                        'name' => $type->name,
                        'display_name' => $type->display_name,
                        'sort_order' => $type->sort_order,
                        'options' => []
                    ];
                }
                
                if (!isset($typeMap[$type->id]['options'][$option->id])) {
                    $typeMap[$type->id]['options'][$option->id] = $option;
                }
            }
        }
        
        return collect($typeMap)
            ->sortBy('sort_order')
            ->values()
            ->map(function($type) {
                return (object) [
                    'id' => $type['id'],
                    'name' => $type['name'],
                    'display_name' => $type['display_name'],
                    'sort_order' => $type['sort_order'],
                    'options' => collect($type['options'])
                        ->sortBy('sort_order')
                        ->values()
                ];
            });
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
        
        if (RedisHelper::has($key)) {
            return RedisHelper::get($key);
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
        RedisHelper::put($key, $json, 3600);
        
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
            RedisHelper::forget("product_variants:{$product->id}");
        });

        static::deleted(function ($product) {
            Cache::forget("product_rating:{$product->id}");
            RedisHelper::forget("product_variants:{$product->id}");
        });
    }
}