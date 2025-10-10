<?php

namespace App\Models;

use App\Helpers\RedisHelper;
use Illuminate\Database\Eloquent\Model;
use App\Models\Cart;
use App\Services\DiscountService;
use Illuminate\Support\Facades\Cache;

class Product extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'description', 'cat_id', 'child_cat_id', 'base_price', 'brand_id', 'base_discount', 'status', 'base_sku', 'base_stock', 'is_featured', 'condition', 'has_variants'];

    protected $casts = [
        'base_price' => 'decimal:2',
        'base_discount' => 'decimal:2',
        'base_stock' => 'integer',
        'has_variants' => 'boolean',
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
            'base_price' => $this->base_price,
            'base_discount' => $this->base_discount,
            'base_stock' => $this->base_stock,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function searchableAs()
    {
        return config('elasticsearch.index');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_primary')->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function cat_info()
    {
        return $this->hasOne(Category::class, 'id', 'cat_id');
    }

    public function sub_cat_info()
    {
        return $this->hasOne(Category::class, 'id', 'child_cat_id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
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
        return $data ?: 0;
    }

    public function carts()
    {
        return $this->hasMany(Cart::class)->whereNotNull('order_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class)->whereNotNull('cart_id');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

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

    public function getVariantsJsonAttribute()
    {
        $key = "product_variants:{$this->id}";
        if (RedisHelper::has($key)) {
            return RedisHelper::get($key);
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
        RedisHelper::put($key, $json, 3600);
        return $json;
    }
}