<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Exception;

class GenerateProductSkusSeeder extends Seeder
{
    private int $processedCount = 0;
    private int $logInterval = 10000;
    private int $chunkSize = 500; // Larger chunks for PostgreSQL

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $startTime = microtime(true);
        echo "Starting SKU generation for products without SKUs (PostgreSQL)...\n";
        echo "Started at: " . date('Y-m-d H:i:s') . "\n";

        // Get total count for progress tracking
        $totalProducts = Product::whereNull('sku')->count();
        echo "Total products to process: {$totalProducts}\n";

        if ($totalProducts === 0) {
            echo "No products found without SKUs. Exiting.\n";
            return;
        }

        // PostgreSQL optimizations
        ini_set('memory_limit', '1G');
        set_time_limit(0);

        // PostgreSQL-specific optimizations (runtime changeable only)
        try {
            DB::statement('SET work_mem = "256MB"');
            DB::statement('SET maintenance_work_mem = "512MB"');
            DB::statement('SET synchronous_commit = OFF');
            DB::statement('SET random_page_cost = 1.1'); // For SSD optimization
            DB::statement('SET effective_cache_size = "1GB"');
            echo "PostgreSQL optimizations applied successfully.\n";
        } catch (Exception $e) {
            echo "Warning: Some PostgreSQL optimizations could not be applied: " . $e->getMessage() . "\n";
            echo "Continuing with default settings...\n";
        }

        // Disable query log to save memory
        DB::disableQueryLog();

        try {
            $this->processProducts();
        } catch (Exception $e) {
            echo "Error occurred: " . $e->getMessage() . "\n";
            echo "Processed {$this->processedCount} products before error.\n";

            // Continue processing from where we left off
            echo "Attempting to continue processing...\n";
            $this->processProducts();
        } finally {
            // Reset PostgreSQL settings (only the ones we can change)
            try {
                DB::statement('RESET synchronous_commit');
                DB::statement('RESET work_mem');
                DB::statement('RESET maintenance_work_mem');
                DB::statement('RESET random_page_cost');
                DB::statement('RESET effective_cache_size');
                echo "PostgreSQL settings reset successfully.\n";
            } catch (Exception $e) {
                echo "Warning: Could not reset some PostgreSQL settings: " . $e->getMessage() . "\n";
            }
        }

        $endTime = microtime(true);
        $executionTime = round($endTime - $startTime, 2);

        echo "\n=== SKU Generation Completed ===\n";
        echo "Total processed: {$this->processedCount} products\n";
        echo "Execution time: {$executionTime} seconds\n";
        echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    }

    /**
     * Process products in chunks with PostgreSQL optimizations
     */
    private function processProducts(): void
    {
        $lastProcessedId = 0;
        $hasMoreRecords = true;

        while ($hasMoreRecords) {
            try {
                // Use cursor-style pagination for PostgreSQL efficiency
                $products = Product::with(['category', 'brand'])
                    ->whereNull('sku')
                    ->where('id', '>', $lastProcessedId)
                    ->orderBy('id')
                    ->limit($this->chunkSize)
                    ->get();

                if ($products->isEmpty()) {
                    $hasMoreRecords = false;
                    break;
                }

                $this->processBatchUpdate($products);

                // Update last processed ID for cursor pagination
                $lastProcessedId = $products->last()->id;

                // Force garbage collection periodically
                if ($this->processedCount % 2000 === 0) {
                    gc_collect_cycles();
                }
            } catch (Exception $e) {
                echo "Batch processing error: " . $e->getMessage() . "\n";
                echo "Waiting 3 seconds before retry...\n";
                sleep(3);
                continue;
            }
        }
    }

    /**
     * Process products using PostgreSQL batch operations
     */
    private function processBatchUpdate($products): void
    {
        $updateCases = [];
        $productIds = [];
        $skuCheck = [];

        // Prepare batch data
        foreach ($products as $product) {
            try {
                $sku = $this->generateSKU($product);

                // Store for uniqueness check
                $skuCheck[$sku] = isset($skuCheck[$sku]) ? $skuCheck[$sku] + 1 : 1;

                // Handle duplicates within the batch
                if ($skuCheck[$sku] > 1) {
                    $sku = $sku . '-' . str_pad($skuCheck[$sku] - 1, 2, '0', STR_PAD_LEFT);
                }

                $updateCases[] = "WHEN {$product->id} THEN '{$sku}'";
                $productIds[] = $product->id;
            } catch (Exception $e) {
                echo "Error preparing Product ID {$product->id}: " . $e->getMessage() . "\n";
                continue;
            }
        }

        if (empty($updateCases)) {
            return;
        }

        try {
            // Use PostgreSQL CASE statement for batch update
            DB::transaction(function () use ($updateCases, $productIds) {
                $productIdsList = implode(',', $productIds);
                $caseStatements = implode(' ', $updateCases);

                $sql = "
                    UPDATE products SET 
                        sku = CASE id {$caseStatements} END,
                        updated_at = NOW()
                    WHERE id IN ({$productIdsList}) 
                    AND sku IS NULL
                ";

                $affectedRows = DB::update($sql);
                $this->processedCount += $affectedRows;

                // Log progress every 10k records
                if ($this->processedCount % $this->logInterval === 0) {
                    $this->logProgress();
                }

                // Show progress updates
                if ($this->processedCount <= 500 || $this->processedCount % 2000 === 0) {
                    echo "Batch updated {$affectedRows} products (Total: {$this->processedCount})\n";
                }
            }, 3); // 3 retry attempts for deadlocks

        } catch (Exception $e) {
            echo "Batch update failed: " . $e->getMessage() . "\n";
            // Fallback to individual updates
            $this->fallbackIndividualUpdate($products);
        }
    }

    /**
     * Fallback to individual updates if batch fails
     */
    private function fallbackIndividualUpdate($products): void
    {
        echo "Using fallback individual update method...\n";

        foreach ($products as $product) {
            try {
                DB::transaction(function () use ($product) {
                    $freshProduct = Product::lockForUpdate()->find($product->id);

                    if ($freshProduct && is_null($freshProduct->sku)) {
                        $sku = $this->generateSKU($product);

                        // Check for SKU uniqueness with PostgreSQL-specific query
                        $counter = 1;
                        $originalSku = $sku;

                        while (DB::table('products')->where('sku', $sku)->exists()) {
                            $sku = $originalSku . '-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                            $counter++;

                            // Prevent infinite loop
                            if ($counter > 999) {
                                $sku = $originalSku . '-' . uniqid();
                                break;
                            }
                        }

                        $freshProduct->sku = $sku;
                        $freshProduct->save();

                        $this->processedCount++;

                        if ($this->processedCount % 1000 === 0) {
                            echo "Individual update - Generated SKU: {$sku} for Product ID: {$product->id} (Total: {$this->processedCount})\n";
                        }
                    }
                });
            } catch (Exception $e) {
                echo "Error in individual update for Product ID {$product->id}: " . $e->getMessage() . "\n";
                continue;
            }
        }
    }

    /**
     * Log progress milestone with PostgreSQL-specific metrics
     */
    private function logProgress(): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $memoryUsage = round(memory_get_usage(true) / 1024 / 1024, 2);
        $peakMemory = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        echo "\n=== PROGRESS MILESTONE (PostgreSQL) ===\n";
        echo "Timestamp: {$timestamp}\n";
        echo "Processed: {$this->processedCount} products\n";
        echo "Memory Usage: {$memoryUsage} MB\n";
        echo "Peak Memory: {$peakMemory} MB\n";

        // Check remaining products
        $remaining = Product::whereNull('sku')->count();
        echo "Remaining: {$remaining} products\n";

        // PostgreSQL-specific stats
        try {
            $connections = DB::select("SELECT count(*) as active_connections FROM pg_stat_activity WHERE state = 'active'");
            echo "Active DB Connections: " . $connections[0]->active_connections . "\n";

            $dbSize = DB::select("SELECT pg_size_pretty(pg_database_size(current_database())) as size");
            echo "Database Size: " . $dbSize[0]->size . "\n";
        } catch (Exception $e) {
            // Ignore if we can't get DB stats
        }

        echo "=====================================\n\n";
    }

    /**
     * Generate a SKU for the product with PostgreSQL considerations
     */
    private function generateSKU(Product $product): string
    {
        try {
            $cat = 'GEN';
            $brand = 'NON';
            $size = 'M';

            // Safely get category
            if ($product->category && $product->category->slug) {
                $cat = strtoupper(substr(trim($product->category->slug), 0, 3));
                // Remove special characters for PostgreSQL compatibility
                $cat = preg_replace('/[^A-Z0-9]/', '', $cat);
                $cat = $cat ?: 'GEN';
            }

            // Safely get brand
            if ($product->brand && $product->brand->slug) {
                $brand = strtoupper(substr(trim($product->brand->slug), 0, 3));
                $brand = preg_replace('/[^A-Z0-9]/', '', $brand);
                $brand = $brand ?: 'NON';
            }

            // Safely get size
            if ($product->size) {
                $size = strtoupper(substr(trim($product->size), 0, 3));
                $size = preg_replace('/[^A-Z0-9]/', '', $size);
                $size = $size ?: 'M';
            }

            $id = str_pad($product->id, 6, '0', STR_PAD_LEFT); // Longer ID for PostgreSQL bigint

            return "{$cat}-{$brand}-{$size}-{$id}";
        } catch (Exception $e) {
            // Fallback SKU generation
            $id = str_pad($product->id, 6, '0', STR_PAD_LEFT);
            return "GEN-NON-M-{$id}";
        }
    }
}
