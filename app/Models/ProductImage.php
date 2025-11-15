<?php

namespace App\Models;

use App\Helpers\ImageHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'image_path',
        'is_primary',
        'sort_order',
        'alt_text',
        'thumbnail_path'
    ];

    protected $appends = ['url', 'thumbnail_url'];

    protected $casts = [
        'is_primary' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get full URL for the image (filename-only storage optimization)
     */
    public function getUrlAttribute()
    {
        if (empty($this->image_path)) {
            return ImageHelper::defaultProductImage();
        }
        return ImageHelper::productImageUrl($this->image_path);
    }

    /**
     * Get full URL for the thumbnail (filename-only storage optimization)
     */
    public function getThumbnailUrlAttribute()
    {
        if (empty($this->thumbnail_path)) {
            return null;
        }
        return ImageHelper::productImageUrl($this->thumbnail_path, true);
    }
}
