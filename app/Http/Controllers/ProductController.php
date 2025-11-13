<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use Illuminate\Http\Request;
use App\Models\{Product, Category, Brand, ProductImage, ProductVariant, VariantImage, ProductVariantOption, ProductVariantTypeSelection};
use Illuminate\Support\Facades\{DB, Log, Redis, Storage};
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with([
            'cat_info',
            'sub_cat_info',
            'brand',
            'primaryImage',
            'variants' => fn($query) => $query->where('status', 'active')->with('primaryImage')
        ])->orderByDesc('id')->paginate(10);

        return view('backend.product.index', compact('products'));
    }

    public function create()
    {
        $brands = Brand::get();
        $categories = Category::all();
        return view('backend.product.create', compact('categories', 'brands'));
    }

    public function store(Request $request)
    {
        Log::debug('Product store - incoming request', $request->only([
            'title',
            'slug',
            'cat_id',
            'child_cat_id',
            'brand_id',
            'has_variants',
            'base_sku',
            'base_price',
            'base_stock',
            'variants'
        ]));

        $rules = [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'size' => 'nullable|array',
            'enable_alt_text' => 'nullable|boolean',
            'has_variants' => 'nullable|boolean',
        ];

        if ($request->boolean('has_variants')) {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.sku'] = 'required|string|max:255|unique:product_variants,sku';
            $rules['variants.*.price'] = 'required|numeric|min:0';
            $rules['variants.*.discount'] = 'nullable|numeric|min:0|max:100';
            $rules['variants.*.stock'] = 'required|integer|min:0';
            // $rules['variants.*.images'] = 'required|string'; // TEMPORARILY DISABLED FOR TESTING
            $rules['variant_options'] = 'required|array|min:1';
            $rules['variant_options.*'] = 'required|array|min:1';
        } else {
            $rules['base_price'] = 'required|numeric|min:0';
            $rules['base_discount'] = 'nullable|numeric|min:0|max:100';
            $rules['base_stock'] = 'required|integer|min:0';
            $rules['base_sku'] = 'required|string|max:255|unique:products,base_sku';
            $rules['photo'] = 'required|string';
            $rules['alt_text'] = 'nullable|array';
            $rules['alt_text.*'] = 'nullable|string|max:125';
        }

        $messages = [
            'title.required' => 'Product title is required.',
            'slug.required' => 'Product slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'summary.required' => 'Product summary is required.',
            'cat_id.required' => 'Please select a category.',
            'cat_id.exists' => 'Selected category does not exist.',
            'status.required' => 'Please select a status.',
            'condition.required' => 'Please select a condition.',
            'base_price.required' => 'Price is required.',
            'base_price.min' => 'Price must be greater than or equal to 0.',
            'base_stock.required' => 'Stock quantity is required.',
            'base_stock.min' => 'Stock cannot be negative.',
            'base_sku.required' => 'SKU is required.',
            'base_sku.unique' => 'This SKU is already in use.',
            'photo.required' => 'At least one product image is required.',
            'base_discount.min' => 'Discount cannot be negative.',
            'base_discount.max' => 'Discount cannot exceed 100%.',
            'variants.required' => 'Please generate variants before submitting.',
            'variants.min' => 'At least one variant is required.',
            'variants.*.sku.required' => 'Variant SKU is required.',
            'variants.*.sku.unique' => 'This variant SKU is already in use.',
            'variants.*.price.required' => 'Variant price is required.',
            'variants.*.stock.required' => 'Variant stock is required.',
            // 'variants.*.images.required' => 'Variant images are required.', // TEMPORARILY DISABLED FOR TESTING
            'variant_options.required' => 'Please select variant options.',
            'alt_text.*.max' => 'Alt text cannot exceed 125 characters.',
        ];

        $validatedData = $request->validate($rules, $messages);

        Log::info('Product store - validation passed', array_merge(
            ['has_variants' => $request->boolean('has_variants')],
            array_intersect_key($validatedData, array_flip([
                'title',
                'slug',
                'cat_id',
                'child_cat_id',
                'brand_id',
                'base_sku',
                'base_price',
                'base_stock'
            ]))
        ));

        $webpPaths = [];
        if (!$request->boolean('has_variants') && !empty($validatedData['photo'])) {
            $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));

            foreach ($rawPaths as $index => $url) {
                try {
                    $parsed = parse_url($url, PHP_URL_PATH);
                    $publicPath = ltrim(str_replace('/storage/', '', $parsed), '/');
                    $fullPath = storage_path("app/public/{$publicPath}");

                    if (file_exists($fullPath)) {
                        $image = Image::make($fullPath)->encode('webp', 75);
                        $webpFilename = 'product_' . uniqid() . "_{$index}.webp";
                        $webpPath = "public/products/{$webpFilename}";
                        Storage::put($webpPath, (string) $image);
                        $webpPaths[] = "products/{$webpFilename}";
                        Log::info('Product store - converted image to webp', [
                            'original' => $fullPath,
                            'webp_path' => $webpPath,
                            'public_path' => end($webpPaths)
                        ]);
                    } else {
                        Log::warning("Product store - source image not found", ['path' => $fullPath, 'url' => $url]);
                    }
                } catch (\Exception $e) {
                    Log::error("Product store - image processing failed", ['url' => $url, 'error' => $e->getMessage()]);
                }
            }

            if (empty($webpPaths)) {
                Log::error('Product store - no images processed successfully', ['photo_input' => $validatedData['photo']]);
                return redirect()->back()->withInput()->withErrors(['photo' => 'Failed to process images. Please try again.']);
            }
        }

        $productData = [
            'title' => $validatedData['title'],
            'slug' => $validatedData['slug'],
            'summary' => $validatedData['summary'],
            'description' => $validatedData['description'] ?? null,
            'cat_id' => $validatedData['cat_id'],
            'child_cat_id' => $validatedData['child_cat_id'] ?? null,
            'brand_id' => $validatedData['brand_id'] ?? null,
            'is_featured' => $request->boolean('is_featured'),
            'status' => $validatedData['status'],
            'condition' => $validatedData['condition'],
            'size' => $request->has('size') ? implode(',', $validatedData['size']) : 'M',
            'has_variants' => $request->boolean('has_variants'),
        ];

        if (!$request->boolean('has_variants')) {
            $productData['base_price'] = $validatedData['base_price'];
            $productData['base_discount'] = $validatedData['base_discount'] ?? null;
            $productData['base_stock'] = $validatedData['base_stock'];
            $productData['base_sku'] = $validatedData['base_sku'];
        }

        Log::debug('Product store - beginning transaction', [
            'has_variants' => $request->boolean('has_variants'),
            'webp_count' => count($webpPaths)
        ]);

        DB::beginTransaction();

        try {
            $pdo = DB::getPdo();
            $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ?? '';
            if ($driver === 'pgsql') {
                $seqRow = DB::selectOne("SELECT pg_get_serial_sequence('products', 'id') as seq");
                if ($seqRow && isset($seqRow->seq)) {
                    DB::statement("SELECT setval('" . $seqRow->seq . "', (SELECT COALESCE(MAX(id), 0) FROM products))");
                    Log::info('Product store - synced products sequence for pgsql', ['sequence' => $seqRow->seq]);
                }
            }

            $product = Product::create($productData);
            Log::info('Product store - product created', ['product_id' => $product->id, 'base_sku' => $product->base_sku ?? null]);

            if (!$request->boolean('has_variants') && !empty($webpPaths)) {
                $enableAltText = $request->boolean('enable_alt_text');
                $altTexts = $enableAltText && $request->has('alt_text') ? array_values($request->input('alt_text', [])) : [];

                foreach ($webpPaths as $index => $path) {
                    $altText = $enableAltText && isset($altTexts[$index]) && !empty(trim($altTexts[$index]))
                        ? trim($altTexts[$index])
                        : ($enableAltText ? $validatedData['title'] . ' - ' . ($index === 0 ? 'Main Image' : 'Image ' . ($index + 1)) : null);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'alt_text' => $altText,
                        'is_primary' => $index === 0,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            if ($request->boolean('has_variants')) {
                if ($driver === 'pgsql') {
                    $seqRow = DB::selectOne("SELECT pg_get_serial_sequence('product_variants', 'id') as seq");
                    if ($seqRow && isset($seqRow->seq)) {
                        DB::statement("SELECT setval('" . $seqRow->seq . "', (SELECT COALESCE(MAX(id), 0) FROM product_variants))");
                        Log::info('Product store - synced product_variants sequence for pgsql', ['sequence' => $seqRow->seq]);
                    }
                }

                Log::debug('Product store - handling variants', ['product_id' => $product->id, 'variants_count' => count($request->input('variants', []))]);
                $this->handleVariants($request, $product);
                Log::debug('Product store - finished handling variants', ['product_id' => $product->id]);
            }

            DB::commit();
            Log::info('Product store - transaction committed', ['product_id' => $product->id]);

            return redirect()->route('product.index')->with('success', 'Product created successfully!');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            foreach ($webpPaths as $path) {
                $fullWebpPath = str_replace('storage/', 'public/', $path);
                if (Storage::exists($fullWebpPath)) {
                    Storage::delete($fullWebpPath);
                }
            }

            Log::error('Database error during product creation', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
            ]);

            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique constraint') !== false) {
                return redirect()->back()->withInput()->withErrors(['error' => 'A product with this ID, SKU, or slug already exists. Check database sequence.']);
            }

            return redirect()->back()->withInput()->withErrors(['error' => 'Database error occurred. Please try again.']);
        } catch (\Exception $e) {
            DB::rollBack();

            foreach ($webpPaths as $path) {
                $fullWebpPath = str_replace('storage/', 'public/', $path);
                if (Storage::exists($fullWebpPath)) {
                    Storage::delete($fullWebpPath);
                }
            }

            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->except(['_token']),
            ]);

            return redirect()->back()->withInput()->withErrors(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
        }
    }

    private function handleVariants(Request $request, Product $product)
    {
        $variantOptions = $request->input('variant_options', []);
        $variantsData = $request->input('variants', []);

        if (empty($variantsData)) {
            throw new \Exception('No variant data provided');
        }

        Log::info('Processing variants for product', [
            'product_id' => $product->id,
            'variants_count' => count($variantsData),
            'has_variant_options' => !empty($variantOptions)
        ]);

    foreach ($variantsData as $index => $variantData) {
            if (empty($variantData['sku']) || empty($variantData['price']) || !isset($variantData['stock']) || empty($variantData['images'])) {
                throw new \Exception("Missing required data for variant at index {$index}");
            }

            $webpPaths = [];
            $rawPaths = array_filter(array_map('trim', explode(',', $variantData['images'])));

            foreach ($rawPaths as $imgIndex => $url) {
                try {
                    $parsed = parse_url($url, PHP_URL_PATH);
                    $publicPath = ltrim(str_replace('/storage/', '', $parsed), '/');
                    $fullPath = storage_path("app/public/{$publicPath}");

                    if (file_exists($fullPath)) {
                        $image = Image::make($fullPath)->encode('webp', 75);
                        $webpFilename = 'variant_' . uniqid() . "_{$imgIndex}.webp";
                        $webpPath = "public/products/variants/{$webpFilename}";
                        Storage::put($webpPath, (string) $image);
                        $webpPaths[] = "products/variants/{$webpFilename}";
                    } else {
                        Log::warning("Variant image not found: {$fullPath}");
                    }
                } catch (\Exception $e) {
                    Log::error("Variant image processing failed", ['url' => $url, 'error' => $e->getMessage()]);
                }
            }

            if (empty($webpPaths)) {
                throw new \Exception("Failed to process images for variant at index {$index}");
            }

            // Build variant_values array and option IDs for assignment
            $variantValues = [];
            $optionIdsForAssignment = [];

            // PRIORITY 1: Use explicit option_ids from frontend if provided (from preview)
            if (!empty($variantData['option_ids']) && is_array($variantData['option_ids'])) {
                $optionIdsForAssignment = $variantData['option_ids'];
                
                // Build variant_values map (typeId => optionId) from these option IDs
                foreach ($optionIdsForAssignment as $optionId) {
                    $option = ProductVariantOption::with('variantType')->find($optionId);
                    if ($option && $option->variantType) {
                        $variantValues[$option->variantType->id] = $optionId;
                    }
                }
                
                Log::debug('Using explicit option_ids from frontend', [
                    'index' => $index,
                    'option_ids' => $optionIdsForAssignment,
                    'variant_values' => $variantValues
                ]);
            }
            // FALLBACK: Try SKU matching if option_ids not provided
            elseif (!empty($variantOptions)) {
                foreach ($variantOptions as $typeId => $optionIds) {
                    foreach ($optionIds as $optionId) {
                        $option = ProductVariantOption::find($optionId);
                        if ($option && stripos($variantData['sku'], strtoupper($option->display_value)) !== false) {
                            $variantValues[$typeId] = $optionId;
                            $optionIdsForAssignment[] = $optionId;
                            break;
                        }
                    }
                }
                
                Log::debug('Using SKU matching fallback', [
                    'index' => $index,
                    'sku' => $variantData['sku'],
                    'matched_option_ids' => $optionIdsForAssignment
                ]);
            }

            // Build human-friendly display name from selected option IDs
            $displayName = $this->buildVariantDisplayName($optionIdsForAssignment);

            $variant = $product->variants()->create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'price' => $variantData['price'],
                'discount' => $variantData['discount'] ?? null,
                'stock' => $variantData['stock'],
                'display_name' => $displayName ?: $variantData['sku'],
                'variant_values' => $variantValues, // array cast to JSON automatically
                'status' => 'active',
            ]);

            // Create option assignments
            foreach ($optionIdsForAssignment as $optionId) {
                $variant->optionAssignments()->create([
                    'product_variant_option_id' => $optionId
                ]);
            }

            foreach ($webpPaths as $imgIndex => $path) {
                VariantImage::create([
                    'product_variant_id' => $variant->id,
                    'image_path' => $path,
                    'thumbnail_path' => null,
                    'is_primary' => $imgIndex === 0,
                    'sort_order' => $imgIndex + 1,
                ]);
            }
        }

        if (class_exists('App\Helpers\RedisHelper')) {
            RedisHelper::put("product_variants:{$product->id}", null, 0);
        }
    }

    private function generateCombinations(array $selections): array
    {
        $optionsByType = [];
        $typeIdToSortOrder = [];

        // Load all selected options from database WITH variant type info for proper ordering
        foreach ($selections as $typeId => $optionIds) {
            if (empty($optionIds)) {
                continue;
            }

            $typeOptions = ProductVariantOption::whereIn('id', $optionIds)
                ->with('variantType:id,name,display_name,sort_order')
                ->get()
                ->map(function($opt) {
                    return [
                        'id' => $opt->id,
                        'display_value' => $opt->display_value,
                        'variant_type_id' => $opt->variant_type_id,
                        'type_name' => $opt->variantType->name ?? '',
                        'type_sort_order' => $opt->variantType->sort_order ?? 999
                    ];
                })
                ->toArray();

            if (!empty($typeOptions)) {
                // Store type info for proper ordering
                if (isset($typeOptions[0])) {
                    $typeIdToSortOrder[$typeId] = $typeOptions[0]['type_sort_order'];
                }

                $optionsByType[$typeId] = $typeOptions;
            }
        }

        if (empty($optionsByType)) {
            throw new \Exception('No valid variant options found');
        }

        // Sort types by sort_order to ensure consistent SKU format
        // Color (1) -> Size (2) -> Storage (3) -> RAM (4) -> Screen Size (5)
        uasort($optionsByType, function($a, $b) {
            $sortA = $a[0]['type_sort_order'] ?? 999;
            $sortB = $b[0]['type_sort_order'] ?? 999;
            return $sortA <=> $sortB;
        });

        // Generate cartesian product with proper structure
        $combinations = [['values' => [], 'display_values' => [], 'option_ids' => []]];

        foreach ($optionsByType as $typeId => $typeOptions) {
            $newCombs = [];
            foreach ($combinations as $comb) {
                foreach ($typeOptions as $option) {
                    $newComb = $comb;
                    $newComb['values'][$typeId] = $option['id'];
                    $newComb['display_values'][] = $option['display_value'];
                    $newComb['option_ids'][] = $option['id'];
                    $newCombs[] = $newComb;
                }
            }
            $combinations = $newCombs;
        }

        // Don't pre-generate SKUs here - they will be generated in previewVariants()
        // with the correct sequential index that accounts for existing variants

        return $combinations;
    }

    public function edit($id)
    {
        $product = Product::with([
            'images',
            'variants.images',
            'variants.variantOptions.variantType',   // keep the old eager load if you need it elsewhere
        ])->findOrFail($id);

        // NEW – load **all** types with the “selected” flag
        $product->loadVariantTypes();

        // Regenerate display names for all variants to ensure they're in the correct format
        // This fixes any variants that were saved with old display name formats
        foreach ($product->variants as $variant) {
            $assignedOptionIds = $variant->optionAssignments()->pluck('product_variant_option_id')->toArray();
            
            // If no option assignments, try to derive from variant_values
            if (empty($assignedOptionIds) && is_array($variant->variant_values)) {
                $assignedOptionIds = array_values(array_filter($variant->variant_values, fn($v) => is_numeric($v)));
            }
            
            // Regenerate display name if we have option IDs
            if (!empty($assignedOptionIds)) {
                $newDisplayName = $this->buildVariantDisplayName($assignedOptionIds);
                if ($newDisplayName && $newDisplayName !== $variant->display_name) {
                    $variant->display_name = $newDisplayName;
                    $variant->save();
                    Log::info('Regenerated display name for variant on edit load', [
                        'variant_id' => $variant->id,
                        'old_name' => $variant->getOriginal('display_name'),
                        'new_name' => $newDisplayName
                    ]);
                }
            }
        }

        $brands       = Brand::all();
        $categories   = Category::all();
        $subcategories = Category::whereNotNull('parent_id')->get();

        return view('backend.product.edit', compact(
            'product',
            'brands',
            'categories',
            'subcategories'
        ));
    }

    public function update(Request $request, $id)
    {
        Log::info('Product update request received', [
            'product_id' => $id,
            'has_variants' => $request->has('has_variants'),
            'new_variants_count' => count($request->input('new_variants', [])),
            'existing_variants_count' => count($request->input('variants', [])),
            'variant_options_count' => !empty($request->input('variant_options')) ? count($request->input('variant_options')) : 0
        ]);

        $product = Product::with(['images', 'variants', 'variants.images'])->findOrFail($id);
        $requestData = $this->normalizeImageUrls($request->all());
        $request->merge($requestData);

        $rules = [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:products,slug,' . $id,
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'size' => 'nullable|array',
            'enable_alt_text' => 'nullable|boolean',
            'existing_alt_text' => 'nullable|array',
            'existing_alt_text.*' => 'nullable|string|max:125',
            'new_alt_text' => 'nullable|array',
            'new_alt_text.*' => 'nullable|string|max:125',
            'has_variants' => 'nullable|boolean',
        ];

        if (!$request->boolean('has_variants')) {
            $rules['base_price'] = 'required|numeric|min:0';
            $rules['base_discount'] = 'nullable|numeric|min:0|max:100';
            $rules['base_stock'] = 'required|integer|min:0';
            $rules['base_sku'] = 'required|string|max:255|unique:products,base_sku,' . $id;
            $rules['photo'] = $product->images->isEmpty() ? 'required|string' : 'nullable|string';
        } else {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.id'] = 'nullable|integer|exists:product_variants,id';
            $rules['variants.*.sku'] = 'required|string|max:255';
            $rules['variants.*.price'] = 'required|numeric|min:0';
            $rules['variants.*.discount'] = 'nullable|numeric|min:0|max:100';
            $rules['variants.*.stock'] = 'required|integer|min:0';
            // TEMPORARILY DISABLED FOR TESTING - Variant image validation
            // $rules['variants.*.images'] = ['required', 'string', function ($attribute, $value, $fail) {
            //     $urls = array_filter(array_map('trim', explode(',', $value)));
            //     foreach ($urls as $url) {
            //         $publicPath = ltrim($url, '/');
            //         $fullPath = storage_path("app/public/{$publicPath}");
            //         if (!file_exists($fullPath)) {
            //             $fail("The image file at {$url} does not exist.");
            //         }
            //         if (!Str::startsWith($publicPath, ['photos/', 'products/'])) {
            //             $fail("The image path {$url} is invalid. It must start with 'photos/' or 'products/'.");
            //         }
            //     }
            // }];
            $rules['new_variants'] = 'nullable|array';
            $rules['new_variants.*.sku'] = 'nullable|string|max:255|unique:product_variants,sku';
            $rules['new_variants.*.price'] = 'nullable|numeric|min:0';
            $rules['new_variants.*.discount'] = 'nullable|numeric|min:0|max:100';
            $rules['new_variants.*.stock'] = 'nullable|integer|min:0';
            // TEMPORARILY DISABLED FOR TESTING - New variant image validation
            // $rules['new_variants.*.images'] = ['nullable', 'string', function ($attribute, $value, $fail) {
            //     if ($value) {
            //         $urls = array_filter(array_map('trim', explode(',', $value)));
            //         foreach ($urls as $url) {
            //             $publicPath = ltrim($url, '/');
            //             $fullPath = storage_path("app/public/{$publicPath}");
            //             if (!file_exists($fullPath)) {
            //                 $fail("The image file at {$url} does not exist.");
            //             }
            //         }
            //     }
            // }];
            $rules['variant_options'] = 'nullable|array';
            $rules['variant_options.*'] = 'nullable|array';
        }

        $messages = [
            'title.required' => 'Product title is required.',
            'slug.required' => 'Product slug is required.',
            'slug.unique' => 'This slug is already taken.',
            'summary.required' => 'Product summary is required.',
            'cat_id.required' => 'Please select a category.',
            'status.required' => 'Please select a status.',
            'condition.required' => 'Please select a condition.',
            'base_price.required' => 'Price is required.',
            'base_stock.required' => 'Stock quantity is required.',
            'base_sku.required' => 'SKU is required.',
            'base_sku.unique' => 'This SKU is already in use.',
            'photo.required' => 'At least one product image is required.',
            'variants.required' => 'Please keep or generate at least one variant.',
            'existing_alt_text.*.max' => 'Alt text cannot exceed 125 characters.',
            'new_alt_text.*.max' => 'Alt text cannot exceed 125 characters.',
            'new_variants.*.sku.unique' => 'This variant SKU is already in use.',
        ];

        $validatedData = $request->validate($rules, $messages);
        Log::debug('Product update - validation passed', ['product_id' => $id, 'has_variants' => $request->boolean('has_variants')]);

        DB::beginTransaction();

        try {
            $productData = [
                'title' => $validatedData['title'],
                'slug' => $validatedData['slug'],
                'summary' => $validatedData['summary'],
                'description' => $validatedData['description'] ?? null,
                'cat_id' => $validatedData['cat_id'],
                'child_cat_id' => $validatedData['child_cat_id'] ?? null,
                'brand_id' => $validatedData['brand_id'] ?? null,
                'is_featured' => $request->boolean('is_featured'),
                'status' => $validatedData['status'],
                'condition' => $validatedData['condition'],
                'size' => $request->has('size') ? implode(',', $validatedData['size']) : 'M',
                'has_variants' => $request->boolean('has_variants'),
            ];

            if (!$request->boolean('has_variants')) {
                $productData['base_price'] = $validatedData['base_price'];
                $productData['base_discount'] = $validatedData['base_discount'] ?? null;
                $productData['base_stock'] = $validatedData['base_stock'];
                $productData['base_sku'] = $validatedData['base_sku'];
            }

            $product->update($productData);
            Log::info('Product updated', ['product_id' => $id]);

            if (!$request->boolean('has_variants')) {
                $this->updateNonVariantImages($request, $product, $validatedData);

                if ($product->getOriginal('has_variants')) {
                    foreach ($product->variants as $variant) {
                        foreach ($variant->images as $image) {
                            Storage::delete('public/' . $image->image_path);
                        }
                        $variant->delete();
                    }
                }
            } else {
                if (!$product->getOriginal('has_variants')) {
                    foreach ($product->images as $image) {
                        Storage::delete('public/' . $image->image_path);
                    }
                    ProductImage::where('product_id', $product->id)->delete();
                }

                $this->updateVariants($request, $product, $validatedData);

                // Sync variant type selections (track which types are active for this product)
                if (!empty($validatedData['variant_options'])) {
                    $this->syncVariantTypeSelections($product, $validatedData['variant_options']);
                }
            }

            DB::commit();
            Log::info('Product update completed', ['product_id' => $id]);

            return redirect()->route('product.index')->with('success', 'Product updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Product update failed', ['product_id' => $id, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return redirect()->back()->withInput()->withErrors(['error' => 'Update failed: ' . $e->getMessage()]);
        }
    }

    private function normalizeImageUrls(array $requestData): array
    {
        $baseUrl = config('app.url') . '/storage/';

        if (isset($requestData['variants']) && is_array($requestData['variants'])) {
            foreach ($requestData['variants'] as $index => $variant) {
                if (isset($variant['images']) && is_string($variant['images'])) {
                    $imageUrls = array_filter(array_map('trim', explode(',', $variant['images'])));
                    $normalizedUrls = array_map(fn($url) => strpos($url, $baseUrl) === 0 ? ltrim(str_replace($baseUrl, '', $url), '/') : ltrim($url, '/'), $imageUrls);
                    $requestData['variants'][$index]['images'] = implode(',', $normalizedUrls);
                    Log::debug('Normalized variant images', ['variant_index' => $index, 'original' => $variant['images'], 'normalized' => $requestData['variants'][$index]['images']]);
                }
            }
        }

        if (isset($requestData['new_variants']) && is_array($requestData['new_variants'])) {
            foreach ($requestData['new_variants'] as $index => $variant) {
                if (isset($variant['images']) && is_string($variant['images'])) {
                    $imageUrls = array_filter(array_map('trim', explode(',', $variant['images'])));
                    $normalizedUrls = array_map(fn($url) => strpos($url, $baseUrl) === 0 ? ltrim(str_replace($baseUrl, '', $url), '/') : ltrim($url, '/'), $imageUrls);
                    $requestData['new_variants'][$index]['images'] = implode(',', $normalizedUrls);
                    Log::debug('Normalized new variant images', ['new_variant_index' => $index, 'original' => $variant['images'], 'normalized' => $requestData['new_variants'][$index]['images']]);
                }
            }
        }

        return $requestData;
    }

    private function updateNonVariantImages(Request $request, Product $product, array $validatedData)
    {
        $enableAltText = $request->boolean('enable_alt_text');
        $newPhotoPaths = [];
        $newPhotoInputs = [];

        if (!empty($validatedData['photo'])) {
            $allPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
            $existingImagePaths = $product->images()->pluck('image_path')->toArray();

            foreach ($allPaths as $index => $url) {
                $isExisting = false;
                foreach ($existingImagePaths as $existingPath) {
                    if (strpos($url, basename($existingPath)) !== false) {
                        $isExisting = true;
                        break;
                    }
                }

                if ($isExisting) continue;

                try {
                    $parsed = parse_url($url, PHP_URL_PATH);
                    $publicPath = ltrim(str_replace('/storage/', '', $parsed), '/');
                    $fullPath = storage_path("app/public/{$publicPath}");

                    if (file_exists($fullPath)) {
                        $image = Image::make($fullPath)->encode('webp', 75);
                        $webpFilename = 'product_' . uniqid() . "_{$index}.webp";
                        $webpPath = "public/products/{$webpFilename}";
                        Storage::put($webpPath, (string) $image);
                        $newPhotoPaths[] = "products/{$webpFilename}";
                        $newPhotoInputs[] = $index;
                        Log::info('Image converted to webp', ['webp_path' => $webpPath]);
                    } else {
                        Log::warning('Source image not found', ['path' => $fullPath]);
                    }
                } catch (\Exception $e) {
                    Log::error('Image processing failed', ['url' => $url, 'error' => $e->getMessage()]);
                }
            }
        }

        if (!empty($newPhotoPaths)) {
            $newAltTexts = $request->input('new_alt_text', []);
            $existingImageCount = $product->images()->count();

            foreach ($newPhotoPaths as $dbIndex => $path) {
                $altText = null;
                $originalIndex = $newPhotoInputs[$dbIndex] ?? $dbIndex;

                if ($enableAltText && isset($newAltTexts[$originalIndex]) && !empty(trim($newAltTexts[$originalIndex]))) {
                    $altText = trim($newAltTexts[$originalIndex]);
                } elseif ($enableAltText) {
                    $altText = $validatedData['title'] . ' - Image ' . ($existingImageCount + $dbIndex + 1);
                }

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'alt_text' => $altText,
                    'is_primary' => false,
                    'sort_order' => $existingImageCount + $dbIndex + 1,
                ]);

                Log::info('New image added', ['product_id' => $product->id, 'path' => $path]);
            }
        }

        if ($enableAltText && $request->has('existing_alt_text')) {
            foreach ($request->input('existing_alt_text', []) as $imageId => $altText) {
                ProductImage::where('id', $imageId)->where('product_id', $product->id)->update(['alt_text' => trim($altText) ?: null]);
            }
            Log::info('Alt text updated', ['product_id' => $product->id]);
        } elseif (!$enableAltText) {
            ProductImage::where('product_id', $product->id)->update(['alt_text' => null]);
        }
    }

    private function updateVariants(Request $request, Product $product, array $validatedData)
    {
        // Use validated data instead of raw request input
        $existingVariantsData = $validatedData['variants'] ?? [];
        // IMPORTANT: pull new_variants from raw request to preserve hidden option_ids[]
        $newVariantsData = $request->input('new_variants', []);
        $variantOptions = $validatedData['variant_options'] ?? [];

        Log::debug('Handling variants', ['product_id' => $product->id, 'existing_count' => count($existingVariantsData), 'new_count' => count($newVariantsData)]);

    foreach ($existingVariantsData as $index => $variantData) {
            if (empty($variantData['id'])) {
                Log::warning('Skipping variant - missing ID', ['product_id' => $product->id, 'variant_index' => $index]);
                continue;
            }

            $variant = ProductVariant::find($variantData['id']);
            if (!$variant || $variant->product_id !== $product->id) {
                Log::warning('Invalid variant ID', ['product_id' => $product->id, 'variant_id' => $variantData['id']]);
                continue;
            }

            // Check if the new SKU conflicts with another variant (excluding this one)
            if (!empty($variantData['sku']) && $variantData['sku'] !== $variant->sku) {
                $skuConflict = ProductVariant::where('sku', $variantData['sku'])
                    ->where('id', '!=', $variant->id)
                    ->first();

                if ($skuConflict) {
                    Log::warning('Skipping variant update - SKU already in use', [
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'attempted_sku' => $variantData['sku'],
                        'conflict_variant_id' => $skuConflict->id,
                        'conflict_product_id' => $skuConflict->product_id
                    ]);
                    continue;
                }
            }

            $variant->update([
                'sku' => $variantData['sku'],
                'price' => $variantData['price'],
                'discount' => $variantData['discount'] ?? null,
                'stock' => $variantData['stock'],
            ]);

            // Ensure option assignments are complete – repair from variant_values if partial
            if (is_array($variant->variant_values) && !empty($variant->variant_values)) {
                $existingAssignedIds = $variant->optionAssignments()->pluck('product_variant_option_id')->toArray();
                $allValueIds = array_values(array_filter($variant->variant_values, fn($v) => is_numeric($v)));
                $missingIds = array_diff($allValueIds, $existingAssignedIds);
                foreach ($missingIds as $missingId) {
                    try {
                        $variant->optionAssignments()->create(['product_variant_option_id' => (int)$missingId]);
                        Log::debug('Repaired missing option assignment for existing variant', [
                            'variant_id' => $variant->id,
                            'option_id' => $missingId
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed repairing option assignment', [
                            'variant_id' => $variant->id,
                            'option_id' => $missingId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Always (re)generate display_name from current option assignments to prevent stale or partial labels
            $assignedOptionIds = $variant->optionAssignments()->pluck('product_variant_option_id')->toArray();
            // Fallback / augmentation: derive option IDs from stored variant_values structure (typeId => optionId)
            if (is_array($variant->variant_values) && !empty($variant->variant_values)) {
                $candidateIds = array_values(array_filter($variant->variant_values, fn($v) => is_numeric($v)));
                // If none assigned OR partial (e.g., only Color), merge missing ones
                $missingFromAssignments = array_diff($candidateIds, $assignedOptionIds);
                if (empty($assignedOptionIds) || !empty($missingFromAssignments)) {
                    $assignedOptionIds = array_values(array_unique(array_merge($assignedOptionIds, $candidateIds)));
                    Log::debug('Augmented option IDs from variant_values for existing variant', [
                        'variant_id' => $variant->id,
                        'option_ids_final' => $assignedOptionIds
                    ]);
                }
            }
            if (!empty($assignedOptionIds)) {
                $variant->display_name = $this->buildVariantDisplayName($assignedOptionIds);
                $variant->save();
            } else {
                Log::warning('Could not determine option IDs to build display_name for existing variant', [
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku
                ]);
            }

            // DO NOT re-sync option assignments for existing variants
            // The assignments are already correct in the database
            // Re-syncing with SKU matching causes incorrect assignments
            // Only sync if variant has no option assignments at all (edge case)
            if ($variant->optionAssignments()->count() === 0 && !empty($variantOptions)) {
                Log::debug('Variant has no option assignments, syncing from SKU', [
                    'variant_id' => $variant->id,
                    'sku' => $variantData['sku']
                ]);
                $this->syncVariantOptionAssignments($variant, $variantData['sku'], $variantOptions);
            } else {
                Log::debug('Skipping option assignment sync for existing variant (already has assignments)', [
                    'variant_id' => $variant->id,
                    'existing_assignments' => $variant->optionAssignments()->count()
                ]);
            }

            if (!empty($variantData['images'])) {
                Log::debug('Syncing variant images', ['variant_id' => $variant->id, 'images' => $variantData['images']]);
                $this->syncVariantImages($variant, $variantData['images']);
            } else {
                Log::warning('No images provided for variant', ['variant_id' => $variant->id]);
            }

            Log::info('Variant updated', ['variant_id' => $variant->id, 'sku' => $variant->sku]);
        }

        if (!empty($newVariantsData)) {
            // Generate all combinations from variant_options
            $combinations = !empty($variantOptions) ? $this->generateCombinations($variantOptions) : [];

            // Build a map of SKU => combination for matching
            $skuToCombinationMap = [];
            foreach ($combinations as $combo) {
                if (!isset($combo['sku'])) continue;
                $skuToCombinationMap[$combo['sku']] = $combo;
            }

            Log::debug('New variants processing', [
                'product_id' => $product->id,
                'new_variants_count' => count($newVariantsData),
                'combinations_count' => count($combinations),
                'variant_options_count' => count($variantOptions),
                'variant_options_types' => array_keys($variantOptions)
            ]);

            foreach ($newVariantsData as $index => $variantData) {
                if (empty($variantData['sku']) || empty($variantData['price']) || empty($variantData['images'])) {
                    Log::warning('Skipping new variant - missing required data', [
                        'product_id' => $product->id,
                        'variant_index' => $index,
                        'sku' => $variantData['sku'] ?? 'N/A',
                        'price' => $variantData['price'] ?? 'N/A',
                        'images' => !empty($variantData['images']) ? 'present' : 'missing'
                    ]);
                    continue;
                }

                // Check if SKU already exists in database
                $existingSku = ProductVariant::where('sku', $variantData['sku'])->first();
                if ($existingSku) {
                    Log::warning('Skipping new variant - SKU already exists', [
                        'product_id' => $product->id,
                        'variant_index' => $index,
                        'sku' => $variantData['sku'],
                        'existing_variant_id' => $existingSku->id,
                        'existing_product_id' => $existingSku->product_id
                    ]);
                    continue;
                }

                // Prefer explicit option_ids from the form (added by preview table)
                $explicitOptionIds = isset($variantData['option_ids']) && is_array($variantData['option_ids'])
                    ? array_filter(array_map('intval', $variantData['option_ids']))
                    : [];

                // Try to find matching combination by SKU (exact match)
                $matchingCombo = $skuToCombinationMap[$variantData['sku']] ?? null;

                // If no exact match, try fuzzy matching by comparing SKU parts
                if (!$matchingCombo) {
                    // Extract meaningful parts from the submitted SKU (remove common prefixes/suffixes)
                    $submittedSkuParts = array_map('strtoupper', array_filter(explode('-', $variantData['sku'])));

                    // Try to find a combination that has similar variant option values
                    foreach ($skuToCombinationMap as $comboSku => $combo) {
                        $comboSkuParts = array_map('strtoupper', array_filter(explode('-', $comboSku)));

                        // Check if all submitted SKU parts are in the combo SKU parts
                        $matchCount = 0;
                        foreach ($submittedSkuParts as $part) {
                            if (in_array($part, $comboSkuParts)) {
                                $matchCount++;
                            }
                        }

                        // If most parts match, consider it a match
                        if ($matchCount >= count($submittedSkuParts) * 0.8) {
                            $matchingCombo = $combo;
                            Log::info('Found fuzzy SKU match', [
                                'submitted_sku' => $variantData['sku'],
                                'matched_sku' => $comboSku,
                                'match_count' => $matchCount,
                                'total_parts' => count($submittedSkuParts)
                            ]);
                            break;
                        }
                    }
                }

                // Extract variant values and option IDs
                $variantValues = [];
                $variantOptionIds = $explicitOptionIds;

                if (empty($variantOptionIds) && $matchingCombo) {
                    $variantValues = $matchingCombo['values'] ?? [];
                    $variantOptionIds = $matchingCombo['option_ids'] ?? [];
                    Log::debug('Found matching combination', [
                        'sku' => $variantData['sku'],
                        'matched_sku' => $matchingCombo['sku'] ?? 'N/A',
                        'values' => $variantValues,
                        'option_ids' => $variantOptionIds
                    ]);
                } else {
                    // Fallback: Try to parse variant info from SKU or variant_options
                    Log::warning('No matching combination found for SKU, will use empty variant_values', [
                        'sku' => $variantData['sku'],
                        'available_skus' => array_keys($skuToCombinationMap)
                    ]);
                }

                // Create the variant (store variant_values as array; cast will handle)
                $variant = $product->variants()->create([
                    'product_id' => $product->id,
                    'sku' => $variantData['sku'],
                    'price' => $variantData['price'],
                    'discount' => $variantData['discount'] ?? null,
                    'stock' => $variantData['stock'] ?? 0,
                    'variant_values' => $variantValues,
                    'status' => 'active',
                ]);

                // Consolidate option IDs: explicit > matched combo > variant_values
                if (empty($variantOptionIds) && !empty($variantValues)) {
                    $variantOptionIds = array_values(array_filter($variantValues, fn($v) => is_numeric($v)));
                    Log::debug('Using variant_values to derive option IDs for new variant', [
                        'variant_id' => $variant->id,
                        'derived_option_ids' => $variantOptionIds
                    ]);
                }

                // Create option assignments from resolved option IDs
                if (!empty($variantOptionIds)) {
                    foreach ($variantOptionIds as $optionId) {
                        try {
                            $variant->optionAssignments()->create([
                                'product_variant_option_id' => $optionId
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Failed to create option assignment (new variant)', [
                                'variant_id' => $variant->id,
                                'option_id' => $optionId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } elseif (!empty($variantOptions)) {
                    // Final fallback: SKU matching
                    Log::debug('Fallback SKU matching for new variant (no explicit option IDs)', [
                        'variant_id' => $variant->id,
                        'sku' => $variantData['sku']
                    ]);
                    $this->syncVariantOptionAssignments($variant, $variantData['sku'], $variantOptions);
                }

                // Set / refresh display_name after assignments resolution
                $finalOptionIds = $variant->optionAssignments()->pluck('product_variant_option_id')->toArray();
                if (!empty($finalOptionIds)) {
                    $variant->display_name = $this->buildVariantDisplayName($finalOptionIds);
                    $variant->save();
                } else {
                    Log::warning('New variant has no option assignments for display_name', [
                        'variant_id' => $variant->id,
                        'sku' => $variant->sku
                    ]);
                }

                // Process images
                $imageUrls = array_filter(array_map('trim', explode(',', $variantData['images'])));
                if (!empty($imageUrls)) {
                    try {
                        $this->storeVariantImages($variant, $imageUrls);
                        Log::info('Variant images stored', [
                            'variant_id' => $variant->id,
                            'image_count' => count($imageUrls)
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to store variant images', [
                            'variant_id' => $variant->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                Log::info('New variant created successfully', [
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => $variant->price,
                    'stock' => $variant->stock
                ]);
            }
        }

        Log::info('Variants processed', ['product_id' => $product->id]);
    }

    private function syncVariantImages(ProductVariant $variant, string $imagesInput)
    {
        $allImageUrls = array_filter(array_map('trim', explode(',', $imagesInput)));
        $existingImages = $variant->images()->get()->keyBy('id')->toArray();
        $sortOrder = 1;
        $processedImages = [];

        Log::info('VARIANT IMAGE SYNC START', ['variant_id' => $variant->id, 'input_count' => count($allImageUrls), 'existing_count' => count($existingImages), 'input_urls' => $allImageUrls]);

        foreach ($allImageUrls as $index => $publicPath) {
            $publicPath = ltrim($publicPath, '/');
            $fullPath = storage_path("app/public/{$publicPath}");

            Log::debug('Processing image', ['variant_id' => $variant->id, 'index' => $index, 'public_path' => $publicPath, 'full_path' => $fullPath, 'file_exists' => file_exists($fullPath)]);

            if (!file_exists($fullPath)) {
                Log::warning('VARIANT IMAGE FILE NOT FOUND', ['variant_id' => $variant->id, 'public_path' => $publicPath, 'full_path' => $fullPath]);
                continue;
            }

            try {
                $image = Image::make($fullPath)->encode('webp', 75);
                $webpFilename = 'variant_' . uniqid() . '_' . $index . '.webp';
                $webpPath = "products/variants/{$webpFilename}";
                Storage::put("public/{$webpPath}", (string) $image);
                $processedImages[] = ['path' => $webpPath, 'is_primary' => ($sortOrder === 1), 'sort_order' => $sortOrder];
                Log::info('VARIANT IMAGE CONVERTED', ['variant_id' => $variant->id, 'original_path' => $publicPath, 'webp_path' => $webpPath, 'sort_order' => $sortOrder, 'is_primary' => ($sortOrder === 1)]);
                $sortOrder++;
            } catch (\Exception $e) {
                Log::error('VARIANT IMAGE CONVERSION FAILED', ['variant_id' => $variant->id, 'public_path' => $publicPath, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
                continue;
            }
        }

        if (empty($processedImages)) {
            Log::error('NO IMAGES PROCESSED', ['variant_id' => $variant->id, 'input_urls' => $allImageUrls]);
            throw new \Exception('No valid images could be processed for variant ID ' . $variant->id);
        }

        Log::info('DELETING OLD VARIANT IMAGES', ['variant_id' => $variant->id, 'old_image_count' => count($existingImages)]);

        foreach ($existingImages as $image) {
            if (Storage::exists("public/{$image['image_path']}")) {
                Storage::delete("public/{$image['image_path']}");
                Log::info('OLD IMAGE DELETED FROM STORAGE', ['variant_id' => $variant->id, 'image_id' => $image['id'], 'path' => $image['image_path']]);
            }
            VariantImage::where('id', $image['id'])->delete();
            Log::info('OLD IMAGE DELETED FROM DATABASE', ['variant_id' => $variant->id, 'image_id' => $image['id']]);
        }

        Log::info('CREATING NEW VARIANT IMAGE RECORDS', ['variant_id' => $variant->id, 'new_image_count' => count($processedImages)]);

        foreach ($processedImages as $imageData) {
            $created = VariantImage::create([
                'product_variant_id' => $variant->id,
                'image_path' => $imageData['path'],
                'thumbnail_path' => null,
                'is_primary' => $imageData['is_primary'],
                'sort_order' => $imageData['sort_order'],
            ]);

            Log::info('NEW VARIANT IMAGE CREATED', ['variant_id' => $variant->id, 'image_id' => $created->id, 'path' => $imageData['path'], 'is_primary' => $imageData['is_primary'], 'sort_order' => $imageData['sort_order']]);
        }

        if (class_exists('App\Helpers\RedisHelper')) {
            RedisHelper::put("product_variants:{$variant->product_id}", null, 0);
            Log::info('Redis cache invalidated', ['cache_key' => "product_variants:{$variant->product_id}"]);
        }

        Log::info('VARIANT IMAGE SYNC COMPLETE', ['variant_id' => $variant->id, 'final_image_count' => count($processedImages)]);
    }

    private function storeVariantImages(ProductVariant $variant, array $imageUrls)
    {
        $webpPaths = [];

        Log::info('STORING NEW VARIANT IMAGES', ['variant_id' => $variant->id, 'image_count' => count($imageUrls), 'image_urls' => $imageUrls]);

        foreach ($imageUrls as $imgIndex => $publicPath) {
            $publicPath = ltrim($publicPath, '/');
            $fullPath = storage_path("app/public/{$publicPath}");

            Log::debug('Processing new variant image', ['variant_id' => $variant->id, 'index' => $imgIndex, 'public_path' => $publicPath, 'full_path' => $fullPath, 'file_exists' => file_exists($fullPath)]);

            try {
                if (file_exists($fullPath)) {
                    $image = Image::make($fullPath)->encode('webp', 75);
                    $webpFilename = 'variant_' . uniqid() . "_{$imgIndex}.webp";
                    $webpPath = "public/products/variants/{$webpFilename}";
                    Storage::put($webpPath, (string) $image);
                    $webpPaths[] = ['path' => "products/variants/{$webpFilename}", 'is_primary' => $imgIndex === 0];
                    Log::info('NEW VARIANT IMAGE CONVERTED', ['variant_id' => $variant->id, 'original_path' => $publicPath, 'webp_path' => $webpPath, 'is_primary' => $imgIndex === 0]);
                } else {
                    Log::warning('NEW VARIANT IMAGE FILE NOT FOUND', ['variant_id' => $variant->id, 'public_path' => $publicPath, 'full_path' => $fullPath]);
                }
            } catch (\Exception $e) {
                Log::error('NEW VARIANT IMAGE PROCESSING FAILED', ['variant_id' => $variant->id, 'public_path' => $publicPath, 'error' => $e->getMessage()]);
            }
        }

        foreach ($webpPaths as $imgIndex => $imageData) {
            $created = VariantImage::create([
                'product_variant_id' => $variant->id,
                'image_path' => $imageData['path'],
                'is_primary' => $imageData['is_primary'],
                'sort_order' => $imgIndex + 1,
            ]);

            Log::info('NEW VARIANT IMAGE RECORD CREATED', ['variant_id' => $variant->id, 'image_id' => $created->id, 'path' => $imageData['path'], 'is_primary' => $imageData['is_primary'], 'sort_order' => $imgIndex + 1]);
        }

        Log::info('NEW VARIANT IMAGES STORED', ['variant_id' => $variant->id, 'stored_count' => count($webpPaths)]);
    }

    /**
     * Sync variant option assignments by matching SKU against variant_options
     * This populates the product_variant_option_assignments table
     */
    private function syncVariantOptionAssignments(ProductVariant $variant, string $sku, array $variantOptions)
    {
        // Get all option IDs from variant_options array
        $allOptionIds = [];
        foreach ($variantOptions as $typeId => $optionIds) {
            $allOptionIds = array_merge($allOptionIds, $optionIds);
        }

        if (empty($allOptionIds)) {
            Log::debug('No variant options to sync', ['variant_id' => $variant->id]);
            return;
        }

        // Load options with their types for better matching
        $options = ProductVariantOption::with('variantType:id,name,display_name,sort_order')
            ->whereIn('id', $allOptionIds)
            ->get();

        // Group options by type
        $optionsByType = $options->groupBy('product_variant_type_id');

        // Match SKU against option display values
        // For each type, select only ONE matching option (the best match)
        $matchedOptionIds = [];
        $skuUpper = strtoupper($sku);

        foreach ($optionsByType as $typeId => $typeOptions) {
            $bestMatch = null;
            $bestMatchScore = 0;

            foreach ($typeOptions as $option) {
                $optionValue = strtoupper($option->display_value);
                $score = 0;

                // Exact match in SKU (highest priority)
                if (strpos($skuUpper, $optionValue) !== false) {
                    $score = strlen($optionValue) * 10; // Longer matches get higher score
                }
                // Match without spaces
                elseif (strpos($skuUpper, str_replace(' ', '', $optionValue)) !== false) {
                    $score = strlen($optionValue) * 8;
                }
                // Match with hyphens instead of spaces
                elseif (strpos($skuUpper, str_replace(' ', '-', $optionValue)) !== false) {
                    $score = strlen($optionValue) * 8;
                }
                // Abbreviation match (first 3+ chars) - lowest priority
                elseif (strlen($optionValue) >= 3) {
                    $prefix = substr($optionValue, 0, min(4, strlen($optionValue)));
                    if (strpos($skuUpper, $prefix) !== false) {
                        $score = strlen($prefix) * 2;
                    }
                }

                // Keep track of best match for this type
                if ($score > $bestMatchScore) {
                    $bestMatchScore = $score;
                    $bestMatch = $option;
                }
            }

            // Add the best matching option for this type
            if ($bestMatch) {
                $matchedOptionIds[] = $bestMatch->id;
                Log::debug('Matched option for type', [
                    'variant_id' => $variant->id,
                    'type_id' => $typeId,
                    'type_name' => $bestMatch->variantType->display_name ?? 'Unknown',
                    'option_id' => $bestMatch->id,
                    'option_value' => $bestMatch->display_value,
                    'score' => $bestMatchScore
                ]);
            } else {
                Log::warning('No match found for variant type', [
                    'variant_id' => $variant->id,
                    'type_id' => $typeId,
                    'sku' => $sku,
                    'available_options' => $typeOptions->pluck('display_value')->toArray()
                ]);
            }
        }

        if (empty($matchedOptionIds)) {
            Log::warning('No matching options found for variant SKU', [
                'variant_id' => $variant->id,
                'sku' => $sku,
                'available_option_ids' => $allOptionIds
            ]);
            return;
        }

        // Delete existing assignments
        $variant->optionAssignments()->delete();

        // Create new assignments
        foreach ($matchedOptionIds as $optionId) {
            try {
                $variant->optionAssignments()->create([
                    'product_variant_option_id' => $optionId
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to create option assignment', [
                    'variant_id' => $variant->id,
                    'option_id' => $optionId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Variant option assignments synced', [
            'variant_id' => $variant->id,
            'matched_option_ids' => $matchedOptionIds,
            'assignment_count' => count($matchedOptionIds)
        ]);
    }

    /**
     * Sync product variant type selections
     * Stores which variant types are active for this product in product_variant_type_selections table
     */
    private function syncVariantTypeSelections(Product $product, array $variantOptions)
    {
        // Get all variant type IDs from the variant_options array
        // variant_options format: ['type_id' => [option_id1, option_id2], ...]
        $variantTypeIds = array_keys($variantOptions);

        if (empty($variantTypeIds)) {
            Log::debug('No variant types to sync', ['product_id' => $product->id]);
            return;
        }

        // Get current selections
        $currentTypeIds = ProductVariantTypeSelection::where('product_id', $product->id)
            ->pluck('product_variant_type_id')
            ->toArray();

        // Determine what to add and what to remove
        $typesToAdd = array_diff($variantTypeIds, $currentTypeIds);
        $typesToRemove = array_diff($currentTypeIds, $variantTypeIds);

        // Remove types no longer selected
        if (!empty($typesToRemove)) {
            ProductVariantTypeSelection::where('product_id', $product->id)
                ->whereIn('product_variant_type_id', $typesToRemove)
                ->delete();
        }

        // Add new types
        foreach ($typesToAdd as $typeId) {
            ProductVariantTypeSelection::create([
                'product_id' => $product->id,
                'product_variant_type_id' => $typeId,
            ]);
        }

        Log::info('Variant type selections synced', [
            'product_id' => $product->id,
            'active_type_ids' => $variantTypeIds,
            'added_count' => count($typesToAdd),
            'removed_count' => count($typesToRemove)
        ]);
    }

    public function deleteVariant($variantId)
    {
        try {
            $variant = ProductVariant::findOrFail($variantId);

            Log::info('DELETING VARIANT', ['variant_id' => $variantId, 'sku' => $variant->sku]);

            // Delete all associated images first
            foreach ($variant->images as $image) {
                if (Storage::exists('public/' . $image->image_path)) {
                    Storage::delete('public/' . $image->image_path);
                }
                $image->delete();
            }

            // Delete the variant itself
            $variant->delete();

            Log::info('VARIANT DELETED SUCCESSFULLY', ['variant_id' => $variantId]);

            return response()->json(['success' => true, 'message' => 'Variant deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('VARIANT DELETION FAILED', ['variant_id' => $variantId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete variant: ' . $e->getMessage()], 400);
        }
    }

    public function deleteVariantImage($variantId, $imageId)
    {
        try {
            $variant = ProductVariant::findOrFail($variantId);
            $image = VariantImage::where('id', $imageId)->where('product_variant_id', $variantId)->firstOrFail();

            Log::info('DELETING VARIANT IMAGE', ['variant_id' => $variantId, 'image_id' => $imageId, 'image_path' => $image->image_path]);

            if (Storage::exists('public/' . $image->image_path)) {
                Storage::delete('public/' . $image->image_path);
                Log::info('IMAGE FILE DELETED FROM STORAGE', ['variant_id' => $variantId, 'image_id' => $imageId, 'path' => $image->image_path]);
            }

            $image->delete();
            Log::info('IMAGE RECORD DELETED FROM DATABASE', ['variant_id' => $variantId, 'image_id' => $imageId]);

            return response()->json(['success' => true, 'message' => 'Variant image deleted successfully.']);
        } catch (\Exception $e) {
            Log::error('VARIANT IMAGE DELETION FAILED', ['variant_id' => $variantId, 'image_id' => $imageId, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to delete variant image: ' . $e->getMessage()], 400);
        }
    }

    public function previewVariants(Request $request)
    {
        $request->validate([
            'selections' => 'required|array',
            'selections.*' => 'required|array|min:1',
            'product_id' => 'nullable|exists:products,id'
        ]);

        $selections = $request->input('selections');
        $productId = $request->input('product_id');
        $basePrice = null;
        $existingVariants = [];

        // Get base price and existing variants from product if available
        if ($productId) {
            $product = Product::with('variants')->find($productId);
            if ($product) {
                $basePrice = $product->base_price;

                // Build a map of existing variants by their option combination
                foreach ($product->variants as $variant) {
                    // Get the variant's option IDs for matching
                    $optionIds = $variant->variantOptions->pluck('id')->sort()->values()->toArray();
                    $key = implode('-', $optionIds);

                    $existingVariants[$key] = [
                        'id' => $variant->id,
                        'name' => $variant->display_name,
                        'sku' => $variant->sku,  // ✅ Use ACTUAL SKU from database
                        'price' => $variant->price,
                        'discount' => $variant->discount,
                        'stock' => $variant->stock,
                    ];
                }
            }
        }

        Log::info('Preview variants request', [
            'selections' => $selections,
            'product_id' => $productId,
            'base_price' => $basePrice,
            'existing_variants_count' => count($existingVariants)
        ]);

        try {
            // Generate all combinations
            $combinations = $this->generateCombinations($selections);

            Log::info('Generated combinations', [
                'count' => count($combinations),
                'sample' => array_slice($combinations, 0, 3)
            ]);

            // Calculate the starting index for new variants (continue sequence from existing variants)
            $maxExistingIndex = -1;
            if ($productId && $product) {
                foreach ($product->variants as $variant) {
                    // Extract index from SKU (last number after last dash)
                    $skuParts = explode('-', $variant->sku);
                    $lastPart = end($skuParts);
                    if (is_numeric($lastPart)) {
                        $maxExistingIndex = max($maxExistingIndex, (int)$lastPart);
                    }
                }
            }
            $nextVariantIndex = $maxExistingIndex + 1;

            Log::info('Variant indexing', [
                'max_existing_index' => $maxExistingIndex,
                'next_index' => $nextVariantIndex
            ]);

            $variants = [];

            foreach ($combinations as $idx => $combo) {
                // Build variant name from display_values (already in correct sorted order)
                $displayValues = $combo['display_values'] ?? [];
                $optionIds = $combo['option_ids'] ?? [];

                // Load variant options with type information to build proper display name
                $options = ProductVariantOption::with('variantType:id,name,display_name,sort_order')
                    ->whereIn('id', $optionIds)
                    ->get()
                    ->sortBy(function($option) {
                        return $option->variantType->sort_order ?? 999;
                    });

                // Build display name with type name and option value
                // Format: "Color: Red / Storage: 64GB / RAM: 8GB"
                $nameParts = $options->map(function($option) {
                    $typeName = $option->variantType->display_name ?? $option->variantType->name ?? 'Option';
                    $optionValue = $option->display_value ?? $option->value;
                    return $typeName . ': ' . $optionValue;
                });

                $name = $nameParts->join(' / ');

                // Create a key from option IDs to match against existing variants
                $sortedOptionIds = $optionIds;
                sort($sortedOptionIds);
                $comboKey = implode('-', $sortedOptionIds);

                // Check if this combination already exists in database
                if (isset($existingVariants[$comboKey])) {
                    // ✅ Use existing variant data with ACTUAL manually updated SKU
                    $existingData = $existingVariants[$comboKey];
                    $variantData = [
                        'name' => $name,
                        'sku' => $existingData['sku'],  // ✅ CRITICAL: Use actual SKU from database, not auto-generated
                        'price' => $existingData['price'],
                        'discount' => $existingData['discount'],
                        'stock' => $existingData['stock'],
                        'images' => '',
                        'option_ids' => $optionIds,
                        'existing' => true,  // Flag to indicate this variant already exists
                        'variant_id' => $existingData['id']
                    ];

                    Log::debug('Using existing variant data', [
                        'index' => $idx,
                        'name' => $name,
                        'sku' => $existingData['sku'],
                        'variant_id' => $existingData['id']
                    ]);
                } else {
                    // Generate new SKU for truly new variants with sequential index
                    $sku = $this->generateVariantSKU($displayValues, $nextVariantIndex);
                    $nextVariantIndex++; // Increment for next new variant

                    $variantData = [
                        'name' => $name,
                        'sku' => $sku,
                        'price' => $basePrice ?? 0,
                        'discount' => null,
                        'stock' => 10, // Default stock
                        'images' => '',
                        'option_ids' => $optionIds,
                        'existing' => false
                    ];

                    Log::debug('New variant preview', [
                        'index' => $idx,
                        'name' => $name,
                        'sku' => $sku
                    ]);
                }

                $variants[] = $variantData;
            }

            Log::info('Preview variants completed', [
                'total_variants' => count($variants),
                'new_variants' => count(array_filter($variants, fn($v) => !($v['existing'] ?? false))),
                'existing_variants' => count(array_filter($variants, fn($v) => $v['existing'] ?? false))
            ]);

            return response()->json([
                'success' => true,
                'variants' => $variants,
                'count' => count($variants),
                'selections' => $selections // Return selections for frontend reference
            ]);
        } catch (\Exception $e) {
            Log::error('Preview variants failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate variant preview: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique SKU for a product variant
     *
     * This is the single unified method for SKU generation across the entire system.
     * Format: PART1-PART2-PART3-...-TIMESTAMP-INDEX
     *
     * @param array $displayValues Array of variant option display values (e.g., ['Red', 'Medium', '128GB'])
     *                             Must be pre-sorted by variant type sort_order
     * @param int $index The variant index number (0-based) for uniqueness
     * @param bool $checkUniqueness Optional: Check database for existing SKU and increment if collision
     * @return string Generated SKU
     *
     * @example
     * generateVariantSKU(['Red', 'Medium', '128GB', '4GB'], 0)
     * Returns: "RED-MEDIUM-128GB-4GB-5892-0"
     *
     * generateVariantSKU(['Blue', 'Large'], 5)
     * Returns: "BLUE-LARGE-5892-5"
     */
    protected function generateVariantSKU(array $displayValues, int $index = 0, bool $checkUniqueness = false): string
    {
        // Clean and format each display value for SKU
        $skuParts = [];
        foreach ($displayValues as $value) {
            // Remove special characters, keep only alphanumeric
            $cleaned = preg_replace('/[^a-zA-Z0-9]/', '', trim($value));

            // Convert to uppercase
            $cleaned = strtoupper($cleaned);

            // Truncate if too long (keep max 15 characters per part)
            if (strlen($cleaned) > 15) {
                $cleaned = substr($cleaned, 0, 15);
            }

            // Only add non-empty parts
            if ($cleaned) {
                $skuParts[] = $cleaned;
            }
        }

        // Add timestamp (last 4 digits) for uniqueness
        $timestamp = substr((string)time(), -4);

        // Build final SKU: PART1-PART2-PART3-TIMESTAMP-INDEX
        $sku = implode('-', $skuParts) . "-{$timestamp}-{$index}";

        // Optional: Check for uniqueness in database
        if ($checkUniqueness) {
            $attempt = $index;
            while (ProductVariant::where('sku', $sku)->exists()) {
                $attempt++;
                $sku = implode('-', $skuParts) . "-{$timestamp}-{$attempt}";
            }
        }

        return $sku;
    }

    /**
     * Build a human-friendly variant display name from option IDs
     * Example: "Color: Red / Storage: 128GB / RAM: 8GB"
     */
    protected function buildVariantDisplayName(array $optionIds): string
    {
        if (empty($optionIds)) {
            return '';
        }

        $options = ProductVariantOption::with('variantType:id,name,display_name,sort_order')
            ->whereIn('id', $optionIds)
            ->get()
            ->sortBy(function ($opt) {
                return $opt->variantType->sort_order ?? 999;
            });

        $parts = $options->map(function ($opt) {
            $type = $opt->variantType->display_name ?? $opt->variantType->name ?? 'Option';
            $val = $opt->display_value ?? $opt->value;
            return $type . ': ' . $val;
        })->values();

        return $parts->implode(' / ');
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $status = $product->delete();

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $status ? 'Product successfully deleted' : 'Error while deleting product'
        );
    }

    public function generateUniqueSlug(string $title, $model, string $column = 'slug'): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while ($model::where($column, $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }

    public function deleteImage(Product $product, $imageId)
    {
        try {
            $image = $product->images()->findOrFail($imageId);
            $imagePath = $image->image_path;
            $wasPrimary = $image->is_primary;

            $image->delete();

            if ($imagePath && file_exists(public_path($imagePath))) {
                unlink(public_path($imagePath));
            }

            if ($wasPrimary) {
                $nextImage = $product->images()->first();
                if ($nextImage) {
                    $nextImage->update(['is_primary' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully!',
                'remaining_count' => $product->images()->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product image: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete image. Please try again.'], 500);
        }
    }

    public function deleteImageAlternative(Product $product, $imageId)
    {
        try {
            $images = json_decode($product->images, true) ?? [];

            if (!isset($images[$imageId])) {
                return response()->json(['success' => false, 'message' => 'Image not found.'], 404);
            }

            $imagePath = $images[$imageId]['path'] ?? $images[$imageId];
            unset($images[$imageId]);
            $product->update(['images' => json_encode(array_values($images))]);

            if ($imagePath && file_exists(public_path($imagePath))) {
                unlink(public_path($imagePath));
            }

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully!',
                'remaining_count' => count($images)
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product image: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete image. Please try again.'], 500);
        }
    }
}
