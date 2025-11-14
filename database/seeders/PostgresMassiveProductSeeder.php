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
     * High level configuration (override as needed or via env vars).
     */
    // protected int $totalProducts = 10_000_000;
    protected int $totalProducts = 1_0;
    protected ?int $variantProductTarget = 9;
    // protected ?int $variantProductTarget = 9_500_000;
    protected float $variantProductRatio = 0.95;
    protected int $minVariantsPerProduct = 3;

    protected int $maxVariantsPerProduct = 5;
    protected int $minVariantTypesPerProduct = 2;
    protected int $maxVariantTypesPerProduct = 3;
    protected int $productImagesPerProduct = 2;
    protected int $variantImagesPerVariant = 1;
    protected int $batchSize = 5;
    protected bool $truncateBeforeSeeding = false;
    protected bool $syncSequencesAfterSeeding = true;
    protected bool $useTransactions = true;
    protected bool $allowEnvOverrides = true;
    protected int $progressLogFrequency = 1;
    protected bool $emitConsoleProgress = true;
    protected bool $emitLaravelLog = true;

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
     * Static pools - Using placeholder/default image that should exist
     */
    protected array $conditions = ['default', 'new', 'hot'];

    // These will be populated from actual existing images in storage
    protected array $productImagePool = [
        "product_6889f81c5163c_1.webp", "product_6889f81c59696_2.webp", "product_6889f81c616e8_3.webp",
        "product_6889f81c6a225_4.webp", "product_6889f9113eecd_0.webp", "product_6889f980ae308_0.webp",
        "product_6889fd3e6fc02_0.webp", "product_6889ff20a3003_0.webp", "product_6889ff20ab21f_1.webp",
        "product_6889ff20b31b8_2.webp", "product_688a005542712_0.webp", "product_688a0088c4999_0.webp",
        "product_688a01254a8de_0.webp", "product_688a02ff4c13c_0.webp", "product_688a03fbc0e23_0.webp",
        "product_688aefd6b5d92_0.webp", "product_688b3bcfd79b9_0.webp", "product_688b3d8672a03_0.webp",
        "product_688b4fa2cea02_0.webp", "product_688b4fa2d7cae_1.webp", "product_688b51b8ae4fd_0.webp",
        "product_688b5e59765d5_0.webp", "product_688b603325c06_0.webp", "product_688c3b3d8cd70_0.webp",
        "product_688c51f279b9c_0.webp", "product_688c9e1305a57_0.webp", "product_688c9e130e22d_1.webp",
        "product_68909d01452d4_0.webp", "product_68909d014e1e4_1.webp", "product_68909d015694f_2.webp",
        "product_68909d015f199_3.webp", "product_68909d0168d61_4.webp", "product_68909d0170766_5.webp",
        "product_68909d017985c_6.webp", "product_6892df81c7fe4_0.webp", "product_6892df81d0732_1.webp",
        "product_6892fe3b5293e_0.webp", "product_6892fe3b5ba1b_1.webp", "product_6892fea723299_0.webp",
        "product_6892fea72bb51_1.webp", "product_6892ffc4a0ae1_0.webp", "product_6892ffc4a8cea_1.webp",
        "product_6892ffc4afd6d_2.webp", "product_6893008bb8af3_0.webp", "product_6893008bc2ce2_1.webp",
        "product_6893008bcb95a_2.webp", "product_689300d5e7a6b_0.webp", "product_689300d5f0a7f_1.webp",
        "product_689300d604793_2.webp", "product_6893012f60da8_0.webp", "product_6893012f69029_1.webp",
        "product_6893012f70d3d_2.webp", "product_68e8d66387660_0.webp", "product_68e8d79a74aad_0.webp",
        "product_6901faebb109b_0.webp", "product_6901fe4b2da2d_0.webp", "product_69020083e3b75_0.webp",
        "product_690200b244587_0.webp", "product_690200b24efd9_1.webp", "product_690200b258067_2.webp",
        "product_690200b260592_3.webp", "product_690200b26771a_4.webp", "product_690200b270dd5_5.webp",
        "product_690200b27a849_6.webp", "product_690200b282a87_7.webp", "product_690200b28ab7d_8.webp",
        "product_690200b292edf_9.webp", "product_690200b29bf1e_10.webp", "product_690200b2a5137_11.webp",
        "product_690200b2add93_12.webp", "product_690200b2b62ac_13.webp", "product_690200b2be15d_14.webp",
        "product_690200b2c79a1_15.webp", "product_690200b2cfbe7_16.webp", "product_690200b2d807d_17.webp",
        "product_690200b2dfc85_18.webp", "product_690200b2e7ceb_19.webp", "product_690200b2f080e_20.webp",
        "product_690200b304657_21.webp", "product_690200b30c797_22.webp", "product_690200b3148d2_23.webp",
        "product_690200b31d457_24.webp", "product_6902e66def238_0.webp"
    ];
    protected array $variantImagePool = [
         "variant_68e8c67d53c28_0.webp", "variant_68e8c73e05444_0.webp", "variant_68e8c824a9cf7_0.webp",
        "variant_68e8ca99af65f_0.webp", "variant_68e8cdbf8f7b7_0.webp", "variant_68e8cf3d223f7_0.webp",
        "variant_68e8cfdb27714_0.webp", "variant_68e8d02251456_0.webp", "variant_68e8d04846afc_0.webp",
        "variant_68e8d10d7ef24_0.webp", "variant_68e8d2a66ff27_0.webp", "variant_68e8d3848c5eb_0.webp",
        "variant_68e8d467679cf_0.webp", "variant_68e8d46771062_1.webp", "variant_68e8d46779626_2.webp",
        "variant_68e8d53a7c3af_0.webp", "variant_68ec8d7cd47f3_1.webp", "variant_68ececa9e9fb8_0.webp",
        "variant_68ececa9f2ee2_1.webp", "variant_68ececaa11db9_0.webp", "variant_68ececaa1abba_1.webp",
        "variant_68ececaa2565a_2.webp", "variant_68ececaa33d4d_0.webp", "variant_68ececaa42765_0.webp",
        "variant_68ececaa4bcec_1.webp", "variant_68edfabeda8af_0.webp", "variant_6901f4c839cad_0.webp",
        "variant_6901f4c841ab6_1.webp", "variant_6901f4c849376_2.webp", "variant_6901f4c85d109_0.webp",
        "variant_6901f4c864a76_1.webp", "variant_6901f4c86c824_2.webp", "variant_6901f4c876678_3.webp",
        "variant_6901f4c87e9c8_4.webp", "variant_6901f4c88c5aa_0.webp", "variant_6901f4c893147_1.webp",
        "variant_6901f4c89e529_0.webp", "variant_6901f4c8a616e_1.webp", "variant_6901f4c8addeb_2.webp",
        "variant_690201b061c8f_0.webp", "variant_690201d1267f3_0.webp", "variant_6902021a2cec0_0.webp",
        "variant_69020281e822d_0.webp", "variant_690202b9629f4_0.webp", "variant_690204516e31f_0.webp",
        "variant_6902e6f9d2fd1_0.webp", "variant_6902eba45bd69_0.webp", "variant_69031fe530b80_0.webp",
        "variant_69031fe53b9d9_1.webp", "variant_69031fe544ce7_2.webp", "variant_69031fe55ae68_0.webp",
        "variant_69031fe563f1f_1.webp", "variant_69031fe56d523_2.webp", "variant_69031fe578027_0.webp",
        "variant_69031fe58009a_1.webp", "variant_69031fe583d0a_2.webp", "variant_69031fe58a6f3_3.webp",
        "variant_69031fe59a6a3_0.webp", "variant_69031fe5a3ed3_1.webp", "variant_69031fe5ad104_2.webp",
        "variant_69031fe5b625a_3.webp", "variant_69031fe5bee4e_4.webp", "variant_69031fe5c8cde_5.webp",
        "variant_69031fe5da70e_0.webp", "variant_69031fe5e39e9_1.webp", "variant_69031fe5ed204_2.webp",
        "variant_69031fe6021ae_3.webp", "variant_69031fe60a53c_4.webp", "variant_69031fe62229e_0.webp",
        "variant_69031fe62bd58_1.webp", "variant_69031fe6372d0_0.webp", "variant_69031fe64056a_1.webp",
        "variant_69031fe64bbeb_2.webp", "variant_69031fe654739_3.webp", "variant_69031fe65dde7_4.webp",
        "variant_69031fe667054_5.webp"
    ];

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
        DB::disableQueryLog();

        if ($this->allowEnvOverrides) {
            $this->applyEnvOverrides();
        }

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

    protected function applyEnvOverrides(): void
    {
        $total = $this->getEnv('MASSIVE_PRODUCT_TOTAL');
        if ($total !== null) {
            $this->totalProducts = max(0, (int) $total);
        }

        $variantTarget = $this->getEnv('MASSIVE_PRODUCT_VARIANT_TARGET');
        if ($variantTarget !== null) {
            $this->variantProductTarget = max(0, (int) $variantTarget);
        } else {
            $variantRatio = $this->getEnv('MASSIVE_PRODUCT_VARIANT_RATIO');
            if ($variantRatio !== null) {
                $this->variantProductRatio = max(0.0, min(1.0, (float) $variantRatio));
                $this->variantProductTarget = null; // recompute later using ratio
            }
        }

        $batch = $this->getEnv('MASSIVE_PRODUCT_BATCH');
        if ($batch !== null) {
            $this->batchSize = max(1000, (int) $batch);
        }

        $variantsPerProduct = $this->getEnv('MASSIVE_PRODUCT_VARIANTS_PER_PRODUCT');
        if ($variantsPerProduct !== null) {
            $value = max(1, (int) $variantsPerProduct);
            $this->minVariantsPerProduct = $value;
            $this->maxVariantsPerProduct = $value;
        }
    }

    protected function truncateTables(): void
    {
        DB::statement('TRUNCATE TABLE variant_images, product_variant_option_assignments, product_variant_type_selections, product_variants, product_images, products RESTART IDENTITY CASCADE');
    }

    protected function loadImagePools(): void
    {
        $productDir = storage_path('app/public/products');
        $variantDir = storage_path('app/public/products/variants');

        // Scan for actual product images
        if (is_dir($productDir)) {
            $files = scandir($productDir);
            foreach ($files as $file) {
                if (preg_match('/^product_.*\.(webp|jpg|jpeg|png)$/i', $file)) {
                    $this->productImagePool[] = 'products/' . $file;
                }
            }
        }

        // Scan for actual variant images
        if (is_dir($variantDir)) {
            $files = scandir($variantDir);
            foreach ($files as $file) {
                if (preg_match('/^variant_.*\.(webp|jpg|jpeg|png)$/i', $file)) {
                    $this->variantImagePool[] = 'products/variants/' . $file;
                }
            }
        }

        // Fallback to placeholder if no images found
        if (empty($this->productImagePool)) {
            $this->productImagePool = ['products/placeholder.webp'];
            $this->writeOutput('Warning: No product images found in storage. Using placeholder.');
        }

        if (empty($this->variantImagePool)) {
            $this->variantImagePool = ['products/variants/placeholder.webp'];
            $this->writeOutput('Warning: No variant images found in storage. Using placeholder.');
        }

        $this->writeOutput('Loaded ' . count($this->productImagePool) . ' product images and ' . count($this->variantImagePool) . ' variant images.');
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
        $this->nextProductId = $this->nextIdFor('products');
        $this->nextVariantId = $this->nextIdFor('product_variants');
        $this->nextProductImageId = $this->nextIdFor('product_images');
        $this->nextVariantImageId = $this->nextIdFor('variant_images');
        $this->nextAssignmentId = $this->nextIdFor('product_variant_option_assignments');
        $this->nextTypeSelectionId = $this->nextIdFor('product_variant_type_selections');
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

                foreach ($this->buildProductImages($productId, $timestamp) as $imageRow) {
                    $productImageRows[] = $imageRow;
                }

                if ($isVariantProduct) {
                    $variantPayload = $this->buildVariantsForProduct($productId, $timestamp);

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
            }

            $this->persistBatch($productRows, $productImageRows, $variantRows, $variantImageRows, $assignmentRows, $typeSelectionRows);

            $remaining -= $currentBatchSize;
            $batchIndex++;

            if ($this->emitConsoleProgress && ($batchIndex % $this->progressLogFrequency === 0 || $remaining === 0)) {
                $this->reportProgress($batchIndex, $remaining, $startTime);
            }

            unset($productRows, $productImageRows, $variantRows, $variantImageRows, $assignmentRows, $typeSelectionRows);
            gc_collect_cycles();
        }

        if ($this->emitLaravelLog) {
            Log::info('PostgresMassiveProductSeeder completed', [
                'products_seeded' => $this->productsSeeded,
                'variant_products_seeded' => $this->variantProductsSeeded,
                'non_variant_products_seeded' => $this->nonVariantProductsSeeded,
            ]);
        }
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
        return ($productId % 75 === 0) ? 'inactive' : 'active';
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

        foreach ($randomImages as $i => $imageName) {
            $defaultImage = 'products/placeholders/default.webp';
            $path = $this->normalizeImagePath($imageName, 'products', $defaultImage);
            $rows[] = [
                'id' => $this->nextProductImageId++,
                'product_id' => $productId,
                'image_path' => $path,
                'thumbnail_path' => null,
                'is_primary' => $i === 0,
                'sort_order' => $i + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        return $rows;
    }

    protected function buildVariantsForProduct(int $productId, string $timestamp): array
    {
        $variantCount = $this->determineVariantCount();
        $selectedTypeIds = $this->selectVariantTypes($productId);

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
                'sku' => $this->buildSku($productId, $combo, $index),
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

    protected function selectVariantTypes(int $seed): array
    {
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

    protected function buildSku(int $productId, array $combo, int $index): string
    {
        $parts = ['P' . str_pad((string) $productId, 8, '0', STR_PAD_LEFT)];
        $typeIds = array_keys($combo);
        usort($typeIds, fn ($a, $b) => $this->variantTypes[$a]['sort_order'] <=> $this->variantTypes[$b]['sort_order']);

        foreach ($typeIds as $typeId) {
            $optionId = $combo[$typeId];
            $option = $this->optionLookup[$optionId] ?? null;
            $parts[] = $option['sku_fragment'] ?? ('OPT' . $optionId);
        }

        $parts[] = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        return implode('-', $parts);
    }

    protected function buildVariantImages(int $variantId, string $timestamp): array
    {
        $rows = [];
        $randomImages = $this->getRandomImages($this->variantImagePool, $this->variantImagesPerVariant);

        foreach ($randomImages as $i => $imageName) {
            $defaultImage = 'products/variants/placeholders/default.webp';
            $path = $this->normalizeImagePath($imageName, 'products/variants', $defaultImage);
            $rows[] = [
                'id' => $this->nextVariantImageId++,
                'product_variant_id' => $variantId,
                'image_path' => $path,
                'thumbnail_path' => null,
                'is_primary' => $i === 0,
                'sort_order' => $i + 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }
        return $rows;
    }

    protected function normalizeImagePath(string $path, string $directory, string $default): string
    {
        $normalized = str_replace('\\', '/', trim($path));

        if ($normalized === '') {
            return $default;
        }

        if (strpos($normalized, 'storage/') === 0) {
            $normalized = substr($normalized, 8);
        }

        $normalized = ltrim($normalized, '/');

        if (strpos($normalized, '/') === false) {
            $normalized = trim($directory, '/') . '/' . $normalized;
        }

        return $normalized;
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

        $message = sprintf(
            '[Batch %d] Seeded: %s (variant %s / non-variant %s) Remaining: %s Rate: %.0f rows/s ETA: %s',
            $batchIndex,
            number_format($this->productsSeeded),
            number_format($this->variantProductsSeeded),
            number_format($this->nonVariantProductsSeeded),
            number_format($remaining),
            $rate,
            $this->formatInterval($eta)
        );

        $this->writeOutput($message);
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

    protected function getEnv(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
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
