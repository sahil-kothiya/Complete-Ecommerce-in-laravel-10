<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
use App\Models\VariantImage;
use App\Models\ProductVariantOption;
use Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::with([
            'cat_info',
            'sub_cat_info',
            'brand',
            'primaryImage',
            'variants' => function ($query) {
                $query->where('status', 'active')->with('primaryImage');
            }
        ])
            // ->where('has_variants', false)
            ->orderBy('id', 'desc')
            ->paginate(10);

        return view('backend.product.index', compact('products'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $brands = Brand::get();
        // $categories = Category::where('is_parent', 1)->get();
        $categories = Category::all();
        return view('backend.product.create', compact('categories', 'brands'));
    }

    public function store(Request $request)
    {
        // Log incoming request summary for debugging (exclude sensitive fields)
        try {
            Log::debug('Product store - incoming request', $request->only([
                'title', 'slug', 'cat_id', 'child_cat_id', 'brand_id', 'has_variants',
                'base_sku', 'base_price', 'base_stock', 'variants'
            ]));
        } catch (\Exception $e) {
            // Ensure logging won't break the flow
            Log::debug('Product store - failed to log incoming request', ['error' => $e->getMessage()]);
        }
        // Define validation rules
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

        // Conditional validation based on has_variants
        if ($request->boolean('has_variants')) {
            $rules['variants'] = 'required|array|min:1';
            $rules['variants.*.sku'] = 'required|string|max:255|unique:product_variants,sku';
            $rules['variants.*.price'] = 'required|numeric|min:0';
            $rules['variants.*.discount'] = 'nullable|numeric|min:0|max:100';
            $rules['variants.*.stock'] = 'required|integer|min:0';
            $rules['variants.*.images'] = 'required|string';
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

        // Custom validation messages
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
            'variants.*.images.required' => 'Variant images are required.',
            'variant_options.required' => 'Please select variant options.',
            'alt_text.*.max' => 'Alt text cannot exceed 125 characters.',
        ];

        // Validate the request
        $validatedData = $request->validate($rules, $messages);

        // Log validated data summary
        try {
            Log::info('Product store - validation passed', array_merge(
                ['has_variants' => $request->boolean('has_variants')],
                array_intersect_key($validatedData, array_flip([
                    'title', 'slug', 'cat_id', 'child_cat_id', 'brand_id', 'base_sku', 'base_price', 'base_stock'
                ]))
            ));
        } catch (\Exception $e) {
            Log::debug('Product store - failed to log validated data', ['error' => $e->getMessage()]);
        }

        // Process non-variant images
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

                        // Log each successful image conversion
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

            // Validate that at least one image was processed successfully
            if (empty($webpPaths)) {
                Log::error('Product store - no images processed successfully', ['photo_input' => $validatedData['photo']]);
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['photo' => 'Failed to process images. Please try again.']);
            }
        }

        // Prepare product data (fixed keys to match model)
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

        // Add non-variant specific fields (fixed keys to 'base_*')
        if (!$request->boolean('has_variants')) {
            $productData['base_price'] = $validatedData['base_price'];
            $productData['base_discount'] = $validatedData['base_discount'] ?? null;
            $productData['base_stock'] = $validatedData['base_stock'];
            $productData['base_sku'] = $validatedData['base_sku'];
        }

        // Log about to begin DB transaction and product summary
        Log::debug('Product store - beginning transaction', [
            'has_variants' => $request->boolean('has_variants'),
            'webp_count' => count($webpPaths)
        ]);

        DB::beginTransaction();

        try {
            // If using PostgreSQL ensure the products sequence is synced with the max id
            try {
                $pdo = DB::getPdo();
                $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ?? '';
                if ($driver === 'pgsql') {
                    $seqRow = DB::selectOne("SELECT pg_get_serial_sequence('products', 'id') as seq");
                    if ($seqRow && isset($seqRow->seq)) {
                        // set sequence to current max(id) so nextval returns max+1
                        DB::statement("SELECT setval('" . $seqRow->seq . "', (SELECT COALESCE(MAX(id), 0) FROM products))");
                        Log::info('Product store - synced products sequence for pgsql', ['sequence' => $seqRow->seq]);
                    }
                }
            } catch (\Exception $e) {
                // Non-fatal: log and continue. This prevents sequence issues on Postgres only.
                Log::warning('Product store - failed to sync products sequence', ['error' => $e->getMessage()]);
            }
            // Create product
            $product = Product::create($productData);

            Log::info('Product store - product created', ['product_id' => $product->id, 'base_sku' => $product->base_sku ?? null]);

            // Handle non-variant images
            if (!$request->boolean('has_variants') && !empty($webpPaths)) {
                $enableAltText = $request->boolean('enable_alt_text');
                $altTexts = $enableAltText && $request->has('alt_text')
                    ? array_values($request->input('alt_text', []))
                    : [];

                foreach ($webpPaths as $index => $path) {
                    $altText = null;

                    if ($enableAltText && isset($altTexts[$index]) && !empty(trim($altTexts[$index]))) {
                        $altText = trim($altTexts[$index]);
                    } elseif ($enableAltText) {
                        $altText = $validatedData['title'] . ' - ' . ($index === 0 ? 'Main Image' : 'Image ' . ($index + 1));
                    }

                    // Use the relative path without 'storage/'
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path, // e.g., 'products/variant_686d384c5eb_0.webp'
                        'alt_text' => $altText,
                        'is_primary' => $index === 0,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            // Handle variants
            if ($request->boolean('has_variants')) {
                // Ensure product_variants sequence is in sync on PostgreSQL to avoid duplicate key errors
                try {
                    $pdo = DB::getPdo();
                    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) ?? '';
                    if ($driver === 'pgsql') {
                        $seqRow = DB::selectOne("SELECT pg_get_serial_sequence('product_variants', 'id') as seq");
                        if ($seqRow && isset($seqRow->seq)) {
                            DB::statement("SELECT setval('" . $seqRow->seq . "', (SELECT COALESCE(MAX(id), 0) FROM product_variants))");
                            Log::info('Product store - synced product_variants sequence for pgsql', ['sequence' => $seqRow->seq]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Product store - failed to sync product_variants sequence', ['error' => $e->getMessage()]);
                }

                Log::debug('Product store - handling variants', ['product_id' => $product->id, 'variants_count' => count($request->input('variants', []))]);
                $this->handleVariants($request, $product);
                Log::debug('Product store - finished handling variants', ['product_id' => $product->id]);
            }

            DB::commit();

            Log::info('Product store - transaction committed', ['product_id' => $product->id]);

            return redirect()->route('product.index')
                ->with('success', 'Product created successfully!');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();

            // Clean up uploaded WebP files on failure
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

            // Check for specific database errors
            if (strpos($e->getMessage(), 'Duplicate entry') !== false || strpos($e->getMessage(), 'unique constraint') !== false) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['error' => 'A product with this ID, SKU, or slug already exists. Check database sequence.']);
            }

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'Database error occurred. Please try again.']);
        } catch (\Exception $e) {
            DB::rollBack();

            // Clean up uploaded WebP files on failure
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

            return redirect()->back()
                ->withInput()
                ->withErrors(['error' => 'An unexpected error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle variant creation for the product
     */
    private function handleVariants(Request $request, Product $product)
    {
        $variantOptions = $request->input('variant_options', []);
        $variantsData = $request->input('variants', []);

        if (empty($variantOptions) || empty($variantsData)) {
            throw new \Exception('No variant options or data provided');
        }

        // Generate combinations from selected variant options
        $combinations = $this->generateCombinations($variantOptions);

        if (count($combinations) !== count($variantsData)) {
            throw new \Exception('Mismatch between generated combinations and provided variant data');
        }

        foreach ($variantsData as $index => $variantData) {
            // Validate required variant fields
            if (
                empty($variantData['sku']) || empty($variantData['price']) ||
                !isset($variantData['stock']) || empty($variantData['images'])
            ) {
                throw new \Exception("Missing required data for variant at index {$index}");
            }

            // Process variant images
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
                    Log::error("Variant image processing failed", [
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            if (empty($webpPaths)) {
                throw new \Exception("Failed to process images for variant at index {$index}");
            }

            // Create variant
            $variant = $product->variants()->create([
                'product_id' => $product->id,
                'sku' => $variantData['sku'],
                'price' => $variantData['price'],
                'discount' => $variantData['discount'] ?? null,
                'stock' => $variantData['stock'],
                'variant_values' => json_encode($combinations[$index]['values'] ?? []),
                'status' => 'active',
            ]);

            // Associate variant options
            foreach ($combinations[$index]['values'] ?? [] as $typeId => $optionId) {
                $variant->variantCombinations()->create([
                    'variant_option_id' => $optionId,
                ]);
            }

            // Store variant images in the dedicated variant_images table
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

        // Invalidate cache if using Redis
        if (class_exists('App\Helpers\RedisHelper')) {
            $key = "product_variants:{$product->id}";
            RedisHelper::put($key, null, 0);
        }
    }

    /**
     * Generate variant combinations from selected options
     */
    private function generateCombinations(array $selections): array
    {
        $options = [];

        foreach ($selections as $typeId => $optionIds) {
            if (empty($optionIds)) {
                continue;
            }

            $typeOptions = ProductVariantOption::whereIn('id', $optionIds)
                ->select('id', 'display_value', 'variant_type_id')
                ->get()
                ->toArray();

            if (!empty($typeOptions)) {
                $options[$typeId] = $typeOptions;
            }
        }

        if (empty($options)) {
            throw new \Exception('No valid variant options found');
        }

        $combinations = [['values' => []]];

        foreach ($options as $typeId => $typeOptions) {
            $newCombs = [];
            foreach ($combinations as $comb) {
                foreach ($typeOptions as $option) {
                    $newComb = $comb;
                    $newComb['values'][$typeId] = $option['id'];
                    $newCombs[] = $newComb;
                }
            }
            $combinations = $newCombs;
        }

        return $combinations;
    }

    public function edit($id)
    {
        $product = Product::with(['images', 'variants.images', 'variants.variantOptions.variantType'])->findOrFail($id);
        $brands = Brand::all();
        // Fetch parent categories where parent_id is NULL
        $categories = Category::whereNull('parent_id')->get();
        // Fetch subcategories based on the product's category
        $subcategories = $product->cat_id ? Category::where('parent_id', $product->cat_id)->get() : collect();

        return view('backend.product.edit', compact('product', 'brands', 'categories', 'subcategories'));
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'photo' => ($product->images->isEmpty() ? 'required' : 'nullable') . '|string',
            'size' => 'nullable|array',
            'stock' => 'required|integer|min:0',
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'enable_alt_text' => 'nullable|boolean',
            'existing_alt_text' => 'nullable|array',
            'existing_alt_text.*' => 'nullable|string|max:255',
            'new_alt_text' => 'nullable|array',
            'new_alt_text.*' => 'nullable|string|max:255',
        ]);

        // Extract and process photo paths (if any)
        $webpPaths = [];
        if (!empty($validatedData['photo'])) {
            $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
            unset($validatedData['photo']);

            foreach ($rawPaths as $index => $url) {
                $parsed = parse_url($url, PHP_URL_PATH);
                $relativePath = ltrim(str_replace('/storage/', '', $parsed), '/');
                $storagePath = storage_path("app/public/{$relativePath}");

                if (file_exists($storagePath)) {
                    $image = Image::make($storagePath)->encode('webp', 75);

                    $webpFilename = 'product_' . uniqid() . '_' . $index . '.webp';
                    $webpPath = "public/products/{$webpFilename}";

                    Storage::put($webpPath, (string) $image);
                    $webpPaths[] = "storage/products/{$webpFilename}";
                }
            }
        }

        // Handle slug regeneration
        if ($product->title !== $validatedData['title']) {
            $validatedData['slug'] = generateUniqueSlug($validatedData['title'], Product::class, 'slug', $product->id);
        }

        $validatedData['is_featured'] = $request->boolean('is_featured');
        $validatedData['size'] = $request->has('size') ? implode(',', $validatedData['size']) : 'M';

        // Remove alt text fields from product data
        unset($validatedData['enable_alt_text'], $validatedData['existing_alt_text'], $validatedData['new_alt_text']);

        DB::beginTransaction();

        try {
            // Check if SKU-affecting fields have changed and regenerate SKU if needed
            $skuAffectingFields = ['cat_id', 'brand_id', 'size'];
            $shouldRegenerateSku = false;

            foreach ($skuAffectingFields as $field) {
                if ($product->{$field} !== $validatedData[$field]) {
                    $shouldRegenerateSku = true;
                    break;
                }
            }

            // Update the product
            $product->update($validatedData);

            // Regenerate SKU if necessary
            if ($shouldRegenerateSku) {
                // Refresh the model to get updated relationships
                $product->refresh();
                $newSku = $this->generateUniqueSKU($product);
                if ($newSku) {
                    $product->update(['sku' => $newSku]);
                } else {
                    throw new \Exception('Could not generate unique SKU after maximum attempts');
                }
            }

            // Handle alt text updates if checkbox is enabled
            if ($request->boolean('enable_alt_text')) {
                // Update existing images alt text
                if ($request->has('existing_alt_text') && is_array($request->existing_alt_text)) {
                    foreach ($request->existing_alt_text as $imageId => $altText) {
                        ProductImage::where('id', $imageId)
                            ->where('product_id', $product->id)
                            ->update(['alt_text' => $altText]);
                    }
                }
            } else {
                // If alt text is disabled, clear existing alt text
                ProductImage::where('product_id', $product->id)
                    ->update(['alt_text' => null]);
            }

            // Only delete and re-create images if new ones are provided
            if (!empty($webpPaths)) {
                $oldImages = ProductImage::where('product_id', $product->id)->get();
                foreach ($oldImages as $img) {
                    $path = str_replace('storage/', 'public/', $img->image_path);
                    Storage::delete($path);
                }

                ProductImage::where('product_id', $product->id)->delete();

                $newAltTexts = $request->new_alt_text ?? [];

                foreach ($webpPaths as $index => $path) {
                    $altText = null;

                    // Set alt text for new images if alt text is enabled
                    if ($request->boolean('enable_alt_text') && isset($newAltTexts[$index])) {
                        $altText = $newAltTexts[$index];
                    }

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                        'sort_order' => $index + 1,
                        'alt_text' => $altText,
                    ]);
                }
            }

            DB::commit();

            $message = 'Product updated successfully.';
            // if ($shouldRegenerateSku) {
            //     $message .= ' New SKU generated: ' . $product->sku;
            // }

            return redirect()->route('product.index')->with('success', $message);
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('product.index')->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    // Add preview endpoint
    public function previewVariants(Request $request)
    {
        $request->validate([
            'selections' => 'required|array',
            'base_price' => 'nullable|numeric|min:0',
        ]);
        $selections = $request->input('selections'); // Already an array, no json_decode needed
        $basePrice = $request->input('base_price');
        $variants = [];

        // Validate selections
        if (!is_array($selections) || empty($selections)) {
            return response()->json(['error' => 'Invalid or empty selections provided'], 400);
        }

        // Generate combinations
        $combinations = $this->generateVariantCombinations($selections);
        foreach ($combinations as $idx => $combo) {
            $name = implode(' / ', array_map(fn($opt) => $opt['display_value'], $combo));
            $sku = $this->generateSKU($name, $idx);
            $variants[] = [
                'name' => $name,
                'sku' => $sku,
                'price' => $basePrice,
                'discount' => null,
                'stock' => 10,
            ];
        }

        return response()->json(['variants' => $variants]);
    }

    protected function generateVariantCombinations($selections)
    {
        $options = [];
        foreach ($selections as $typeId => $optionIds) {
            $typeOptions = ProductVariantOption::whereIn('id', $optionIds)
                ->select('id', 'display_value')
                ->get()
                ->toArray();
            $options[] = $typeOptions;
        }

        // Generate Cartesian product of options
        $combinations = [[]];
        foreach ($options as $typeOptions) {
            $temp = [];
            foreach ($combinations as $combo) {
                foreach ($typeOptions as $option) {
                    $temp[] = array_merge($combo, [$option]);
                }
            }
            $combinations = $temp;
        }

        return $combinations;
    }

    protected function generateSKU($name, $index)
    {
        $slug = Str::slug($name);
        return "SKU-{$slug}-{$index}";
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);
        $status = $product->delete();

        $message = $status
            ? 'Product successfully deleted'
            : 'Error while deleting product';

        return redirect()->route('product.index')->with(
            $status ? 'success' : 'error',
            $message
        );
    }

    function generateUniqueSlug(string $title, $model, string $column = 'slug'): string
    {
        $slug = Str::slug($title);
        $original = $slug;
        $i = 1;

        while ($model::where($column, $slug)->exists()) {
            $slug = $original . '-' . $i++;
        }

        return $slug;
    }

    /**
     * Generate unique SKU for the product
     */
    private function generateUniqueSKU(Product $product): ?string
    {
        $maxRetries = 100;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $sku = $this->generateSKU($product, $attempt);

            // Check if SKU already exists (excluding current product)
            $existingProduct = Product::where('sku', $sku)->where('id', '!=', $product->id)->first();

            if (!$existingProduct) {
                return $sku;
            }
        }

        return null; // Could not generate unique SKU
    }

    // /**
    //  * Generate SKU based on product attributes
    //  */
    // private function generateSKU(Product $product, int $attempt): string
    // {
    //     // Load relationships if not already loaded
    //     $product->load(['category', 'brand']);

    //     $cat = $this->getCode($product->category->code ?? $product->category->name ?? 'GEN');
    //     $brand = $this->getCode($product->brand->code ?? $product->brand->name ?? 'GEN');
    //     $variant = $this->mapSize($product->size) . $this->hashDigit($product->id);
    //     $unique = $this->generateUniqueID($product->id, $attempt);
    //     $checksum = $this->crc16Checksum($cat . $brand . $variant . $unique);

    //     return $cat . $brand . $variant . $unique . $checksum;
    // }

    /**
     * Get 3-character code from string
     */
    private function getCode(string $value): string
    {
        return strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', strtolower($value)), 0, 3)) ?: 'XXX';
    }

    /**
     * Map size to single digit
     */
    private function mapSize(?string $size): string
    {
        $map = ['XS' => '1', 'S' => '2', 'M' => '3', 'L' => '4', 'XL' => '5'];
        return $map[strtoupper($size ?? '')] ?? $this->hashDigit($size ?? '0');
    }

    /**
     * Generate hash digit from input
     */
    private function hashDigit($input): string
    {
        return substr(dechex(crc32((string) $input)), -1);
    }

    /**
     * Generate unique ID part
     */
    private function generateUniqueID(int $productId, int $attempt): string
    {
        $idPart = str_pad(substr((string)$productId, -2), 2, '0', STR_PAD_LEFT);
        $timePart = substr(dechex(time()), -1);
        $retryPart = dechex($attempt % 16);
        return $idPart . $timePart . $retryPart;
    }

    /**
     * Generate CRC16 checksum
     */
    private function crc16Checksum(string $input): string
    {
        $crc = 0xFFFF;
        $poly = 0x1021;

        for ($i = 0; $i < strlen($input); $i++) {
            $crc ^= (ord($input[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ $poly) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc & 0xFF), 2, '0', STR_PAD_LEFT));
    }

    public function deleteImage(Product $product, $imageId)
    {
        try {
            // Find the image - adjust this based on your model structure
            // If you have a separate ProductImage model:
            $image = $product->images()->findOrFail($imageId);

            // Or if images are stored differently, adjust accordingly
            // $image = ProductImage::where('product_id', $product->id)->findOrFail($imageId);

            // Store image path for file deletion
            $imagePath = $image->image_path;

            // Check if this is the primary image
            $wasPrimary = $image->is_primary;

            // Delete the database record
            $image->delete();

            // Delete the physical file if it exists
            if ($imagePath && file_exists(public_path($imagePath))) {
                unlink(public_path($imagePath));
            }

            // If deleted image was primary, set another image as primary (if any exist)
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

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image. Please try again.'
            ], 500);
        }
    }

    // Alternative method if you don't have separate ProductImage model
    // and store images differently (adjust based on your implementation)
    public function deleteImageAlternative(Product $product, $imageId)
    {
        try {
            // If images are stored as JSON or comma-separated in product table
            $images = json_decode($product->images, true) ?? [];

            if (!isset($images[$imageId])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Image not found.'
                ], 404);
            }

            // Get image path before removal
            $imagePath = $images[$imageId]['path'] ?? $images[$imageId];

            // Remove from array
            unset($images[$imageId]);

            // Update product
            $product->update(['images' => json_encode(array_values($images))]);

            // Delete physical file
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

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image. Please try again.'
            ], 500);
        }
    }
}
