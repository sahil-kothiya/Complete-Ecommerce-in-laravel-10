<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Console\Command;

class FixInvalidProductData extends Command
{
    protected $signature = 'products:fix-invalid-data {--dry-run : Show what would be fixed without making changes}';

    protected $description = 'Fix products with invalid data (missing variants, NULL prices, no images)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->info('Analyzing product data quality...');
        $this->newLine();

        // Issue 1: Products with has_variants=true but no actual variants
        $this->runCheck('Checking products marked as has_variants but missing variants', function () use ($dryRun) {
            $invalidProducts = Product::where('has_variants', true)
                ->whereDoesntHave('variants')
                ->get();

            if ($invalidProducts->isEmpty()) {
                $this->info('  ✅ No issues found');

                return;
            }

            $this->warn("  ⚠️  Found {$invalidProducts->count()} products with has_variants=true but no variants");

            foreach ($invalidProducts as $product) {
                $this->line("  - Product #{$product->id}: {$product->title}");

                if (! $dryRun) {
                    // Fix: Deactivate so it stops surfacing in filters and can be reviewed later
                    $product->update(['status' => 'inactive']);
                    $this->info('    → Fixed: Set status=inactive');
                }
            }
        });

        // Issue 2: Products without variants that have NULL base_price
        $this->runCheck('Checking non-variant products with NULL base_price', function () use ($dryRun) {
            $noPriceProducts = Product::where('has_variants', false)
                ->whereNull('base_price')
                ->get();

            if ($noPriceProducts->isEmpty()) {
                $this->info('  ✅ No issues found');

                return;
            }

            $this->warn("  ⚠️  Found {$noPriceProducts->count()} products with NULL base_price");

            foreach ($noPriceProducts as $product) {
                $this->line("  - Product #{$product->id}: {$product->title}");

                if (! $dryRun) {
                    // Fix: Set base_price to 0 (admin needs to update manually)
                    $product->update([
                        'base_price' => 0,
                        'status' => 'inactive', // Deactivate until price is set
                    ]);
                    $this->info('    → Fixed: Set base_price=0 and status=inactive');
                }
            }
        });

        // Issue 3: Products with no images
        $this->runCheck('Checking products with no images', function () {
            $baseQuery = Product::where('status', 'active')
                ->whereDoesntHave('images');

            $missingCount = (clone $baseQuery)->count();

            if ($missingCount === 0) {
                $this->info('  ✅ No issues found');

                return;
            }

            $this->warn("  ⚠️  Found {$missingCount} active products with no images");

            $sample = (clone $baseQuery)->limit(10)->get();
            foreach ($sample as $product) {
                $this->line("  - Product #{$product->id}: {$product->title}");
            }

            if ($missingCount > $sample->count()) {
                $this->line('  ... and '.($missingCount - $sample->count()).' more');
            }

            $this->comment('  ℹ️  Note: These products will use placeholder images in frontend');
        });

        // Issue 4: Variants with NULL/zero price
        $this->runCheck('Checking variants with invalid prices', function () use ($dryRun) {
            $variantQuery = ProductVariant::where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('price')
                      ->orWhere('price', '<=', 0);
                });

            $variantCount = (clone $variantQuery)->count();

            if ($variantCount === 0) {
                $this->info('  ✅ No issues found');

                return;
            }

            $this->warn("  ⚠️  Found {$variantCount} variants with invalid prices");

            if ($dryRun) {
                $variantQuery->limit(10)->get()->each(function ($variant) {
                    $this->line("  - Variant #{$variant->id} (Product: {$variant->product_id})");
                });

                if ($variantCount > 10) {
                    $this->line('  ... and '.($variantCount - 10).' more');
                }

                return;
            }

            $fixed = 0;
            (clone $variantQuery)->chunkById(1000, function ($variants) use (&$fixed) {
                foreach ($variants as $variant) {
                    $variant->update(['status' => 'inactive']);
                    $fixed++;
                }
            });

            $this->info("  ✅ Fixed {$fixed} variants by setting status=inactive");
        });

        $this->newLine();

        // Summary
        if ($dryRun) {
            $this->warn('🔍 DRY RUN COMPLETE - Run without --dry-run to apply fixes');
        } else {
            $this->info('✅ Data quality check and fixes complete!');
            $this->newLine();
            $this->comment('Recommended next steps:');
            $this->line('  1. Run: php artisan cache:clear');
            $this->line('  2. Run: php -d memory_limit=2G artisan indexes:manage build --force');
            $this->line('  3. Review inactive products and set proper prices');
        }

        return Command::SUCCESS;
    }

    private function runCheck(string $title, callable $callback): void
    {
        $this->info($title);
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->error('  ❌ Check failed: '.$e->getMessage());
        }
        $this->newLine();
    }
}
