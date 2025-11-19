<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RepairProductDataSeeder extends Seeder
{
    /**
     * Repair incomplete product data by adding missing images and variant assignments
     */

    protected int $batchSize = 100;  // Process 100 products at a time
    protected int $insertChunkSize = 200;  // Insert max 200 rows (200 × 7 = 1400 params, well under 65k)
    protected int $progressLogFrequency = 10;
    protected array $productImagePool = [];
    protected array $variantImagePool = [];
    protected array $variantTypes = [];
    protected array $variantTypeOptions = [];
    protected array $optionLookup = [];

    public function run(): void
    {
        $this->ensurePostgres();
        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║              PRODUCT DATA REPAIR SEEDER                            ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');
        $this->writeOutput('');

        $this->loadImagePools();
        $this->loadVariantMetadata();

        // Check what needs repair
        $productsWithoutImages = DB::table('products')
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('product_images')
                      ->whereColumn('product_images.product_id', 'products.id');
            })
            ->count();

        $variantsWithoutAssignments = DB::table('product_variants')
            ->whereNotExists(function($query) {
                $query->select(DB::raw(1))
                      ->from('product_variant_option_assignments')
                      ->whereColumn('product_variant_option_assignments.product_variant_id', 'product_variants.id');
            })
            ->count();

        $this->writeOutput('📊 Issues Found:');
        $this->writeOutput('  Products without images:      ' . number_format($productsWithoutImages));
        $this->writeOutput('  Variants without assignments: ' . number_format($variantsWithoutAssignments));
        $this->writeOutput('');

        if ($productsWithoutImages === 0 && $variantsWithoutAssignments === 0) {
            $this->writeOutput('✅ No repairs needed - all data is intact!');
            return;
        }

        $this->writeOutput('🔧 Starting repair process...');
        $this->writeOutput('');

        // Repair products without images
        if ($productsWithoutImages > 0) {
            $this->repairProductImages($productsWithoutImages);
        }

        // Repair variants without assignments
        if ($variantsWithoutAssignments > 0) {
            $this->repairVariantAssignments($variantsWithoutAssignments);
        }

        $this->writeOutput('');
        $this->writeOutput('✅ Repair completed successfully!');
        $this->writeOutput('');
    }

    protected function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('RepairProductDataSeeder requires a PostgreSQL connection.');
        }
    }

    protected function loadImagePools(): void
    {
        $productsListFile = storage_path('app/public/products_list.php');
        $variantsListFile = storage_path('app/public/variants_list.php');

        if (file_exists($productsListFile)) {
            $this->productImagePool = require $productsListFile;
        }

        if (file_exists($variantsListFile)) {
            $this->variantImagePool = require $variantsListFile;
        }

        if (empty($this->productImagePool)) {
            $this->productImagePool = ['default-product.webp'];
        }
        if (empty($this->variantImagePool)) {
            $this->variantImagePool = ['default-variant.webp'];
        }
    }

    protected function loadVariantMetadata(): void
    {
        $typeRows = DB::table('product_variant_types')
            ->select('id', 'name', 'display_name', 'sort_order', 'status')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($typeRows as $typeRow) {
            $options = DB::table('product_variant_options')
                ->select('id', 'variant_type_id', 'value', 'display_value', 'sort_order', 'status')
                ->where('variant_type_id', $typeRow->id)
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            $typeId = (int) $typeRow->id;
            $this->variantTypes[$typeId] = [
                'id' => $typeId,
                'name' => $typeRow->name,
                'display_name' => $typeRow->display_name,
                'sort_order' => $typeRow->sort_order,
            ];

            $this->variantTypeOptions[$typeId] = [];

            foreach ($options as $option) {
                $optionId = (int) $option->id;
                $optionData = [
                    'id' => $optionId,
                    'type_id' => $typeId,
                    'type_name' => $typeRow->name,
                    'type_display_name' => $typeRow->display_name,
                    'value' => $option->value,
                    'display_value' => $option->display_value,
                    'sort_order' => $option->sort_order,
                ];

                $this->variantTypeOptions[$typeId][] = $optionData;
                $this->optionLookup[$optionId] = $optionData;
            }
        }
    }

    protected function repairProductImages(int $totalToRepair): void
    {
        $this->writeOutput('🖼️  Repairing Product Images');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');

        $repaired = 0;
        $batchIndex = 0;
        $startTime = microtime(true);

        while ($repaired < $totalToRepair) {
            $products = DB::table('products')
                ->select('id')
                ->whereNotExists(function($query) {
                    $query->select(DB::raw(1))
                          ->from('product_images')
                          ->whereColumn('product_images.product_id', 'products.id');
                })
                ->limit($this->batchSize)
                ->get();

            if ($products->isEmpty()) {
                break;
            }

            $imageRows = [];
            $nextImageId = $this->getNextId('product_images');
            $timestamp = Carbon::now()->toDateTimeString();

            foreach ($products as $product) {
                $productId = (int) $product->id;
                $randomImages = $this->getRandomImages($this->productImagePool, 3);

                foreach ($randomImages as $i => $filename) {
                    $imageRows[] = [
                        'id' => $nextImageId++,
                        'product_id' => $productId,
                        'image_path' => $filename,
                        'is_primary' => $i === 0,
                        'sort_order' => $i + 1,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            // Insert in chunks to avoid PostgreSQL 65k parameter limit
            if (!empty($imageRows)) {
                foreach (array_chunk($imageRows, $this->insertChunkSize) as $chunk) {
                    DB::table('product_images')->insert($chunk);
                }
            }

            $repaired += count($products);
            $batchIndex++;

            if ($batchIndex % $this->progressLogFrequency === 0) {
                $elapsed = microtime(true) - $startTime;
                $rate = $elapsed > 0 ? $repaired / $elapsed : 0;
                $remaining = $totalToRepair - $repaired;
                $eta = $rate > 0 ? $remaining / $rate : 0;

                $this->writeOutput(sprintf(
                    '  Progress: %6.2f%% | Repaired: %s | Remaining: %s | Rate: %s/s | ETA: %s',
                    ($repaired / $totalToRepair) * 100,
                    str_pad(number_format($repaired), 10, ' ', STR_PAD_LEFT),
                    str_pad(number_format($remaining), 10, ' ', STR_PAD_LEFT),
                    str_pad(number_format($rate, 0), 6, ' ', STR_PAD_LEFT),
                    $this->formatInterval($eta)
                ));
            }
        }

        $elapsed = microtime(true) - $startTime;
        $this->writeOutput('  ✅ Repaired ' . number_format($repaired) . ' products in ' . $this->formatInterval($elapsed));
        $this->writeOutput('');
    }

    protected function repairVariantAssignments(int $totalToRepair): void
    {
        $this->writeOutput('🔗 Repairing Variant Assignments');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');

        $repaired = 0;
        $batchIndex = 0;
        $startTime = microtime(true);

        while ($repaired < $totalToRepair) {
            $variants = DB::table('product_variants')
                ->select('id', 'product_id', 'sku')
                ->whereNotExists(function($query) {
                    $query->select(DB::raw(1))
                          ->from('product_variant_option_assignments')
                          ->whereColumn('product_variant_option_assignments.product_variant_id', 'product_variants.id');
                })
                ->limit($this->batchSize)
                ->get();

            if ($variants->isEmpty()) {
                break;
            }

            $assignmentRows = [];
            $nextAssignmentId = $this->getNextId('product_variant_option_assignments');
            $timestamp = Carbon::now()->toDateTimeString();

            foreach ($variants as $variant) {
                $variantId = (int) $variant->id;

                // Infer options from SKU (format: COLOR-STORAGE-RAM-SIZE-timestamp-seq or variations)
                $skuParts = explode('-', $variant->sku);

                // Assign 2-3 random options from available types
                $typeIds = array_keys($this->variantTypeOptions);
                $selectedTypes = array_slice($typeIds, 0, min(3, count($typeIds)));

                foreach ($selectedTypes as $typeId) {
                    if (empty($this->variantTypeOptions[$typeId])) {
                        continue;
                    }

                    // Pick a random option from this type
                    $option = $this->variantTypeOptions[$typeId][array_rand($this->variantTypeOptions[$typeId])];

                    $assignmentRows[] = [
                        'id' => $nextAssignmentId++,
                        'product_variant_id' => $variantId,
                        'product_variant_option_id' => $option['id'],
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
            }

            // Insert in chunks to avoid PostgreSQL 65k parameter limit
            if (!empty($assignmentRows)) {
                foreach (array_chunk($assignmentRows, $this->insertChunkSize) as $chunk) {
                    DB::table('product_variant_option_assignments')->insert($chunk);
                }
            }

            $repaired += count($variants);
            $batchIndex++;

            if ($batchIndex % $this->progressLogFrequency === 0) {
                $elapsed = microtime(true) - $startTime;
                $rate = $elapsed > 0 ? $repaired / $elapsed : 0;
                $remaining = $totalToRepair - $repaired;
                $eta = $rate > 0 ? $remaining / $rate : 0;

                $this->writeOutput(sprintf(
                    '  Progress: %6.2f%% | Repaired: %s | Remaining: %s | Rate: %s/s | ETA: %s',
                    ($repaired / $totalToRepair) * 100,
                    str_pad(number_format($repaired), 10, ' ', STR_PAD_LEFT),
                    str_pad(number_format($remaining), 10, ' ', STR_PAD_LEFT),
                    str_pad(number_format($rate, 0), 6, ' ', STR_PAD_LEFT),
                    $this->formatInterval($eta)
                ));
            }
        }

        $elapsed = microtime(true) - $startTime;
        $this->writeOutput('  ✅ Repaired ' . number_format($repaired) . ' variants in ' . $this->formatInterval($elapsed));
        $this->writeOutput('');
    }

    protected function getNextId(string $table): int
    {
        $max = DB::table($table)->max('id');
        return $max ? ((int) $max + 1) : 1;
    }

    protected function getRandomImages(array $pool, int $count): array
    {
        if (empty($pool)) {
            return [];
        }

        $poolSize = count($pool);
        $count = min($count, $poolSize);

        if ($count === 1) {
            return [$pool[array_rand($pool)]];
        }

        if ($count >= $poolSize) {
            $shuffled = $pool;
            shuffle($shuffled);
            return $shuffled;
        }

        $randomKeys = array_rand($pool, $count);
        if (!is_array($randomKeys)) {
            $randomKeys = [$randomKeys];
        }

        return array_map(fn($key) => $pool[$key], $randomKeys);
    }

    protected function formatInterval(float $seconds): string
    {
        if ($seconds <= 0) {
            return '00:00:00';
        }

        $seconds = (int) round($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    protected function writeOutput(string $message): void
    {
        if (isset($this->command) && $this->command) {
            $this->command->line($message);
        } else {
            echo $message . PHP_EOL;
        }
    }
}
