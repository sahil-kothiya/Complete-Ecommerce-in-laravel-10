<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Cart;
use App\Services\DiscountService;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'description', 'cat_id', 'child_cat_id', 'price', 'brand_id', 'discount', 'status', 'photo', 'size', 'stock', 'is_featured', 'condition', 'sku'];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'integer',
        'stock' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the indexable data array for the model.
     */
    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'price' => $this->price,
            'discount' => $this->discount,
            'stock' => $this->stock,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Get the index name for the model.
     */
    public function searchableAs()
    {
        return config('elasticsearch.index');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }
    public function defaultImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_default', true);
    }

    public function cat_info()
    {
        return $this->hasOne(Category::class, 'id', 'cat_id');
    }
    public function sub_cat_info()
    {
        return $this->hasOne(Category::class, 'id', 'child_cat_id');
    }
    public static function getAllProduct()
    {
        return Product::with(['cat_info', 'sub_cat_info'])->orderBy('id', 'desc')->paginate(10);
    }
    public function rel_prods()
    {
        return $this->hasMany(Product::class, 'cat_id', 'cat_id')->where('status', 'active')->orderBy('id', 'DESC')->limit(12);
    }
    public function getReview()
    {
        return $this->hasMany(ProductReview::class, 'product_id', 'id')->with('user_info')->where('status', 'active')->orderBy('id', 'DESC');
    }
    public static function getProductBySlug($slug)
    {
        return Product::with(['cat_info', 'rel_prods.images', 'getReview'])->where('slug', $slug)->first();
    }
    public static function countActiveProduct()
    {
        $data = Product::where('status', 'active')->count();
        if ($data) {
            return $data;
        }
        return 0;
    }

    public function getFirstImagePathAttribute()
    {
        return $this->images->first()->image_path ?? 'images/no-image.png';
    }

    public function carts()
    {
        return $this->hasMany(Cart::class)->whereNotNull('order_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class)->whereNotNull('cart_id');
    }

    public function brand()
    {
        return $this->hasOne(Brand::class, 'id', 'brand_id');
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'product_discount');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class, 'product_id');
    }

    public function getDiscountedPriceAttribute(): float
    {
        return app(DiscountService::class)->getCachedDiscountedPrice($this);
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'cat_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function scopeInStock(Builder $query)
    {
        return $query->where('stock', '>', 0);
    }

    public function scopeWithDiscount(Builder $query)
    {
        return $query->where('discount', '>', 0);
    }

    public function scopeByCategory(Builder $query, $categoryIds)
    {
        if (!is_array($categoryIds)) {
            $categoryIds = [$categoryIds];
        }

        return $query->where(function ($q) use ($categoryIds) {
            $q->whereIn('cat_id', $categoryIds)
                ->orWhereIn('child_cat_id', $categoryIds);
        });
    }

    public function scopePriceRange(Builder $query, $minPrice, $maxPrice)
    {
        return $query->whereRaw('
            CASE 
                WHEN discount > 0 THEN 
                    price * (1 - discount::decimal / 100)
                ELSE 
                    price 
            END BETWEEN ? AND ?
        ', [$minPrice, $maxPrice]);
    }

    // Computed attributes
    public function getFinalPriceAttribute()
    {
        if ($this->discount > 0) {
            return $this->price * (1 - $this->discount / 100);
        }
        return $this->price;
    }

    public function getIsInStockAttribute()
    {
        return $this->stock > 0;
    }

    // In Product.php model

    // Accessor for rating average
    public function getRatingAverageAttribute()
    {
        $rating = Cache::remember(
            "product_rating_avg_{$this->id}",
            3600,
            function () {
                return $this->reviews()
                    ->selectRaw('AVG(CAST(rate AS DECIMAL(3,2))) as avg_rating')
                    ->value('avg_rating');
            }
        );

        return $rating ? round($rating, 1) : 0;
    }

    // Accessor for rating count
    public function getRatingCountAttribute()
    {
        return Cache::remember(
            "product_rating_count_{$this->id}",
            3600,
            function () {
                return $this->reviews()->count();
            }
        );
    }

    // Cache rating for performance
    public function getRatingAttribute()
    {
        return Cache::remember(
            "product_rating_{$this->id}",
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

    // Add these if missing
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
        return $this->hasMany(ProductVariant::class)->where('status', 'active')->where('stock', '>', 0);
    }

    public function getVariantTypesAttribute()
    {
        if (!$this->has_variants) return collect();
        $optionIds = $this->variants()->with('variantOptions')->get()->pluck('variantOptions.*.id')->flatten()->unique();
        return ProductVariantType::whereHas('options', fn($q) => $q->whereIn('id', $optionIds))
            ->with(['options' => fn($q) => $q->whereIn('id', $optionIds)->active()])
            ->active()->orderBy('sort_order')->get();
    }

    public function getPriceRangeAttribute()
    {
        if (!$this->has_variants) return null;
        $variants = $this->activeVariants()->where('stock', '>', 0)->get();
        if ($variants->isEmpty()) return null;
        $min = $variants->min('discounted_price');
        $max = $variants->max('discounted_price');
        return $min === $max ? '$' . number_format($min, 2) : '$' . number_format($min, 2) . ' - $' . number_format($max, 2);
    }

    // Cached variants for frontend (using RedisHelper)
    public function getVariantsJsonAttribute()
    {
        $key = "product_variants:{$this->id}";
        if (\App\Helpers\RedisHelper::has($key)) {
            return \App\Helpers\RedisHelper::get($key);
        }
        $variants = $this->activeVariants()->with('variantOptions.variantType')->get()->map(fn($v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'price' => $v->price,
            'stock' => $v->stock,
            'display' => $v->display_name,
            'values' => $v->variant_values
        ]);
        $json = $variants->toJson();
        \App\Helpers\RedisHelper::put($key, $json, 3600);
        return $json;
    }
}
