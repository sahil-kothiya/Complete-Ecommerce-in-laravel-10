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
        if (!$this->has_variants) {
            return collect();
        }

        // Check if variants are loaded
        if (!$this->relationLoaded('variants')) {
            // Load variants with necessary relationships
            $this->load([
                'variants.optionAssignments.option.variantType'
            ]);
        }

        $variants = $this->variants;

        if ($variants->isEmpty()) {
            return collect();
        }

        $typeGroups = collect();

        foreach ($variants as $variant) {
            // Use optionAssignments to get the options
            if ($variant->relationLoaded('optionAssignments')) {
                foreach ($variant->optionAssignments as $assignment) {
                    if ($assignment->relationLoaded('option') && $assignment->option) {
                        $option = $assignment->option;
                        
                        if ($option->relationLoaded('variantType') && $option->variantType) {
                            $type = $option->variantType;
                            
                            // Initialize type group if not exists
                            if (!$typeGroups->has($type->id)) {
                                $typeGroups->put($type->id, [
                                    'id' => $type->id,
                                    'name' => $type->name,
                                    'display_name' => $type->display_name,
                                    'sort_order' => $type->sort_order ?? 0,
                                    'options' => collect()
                                ]);
                            }
                            
                            // Add option if not already present
                            $existingOptions = $typeGroups[$type->id]['options'];
                            if (!$existingOptions->contains('id', $option->id)) {
                                $typeGroups[$type->id]['options']->push($option);
                            }
                        }
                    }
                }
            }
        }

        // Convert to collection of objects and sort
        return $typeGroups
            ->sortBy('sort_order')
            ->values()
            ->map(function($type) {
                return (object) [
                    'id' => $type['id'],
                    'name' => $type['name'],
                    'display_name' => $type['display_name'],
                    'sort_order' => $type['sort_order'],
                    'options' => $type['options']->sortBy('sort_order')->values()
                ];
            });
    }

    /**
     * Alternative method: Get variant types using product_variant_option_assignments
     * Use this in the controller for better control
     */
    public function getVariantTypesViaAssignments()
    {
        if (!$this->has_variants) {
            return collect();
        }

        // Get all variant option IDs for this product's active variants
        $optionIds = ProductVariantOptionAssignment::whereHas('variant', function($q) {
                $q->where('product_id', $this->id)
                ->where('status', 'active');
            })
            ->distinct()
            ->pluck('variant_option_id');

        if ($optionIds->isEmpty()) {
            return collect();
        }

        // Get variant types with their options
        return ProductVariantType::whereHas('options', function($q) use ($optionIds) {
                $q->whereIn('id', $optionIds)
                ->where('status', 'active');
            })
            ->with(['options' => function($q) use ($optionIds) {
                $q->whereIn('id', $optionIds)
                ->where('status', 'active')
                ->orderBy('sort_order');
            }])
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->get();
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