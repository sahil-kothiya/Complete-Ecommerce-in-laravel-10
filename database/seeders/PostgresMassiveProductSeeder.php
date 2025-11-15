<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PostgresMassiveProductSeeder extends Seeder
{
    /**
     * ══════════════════════════════════════════════════════════════════════════
     * SEEDER CONFIGURATION - Adjust these values as needed
     * ══════════════════════════════════════════════════════════════════════════
     */

    // Product generation settings
    protected int $totalProducts = 20;                    // Total number of products to generate
    protected ?int $variantProductTarget = 15;            // Target number of products with variants (null = use ratio)
    protected float $variantProductRatio = 0.95;             // Ratio of variant products if target not set (0.95 = 95%)

    // Variant configuration per product
    protected int $minVariantsPerProduct = 3;                // Minimum variants per product
    protected int $maxVariantsPerProduct = 5;                // Maximum variants per product
    protected int $minVariantTypesPerProduct = 2;            // Minimum variant types (e.g., color + size)
    protected int $maxVariantTypesPerProduct = 3;            // Maximum variant types

    // Image configuration
    protected int $productImagesPerProduct = 3;              // Images per product
    protected int $variantImagesPerVariant = 3;              // Images per variant
    protected bool $forceRefreshImageLists = false;          // Force rescan storage folders (true = rescan, false = use cache)

    // Performance settings
    protected int $batchSize = 5;                         // Number of products per batch insert
    protected bool $streamInserts = true;                    // Flush partial batches during processing
    protected int $streamFlushInterval = 500;                // Flush after this many products within batch
    protected int $maxTrackInsertedIds = 100000;             // Don't track IDs beyond this threshold (saves memory)

    // Database operations
    protected bool $truncateBeforeSeeding = false;           // Truncate tables before seeding
    protected bool $truncateProductTables = false;           // If truncating, include products table
    protected bool $truncateRelatedTables = false;           // If truncating, include related tables
    protected bool $syncSequencesAfterSeeding = true;        // Sync PostgreSQL sequences after seeding
    protected bool $useTransactions = true;                  // Use database transactions for inserts

    // Logging settings
    protected int $progressLogFrequency = 1;                 // Log progress every N batches
    protected bool $emitConsoleProgress = true;              // Show progress in console
    protected bool $emitLaravelLog = true;                   // Write to Laravel log

    /**
     * ══════════════════════════════════════════════════════════════════════════
     * QUICK PRESETS - Uncomment one to use predefined configurations
     * ══════════════════════════════════════════════════════════════════════════
     */

    // SMALL TEST (100 products)
    // protected int $totalProducts = 100;
    // protected ?int $variantProductTarget = 95;

    // MEDIUM TEST (1,000 products)
    // protected int $totalProducts = 1_000;
    // protected ?int $variantProductTarget = 950;

    // LARGE DATASET (100,000 products)
    // protected int $totalProducts = 100_000;
    // protected ?int $variantProductTarget = 95_000;

    // MASSIVE DATASET (1,000,000 products)
    // protected int $totalProducts = 1_000_000;
    // protected ?int $variantProductTarget = 950_000;
    // protected int $batchSize = 10_000;
    // protected int $streamFlushInterval = 1000;

    // ULTRA MASSIVE (10,000,000 products)
    // protected int $totalProducts = 10_000_000;
    // protected ?int $variantProductTarget = 9_500_000;
    // protected int $batchSize = 20_000;
    // protected int $streamFlushInterval = 2000;
    // protected int $maxTrackInsertedIds = 0; // Disable ID tracking

    /**
     * ══════════════════════════════════════════════════════════════════════════
     */

    /**
     * Runtime counters.
     */
    protected int $nextProductId = 1;
    protected int $nextVariantId = 1;
    protected int $nextProductImageId = 1;
    protected int $nextVariantImageId = 1;
    protected int $nextAssignmentId = 1;
    protected int $nextTypeSelectionId = 1;

    protected int $productsSeeded = 0;
    protected int $variantProductsSeeded = 0;
    protected int $nonVariantProductsSeeded = 0;

    /**
     * Tracking inserted product IDs and SKU sequences
     */
    protected array $insertedProductIds = [];
    protected array $skuSequenceCounters = [];

    /**
     * When product count exceeds threshold we skip tracking IDs to save memory
     */
    protected function shouldTrackInsertedIds(): bool
    {
        return $this->totalProducts <= $this->maxTrackInsertedIds;
    }

    /**
     * Cached metadata.
     */
    protected array $leafCategories = [];
    protected array $allCategories = [];
    protected array $categoryParentMap = [];
    protected array $topCategoryCache = [];
    protected array $brands = [];
    protected array $variantTypes = [];
    protected array $variantTypeOptions = [];
    protected array $optionLookup = [];
    protected array $variantOptionCursor = [];

    /**
     * Tracking cursors.
     */
    protected int $categoryCursor = 0;
    protected int $brandCursor = 0;

    /**
     * Category-specific variant type mappings
     * Key can be exact slug match or partial match (contains)
     */
    protected array $categoryVariantMapping = [
        // Electronics - RAM, Storage (no color for simplicity)
        'smartphones' => ['color', 'ram', 'storage'],
        'mobiles' => ['color', 'ram', 'storage'],
        'mobile' => ['color', 'ram', 'storage'],
        'phone' => ['color', 'ram', 'storage'],
        'laptops' => ['color', 'ram', 'storage'],
        'laptop' => ['color', 'ram', 'storage'],
        'tablet' => ['color', 'ram', 'storage'],
        'computer' => ['color', 'ram', 'storage'],

        // TV/Displays - screen size
        'tv' => ['size'],
        'television' => ['size'],
        'monitor' => ['size'],
        'display' => ['size'],

        // Audio - color only
        'audio' => ['color'],
        'headphone' => ['color'],
        'speaker' => ['color'],
        'earphone' => ['color'],

        // Clothing/Fashion - color and size
        'shirt' => ['color', 'size'],
        'tshirt' => ['color', 'size'],
        't-shirt' => ['color', 'size'],
        'jeans' => ['color', 'size'],
        'pant' => ['color', 'size'],
        'trouser' => ['color', 'size'],
        'dress' => ['color', 'size'],
        'skirt' => ['color', 'size'],
        'top' => ['color', 'size'],
        'jacket' => ['color', 'size'],
        'coat' => ['color', 'size'],
        'sweater' => ['color', 'size'],
        'hoodie' => ['color', 'size'],
        'shoe' => ['color', 'size'],
        'boot' => ['color', 'size'],
        'sandal' => ['color', 'size'],
        'sneaker' => ['color', 'size'],
        'women' => ['color', 'size'],
        'men' => ['color', 'size'],
        'kids' => ['color', 'size'],
        'boy' => ['color', 'size'],
        'girl' => ['color', 'size'],
        'clothing' => ['color', 'size'],

        // Furniture - color only
        'furniture' => ['color'],
        'chair' => ['color'],
        'table' => ['color'],
        'sofa' => ['color'],
        'bed' => ['color'],
    ];

    /**
     * Static pools - Using placeholder/default image that should exist
     */
    protected array $conditions = ['default', 'new', 'hot'];

    // These will be populated dynamically from storage folder
    protected array $productImagePool = [];
    protected array $variantImagePool = [];

    public function run(): void
    {
        $this->ensurePostgres();
        $this->initialize();
        $this->seedProducts();

        if ($this->syncSequencesAfterSeeding) {
            $this->syncSequences();
        }
    }

    protected function ensurePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('PostgresMassiveProductSeeder requires a PostgreSQL connection.');
        }
    }

    protected function initialize(): void
    {
        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║          POSTGRESQL MASSIVE PRODUCT SEEDER v2.0                    ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');

        $dbName = DB::connection()->getDatabaseName();
        $this->writeOutput('  Database: ' . $dbName);

        // Increase memory limit for large scale seeding if possible (safe fallback)
        $currentLimit = ini_get('memory_limit');
        if ($currentLimit !== '-1') {
            @ini_set('memory_limit', '512M');
            $this->writeOutput('  Memory Limit: ' . $currentLimit . ' → 512M');
        } else {
            $this->writeOutput('  Memory Limit: Unlimited');
        }

        DB::disableQueryLog();
        $this->writeOutput('  Query Logging: Disabled (performance optimization)');

        $this->writeOutput('');
        $this->writeOutput('  Seeding Configuration:');
        $this->writeOutput('    • Total Products:    ' . number_format($this->totalProducts));
        $this->writeOutput('    • Variant Target:    ' . number_format($this->variantProductTarget ?? (int)($this->totalProducts * $this->variantProductRatio)));
        $this->writeOutput('    • Batch Size:        ' . number_format($this->batchSize));
        $this->writeOutput('    • Stream Inserts:    ' . ($this->streamInserts ? 'Enabled' : 'Disabled'));
        $this->writeOutput('    • Use Transactions:  ' . ($this->useTransactions ? 'Enabled' : 'Disabled'));
        $this->writeOutput('    • Refresh Images:    ' . ($this->forceRefreshImageLists ? 'Yes (rescan)' : 'No (use cache)'));
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');

        if ($this->truncateBeforeSeeding) {
            $this->truncateTables();
        }

        $this->loadImagePools();
        $this->loadLookups();
        $this->initializeSequenceCounters();

        if ($this->emitLaravelLog) {
            Log::info('PostgresMassiveProductSeeder initialized', [
                'total_products' => $this->totalProducts,
                'variant_target' => $this->variantProductTarget,
                'batch_size' => $this->batchSize,
            ]);
        }
    }



    protected function truncateTables(): void
    {
        $this->writeOutput('Truncation settings: Products=' . ($this->truncateProductTables ? 'YES' : 'NO') . ', Related=' . ($this->truncateRelatedTables ? 'YES' : 'NO'));

        if ($this->truncateProductTables) {
            $this->writeOutput('Truncating product tables...');
            DB::statement('TRUNCATE TABLE products RESTART IDENTITY CASCADE');
        }

        if ($this->truncateRelatedTables) {
            $this->writeOutput('Truncating related tables...');
            DB::statement('TRUNCATE TABLE variant_images, product_variant_option_assignments, product_variant_type_selections, product_variants, product_images RESTART IDENTITY CASCADE');
        }
    }

    protected function loadImagePools(): void
    {
        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║               IMAGE POOL INITIALIZATION                            ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');

        $productsDir = storage_path('app/public/products');
        $variantsDir = storage_path('app/public/products/variants');

        $productsListFile = storage_path('app/public/products_list.php');
        $variantsListFile = storage_path('app/public/variants_list.php');
        $productsTextFile = storage_path('app/public/products_list.txt');
        $variantsTextFile = storage_path('app/public/variants_list.txt');

        $this->writeOutput('');
        $this->writeOutput('📂 Storage Directories:');
        $this->writeOutput('   Products:    ' . $productsDir);
        $this->writeOutput('   Variants:    ' . $variantsDir);
        $this->writeOutput('');
        $this->writeOutput('📄 List Files Configuration:');
        $this->writeOutput('   Products PHP: ' . basename($productsListFile));
        $this->writeOutput('   Products TXT: ' . basename($productsTextFile));
        $this->writeOutput('   Variants PHP: ' . basename($variantsListFile));
        $this->writeOutput('   Variants TXT: ' . basename($variantsTextFile));

        $refresh = $this->forceRefreshImageLists;

        if ($refresh) {
            $this->writeOutput('');
            $this->writeOutput('🔄 Force refresh mode enabled - rescanning storage directories...');
        }

        // Load or build products list (top-level files only; ignore subfolders like 'variants')
        $productList = [];
        $productSource = '';

        $this->writeOutput('');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');
        $this->writeOutput('📦 PRODUCT IMAGES');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');

        if (!$refresh && file_exists($productsListFile)) {
            $productList = $this->loadImageListFromPhpFile($productsListFile);
            if (!empty($productList)) {
                $productSource = 'cached';
                $this->writeOutput('✓ Loaded from cache');
                $this->writeOutput('  File:   ' . basename($productsListFile));
                $this->writeOutput('  Count:  ' . number_format(count($productList)) . ' images');
                $this->writeOutput('  Source: PHP array (cached)');
            }
        }

        if (empty($productList)) {
            $this->writeOutput('⚙ Scanning storage directory...');
            $this->writeOutput('  Path: ' . $productsDir);
            $topFiles = $this->scanTopLevelImages($productsDir);
            $this->writeOutput('  Found: ' . number_format(count($topFiles)) . ' images (top-level only)');
            $this->writeOutput('  Note:  Subdirectories (e.g., variants) are ignored');
            $this->writeOutput('  Mode:  Filename-only storage (50% reduction vs full paths)');

            $productList = $topFiles; // Store only filenames, not paths
            $productSource = 'fresh scan';

            if (!empty($productList)) {
                $this->writeImageListPhpFile($productsListFile, $productList);
                $this->writeImageListTextFile($productsTextFile, $productList);
                $this->writeOutput('');
                $this->writeOutput('  ✓ Generated cache files:');
                $this->writeOutput('    • ' . basename($productsListFile) . ' (PHP array)');
                $this->writeOutput('    • ' . basename($productsTextFile) . ' (text list)');

                // Show first few images as sample
                $sample = array_slice($productList, 0, 3);
                if (!empty($sample)) {
                    $this->writeOutput('');
                    $this->writeOutput('  Sample images:');
                    foreach ($sample as $img) {
                        $this->writeOutput('    • ' . basename($img));
                    }
                }
            } else {
                $this->writeOutput('');
                $this->writeOutput('  ⚠ WARNING: No images found in directory');
            }
        }

        // Load or build variants list (files directly under products/variants)
        $variantList = [];
        $variantSource = '';

        $this->writeOutput('');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');
        $this->writeOutput('🎨 VARIANT IMAGES');
        $this->writeOutput('─────────────────────────────────────────────────────────────────────');

        if (!$refresh && file_exists($variantsListFile)) {
            $variantList = $this->loadImageListFromPhpFile($variantsListFile);
            if (!empty($variantList)) {
                $variantSource = 'cached';
                $this->writeOutput('✓ Loaded from cache');
                $this->writeOutput('  File:   ' . basename($variantsListFile));
                $this->writeOutput('  Count:  ' . number_format(count($variantList)) . ' images');
                $this->writeOutput('  Source: PHP array (cached)');
            }
        }

        if (empty($variantList)) {
            $this->writeOutput('⚙ Scanning storage directory...');
            $this->writeOutput('  Path: ' . $variantsDir);
            $variantFiles = $this->scanTopLevelImages($variantsDir);
            $this->writeOutput('  Found: ' . number_format(count($variantFiles)) . ' images');
            $this->writeOutput('  Mode:  Filename-only storage (50% reduction vs full paths)');

            $variantList = $variantFiles; // Store only filenames, not paths
            $variantSource = 'fresh scan';

            if (!empty($variantList)) {
                $this->writeImageListPhpFile($variantsListFile, $variantList);
                $this->writeImageListTextFile($variantsTextFile, $variantList);
                $this->writeOutput('');
                $this->writeOutput('  ✓ Generated cache files:');
                $this->writeOutput('    • ' . basename($variantsListFile) . ' (PHP array)');
                $this->writeOutput('    • ' . basename($variantsTextFile) . ' (text list)');

                // Show first few images as sample
                $sample = array_slice($variantList, 0, 3);
                if (!empty($sample)) {
                    $this->writeOutput('');
                    $this->writeOutput('  Sample images:');
                    foreach ($sample as $img) {
                        $this->writeOutput('    • ' . basename($img));
                    }
                }
            } else {
                $this->writeOutput('');
                $this->writeOutput('  ⚠ WARNING: No images found in directory');
            }
        }

        // Apply lists or fallback
        if (!empty($productList)) {
            $this->productImagePool = $productList;
        }
        if (!empty($variantList)) {
            $this->variantImagePool = $variantList;
        }

        // Fallback to placeholder if still empty
        if (empty($this->productImagePool)) {
            $this->productImagePool = ['default-product.webp'];
            $this->writeOutput('');
            $this->writeOutput('⚠ CRITICAL WARNING: No product images found!');
            $this->writeOutput('  Using placeholder: default-product.webp');
            $this->writeOutput('  Please add images to: ' . $productsDir);
        }
        if (empty($this->variantImagePool)) {
            $this->variantImagePool = ['default-variant.webp'];
            $this->writeOutput('');
            $this->writeOutput('⚠ CRITICAL WARNING: No variant images found!');
            $this->writeOutput('  Using placeholder: default-variant.webp');
            $this->writeOutput('  Please add images to: ' . $variantsDir);
        }

        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║                    IMAGE POOL SUMMARY                              ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');
        $this->writeOutput('  Product Images: ' . str_pad(number_format(count($this->productImagePool)), 10, ' ', STR_PAD_LEFT) . ' (' . $productSource . ')');
        $this->writeOutput('  Variant Images: ' . str_pad(number_format(count($this->variantImagePool)), 10, ' ', STR_PAD_LEFT) . ' (' . $variantSource . ')');
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');
        $this->writeOutput('');
    }

    protected function loadLookups(): void
    {
        $categoryRows = DB::table('categories')
            ->select('id', 'parent_id', 'slug', 'has_children')
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row);

        if ($categoryRows->isEmpty()) {
            throw new RuntimeException('No active categories found. Seed categories before running this seeder.');
        }

        foreach ($categoryRows as $row) {
            $category = [
                'id' => (int) $row['id'],
                'parent_id' => !empty($row['parent_id']) ? (int) $row['parent_id'] : null,
                'slug' => !empty($row['slug']) ? $row['slug'] : 'product',
                'has_children' => !empty($row['has_children']),
            ];

            $this->allCategories[] = $category;
            $this->categoryParentMap[$category['id']] = $category['parent_id'];

            if (!$category['has_children']) {
                $this->leafCategories[] = $category;
            }
        }

        if (empty($this->leafCategories)) {
            $this->leafCategories = $this->allCategories;
        }

        $brandRows = DB::table('brands')
            ->select('id')
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        $this->brands = $brandRows->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($this->brands)) {
            $this->brands = [null];
        }

        $typeRows = DB::table('product_variant_types')
            ->select('id', 'name', 'display_name', 'sort_order', 'status')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row);

        foreach ($typeRows as $typeRow) {
            $options = DB::table('product_variant_options')
                ->select('id', 'variant_type_id', 'value', 'display_value', 'sort_order', 'status')
                ->where('variant_type_id', $typeRow['id'])
                ->where('status', 'active')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn ($row) => (array) $row);

            if ($options->isEmpty()) {
                continue;
            }

            $typeId = (int) $typeRow['id'];
            $this->variantTypes[$typeId] = [
                'id' => $typeId,
                'name' => $typeRow['name'],
                'display_name' => $typeRow['display_name'],
                'sort_order' => (int) ($typeRow['sort_order'] ?? 0),
            ];

            $this->variantTypeOptions[$typeId] = [];
            $this->variantOptionCursor[$typeId] = 0;

            foreach ($options as $option) {
                $optionId = (int) $option['id'];
                $prepared = [
                    'id' => $optionId,
                    'variant_type_id' => $typeId,
                    'display_value' => $option['display_value'] ?? $option['value'],
                    'value' => $option['value'],
                    'sku_fragment' => $this->buildSkuFragment($option['display_value'] ?? $option['value'] ?? ('OPT' . $optionId)),
                ];
                $this->variantTypeOptions[$typeId][] = $prepared;
                $this->optionLookup[$optionId] = array_merge($prepared, [
                    'type_display_name' => $typeRow['display_name'],
                    'type_name' => $typeRow['name'],
                    'type_sort_order' => (int) ($typeRow['sort_order'] ?? 0),
                ]);
            }
        }

        if (empty($this->variantTypes)) {
            throw new RuntimeException('No active variant types with options found. Seed variant metadata before running this seeder.');
        }
    }

    protected function initializeSequenceCounters(): void
    {
        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║                 SEQUENCE INITIALIZATION                            ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');

        $lastProductId = DB::table('products')->max('id');
        $this->nextProductId = $lastProductId ? ((int) $lastProductId + 1) : 1;

        $this->nextVariantId = $this->nextIdFor('product_variants');
        $this->nextProductImageId = $this->nextIdFor('product_images');
        $this->nextVariantImageId = $this->nextIdFor('variant_images');
        $this->nextAssignmentId = $this->nextIdFor('product_variant_option_assignments');
        $this->nextTypeSelectionId = $this->nextIdFor('product_variant_type_selections');

        $this->writeOutput('  Starting Sequence IDs:');
        $this->writeOutput('    • Products:             ' . str_pad(number_format($this->nextProductId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('    • Variants:             ' . str_pad(number_format($this->nextVariantId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('    • Product Images:       ' . str_pad(number_format($this->nextProductImageId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('    • Variant Images:       ' . str_pad(number_format($this->nextVariantImageId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('    • Variant Assignments:  ' . str_pad(number_format($this->nextAssignmentId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('    • Type Selections:      ' . str_pad(number_format($this->nextTypeSelectionId), 12, ' ', STR_PAD_LEFT));
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');
        $this->writeOutput('');
    }

    protected function nextIdFor(string $table): int
    {
        $max = DB::table($table)->max('id');
        return $max ? ((int) $max + 1) : 1;
    }

    protected function seedProducts(): void
    {
        if ($this->totalProducts <= 0) {
            $this->writeOutput('No products requested. Exiting seeder.');
            return;
        }

        $variantTarget = $this->variantProductTarget;
        if ($variantTarget === null) {
            $variantTarget = (int) round($this->totalProducts * $this->variantProductRatio);
        }
        $variantTarget = max(0, min($this->totalProducts, $variantTarget));
        $nonVariantTarget = $this->totalProducts - $variantTarget;

        $startTime = microtime(true);
        $remaining = $this->totalProducts;
        $batchIndex = 0;

        while ($remaining > 0) {
            $currentBatchSize = (int) min($this->batchSize, $remaining);
            $timestamp = Carbon::now()->toDateTimeString();

            // Working buffers (will be flushed early if streaming is enabled)
            $productRows = [];
            $productImageRows = [];
            $variantRows = [];
            $variantImageRows = [];
            $assignmentRows = [];
            $typeSelectionRows = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $productId = $this->nextProductId++;
                $isVariantProduct = $this->variantProductsSeeded < $variantTarget;

                if ($isVariantProduct) {
                    $this->variantProductsSeeded++;
                } else {
                    if ($this->nonVariantProductsSeeded >= $nonVariantTarget) {
                        $isVariantProduct = true;
                        $this->variantProductsSeeded++;
                    } else {
                        $this->nonVariantProductsSeeded++;
                    }
                }

                [$catId, $childCatId, $categorySlug] = $this->resolveCategory();
                $brandId = $this->resolveBrand();

                $title = "Generated Product {$productId}";
                $slug = $this->buildProductSlug($categorySlug, $productId);

                $productRows[] = [
                    'id' => $productId,
                    'title' => $title,
                    'slug' => $slug,
                    'summary' => "Autogenerated summary for product {$productId}.",
                    'description' => "Autogenerated description for product {$productId} used for large scale performance testing.",
                    'condition' => $this->conditions[$productId % count($this->conditions)],
                    'status' => $this->determineStatus($productId),
                    'is_featured' => ($productId % 150 === 0),
                    'has_variants' => $isVariantProduct,
                    'base_price' => $isVariantProduct ? null : $this->generateBasePrice(),
                    'base_discount' => $isVariantProduct ? null : $this->generateBaseDiscount(),
                    'base_stock' => $isVariantProduct ? null : $this->generateBaseStock(),
                    'base_sku' => $isVariantProduct ? null : $this->generateBaseSku($productId),
                    'cat_id' => $catId,
                    'child_cat_id' => $childCatId,
                    'brand_id' => $brandId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];

                $this->productsSeeded++;
                if ($this->shouldTrackInsertedIds()) {
                    $this->insertedProductIds[] = $productId;
                }

                foreach ($this->buildProductImages($productId, $timestamp) as $imageRow) {
                    $productImageRows[] = $imageRow;
                }

                if ($isVariantProduct) {
                    $variantPayload = $this->buildVariantsForProduct($productId, $timestamp, $categorySlug);

                    foreach ($variantPayload['variants'] as $row) {
                        $variantRows[] = $row;
                    }
                    foreach ($variantPayload['images'] as $row) {
                        $variantImageRows[] = $row;
                    }
                    foreach ($variantPayload['assignments'] as $row) {
                        $assignmentRows[] = $row;
                    }
                    foreach ($variantPayload['typeSelections'] as $row) {
                        $typeSelectionRows[] = $row;
                    }
                }

                // Stream flush if enabled and interval reached
                if ($this->streamInserts && ($this->productsSeeded % $this->streamFlushInterval === 0)) {
                    $this->persistBatch($productRows, $productImageRows, $variantRows, $variantImageRows, $assignmentRows, $typeSelectionRows);
                    // Reset buffers to free memory
                    $productRows = [];
                    $productImageRows = [];
                    $variantRows = [];
                    $variantImageRows = [];
                    $assignmentRows = [];
                    $typeSelectionRows = [];
                    // Opportunistic GC
                    gc_collect_cycles();
                }
            }
            // Final flush for this outer batch if any rows remain
            if (!empty($productRows) || !empty($productImageRows) || !empty($variantRows) || !empty($variantImageRows) || !empty($assignmentRows) || !empty($typeSelectionRows)) {
                $this->persistBatch($productRows, $productImageRows, $variantRows, $variantImageRows, $assignmentRows, $typeSelectionRows);
            }

            $remaining -= $currentBatchSize;
            $batchIndex++;

            if ($this->emitConsoleProgress && ($batchIndex % $this->progressLogFrequency === 0 || $remaining === 0)) {
                $this->reportProgress($batchIndex, $remaining, $startTime);
            }

            unset($productRows, $productImageRows, $variantRows, $variantImageRows, $assignmentRows, $typeSelectionRows);
            gc_collect_cycles();
        }

        $totalElapsed = microtime(true) - $startTime;

        if ($this->emitLaravelLog) {
            Log::info('PostgresMassiveProductSeeder completed', [
                'products_seeded' => $this->productsSeeded,
                'variant_products_seeded' => $this->variantProductsSeeded,
                'non_variant_products_seeded' => $this->nonVariantProductsSeeded,
                'elapsed_time' => round($totalElapsed, 2),
            ]);
        }

        // Log inserted product IDs only if tracking enabled
        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput('║                     SEEDING SUMMARY                                ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');
        $this->writeOutput('  Total Products:        ' . str_pad(number_format($this->productsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  → Variant Products:    ' . str_pad(number_format($this->variantProductsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  → Non-Variant:         ' . str_pad(number_format($this->nonVariantProductsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('');
        $this->writeOutput('  Images per Product:    ' . str_pad(number_format($this->productImagesPerProduct), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  Images per Variant:    ' . str_pad(number_format($this->variantImagesPerVariant), 15, ' ', STR_PAD_LEFT));

        if ($this->shouldTrackInsertedIds() && !empty($this->insertedProductIds)) {
            $this->writeOutput('');
            $this->writeOutput('  Product ID Range:      ' . min($this->insertedProductIds) . ' → ' . max($this->insertedProductIds));
            $this->writeOutput('');
            $this->writeOutput('  First 10 IDs: ' . implode(', ', array_slice($this->insertedProductIds, 0, 10)));
            if (count($this->insertedProductIds) > 10) {
                $this->writeOutput('  Last 10 IDs:  ' . implode(', ', array_slice($this->insertedProductIds, -10)));
            }
        } else {
            $this->writeOutput('');
            $this->writeOutput('  ℹ ID tracking disabled (threshold: ' . number_format($this->maxTrackInsertedIds) . ')');
        }

        $this->writeOutput('');
        $this->writeOutput('  ⏱ Total Time: ' . $this->formatInterval($totalElapsed));
        $this->writeOutput('  ⚡ Average Rate: ' . number_format($totalElapsed > 0 ? $this->productsSeeded / $totalElapsed : 0, 0) . ' products/second');
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');
        $this->writeOutput('');
        $this->writeOutput('✅ Seeding completed successfully!');
        $this->writeOutput('');
    }

    protected function resolveCategory(): array
    {
        $leafCount = count($this->leafCategories);
        if ($leafCount === 0) {
            throw new RuntimeException('No categories available for assignment.');
        }

        $category = $this->leafCategories[$this->categoryCursor % $leafCount];
        $this->categoryCursor++;

        $childCatId = $category['parent_id'] ? $category['id'] : null;
        $catId = $category['parent_id'] ? $this->getTopCategoryId($category['id']) : $category['id'];

        return [$catId, $childCatId, $category['slug'] ?? 'product'];
    }

    protected function getTopCategoryId(int $categoryId): int
    {
        if (isset($this->topCategoryCache[$categoryId])) {
            return $this->topCategoryCache[$categoryId];
        }

        $current = $categoryId;
        while (isset($this->categoryParentMap[$current]) && $this->categoryParentMap[$current]) {
            $current = $this->categoryParentMap[$current];
        }

        $this->topCategoryCache[$categoryId] = $current;
        return $current;
    }

    protected function resolveBrand(): ?int
    {
        if (empty($this->brands)) {
            return null;
        }

        $brand = $this->brands[$this->brandCursor % count($this->brands)];
        $this->brandCursor++;
        return $brand;
    }

    protected function buildProductSlug(string $categorySlug, int $productId): string
    {
        return trim($categorySlug ?: 'product') . '-' . $productId;
    }

    protected function determineStatus(int $productId): string
    {
        return 'active';
    }

    protected function generateBasePrice(): string
    {
        return $this->formatMoney(random_int(1500, 250000));
    }

    protected function generateBaseDiscount(): ?string
    {
        if (random_int(0, 100) < 70) {
            return null;
        }

        return $this->formatPercent(random_int(200, 2500));
    }

    protected function generateBaseStock(): int
    {
        return random_int(25, 400);
    }

    protected function generateBaseSku(int $productId): string
    {
        return 'BASE-' . str_pad((string) $productId, 9, '0', STR_PAD_LEFT);
    }

    protected function buildProductImages(int $productId, string $timestamp): array
    {
        $rows = [];
        $randomImages = $this->getRandomImages($this->productImagePool, $this->productImagesPerProduct);

        // Ensure we always have at least the requested number of images
        // If pool is small, repeat images to reach the target count
        if (count($randomImages) < $this->productImagesPerProduct && !empty($this->productImagePool)) {
            while (count($randomImages) < $this->productImagesPerProduct) {
                $randomImages[] = $this->productImagePool[array_rand($this->productImagePool)];
            }
        }

        foreach ($randomImages as $i => $filename) {
            // Store only filename (not path) - 50% storage reduction
            // Path handling done in application layer via config/helper
            $rows[] = [
                'id' => $this->nextProductImageId++,
                'product_id' => $productId,
                'image_path' => $filename, // Filename only!
                'thumbnail_path' => null,
                'is_primary' => $i === 0,
                'sort_order' => $i + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        return $rows;
    }

    protected function buildVariantsForProduct(int $productId, string $timestamp, string $categorySlug = ''): array
    {
        $variantCount = $this->determineVariantCount();
        $selectedTypeIds = $this->selectVariantTypes($productId, $categorySlug);

        $optionSets = [];
        foreach ($selectedTypeIds as $typeId) {
            $rotation = $this->variantOptionCursor[$typeId] ?? 0;
            $optionSets[$typeId] = $this->rotateOptions($this->variantTypeOptions[$typeId], $rotation);
        }

        $maxCombos = 1;
        foreach ($selectedTypeIds as $typeId) {
            $optionCount = count($optionSets[$typeId]);
            if ($optionCount === 0) {
                throw new RuntimeException("Variant type {$typeId} has no options available.");
            }
            $maxCombos *= $optionCount;
        }

        $variantCount = max(1, min($variantCount, $maxCombos));
        $combinations = $this->generateVariantCombinations($optionSets, $selectedTypeIds, $variantCount);

        foreach ($selectedTypeIds as $typeId) {
            $optionCount = count($this->variantTypeOptions[$typeId]);
            if ($optionCount > 0) {
                $this->variantOptionCursor[$typeId] = ($this->variantOptionCursor[$typeId] + $variantCount) % $optionCount;
            }
        }

        $variants = [];
        $variantImages = [];
        $assignments = [];
        $typeSelections = [];

        foreach ($selectedTypeIds as $typeId) {
            $typeSelections[] = [
                'id' => $this->nextTypeSelectionId++,
                'product_id' => $productId,
                'product_variant_type_id' => $typeId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        foreach ($combinations as $index => $combo) {
            $variantId = $this->nextVariantId++;
            $variants[] = [
                'id' => $variantId,
                'product_id' => $productId,
                'sku' => $this->buildSku($productId, $combo, $index, $categorySlug),
                'price' => $this->generateVariantPrice(),
                'discount' => $this->generateVariantDiscount(),
                'stock' => $this->generateVariantStock(),
                'status' => 'active',
                'variant_values' => json_encode($combo, JSON_UNESCAPED_UNICODE),
                'display_name' => $this->buildDisplayName($combo),
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            foreach ($combo as $optionId) {
                $assignments[] = [
                    'id' => $this->nextAssignmentId++,
                    'product_variant_id' => $variantId,
                    'product_variant_option_id' => $optionId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }

            foreach ($this->buildVariantImages($variantId, $timestamp) as $imageRow) {
                $variantImages[] = $imageRow;
            }
        }

        return [
            'variants' => $variants,
            'images' => $variantImages,
            'assignments' => $assignments,
            'typeSelections' => $typeSelections,
        ];
    }

    protected function determineVariantCount(): int
    {
        if ($this->minVariantsPerProduct >= $this->maxVariantsPerProduct) {
            return max(1, $this->maxVariantsPerProduct);
        }

        return random_int($this->minVariantsPerProduct, $this->maxVariantsPerProduct);
    }

    protected function selectVariantTypes(int $seed, string $categorySlug = ''): array
    {
        // Check if category has specific variant type mapping (exact or partial match)
        if (!empty($categorySlug)) {
            $allowedTypeNames = null;

            // First try exact match
            if (isset($this->categoryVariantMapping[$categorySlug])) {
                $allowedTypeNames = $this->categoryVariantMapping[$categorySlug];
            } else {
                // Try partial match (contains)
                foreach ($this->categoryVariantMapping as $pattern => $types) {
                    if (stripos($categorySlug, $pattern) !== false) {
                        $allowedTypeNames = $types;
                        break;
                    }
                }
            }

            // If we found a mapping, use it
            if ($allowedTypeNames !== null) {
                $selected = [];

                foreach ($allowedTypeNames as $typeName) {
                    foreach ($this->variantTypes as $typeId => $typeData) {
                        if ($typeData['name'] === $typeName) {
                            $selected[] = $typeId;
                            break;
                        }
                    }
                }

                if (!empty($selected)) {
                    usort($selected, fn ($a, $b) => $this->variantTypes[$a]['sort_order'] <=> $this->variantTypes[$b]['sort_order']);
                    return $selected;
                }
            }
        }

        // Fallback to original logic
        $availableTypeIds = array_keys($this->variantTypes);
        $count = count($availableTypeIds);
        if ($count === 0) {
            throw new RuntimeException('Variant type metadata missing.');
        }

        $maxSelectable = min($this->maxVariantTypesPerProduct, $count);
        $minSelectable = min($this->minVariantTypesPerProduct, $maxSelectable);
        $typeCount = $maxSelectable === $minSelectable ? $maxSelectable : random_int($minSelectable, $maxSelectable);

        $selected = [];
        for ($i = 0; $i < $typeCount; $i++) {
            $index = ($seed + $i + $this->variantProductsSeeded) % $count;
            $selected[] = $availableTypeIds[$index];
        }

        $selected = array_unique($selected);
        sort($selected);
        usort($selected, fn ($a, $b) => $this->variantTypes[$a]['sort_order'] <=> $this->variantTypes[$b]['sort_order']);
        return $selected;
    }

    protected function rotateOptions(array $options, int $offset): array
    {
        $count = count($options);
        if ($count === 0) {
            return $options;
        }
        $offset = $offset % $count;
        if ($offset <= 0) {
            return $options;
        }
        return array_merge(array_slice($options, $offset), array_slice($options, 0, $offset));
    }

    protected function generateVariantCombinations(array $optionSets, array $typeOrder, int $limit): array
    {
        $combinations = [];
        $this->walkCombinations($optionSets, $typeOrder, 0, [], $combinations, $limit);
        return $combinations;
    }

    protected function walkCombinations(array $optionSets, array $typeOrder, int $depth, array $current, array &$combinations, int $limit): void
    {
        if (count($combinations) >= $limit) {
            return;
        }

        if ($depth === count($typeOrder)) {
            $combinations[] = $current;
            return;
        }

        $typeId = $typeOrder[$depth];
        $options = $optionSets[$typeId] ?? [];

        foreach ($options as $option) {
            $next = $current;
            $next[$typeId] = $option['id'];
            $this->walkCombinations($optionSets, $typeOrder, $depth + 1, $next, $combinations, $limit);
            if (count($combinations) >= $limit) {
                break;
            }
        }
    }

    protected function buildDisplayName(array $combo): string
    {
        $typeIds = array_keys($combo);
        usort($typeIds, fn ($a, $b) => $this->variantTypes[$a]['sort_order'] <=> $this->variantTypes[$b]['sort_order']);

        $parts = [];
        foreach ($typeIds as $typeId) {
            $optionId = $combo[$typeId];
            $option = $this->optionLookup[$optionId] ?? null;
            if (!$option) {
                continue;
            }
            $typeName = $option['type_display_name'] ?? $option['type_name'] ?? 'Option';
            $value = $option['display_value'] ?? $option['value'];
            $parts[] = $typeName . ': ' . $value;
        }

        return implode(' / ', $parts);
    }

    protected function buildSku(int $productId, array $combo, int $index, string $categorySlug = ''): string
    {
        // Initialize sequence counter for this category
        if (!isset($this->skuSequenceCounters[$categorySlug])) {
            $this->skuSequenceCounters[$categorySlug] = 1;
        }

        $sequenceNumber = $this->skuSequenceCounters[$categorySlug]++;
        // Use microseconds to avoid collisions across categories within same second
        $timestamp = (int) floor(microtime(true) * 1000000);

        // Extract variant option values in order: color, storage, ram, size
        $color = '';
        $storage = '';
        $ram = '';
        $size = '';

        foreach ($combo as $typeId => $optionId) {
            $option = $this->optionLookup[$optionId] ?? null;
            if (!$option) continue;

            $typeName = $this->variantTypes[$typeId]['name'] ?? '';
            $value = $option['sku_fragment'] ?? '';

            if ($typeName === 'color') {
                $color = $value;
            } elseif ($typeName === 'storage') {
                $storage = $value;
            } elseif ($typeName === 'ram') {
                $ram = $value;
            } elseif ($typeName === 'size') {
                $size = $value;
            }
        }

        // Format: {color}-{storage}-{ram}-{size}-{timestamp}-{sequenceNumber}
        // Build parts based on what's available for this product category
        $parts = [];
        if ($color) $parts[] = $color;
        if ($storage) $parts[] = $storage;
        if ($ram) $parts[] = $ram;
        if ($size) $parts[] = $size;
        $parts[] = $timestamp;
        $parts[] = $sequenceNumber;

        return implode('-', $parts);
    }

    protected function buildVariantImages(int $variantId, string $timestamp): array
    {
        $rows = [];
        $randomImages = $this->getRandomImages($this->variantImagePool, $this->variantImagesPerVariant);

        // Ensure we always have at least the requested number of images
        // If pool is small, repeat images to reach the target count
        if (count($randomImages) < $this->variantImagesPerVariant && !empty($this->variantImagePool)) {
            while (count($randomImages) < $this->variantImagesPerVariant) {
                $randomImages[] = $this->variantImagePool[array_rand($this->variantImagePool)];
            }
        }

        foreach ($randomImages as $i => $filename) {
            // Store only filename (not path) - 50% storage reduction
            // Path handling done in application layer via config/helper
            $rows[] = [
                'id' => $this->nextVariantImageId++,
                'product_variant_id' => $variantId,
                'image_path' => $filename, // Filename only!
                'thumbnail_path' => null,
                'is_primary' => $i === 0,
                'sort_order' => $i + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        return $rows;
    }

    /**
     * Read image list from a generated PHP file returning an array.
     */
    protected function loadImageListFromPhpFile(string $file): array
    {
        if (is_file($file)) {
            try {
                $data = include $file;
                if (is_array($data)) {
                    return array_values(array_filter(array_map('strval', $data)));
                }
            } catch (\Throwable $e) {
                // ignore and treat as empty
            }
        }
        return [];
    }

    /**
     * Persist a PHP file that returns the provided array.
     */
    protected function writeImageListPhpFile(string $file, array $relativePaths): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $export = var_export(array_values($relativePaths), true);
        $content = "<?php\nreturn " . $export . ";\n";
        @file_put_contents($file, $content);
    }

    /**
     * Persist a text file with one image path per line.
     */
    protected function writeImageListTextFile(string $file, array $relativePaths): void
    {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $content = implode(PHP_EOL, array_values($relativePaths));
        @file_put_contents($file, $content);
    }

    /**
     * Scan only the top-level of a directory for image files and return file names.
     * Ignores subdirectories completely.
     */
    protected function scanTopLevelImages(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $allowed = ['webp', 'jpg', 'jpeg', 'png'];
        $result = [];
        $dh = @opendir($dir);
        if ($dh === false) {
            return [];
        }
        while (($entry = readdir($dh)) !== false) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            // Skip placeholder files
            if (strtolower($entry) === 'placeholder.webp') {
                continue;
            }
            $full = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_file($full)) {
                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed, true)) {
                    // Store only filename (not full path) - 50% storage reduction
                    $result[] = $entry;
                }
            }
        }
        closedir($dh);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * Get random images from a pool
     */
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

    protected function generateVariantPrice(): string
    {
        return $this->formatMoney(random_int(800, 220000));
    }

    protected function generateVariantDiscount(): ?string
    {
        if (random_int(0, 100) < 60) {
            return null;
        }

        return $this->formatPercent(random_int(100, 2000));
    }

    protected function generateVariantStock(): int
    {
        return random_int(5, 250);
    }

    protected function formatMoney(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected function formatPercent(int $basisPoints): string
    {
        $value = max(0, min($basisPoints / 100, 100));
        return number_format($value, 2, '.', '');
    }

    protected function persistBatch(
        array $productRows,
        array $productImageRows,
        array $variantRows,
        array $variantImageRows,
        array $assignmentRows,
        array $typeSelectionRows
    ): void {
        $callback = function () use (
            $productRows,
            $productImageRows,
            $variantRows,
            $variantImageRows,
            $assignmentRows,
            $typeSelectionRows
        ) {
            if (!empty($productRows)) {
                DB::table('products')->insert($productRows);
            }
            if (!empty($productImageRows)) {
                DB::table('product_images')->insert($productImageRows);
            }
            if (!empty($variantRows)) {
                DB::table('product_variants')->insert($variantRows);
            }
            if (!empty($variantImageRows)) {
                DB::table('variant_images')->insert($variantImageRows);
            }
            if (!empty($assignmentRows)) {
                DB::table('product_variant_option_assignments')->insert($assignmentRows);
            }
            if (!empty($typeSelectionRows)) {
                DB::table('product_variant_type_selections')->insert($typeSelectionRows);
            }
        };

        if ($this->useTransactions) {
            DB::transaction($callback, 5);
        } else {
            $callback();
        }
    }

    protected function reportProgress(int $batchIndex, int $remaining, float $startTime): void
    {
        $elapsed = microtime(true) - $startTime;
        $rate = $elapsed > 0 ? $this->productsSeeded / $elapsed : 0;
        $eta = ($rate > 0 && $remaining > 0) ? $remaining / $rate : 0;
        $percentComplete = $this->totalProducts > 0 ? ($this->productsSeeded / $this->totalProducts) * 100 : 0;

        $message = sprintf(
            '⚡ Batch #%-4d │ Progress: %6.2f%% │ Seeded: %s │ Remaining: %s │ Rate: %s/s │ ETA: %s',
            $batchIndex,
            $percentComplete,
            str_pad(number_format($this->productsSeeded), 10, ' ', STR_PAD_LEFT),
            str_pad(number_format($remaining), 10, ' ', STR_PAD_LEFT),
            str_pad(number_format($rate, 0), 6, ' ', STR_PAD_LEFT),
            $this->formatInterval($eta)
        );

        $this->writeOutput($message);

        // Show detailed breakdown every 10 batches
        if ($batchIndex % 10 === 0) {
            $this->writeOutput('   └─ Variant: ' . number_format($this->variantProductsSeeded) . ' │ Non-Variant: ' . number_format($this->nonVariantProductsSeeded));
        }
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

    protected function syncSequences(): void
    {
        $tables = [
            'products',
            'product_variants',
            'product_images',
            'variant_images',
            'product_variant_option_assignments',
            'product_variant_type_selections',
        ];

        foreach ($tables as $table) {
            DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0))");
        }
    }

    protected function buildSkuFragment(string $value): string
    {
        $fragment = strtoupper(preg_replace('/[^A-Z0-9]/', '', $value));
        if ($fragment === '') {
            return 'OPT';
        }

        return substr($fragment, 0, 6);
    }
}
