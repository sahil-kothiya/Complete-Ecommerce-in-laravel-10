<?php

use App\Helpers\ImageHelper;

if (!function_exists('product_image_url')) {
    /**
     * Get product image URL (filename-only storage optimization)
     *
     * @param string|null $filename
     * @param bool $thumbnail
     * @return string
     */
    function product_image_url(?string $filename, bool $thumbnail = false): string
    {
        return ImageHelper::productImageUrl($filename, $thumbnail);
    }
}

if (!function_exists('variant_image_url')) {
    /**
     * Get variant image URL (filename-only storage optimization)
     *
     * @param string|null $filename
     * @param bool $thumbnail
     * @return string
     */
    function variant_image_url(?string $filename, bool $thumbnail = false): string
    {
        return ImageHelper::variantImageUrl($filename, $thumbnail);
    }
}

if (!function_exists('image_cache_bust')) {
    /**
     * Get cache-busted image URL
     *
     * @param string $filename
     * @param string|null $hash
     * @param string $type
     * @return string
     */
    function image_cache_bust(string $filename, ?string $hash, string $type = 'product'): string
    {
        return ImageHelper::cacheBustedUrl($filename, $hash, $type);
    }
}
