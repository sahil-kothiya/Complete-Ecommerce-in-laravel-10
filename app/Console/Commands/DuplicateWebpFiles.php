<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class DuplicateWebpFiles extends Command
{
    protected $signature = 'webp:duplicate';
    protected $description = 'Duplicate .webp files with unique names in the Products directory';

    public function handle()
    {
        $dir = public_path('storage/photos/1/Products');

        if (!is_dir($dir)) {
            $this->error("Directory does not exist: $dir");
            return Command::FAILURE;
        }

        $files = glob($dir . '/*.webp');

        if (!$files) {
            $this->warn("No .webp files found in: $dir");
            return Command::SUCCESS;
        }

        foreach ($files as $file) {
            $uniqueName = Str::uuid() . '.webp';
            $newPath = $dir . DIRECTORY_SEPARATOR . $uniqueName;

            if (copy($file, $newPath)) {
                $this->info("Copied: " . basename($file) . " → $uniqueName");
            } else {
                $this->error("Failed to copy: " . basename($file));
            }
        }

        return Command::SUCCESS;
    }
}
