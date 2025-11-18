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
    protected int $totalProducts = 10_000_000;                    // Total number of products to generate
    protected ?int $variantProductTarget = 9_500_000;            // Target number of products with variants (null = use ratio)
    protected float $variantProductRatio = 0.95;             // Ratio of variant products if target not set (0.95 = 95%)
    protected int $startProductIdIfEmpty = 8812000;          // Starting product ID when table is empty (set to 1 for fresh start)

    // Variant configuration per product
    protected int $minVariantsPerProduct = 3;                // Minimum variants per product
    protected int $maxVariantsPerProduct = 4;                // Maximum variants per product (reduced for speed)
    protected int $minVariantTypesPerProduct = 2;            // Minimum variant types (e.g., color + size)
    protected int $maxVariantTypesPerProduct = 2;            // Maximum variant types (reduced for speed)

    // Image configuration
    protected int $productImagesPerProduct = 2;              // Images per product (reduced for speed)
    protected int $variantImagesPerVariant = 2;              // Images per variant (reduced for speed)
    protected bool $forceRefreshImageLists = false;          // Force rescan storage folders (true = rescan, false = use cache)

    // Performance settings (optimized for 10M+ records)
    // Note: Batch size limited by PostgreSQL's 65,535 parameter limit
    // Each product with variants generates ~50-100 parameters (product + images + variants + assignments)
    protected int $batchSize = 2000;                         // Increased batch size for speed
    protected bool $streamInserts = true;                    // Flush partial batches during processing
    protected int $streamFlushInterval = 1000;               // Larger interval for fewer flushes
    protected int $maxTrackInsertedIds = 0;                  // Don't track IDs beyond this threshold (saves memory)

    // Database operations
    protected bool $truncateBeforeSeeding = false;           // Truncate tables before seeding
    protected bool $truncateProductTables = false;           // If truncating, include products table
    protected bool $truncateRelatedTables = false;           // If truncating, include related tables
    protected bool $syncSequencesAfterSeeding = true;        // Sync PostgreSQL sequences after seeding
    protected bool $useTransactions = false;                 // Disable transactions for max speed on large inserts
    protected bool $disableIndexesDuringInsert = true;       // Drop/recreate indexes for massive inserts (FASTER!)
    protected bool $useUnloggedTables = true;                // Use UNLOGGED tables (MUCH FASTER but no crash recovery)
    protected bool $dropForeignKeysDuringInsert = true;      // Drop/recreate FKs to allow UNLOGGED (MAX SPEED!)
    protected bool $usePostgresOptimizations = true;         // Apply PostgreSQL-specific optimizations

    // Logging settings
    protected int $progressLogFrequency = 50;                // Log progress every N batches (less frequent for performance)
    protected bool $emitConsoleProgress = true;              // Show progress in console
    protected bool $emitLaravelLog = false;                  // Disable Laravel log for performance

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

    // ULTRA MASSIVE (10,000,000 products) - RECOMMENDED FOR 10M
    // protected int $totalProducts = 10_000_000;
    // protected ?int $variantProductTarget = 9_500_000;
    // protected int $batchSize = 1000; // Limited by PostgreSQL 65k parameter limit
    // protected int $streamFlushInterval = 500;
    // protected int $maxTrackInsertedIds = 0; // Disable ID tracking
    // protected bool $useTransactions = false;
    // protected bool $usePostgresOptimizations = true;
    // protected int $progressLogFrequency = 20;

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
    protected int $existingProductsCount = 0;            // Products already in DB before seeding

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

        try {
            $this->seedProducts();
        } finally {
            // Always restore settings even if seeding fails
            if ($this->useUnloggedTables) {
                $this->restoreLoggedTables();
            }

            if ($this->usePostgresOptimizations) {
                $this->restorePostgresSettings();
            }

            // Recreate indexes after seeding
            if ($this->disableIndexesDuringInsert) {
                $this->recreateIndexes();
            }

            // Recreate foreign keys after restoring LOGGED mode
            if ($this->dropForeignKeysDuringInsert) {
                $this->recreateForeignKeys();
            }

            if ($this->syncSequencesAfterSeeding) {
                $this->syncSequences();
            }
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
        $this->writeOutput('║          POSTGRESQL MASSIVE PRODUCT SEEDER v2.1                    ║');
        $this->writeOutput('║              OPTIMIZED FOR 10M+ RECORDS                            ║');
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');

        $dbName = DB::connection()->getDatabaseName();
        $this->writeOutput('  Database: ' . $dbName);

        // Increase memory limit for large scale seeding
        $currentLimit = ini_get('memory_limit');
        $targetMemory = $this->totalProducts >= 1_000_000 ? '2G' : '1G';
        if ($currentLimit !== '-1') {
            @ini_set('memory_limit', $targetMemory);
            $this->writeOutput('  Memory Limit: ' . $currentLimit . ' → ' . $targetMemory);
        } else {
            $this->writeOutput('  Memory Limit: Unlimited');
        }

        // Disable query log for max performance
        DB::disableQueryLog();
        $this->writeOutput('  Query Logging: Disabled (performance optimization)');

        $this->writeOutput('');
        $this->writeOutput('  Seeding Configuration:');
        $this->writeOutput('    • Total Products:    ' . number_format($this->totalProducts));
        $this->writeOutput('    • Variant Target:    ' . number_format($this->variantProductTarget ?? (int)($this->totalProducts * $this->variantProductRatio)));
        $this->writeOutput('    • Batch Size:        ' . number_format($this->batchSize));
        $this->writeOutput('    • Stream Inserts:    ' . ($this->streamInserts ? 'Enabled' : 'Disabled'));
        $this->writeOutput('    • Use Transactions:  ' . ($this->useTransactions ? 'Enabled' : 'Disabled'));
        $this->writeOutput('    • PG Optimizations:  ' . ($this->usePostgresOptimizations ? 'Enabled' : 'Disabled'));
        $this->writeOutput('    • Refresh Images:    ' . ($this->forceRefreshImageLists ? 'Yes (rescan)' : 'No (use cache)'));
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');

        if ($this->truncateBeforeSeeding) {
            $this->truncateTables();
        }

        // Apply PostgreSQL optimizations for bulk insert
        if ($this->usePostgresOptimizations) {
            $this->applyPostgresOptimizations();
        }

        // Drop foreign keys BEFORE setting UNLOGGED (allows more tables to be UNLOGGED)
        if ($this->dropForeignKeysDuringInsert) {
            $this->dropForeignKeys();
        }

        if ($this->useUnloggedTables) {
            $this->setUnloggedTables();
        }

        // Drop indexes before bulk insert for maximum speed
        if ($this->disableIndexesDuringInsert) {
            $this->dropNonPrimaryIndexes();
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
        $this->existingProductsCount = (int) DB::table('products')->count();

        $startProductId = $lastProductId ? ((int) $lastProductId + 1) : $this->startProductIdIfEmpty;

        // Cap at 10 million
        if ($startProductId > 10_000_000) {
            throw new RuntimeException('Product ID would exceed 10 million limit. Current max ID: ' . $lastProductId);
        }

        $this->nextProductId = $startProductId;

        $this->nextVariantId = $this->nextIdFor('product_variants');
        $this->nextProductImageId = $this->nextIdFor('product_images');
        $this->nextVariantImageId = $this->nextIdFor('variant_images');
        $this->nextAssignmentId = $this->nextIdFor('product_variant_option_assignments');
        $this->nextTypeSelectionId = $this->nextIdFor('product_variant_type_selections');

        $this->writeOutput('  Existing Products:      ' . str_pad(number_format($this->existingProductsCount), 12, ' ', STR_PAD_LEFT));
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
        $lastBatchTime = $startTime;

        while ($remaining > 0) {
            $batchStartTime = microtime(true);
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
                // Check if we would exceed 10 million product ID limit
                if ($this->nextProductId > 10_000_000) {
                    $this->writeOutput('');
                    $this->writeOutput('⚠ WARNING: Reached 10 million product ID limit. Stopping seeding.');
                    $remaining = 0;
                    break;
                }

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

            $batchEndTime = microtime(true);
            $batchDuration = $batchEndTime - $batchStartTime;

            if ($this->emitConsoleProgress && ($batchIndex % $this->progressLogFrequency === 0 || $remaining === 0)) {
                $this->reportProgress($batchIndex, $remaining, $startTime, $batchDuration);
            }

            // Log milestone every 10,000 products
            if ($this->productsSeeded % 10000 === 0 && $this->productsSeeded > 0) {
                $this->logMilestone($this->productsSeeded, $startTime);
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
        $this->writeOutput('  Products Seeded:       ' . str_pad(number_format($this->productsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  → Variant Products:    ' . str_pad(number_format($this->variantProductsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  → Non-Variant:         ' . str_pad(number_format($this->nonVariantProductsSeeded), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('');
        $this->writeOutput('  Previous DB Count:     ' . str_pad(number_format($this->existingProductsCount), 15, ' ', STR_PAD_LEFT));
        $this->writeOutput('  Total in Database:     ' . str_pad(number_format($this->existingProductsCount + $this->productsSeeded), 15, ' ', STR_PAD_LEFT));
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

    protected function reportProgress(int $batchIndex, int $remaining, float $startTime, float $batchDuration = 0): void
    {
        $elapsed = microtime(true) - $startTime;
        $rate = $elapsed > 0 ? $this->productsSeeded / $elapsed : 0;
        $eta = ($rate > 0 && $remaining > 0) ? $remaining / $rate : 0;
        $totalExpected = $this->existingProductsCount + $this->totalProducts;
        $currentTotal = $this->existingProductsCount + $this->productsSeeded;
        $percentComplete = $totalExpected > 0 ? ($currentTotal / $totalExpected) * 100 : 0;

        // Format batch duration
        $batchTimeStr = $batchDuration > 0 ? sprintf('%.2fs', $batchDuration) : 'N/A';

        $message = sprintf(
            '⚡ Batch #%-4d │ Progress: %6.2f%% │ DB Total: %s │ Seeded: %s │ Remaining: %s │ Rate: %s/s │ Batch: %s │ ETA: %s',
            $batchIndex,
            $percentComplete,
            str_pad(number_format($currentTotal), 10, ' ', STR_PAD_LEFT),
            str_pad(number_format($this->productsSeeded), 10, ' ', STR_PAD_LEFT),
            str_pad(number_format($remaining), 10, ' ', STR_PAD_LEFT),
            str_pad(number_format($rate, 0), 6, ' ', STR_PAD_LEFT),
            str_pad($batchTimeStr, 8, ' ', STR_PAD_LEFT),
            $this->formatInterval($eta)
        );

        $this->writeOutput($message);

        // Show detailed breakdown every 10 batches
        if ($batchIndex % 10 === 0) {
            $this->writeOutput('   └─ Variant: ' . number_format($this->variantProductsSeeded) . ' │ Non-Variant: ' . number_format($this->nonVariantProductsSeeded));
        }
    }

    protected function logMilestone(int $productsSeeded, float $startTime): void
    {
        $elapsed = microtime(true) - $startTime;
        $rate = $elapsed > 0 ? $productsSeeded / $elapsed : 0;
        $remaining = $this->totalProducts - $productsSeeded;
        $eta = ($rate > 0 && $remaining > 0) ? $remaining / $rate : 0;
        $totalExpected = $this->existingProductsCount + $this->totalProducts;
        $currentTotal = $this->existingProductsCount + $productsSeeded;
        $percentComplete = $totalExpected > 0 ? ($currentTotal / $totalExpected) * 100 : 0;

        $this->writeOutput('');
        $this->writeOutput('╔════════════════════════════════════════════════════════════════════╗');
        $this->writeOutput(sprintf('║  🎯 MILESTONE: %s PRODUCTS SEEDED%s║',
            number_format($productsSeeded),
            str_repeat(' ', 37 - strlen(number_format($productsSeeded)))
        ));
        $this->writeOutput('╚════════════════════════════════════════════════════════════════════╝');
        $this->writeOutput(sprintf('  📊 Progress:       %6.2f%% complete', $percentComplete));
        $this->writeOutput(sprintf('  📦 DB Total:       %s products', number_format($currentTotal)));
        $this->writeOutput(sprintf('  ⚡ Current Rate:    %s products/second', number_format($rate, 0)));
        $this->writeOutput(sprintf('  ⏱  Elapsed Time:   %s', $this->formatInterval($elapsed)));
        $this->writeOutput(sprintf('  🎯 Remaining:      %s products', number_format($remaining)));
        $this->writeOutput(sprintf('  ⏳ ETA:            %s', $this->formatInterval($eta)));
        $this->writeOutput(sprintf('  📦 Variant:        %s products', number_format($this->variantProductsSeeded)));
        $this->writeOutput(sprintf('  📋 Non-Variant:    %s products', number_format($this->nonVariantProductsSeeded)));
        $this->writeOutput('═══════════════════════════════════════════════════════════════════════');
        $this->writeOutput('');
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

    protected function applyPostgresOptimizations(): void
    {
        $this->writeOutput('');
        $this->writeOutput('🔧 Applying PostgreSQL optimizations for bulk insert...');

        $appliedCount = 0;
        $failedCount = 0;

        // Session-level optimizations (safe to apply)
        $sessionSettings = [
            ['maintenance_work_mem', '1GB', 'maintenance work memory'],
            ['work_mem', '256MB', 'work memory'],
            ['synchronous_commit', 'OFF', 'synchronous commit', !$this->useTransactions],
        ];

        foreach ($sessionSettings as $setting) {
            [$param, $value, $description, $condition] = array_pad($setting, 4, true);

            if ($condition === false) {
                continue; // Skip if condition is false
            }

            try {
                DB::statement("SET {$param} = '{$value}'");
                $appliedCount++;
            } catch (\Exception $e) {
                // Try with lower value for memory settings
                if (str_contains($param, 'mem')) {
                    try {
                        $lowerValue = str_replace(['2GB', '1GB', '256MB'], ['512MB', '256MB', '64MB'], $value);
                        DB::statement("SET {$param} = '{$lowerValue}'");
                        $appliedCount++;
                        $this->writeOutput("  ⓘ Applied {$description} with reduced value: {$lowerValue}");
                    } catch (\Exception $e2) {
                        $failedCount++;
                    }
                } else {
                    $failedCount++;
                }
            }
        }

        // Server-level settings (require superuser - log but don't fail)
        $serverSettings = [
            ['checkpoint_timeout', '1h', 'checkpoint timeout'],
            ['max_wal_size', '10GB', 'max WAL size'],
            ['autovacuum', 'OFF', 'autovacuum'],
        ];

        foreach ($serverSettings as $setting) {
            [$param, $value, $description] = $setting;

            try {
                DB::statement("SET {$param} = '{$value}'");
                $appliedCount++;
            } catch (\Exception $e) {
                // These typically require superuser privileges - it's fine if they fail
                $failedCount++;
            }
        }

        if ($appliedCount > 0) {
            $this->writeOutput("  ✓ Applied {$appliedCount} optimizations");
        }
        if ($failedCount > 0) {
            $this->writeOutput("  ⓘ Skipped {$failedCount} optimizations (require superuser or server config)");
        }
    }

    protected function restorePostgresSettings(): void
    {
        $this->writeOutput('');
        $this->writeOutput('🔧 Restoring PostgreSQL settings...');

        $restoredCount = 0;
        $failedCount = 0;

        // Reset each setting individually to handle errors gracefully
        $settings = [
            'maintenance_work_mem',
            'work_mem',
            'checkpoint_timeout',
            'max_wal_size',
            'synchronous_commit',
            'autovacuum',
        ];

        foreach ($settings as $setting) {
            try {
                DB::statement("RESET {$setting}");
                $restoredCount++;
            } catch (\Exception $e) {
                // Silently skip settings that can't be reset (requires superuser or server restart)
                $failedCount++;
            }
        }

        if ($restoredCount > 0) {
            $this->writeOutput("  ✓ Restored {$restoredCount} PostgreSQL settings");
        }
        if ($failedCount > 0) {
            $this->writeOutput("  ⓘ Skipped {$failedCount} settings (require superuser or server config)");
        }

        // Run ANALYZE to update statistics
        try {
            $this->writeOutput('  Running ANALYZE to update table statistics...');
            DB::statement("ANALYZE products");
            DB::statement("ANALYZE product_variants");
            DB::statement("ANALYZE product_images");
            DB::statement("ANALYZE variant_images");
            $this->writeOutput('  ✓ Table statistics updated');
        } catch (\Exception $e) {
            $this->writeOutput('  ⚠ Warning: Could not update table statistics: ' . $e->getMessage());
        }
    }

    protected function setUnloggedTables(): void
    {
        $this->writeOutput('');
        $this->writeOutput('🔧 Converting tables to UNLOGGED mode...');

        // Must convert in order: child tables first, then parent tables
        $tables = [
            'variant_images',
            'product_variant_option_assignments',
            'product_variant_type_selections',
            'product_variants',
            'product_images',
            'products',
        ];

        $successCount = 0;
        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} SET UNLOGGED");
                $successCount++;
            } catch (\Exception $e) {
                $this->writeOutput("  ⚠ Could not set {$table} to UNLOGGED: " . $e->getMessage());
            }
        }

        if ($successCount > 0) {
            $this->writeOutput("  ✓ Set {$successCount} tables to UNLOGGED (faster but no crash recovery)");
        } else {
            $this->writeOutput('  ⚠ Warning: Could not set any tables to UNLOGGED');
            $this->writeOutput('  ⓘ This is OK - seeding will continue with normal (LOGGED) mode');
        }
    }

    protected function restoreLoggedTables(): void
    {
        $this->writeOutput('');
        $this->writeOutput('🔧 Restoring tables to LOGGED mode...');

        // Must restore in reverse order: parent tables first, then child tables
        $tables = [
            'products',
            'product_images',
            'product_variants',
            'product_variant_type_selections',
            'product_variant_option_assignments',
            'variant_images',
        ];

        $successCount = 0;
        foreach ($tables as $table) {
            try {
                DB::statement("ALTER TABLE {$table} SET LOGGED");
                $successCount++;
            } catch (\Exception $e) {
                // Silently ignore - table might not have been UNLOGGED
            }
        }

        if ($successCount > 0) {
            $this->writeOutput("  ✓ Restored {$successCount} tables to LOGGED mode");
        }
    }

    protected function syncSequences(): void
    {
        $this->writeOutput('');
        $this->writeOutput('🔧 Syncing PostgreSQL sequences...');

        $tables = [
            'products',
            'product_variants',
            'product_images',
            'variant_images',
            'product_variant_option_assignments',
            'product_variant_type_selections',
        ];

        foreach ($tables as $table) {
            try {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 0))");
                $this->writeOutput('  ✓ Synced sequence for ' . $table);
            } catch (\Exception $e) {
                $this->writeOutput('  ⚠ Warning: Could not sync sequence for ' . $table . ': ' . $e->getMessage());
            }
        }
    }

    protected function dropNonPrimaryIndexes(): void
    {
        if (!$this->disableIndexesDuringInsert) {
            return;
        }

        $this->writeOutput('');
        $this->writeOutput('🔧 Dropping non-primary indexes for maximum insert speed...');

        try {
            // Drop indexes on products table
            $this->dropIndexSafely('idx_products_status_featured');
            $this->dropIndexSafely('idx_products_category_status');
            $this->dropIndexSafely('idx_products_brand_status');
            $this->dropIndexSafely('products_slug_unique');
            $this->dropIndexSafely('products_cat_id_foreign');
            $this->dropIndexSafely('products_brand_id_foreign');

            // Drop indexes on product_variants table
            $this->dropIndexSafely('idx_variants_product_status');
            $this->dropIndexSafely('product_variants_product_id_foreign');
            $this->dropIndexSafely('product_variants_sku_unique');

            // Drop indexes on product_images table
            $this->dropIndexSafely('idx_images_product_primary');
            $this->dropIndexSafely('product_images_product_id_foreign');

            // Drop indexes on variant_images table
            $this->dropIndexSafely('variant_images_product_variant_id_foreign');

            // Drop indexes on assignments table
            $this->dropIndexSafely('product_variant_option_assignments_product_variant_id_foreign');
            $this->dropIndexSafely('product_variant_option_assignments_product_variant_option_id_foreign');

            // Drop indexes on type selections table
            $this->dropIndexSafely('product_variant_type_selections_product_id_foreign');
            $this->dropIndexSafely('product_variant_type_selections_product_variant_type_id_foreign');

            $this->writeOutput('  ✓ Dropped non-primary indexes');
            $this->writeOutput('  ⚠ IMPORTANT: Indexes will be recreated after seeding');
        } catch (\Exception $e) {
            $this->writeOutput('  ⚠ Warning: Could not drop all indexes: ' . $e->getMessage());
        }
    }

    protected function recreateIndexes(): void
    {
        if (!$this->disableIndexesDuringInsert) {
            return;
        }

        $this->writeOutput('');
        $this->writeOutput('🔧 Recreating indexes...');

        try {
            // Recreate products indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_status_featured ON products(status, is_featured, id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_category_status ON products(cat_id, status, is_featured, id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_products_brand_status ON products(brand_id, status, id)');
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS products_slug_unique ON products(slug)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS products_cat_id_foreign ON products(cat_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS products_brand_id_foreign ON products(brand_id)');

            // Recreate product_variants indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_variants_product_status ON product_variants(product_id, status, stock)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variants_product_id_foreign ON product_variants(product_id)');
            DB::statement('CREATE UNIQUE INDEX CONCURRENTLY IF NOT EXISTS product_variants_sku_unique ON product_variants(sku)');

            // Recreate product_images indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_images_product_primary ON product_images(product_id, is_primary, sort_order)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_images_product_id_foreign ON product_images(product_id)');

            // Recreate variant_images indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS variant_images_product_variant_id_foreign ON variant_images(product_variant_id)');

            // Recreate assignments indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variant_option_assignments_product_variant_id_foreign ON product_variant_option_assignments(product_variant_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variant_option_assignments_product_variant_option_id_foreign ON product_variant_option_assignments(product_variant_option_id)');

            // Recreate type selections indexes
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variant_type_selections_product_id_foreign ON product_variant_type_selections(product_id)');
            DB::statement('CREATE INDEX CONCURRENTLY IF NOT EXISTS product_variant_type_selections_product_variant_type_id_foreign ON product_variant_type_selections(product_variant_type_id)');

            $this->writeOutput('  ✓ Indexes recreated');
        } catch (\Exception $e) {
            $this->writeOutput('  ⚠ Warning: Could not recreate all indexes: ' . $e->getMessage());
        }
    }

    protected function dropIndexSafely(string $indexName): void
    {
        try {
            DB::statement("DROP INDEX IF EXISTS {$indexName}");
        } catch (\Exception $e) {
            // Silently ignore errors
        }
    }

    protected function dropForeignKeys(): void
    {
        if (!$this->dropForeignKeysDuringInsert) {
            return;
        }

        $this->writeOutput('');
        $this->writeOutput('🔧 Dropping foreign keys to enable full UNLOGGED mode...');

        try {
            // Drop FKs from carts table that prevent UNLOGGED
            $this->dropConstraintSafely('carts', 'carts_product_id_foreign');
            $this->dropConstraintSafely('carts', 'carts_product_variant_id_foreign');

            // Drop FKs from wishlists
            $this->dropConstraintSafely('wishlists', 'wishlists_product_id_foreign');
            $this->dropConstraintSafely('wishlists', 'wishlists_product_variant_id_foreign');

            // Drop FKs from orders (if any)
            $this->dropConstraintSafely('orders', 'orders_product_id_foreign');

            // Drop FKs from product_reviews
            $this->dropConstraintSafely('product_reviews', 'product_reviews_product_id_foreign');

            $this->writeOutput('  ✓ Dropped foreign keys');
            $this->writeOutput('  ⚠ IMPORTANT: Foreign keys will be recreated after seeding');
        } catch (\Exception $e) {
            $this->writeOutput('  ⚠ Warning: Could not drop all foreign keys: ' . $e->getMessage());
        }
    }

    protected function recreateForeignKeys(): void
    {
        if (!$this->dropForeignKeysDuringInsert) {
            return;
        }

        $this->writeOutput('');
        $this->writeOutput('🔧 Recreating foreign keys...');

        try {
            // Recreate carts FKs
            DB::statement('ALTER TABLE carts ADD CONSTRAINT carts_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE NOT VALID');
            DB::statement('ALTER TABLE carts ADD CONSTRAINT carts_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE NOT VALID');

            // Recreate wishlists FKs
            DB::statement('ALTER TABLE wishlists ADD CONSTRAINT wishlists_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE NOT VALID');
            DB::statement('ALTER TABLE wishlists ADD CONSTRAINT wishlists_product_variant_id_foreign FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE NOT VALID');

            // Recreate product_reviews FKs
            DB::statement('ALTER TABLE product_reviews ADD CONSTRAINT product_reviews_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE NOT VALID');

            // Validate constraints asynchronously (doesn't block)
            $this->writeOutput('  Validating constraints...');
            DB::statement('ALTER TABLE carts VALIDATE CONSTRAINT carts_product_id_foreign');
            DB::statement('ALTER TABLE carts VALIDATE CONSTRAINT carts_product_variant_id_foreign');
            DB::statement('ALTER TABLE wishlists VALIDATE CONSTRAINT wishlists_product_id_foreign');
            DB::statement('ALTER TABLE wishlists VALIDATE CONSTRAINT wishlists_product_variant_id_foreign');
            DB::statement('ALTER TABLE product_reviews VALIDATE CONSTRAINT product_reviews_product_id_foreign');

            $this->writeOutput('  ✓ Foreign keys recreated and validated');
        } catch (\Exception $e) {
            $this->writeOutput('  ⚠ Warning: Could not recreate all foreign keys: ' . $e->getMessage());
        }
    }

    protected function dropConstraintSafely(string $table, string $constraint): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
        } catch (\Exception $e) {
            // Silently ignore errors
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
