<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class GenerateProductSkus extends Command
{
    protected $signature = 'products:generate-skus';
    protected $description = 'Generate SKUs for existing products';

    public function handle()
    {
        $this->info("Generating SKUs...");

        Product::with(['category', 'brand'])->chunk(100, function ($products) {
            foreach ($products as $product) {
                if (!$product->sku) {
                    $sku = $this->generateSKU($product);
                    $product->sku = $sku;
                    $product->save();
                    $this->line("SKU Generated: $sku");
                }
            }
        });

        $this->info("Done.");
    }

    private function generateSKU(Product $product): string
    {
        $cat = strtoupper(substr($product->category->slug ?? 'GEN', 0, 3));
        $brand = strtoupper(substr($product->brand->slug ?? 'NON', 0, 3));
        $size = strtoupper($product->size ?? 'M');
        $id = str_pad($product->id, 5, '0', STR_PAD_LEFT);

        return "{$cat}-{$brand}-{$size}-{$id}";
    }
}
