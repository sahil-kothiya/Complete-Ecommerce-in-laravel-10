<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = ['title', 'slug', 'description', 'photo', 'status', 'link_type', 'link'];

    public function discounts()
    {
        return $this->belongsToMany(Discount::class, 'banner_discount');
    }

    /**
     * Get the resolved URL for this banner based on link_type
     */
    public function getResolvedUrlAttribute()
    {
        switch($this->link_type) {
            case 'product':
                return $this->getProductUrl();
                
            case 'category':
                return $this->getCategoryUrl();
                
            case 'url':
                return $this->link;
                
            default:
                return $this->getDiscountBasedUrl();
        }
    }

    /**
     * Get the appropriate CTA text based on link_type
     */
    public function getCtaTextAttribute()
    {
        switch($this->link_type) {
            case 'product':
                return 'View Product';
                
            case 'category':
                return 'Browse Category';
                
            case 'url':
                return 'Learn More';
                
            default:
                return 'Shop Now';
        }
    }

    /**
     * Get product URL by SKU or slug
     */
    private function getProductUrl()
    {
        if (!$this->link) return '#';

        $product = Product::where('sku', $this->link)
                          ->orWhere('slug', $this->link)
                          ->first();

        return $product ? route('product-detail', $product->slug) : '#';
    }

    /**
     * Get category URL by slug
     */
    private function getCategoryUrl()
    {
        if (!$this->link) return '#';

        $category = Category::where('slug', $this->link)->first();
        
        return $category ? route('product-cat', $category->slug) : '#';
    }

    /**
     * Get discount-based URL (fallback to existing logic)
     */
    private function getDiscountBasedUrl()
    {
        $discount = $this->discounts->first();
        $category = $discount?->categories?->first();
        
        return $category ? route('product-cat', $category->slug) : route('product-grids');
    }

    /**
     * Check if the URL is external
     */
    public function getIsExternalLinkAttribute()
    {
        return $this->link_type === 'url' && 
               $this->link && 
               !str_starts_with($this->link, url('/')) &&
               !str_starts_with($this->link, '/');
    }
}