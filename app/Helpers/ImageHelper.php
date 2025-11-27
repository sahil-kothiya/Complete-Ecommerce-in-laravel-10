<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

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
     * Build asset URL honoring optional CDN but defaulting to absolute URLs via Laravel asset() helper
     */
    private static function buildAssetUrl(string $path): string
    {
        $cdnUrl = config('app.cdn_url');
        if (!empty($cdnUrl)) {
            return rtrim($cdnUrl, '/') . '/' . ltrim($path, '/');
        }

        // Use Laravel's asset() helper to generate proper absolute URLs
        return asset(ltrim($path, '/'));
    }

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
            Log::warning('ProductImage: Empty or invalid filename', ['filename' => $filename]);
            return self::defaultProductImage();
        }

        // Strip any leading path separators that might have been stored
        $filename = ltrim($filename, '/\\');
        
        // Check if file actually exists in storage
        $filePath = storage_path('app/public/products/' . $filename);
        $fileExists = file_exists($filePath);
        
        Log::info('ProductImage URL Generation', [
            'filename' => $filename,
            'expected_path' => $filePath,
            'file_exists' => $fileExists,
            'file_size' => $fileExists ? filesize($filePath) : 0
        ]);

        // If full storage path already present, return directly
        if (strpos($filename, 'storage/') === 0) {
            return self::buildAssetUrl($filename);
        }

        // Legacy full relative paths without storage prefix
        if (strpos($filename, 'photos/') === 0 || strpos($filename, 'products/') === 0) {
            return self::buildAssetUrl('storage/' . $filename);
        }

        // Filename-only new optimized storage (just product_*.webp)
        // Ensure products directory always included
        if (preg_match('/^product_[A-Za-z0-9]/', $filename)) {
            return self::buildAssetUrl('storage/products/' . $filename);
        }

        // New format: just filename, use configured base path
        $basePath = config('app.product_image_path', 'storage/products/');
        return self::buildAssetUrl(trim($basePath, '/') . '/' . ltrim($filename, '/'));
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
            Log::warning('VariantImage: Empty or invalid filename', ['filename' => $filename]);
            return self::defaultVariantImage();
        }

        // Strip any leading path separators that might have been stored
        $filename = ltrim($filename, '/\\');
        
        // Check if file actually exists in storage
        $filePath = storage_path('app/public/products/variants/' . $filename);
        $fileExists = file_exists($filePath);
        
        Log::info('VariantImage URL Generation', [
            'filename' => $filename,
            'expected_path' => $filePath,
            'file_exists' => $fileExists,
            'file_size' => $fileExists ? filesize($filePath) : 0
        ]);

        // Already has storage prefix – return
        if (strpos($filename, 'storage/') === 0) {
            return self::buildAssetUrl($filename);
        }

        // Legacy relative paths
        if (strpos($filename, 'photos/') === 0 || strpos($filename, 'products/variants/') === 0 || strpos($filename, 'products/') === 0) {
            return self::buildAssetUrl('storage/' . $filename);
        }

        // If this is actually a product image (product_ prefix), redirect to product path
        if (preg_match('/^product_[A-Za-z0-9]/', $filename)) {
            return self::productImageUrl($filename);
        }

        // Filename-only optimized variant (variant_*.webp) – ensure variants directory included
        if (preg_match('/^variant_[A-Za-z0-9]/', $filename)) {
            return self::buildAssetUrl('storage/products/variants/' . $filename);
        }

        // New format: just filename, use configured base path
        $basePath = config('app.variant_image_path', 'storage/products/variants/');
        return self::buildAssetUrl(trim($basePath, '/') . '/' . ltrim($filename, '/'));
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
        // Use a proper "no image" placeholder
        if (file_exists(public_path('images/no-product-image.svg'))) {
            return self::buildAssetUrl('images/no-product-image.svg');
        }
        
        if (file_exists(public_path('images/no-product-image.png'))) {
            return self::buildAssetUrl('images/no-product-image.png');
        }
        
        // Fallback to avatar if placeholder doesn't exist
        return self::buildAssetUrl('backend/img/avatar.webp');
    }

    /**
     * Default variant image URL
     */
    public static function defaultVariantImage(): string
    {
        // Fallback to existing backend image if default variant image doesn't exist
        return self::buildAssetUrl('backend/img/avatar.webp');
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
            $filename = ltrim($filename, '/\\');
            if (strpos($filename, 'storage/') === 0) {
                return asset($filename);
            }
            if (strpos($filename, 'photos/') === 0 || strpos($filename, 'products/') === 0) {
                return asset('storage/' . $filename);
            }
            if (preg_match('/^product_[A-Za-z0-9]/', $filename)) {
                return asset('storage/products/' . $filename);
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
            $filename = ltrim($filename, '/\\');
            if (strpos($filename, 'storage/') === 0) {
                return asset($filename);
            }
            if (strpos($filename, 'photos/') === 0 || strpos($filename, 'products/variants/') === 0 || strpos($filename, 'products/') === 0) {
                return asset('storage/' . $filename);
            }
            // If this is actually a product image (product_ prefix), redirect to product path
            if (preg_match('/^product_[A-Za-z0-9]/', $filename)) {
                return self::productImageUrl($filename);
            }
            if (preg_match('/^variant_[A-Za-z0-9]/', $filename)) {
                return asset('storage/products/variants/' . $filename);
            }
            if ($cdnUrl) {
                return rtrim($cdnUrl, '/') . '/' . trim($basePath, '/') . '/' . $filename;
            }
            return asset(trim($basePath, '/') . '/' . $filename);
        }, $filenames);
    }
}
