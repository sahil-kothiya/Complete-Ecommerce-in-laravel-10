<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'summary',
        'photo',
        'parent_id',
        'level',
        'path',
        'sort_order',
        'has_children',
        'children_count',
        'products_count',
        'status',
        'is_featured',
        'seo_title',
        'seo_description',
        'added_by',
        'code',
        'code_locked',
        'code_generated_at',
    ];

    // Relationships
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->where('status', 'active');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'cat_id')->where('status', 'active');
    }

    public function sub_products()
    {
        return $this->hasMany(Product::class, 'child_cat_id')->where('status', 'active');
    }

    /**
     * Relationship: Category ↔ Brands (Many-to-Many)
     */
    public function brands()
    {
        return $this->belongsToMany(Brand::class, 'brand_category', 'category_id', 'brand_id')
            ->withTimestamps();
    }

    /**
     * Relationship: Category ↔ Filters (Many-to-Many)
     */
    public function filters()
    {
        return $this->belongsToMany(Filter::class, 'category_filter', 'category_id', 'filter_id')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Static Methods
    public static function getAllCategory()
    {
        return self::select('id', 'title', 'slug', 'parent_id', 'status', 'photo')
            ->with(['parent:id,title,parent_id'])
            ->orderByDesc('id')
            ->paginate(10);
    }

    public static function getChildByParentID($id)
    {
        return self::where('parent_id', $id)->orderBy('id')->pluck('title', 'id');
    }

    public static function getAllParentWithChild()
    {
        return self::with('children')->active()->whereNull('parent_id')->orderBy('title')->get();
    }

    public static function getProductByCat($slug)
    {
        return self::where('slug', $slug)
            ->with(['products' => function ($query) {
                $query->where('status', 'active')->paginate(12); // Note: paginate inside with() won’t work as expected
            }])
            ->first();
    }

    public static function getProductBySubCat($slug)
    {
        return self::with('sub_products')->where('slug', $slug)->first();
    }

    public static function countActiveCategory()
    {
        return self::active()->count();
    }

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'category_discount');
    }

    // Additional methods for tree management
    public function updateSubtreePathAndLevel($newPath, $newLevel)
    {
        $oldPath = $this->path;
        $this->path = $newPath;
        $this->level = $newLevel;
        $this->saveQuietly();

        foreach ($this->children as $child) {
            $childPath = $newPath ? $newPath . '/' . $this->id : (string) $this->id;
            $child->updateSubtreePathAndLevel($childPath, $newLevel + 1);
        }
    }

    /**
     * Get full parent chain like "Electronics > Mobiles > Samsung"
     */
    public function getParentChainAttribute()
    {
        $chain = [];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($chain, $parent->title);
            // 👇 load parent’s parent if not already loaded
            if (!$parent->relationLoaded('parent')) {
                $parent->load('parent');
            }
            $parent = $parent->parent;
        }

        return $chain ? implode(' > ', $chain) : '';
    }

    public function getFullSlugPath()
    {
        $path = [$this->slug];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->slug);
            $parent = $parent->parent;
        }
        return implode('/', $path);
    }

    public function getPath()
    {
        $path = [$this->slug];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->slug);
            $parent = $parent->parent;
        }
        return implode('/', $path);
    }
}
