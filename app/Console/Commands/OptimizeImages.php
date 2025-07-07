<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\ImageOptimizer;
use Illuminate\Support\Facades\DB;

class OptimizeImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:optimize 
                            {--directory=uploads/Products : Directory to optimize}
                            {--update-db : Update database with new paths}
                            {--force : Force regeneration of existing optimized images}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize product images for better performance';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $directory = $this->option('directory');
        $updateDb = $this->option('update-db');
        $force = $this->option('force');

        $this->info("Starting image optimization for directory: {$directory}");
        
        $path = public_path($directory);
        
        if (!is_dir($path)) {
            $this->error("Directory {$path} does not exist!");
            return 1;
        }

        $files = glob($path . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        $total = count($files);
        
        if ($total === 0) {
            $this->info("No images found in {$directory}");
            return 0;
        }

        $this->info("Found {$total} images to optimize");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $processed = 0;
        $errors = 0;

        foreach ($files as $file) {
            try {
                $relativePath = str_replace(public_path(), '', $file);
                $relativePath = ltrim($relativePath, '/');
                
                // Skip if already has responsive versions and not forcing
                if (!$force && $this->hasResponsiveVersions($relativePath)) {
                    $bar->advance();
                    continue;
                }

                $generatedImages = ImageOptimizer::generateResponsiveImages($relativePath);
                
                if (!empty($generatedImages)) {
                    $processed++;
                    
                    // Update database if requested
                    if ($updateDb) {
                        $this->updateDatabasePaths($relativePath, $generatedImages);
                    }
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nError processing {$file}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        
        $this->newLine(2);
        $this->info("Optimization complete!");
        $this->info("Processed: {$processed} images");
        
        if ($errors > 0) {
            $this->warn("Errors: {$errors} images failed to process");
        }

        // Show space savings estimate
        $this->showSpaceSavings($directory);

        return 0;
    }

    /**
     * Check if responsive versions already exist
     */
    private function hasResponsiveVersions($imagePath)
    {
        $pathInfo = pathinfo($imagePath);
        $directory = $pathInfo['dirname'];
        $filename = $pathInfo['filename'];
        
        $testPath = public_path("{$directory}/{$filename}_235x235.webp");
        return file_exists($testPath);
    }

    /**
     * Update database with optimized image paths
     */
    private function updateDatabasePaths($originalPath, $generatedImages)
    {
        // This is a basic example - adjust based on your database structure
        try {
            // Find the image record
            $imageRecord = DB::table('product_images')
                ->where('image_path', $originalPath)
                ->first();
                
            if ($imageRecord) {
                // Store responsive image data as JSON
                $responsiveData = json_encode($generatedImages);
                
                DB::table('product_images')
                    ->where('id', $imageRecord->id)
                    ->update([
                        'responsive_images' => $responsiveData,
                        'updated_at' => now()
                    ]);
            }
        } catch (\Exception $e) {
            $this->warn("Failed to update database for {$originalPath}: " . $e->getMessage());
        }
    }

    /**
     * Calculate and show estimated space savings
     */
    private function showSpaceSavings($directory)
    {
        $originalSize = 0;
        $optimizedSize = 0;
        
        $files = glob(public_path($directory) . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        
        foreach ($files as $file) {
            $originalSize += filesize($file);
            
            // Check for optimized versions
            $pathInfo = pathinfo($file);
            $optimizedPattern = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_*x*.webp';
            $optimizedFiles = glob($optimizedPattern);
            
            foreach ($optimizedFiles as $optimizedFile) {
                $optimizedSize += filesize($optimizedFile);
            }
        }
        
        if ($optimizedSize > 0) {
            $savings = $originalSize - $optimizedSize;
            $percentage = round(($savings / $originalSize) * 100, 1);
            
            $this->info("Estimated space savings: " . $this->formatBytes($savings) . " ({$percentage}%)");
            $this->info("Original size: " . $this->formatBytes($originalSize));
            $this->info("Optimized size: " . $this->formatBytes($optimizedSize));
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}