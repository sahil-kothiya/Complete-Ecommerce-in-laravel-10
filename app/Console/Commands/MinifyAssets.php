<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MinifyAssets extends Command
{
    protected $signature = 'assets:minify';
    protected $description = 'Minify CSS and JS assets';

    public function handle()
    {
        $this->info('Starting asset minification...');
        
        $this->minifyCSS();
        $this->minifyJS();
        
        $this->info('Asset minification completed!');
    }

    private function minifyCSS()
    {
        $cssFiles = File::glob(public_path('css/*.css'));
        
        foreach ($cssFiles as $file) {
            if (strpos($file, '.min.css') !== false) {
                continue; // Skip already minified files
            }
            
            $content = File::get($file);
            $minified = $this->minifyCSS($content);
            
            $minifiedFile = str_replace('.css', '.min.css', $file);
            File::put($minifiedFile, $minified);
            
            $this->line("Minified: " . basename($file) . " -> " . basename($minifiedFile));
        }
    }

    private function minifyJS()
    {
        $jsFiles = File::glob(public_path('js/*.js'));
        
        foreach ($jsFiles as $file) {
            if (strpos($file, '.min.js') !== false) {
                continue; // Skip already minified files
            }
            
            $content = File::get($file);
            $minified = $this->minifyJavaScript($content);
            
            $minifiedFile = str_replace('.js', '.min.js', $file);
            File::put($minifiedFile, $minified);
            
            $this->line("Minified: " . basename($file) . " -> " . basename($minifiedFile));
        }
    }

    private function minifyCSSContent($css)
    {
        // Remove comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        
        // Remove tabs, spaces, newlines, etc.
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);
        
        // Remove spaces around specific characters
        $css = preg_replace('/\s*([{}|:;,>+~])\s*/', '$1', $css);
        
        return trim($css);
    }

    private function minifyJavaScript($js)
    {
        // Remove single line comments
        $js = preg_replace('/\/\/.*$/m', '', $js);
        
        // Remove multi-line comments
        $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
        
        // Remove extra whitespace
        $js = preg_replace('/\s+/', ' ', $js);
        
        // Remove spaces around operators and punctuation
        $js = preg_replace('/\s*([{}|:;,>+\-*/=()])\s*/', '$1', $js);
        
        return trim($js);
    }
}