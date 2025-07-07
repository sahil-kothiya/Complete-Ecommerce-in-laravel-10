<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class OptimizeAssets extends Command
{
    protected $signature = 'assets:optimize';
    protected $description = 'Optimize CSS and JS assets beyond basic minification';

    public function handle()
    {
        $this->optimizeCSS();
        $this->optimizeJS();
        $this->info('Assets optimized successfully!');
    }

    private function optimizeCSS()
    {
        $cssFiles = File::glob(public_path('css/*.css'));
        
        foreach ($cssFiles as $file) {
            $content = File::get($file);
            
            // Advanced CSS optimization
            $content = preg_replace('/\s+/', ' ', $content); // Normalize whitespace
            $content = preg_replace('/;\s*}/', '}', $content); // Remove unnecessary semicolons
            $content = preg_replace('/\s*{\s*/', '{', $content); // Clean braces
            $content = preg_replace('/;\s*/', ';', $content); // Clean semicolons
            $content = preg_replace('/,\s*/', ',', $content); // Clean commas
            $content = str_replace(': ', ':', $content); // Remove spaces after colons
            
            File::put($file, trim($content));
        }
    }

    private function optimizeJS()
    {
        $jsFiles = File::glob(public_path('js/*.js'));
        
        foreach ($jsFiles as $file) {
            // JS files should be processed by Terser via webpack
            // This is just for additional cleanup if needed
            $content = File::get($file);
            $content = preg_replace('/console\.log\([^)]*\);?/', '', $content);
            File::put($file, $content);
        }
    }
}