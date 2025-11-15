<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageOptimizer
{
    /**
     * Generate optimized responsive images
     * 
     * @param string $imagePath Original image path
     * @param array $sizes Array of sizes to generate [width => height]
     * @return array Generated image paths
     */
    // public static function generateResponsiveImages($imagePath, $sizes = [])
    // {
    //     if (empty($sizes)) {
    //         $sizes = [
    //             160 => 160,
    //             235 => 235,
    //             320 => 320,
    //             480 => 480,
    //             640 => 640,
    //         ];
    //     }

    //     $generatedImages = [];
    //     $originalPath = public_path($imagePath);

    //     if (!file_exists($originalPath)) {
    //         return [];
    //     }

    //     $pathInfo = pathinfo($imagePath);
    //     $directory = $pathInfo['dirname'];
    //     $filename = $pathInfo['filename'];

    //     // 👇 Replace 'Products' with 'NewProducts' in the target directory
    //     $targetDirectory = str_replace('/Products', '/NewProducts', $directory);
    //     $fullTargetDir = public_path($targetDirectory);

    //     // Ensure target directory exists
    //     if (!is_dir($fullTargetDir)) {
    //         mkdir($fullTargetDir, 0755, true);
    //     }

    //     foreach ($sizes as $width => $height) {
    //         try {
    //             $newFilename = "{$filename}_{$width}x{$height}.webp";
    //             $newRelativePath = "{$targetDirectory}/{$newFilename}";
    //             $fullNewPath = public_path($newRelativePath);

    //             // Skip if already exists and is newer than original
    //             if (file_exists($fullNewPath) && filemtime($fullNewPath) > filemtime($originalPath)) {
    //                 $generatedImages[$width] = $newRelativePath;
    //                 continue;
    //             }

    //             $image = Image::make($originalPath);
    //             $image->fit($width, $height, function ($constraint) {
    //                 $constraint->aspectRatio();
    //                 $constraint->upsize();
    //             });
    //             $image->encode('webp', 85);
    //             $image->save($fullNewPath);

    //             $generatedImages[$width] = $newRelativePath;
    //         } catch (\Exception $e) {
    //             Log::error("Image optimization failed for {$imagePath}: " . $e->getMessage());
    //         }
    //     }

    //     return $generatedImages;
    // }
    public static function generateResponsiveImages($imagePath)
    {
        $originalPath = public_path($imagePath);

        if (!file_exists($originalPath)) {
            return [];
        }

        try {
            // Load the image
            $image = Image::make($originalPath);

            // Optionally you can keep the same extension or change to .webp
            $pathInfo = pathinfo($imagePath);
            $directory = $pathInfo['dirname'];
            $filename = $pathInfo['filename'];
            $extension = $pathInfo['extension'];

            $newFilename = "{$filename}.{$extension}"; // or "{$filename}.webp" if you want to convert
            $newPath = "{$directory}/{$newFilename}";
            $fullNewPath = public_path($newPath);

            // Apply optimization (no resize)
            $image->encode($extension === 'png' ? 'png' : 'webp', 85); // Adjust logic as per original format

            // Overwrite original
            $image->save($fullNewPath);

            return [$imagePath => $newPath];
        } catch (\Exception $e) {
            Log::error("Image optimization failed for {$imagePath}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate srcset string for responsive images
     * 
     * @param string $originalPath
     * @param array $generatedImages
     * @return string
     */
    public static function generateSrcset($originalPath, $generatedImages)
    {
        $srcset = [];

        foreach ($generatedImages as $width => $path) {
            $srcset[] = asset($path) . " {$width}w";
        }

        // Add original as fallback if no generated images
        if (empty($srcset)) {
            $srcset[] = asset($originalPath) . " 370w";
        }

        return implode(', ', $srcset);
    }

    /**
     * Optimize existing images in batch
     * 
     * @param string $directory Directory to scan
     * @return int Number of images processed
     */
    public static function batchOptimize($directory = 'uploads/Products')
    {
        $path = public_path($directory);
        $files = glob($path . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        $processed = 0;

        foreach ($files as $file) {
            $relativePath = str_replace(public_path(), '', $file);
            self::generateResponsiveImages($relativePath);
            $processed++;
        }

        return $processed;
    }
}

// Helper function for blade templates
if (!function_exists('responsive_image')) {
    /**
     * Generate responsive image HTML
     * 
     * @param string $imagePath
     * @param string $alt
     * @param array $attributes
     * @return string
     */
    function responsive_image($imagePath, $alt = '', $attributes = [])
    {
        $optimizer = new \App\Helpers\ImageOptimizer();
        $generatedImages = $optimizer->generateResponsiveImages($imagePath);
        $srcset = $optimizer->generateSrcset($imagePath, $generatedImages);

        $defaultAttributes = [
            'loading' => 'lazy',
            'decoding' => 'async',
            'fetchpriority' => 'low',
        ];

        $attributes = array_merge($defaultAttributes, $attributes);
        $attributeString = '';

        foreach ($attributes as $key => $value) {
            if ($value !== null) {
                $attributeString .= " {$key}=\"{$value}\"";
            }
        }

        $fallbackSrc = asset($imagePath);

        return "<img src=\"{$fallbackSrc}\" srcset=\"{$srcset}\" alt=\"{$alt}\"{$attributeString}>";
    }
}
