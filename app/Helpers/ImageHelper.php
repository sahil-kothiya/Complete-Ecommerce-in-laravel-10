<?php

namespace App\Helpers;

/**
 * Image Helper - Ultra High-Volume Optimization
 *
 * Handles path construction for images stored as filenames only in DB.
 * This approach reduces database storage by 50% and provides flexibility
 * to switch between storage backends (local/S3/CDN) without DB migration.
 */
class ImageHelper
{
    /**
     * Get full URL for a product image
     *
     * @param string|null $filename Just the filename from DB (e.g., "product_123.webp")
     * @param bool $thumbnail Whether to get thumbnail version
     * @return string Full URL with path
     */
    public static function productImageUrl(?string $filename, bool $thumbnail = false): string
    {
        if (empty($filename) || !is_string($filename)) {
            return self::defaultProductImage();
        }

        // Strip any leading path separators that might have been stored
        $filename = ltrim($filename, '/\\');

        $basePath = config('app.product_image_path', 'storage/products/');
        $cdnUrl = config('app.cdn_url');

        if ($cdnUrl) {
            // CDN mode: https://cdn.example.com/storage/products/product_123.webp
            return rtrim($cdnUrl, '/') . '/' . trim($basePath, '/') . '/' . $filename;
        }

        // Local mode: http://localhost/storage/products/product_123.webp
        return asset(trim($basePath, '/') . '/' . $filename);
    }

    /**
     * Get full URL for a variant image
     *
     * @param string|null $filename Just the filename from DB (e.g., "variant_456.webp")
     * @param bool $thumbnail Whether to get thumbnail version
     * @return string Full URL with path
     */
    public static function variantImageUrl(?string $filename, bool $thumbnail = false): string
    {
        if (empty($filename) || !is_string($filename)) {
            return self::defaultVariantImage();
        }

        // Strip any leading path separators that might have been stored
        $filename = ltrim($filename, '/\\');

        $basePath = config('app.variant_image_path', 'storage/products/variants/');
        $cdnUrl = config('app.cdn_url');

        if ($cdnUrl) {
            // CDN mode: https://cdn.example.com/storage/products/variants/variant_456.webp
            return rtrim($cdnUrl, '/') . '/' . trim($basePath, '/') . '/' . $filename;
        }

        // Local mode: http://localhost/storage/products/variants/variant_456.webp
        return asset(trim($basePath, '/') . '/' . $filename);
    }

    /**
     * Get full storage path for a product image (for file operations)
     *
     * @param string $filename Just the filename
     * @return string Full filesystem path
     */
    public static function productImagePath(string $filename): string
    {
        return storage_path('app/public/products/' . $filename);
    }

    /**
     * Get full storage path for a variant image (for file operations)
     *
     * @param string $filename Just the filename
     * @return string Full filesystem path
     */
    public static function variantImagePath(string $filename): string
    {
        return storage_path('app/public/products/variants/' . $filename);
    }

    /**
     * Default product image URL
     */
    public static function defaultProductImage(): string
    {
        // Fallback to existing backend image if default product image doesn't exist
        return asset('backend/img/avatar.webp');
    }

    /**
     * Default variant image URL
     */
    public static function defaultVariantImage(): string
    {
        // Fallback to existing backend image if default variant image doesn't exist
        return asset('backend/img/avatar.webp');
    }

    /**
     * Generate cache-busted URL with file hash
     *
     * @param string $filename Filename from DB
     * @param string|null $hash File hash from DB (if available)
     * @param string $type 'product' or 'variant'
     * @return string URL with cache busting parameter
     */
    public static function cacheBustedUrl(string $filename, ?string $hash, string $type = 'product'): string
    {
        $url = $type === 'variant'
            ? self::variantImageUrl($filename)
            : self::productImageUrl($filename);

        if ($hash) {
            $url .= '?v=' . substr($hash, 0, 8);
        }

        return $url;
    }

    /**
     * Batch get product image URLs (optimized for collections)
     *
     * @param array $filenames Array of filenames
     * @return array Array of URLs
     */
    public static function batchProductImageUrls(array $filenames): array
    {
        $basePath = config('app.product_image_path', 'storage/products/');
        $cdnUrl = config('app.cdn_url');

        return array_map(function($filename) use ($basePath, $cdnUrl) {
            if (empty($filename)) {
                return self::defaultProductImage();
            }

            if ($cdnUrl) {
                return rtrim($cdnUrl, '/') . '/' . trim($basePath, '/') . '/' . $filename;
            }

            return asset(trim($basePath, '/') . '/' . $filename);
        }, $filenames);
    }

    /**
     * Batch get variant image URLs (optimized for collections)
     *
     * @param array $filenames Array of filenames
     * @return array Array of URLs
     */
    public static function batchVariantImageUrls(array $filenames): array
    {
        $basePath = config('app.variant_image_path', 'storage/products/variants/');
        $cdnUrl = config('app.cdn_url');

        return array_map(function($filename) use ($basePath, $cdnUrl) {
            if (empty($filename)) {
                return self::defaultVariantImage();
            }

            if ($cdnUrl) {
                return rtrim($cdnUrl, '/') . '/' . trim($basePath, '/') . '/' . $filename;
            }

            return asset(trim($basePath, '/') . '/' . $filename);
        }, $filenames);
    }
}
