<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['title', 'slug', 'summary', 'photo', 'status', 'is_parent', 'parent_id', 'added_by'];

    protected $casts = [
        'is_parent' => 'boolean',
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

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Static Methods
    public static function getAllCategory()
    {
        return self::select('id', 'title', 'slug', 'parent_id', 'status')
            ->with(['parent:id,title'])
            ->orderByDesc('id')
            ->paginate(10);
    }

    public static function markAsParent(array $catIds)
    {
        return self::whereIn('id', $catIds)->update(['is_parent' => 1]);
    }

    public static function getChildByParentID($id)
    {
        return self::where('parent_id', $id)->orderBy('id')->pluck('title', 'id');
    }

    public static function getAllParentWithChild()
    {
        return self::with('children')->active()->where('is_parent', 1)->orderBy('title')->get();
    }

    public static function getProductByCat($slug)
    {
        return self::with('products')->where('slug', $slug)->first();
    }

    public static function getProductBySubCat($slug)
    {
        return self::with('sub_products')->where('slug', $slug)->first();
    }

    public static function countActiveCategory()
    {
        return self::active()->count();
    }
}
