<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VariantImage extends Model
{
    use HasFactory;

    protected $table = 'variant_images';

    protected $fillable = [
        'product_variant_id',
        'image_path',
        'thumbnail_path',
        'is_primary',
        'sort_order'
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = ['url', 'thumbnail_url'];

    /**
     * Relationships
     */
    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Get full URL for the image (filename-only storage optimization)
     */
    public function getUrlAttribute()
    {
        return ImageHelper::variantImageUrl($this->image_path);
    }

    /**
     * Get full URL for the thumbnail (filename-only storage optimization)
     */
    public function getThumbnailUrlAttribute()
    {
        return $this->thumbnail_path ? ImageHelper::variantImageUrl($this->thumbnail_path, true) : null;
    }

    /**
     * Scopes
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order', 'asc');
    }
}
