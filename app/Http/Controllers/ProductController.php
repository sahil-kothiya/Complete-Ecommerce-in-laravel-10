<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
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
        // dd(RedisHelper::get('cache:homepage:product_lists'));

        // $products = Product::getAllProduct();
        $products = Product::with(['cat_info', 'sub_cat_info'])->orderBy('id', 'desc')->paginate(10);
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

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // dd($request->all());
        $validatedData = $request->validate([
            'title' => 'required|string|max:255',
            'summary' => 'required|nullable|string',
            'description' => 'nullable|string',
            'photo' => 'required|string', // from FileManager, comma-separated
            'size' => 'nullable|array',
            'stock' => 'required|integer|min:0',
            'cat_id' => 'nullable|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'is_featured' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'enable_alt_text' => 'nullable|boolean',
            'alt_text' => 'nullable|array',
            'alt_text.*' => 'nullable|string|max:255',
            'alt_text_order' => 'nullable|array', // Add validation for order array
        ]);

        // Step 1: Split and clean the photo URLs
        $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'] ?? '')));
        unset($validatedData['photo']);

        // FIXED: Get alt text data with proper ordering
        $enableAltText = $request->boolean('enable_alt_text');
        $altTexts = [];

        if ($enableAltText && $request->has('alt_text')) {
            $rawAltTexts = $request->input('alt_text', []);

            // If alt_text comes as associative array (alt_text[0], alt_text[1], etc.)
            // we need to maintain the proper order matching the images
            if (is_array($rawAltTexts)) {
                // Sort by key to maintain order (0, 1, 2, etc.)
                ksort($rawAltTexts);
                $altTexts = array_values($rawAltTexts); // Convert to indexed array
            }

            // Debug logging (remove in production)
            Log::info('Alt texts received:', [
                'raw' => $rawAltTexts,
                'processed' => $altTexts,
                'image_count' => count($rawPaths)
            ]);
        }

        // Remove alt text related fields from validated data before creating product
        unset($validatedData['enable_alt_text'], $validatedData['alt_text'], $validatedData['alt_text_order']);

        $webpPaths = [];

        foreach ($rawPaths as $index => $url) {
            // Step 2: Extract relative path by removing asset('storage')
            $parsed = parse_url($url, PHP_URL_PATH); // gets only /storage/photos/...
            $publicPath = ltrim(str_replace('/storage/', '', $parsed), '/'); // now: photos/1/Products/filename.webp

            // Step 3: Convert to actual storage path
            $fullPath = storage_path("app/public/{$publicPath}");

            // Step 4: Validate file exists and convert
            if (file_exists($fullPath)) {
                $image = Image::make($fullPath)->encode('webp', 75);

                $webpFilename = 'product_' . uniqid() . "_{$index}.webp";
                $webpPath = "public/products/{$webpFilename}";

                Storage::put($webpPath, (string) $image);

                // Save this for DB or other processing
                $webpPaths[] = "storage/products/{$webpFilename}";
            } else {
                Log::warning("Image not found: {$fullPath}");
            }
        }

        // 2. Safe slug generation (unique)
        $validatedData['slug'] = generateUniqueSlug($validatedData['title'], Product::class, 'slug');

        // 3. Cast/prepare values based on indexed schema
        $validatedData['is_featured'] = $request->boolean('is_featured');
        $validatedData['size'] = $request->has('size') ? implode(',', $validatedData['size']) : 'M';

        DB::beginTransaction();

        try {
            // Create product first to get the ID
            $product = Product::create($validatedData);

            if ($request->boolean('has_variants')) {
                $product->update(['has_variants' => true, 'base_price' => $request->base_price]);
                $this->handleVariants($request, $product);
            }

            // Generate and assign SKU after product creation
            $sku = $this->generateUniqueSKU($product);
            if ($sku) {
                $product->update(['sku' => $sku]);
            } else {
                throw new \Exception('Could not generate unique SKU after maximum attempts');
            }

            // FIXED: Handle product images with alt text - improved logic
            foreach ($webpPaths as $index => $path) {
                // Get alt text for this image if provided
                $altText = null;

                if ($enableAltText) {
                    // Check if alt text exists for this specific index
                    if (isset($altTexts[$index]) && !empty(trim($altTexts[$index]))) {
                        $altText = trim($altTexts[$index]);
                    } else {
                        // Generate default alt text if empty or missing
                        $altText = $validatedData['title'] . ' - ' . ($index === 0 ? 'Main Image' : 'Image ' . ($index + 1));
                    }
                }

                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'alt_text' => $altText, // This will be null if alt text is disabled
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);

                // Debug logging for each image created (remove in production)
                Log::info("Created ProductImage", [
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'alt_text' => $altText,
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('product.index')->with('success', 'Product added successfully');
        } catch (\Throwable $e) {
            DB::rollBack();

            // Clean up any uploaded WebP files on failure
            foreach ($webpPaths as $path) {
                $fullWebpPath = str_replace('storage/', 'public/', $path);
                if (Storage::exists($fullWebpPath)) {
                    Storage::delete($fullWebpPath);
                }
            }

            // Log the full error for debugging
            Log::error('Product creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            report($e);
            return redirect()->route('product.index')->with('error', 'Product creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        // Implement if needed
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $brands = Brand::get();
        $product = Product::with('images')->findOrFail($id); // eager loading images
        // $categories = Category::where('is_parent', 1)->get();
        $categories = Category::all();
        $items = Product::where('id', $id)->get();

        return view('backend.product.edit', compact('product', 'brands', 'categories', 'items'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

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

    private function handleVariants(Request $request, Product $product) {
        $product->variants()->delete(); // Clear old
        $selections = $request->input('variant_selections', []); // e.g., ['color' => [1,2], 'size' => [3,4]]
        $combinations = $this->generateCombinations($selections);
        foreach ($combinations as $combo) {
            $comboKey = md5(json_encode($combo['values']));
            $variant = $product->variants()->create([
                'sku' => $product->sku . '-' . implode('-', array_keys($combo['values'])),
                'price' => $request->input("variant_price_{$comboKey}", $request->base_price),
                'discount' => $request->input("variant_discount_{$comboKey}"),
                'stock' => $request->input("variant_stock_{$comboKey}", 0),
                'variant_values' => json_encode($combo['values']),
                'images' => json_encode($request->input("variant_images_{$comboKey}", [])),
                'status' => 'active'
            ]);
            foreach ($combo['values'] as $typeId => $optId) {
                $variant->variantCombinations()->create(['variant_option_id' => $optId]);
            }
        }
        // Invalidate cache
        $key = "product_variants:{$product->id}";
        \App\Helpers\RedisHelper::put($key, null, 0);
    }

    private function generateCombinations(array $selections): array {
        $combs = [ ['values' => []] ];
        foreach ($selections as $typeId => $optIds) {
            $newCombs = [];
            foreach ($combs as $comb) {
                foreach ($optIds as $optId) {
                    $newComb = $comb;
                    $newComb['values'][$typeId] = $optId;
                    $newCombs[] = $newComb;
                }
            }
            $combs = $newCombs;
        }
        return $combs;
    }

    // Add preview endpoint
    public function previewVariants(Request $request) {
        try {
            $selections = $request->input('selections', []);

            // If selections were sent as a JSON string (client uses JSON.stringify), decode it
            if (is_string($selections)) {
                $decoded = json_decode($selections, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $selections = $decoded;
                } else {
                    // Malformed JSON - treat as empty
                    Log::warning('previewVariants: malformed selections JSON', ['raw' => $selections]);
                    $selections = [];
                }
            }

            // Ensure we have an array of selections
            if (!is_array($selections) || empty($selections)) {
                return response("<div class='alert alert-warning'>No variant options selected.</div>", 200);
            }

            $combs = $this->generateCombinations($selections);

            $html = '<table class="table table-sm"><thead><tr><th>Combination</th><th>Price</th><th>Discount</th><th>Stock</th><th>Images</th></tr></thead><tbody>';
            foreach ($combs as $i => $comb) {
                $key = md5(json_encode($comb['values']));
                $display = collect($comb['values'])->map(fn($id) => ProductVariantOption::find($id)?->display_value ?? 'Unknown')->implode(', ');
                $priceVal = htmlspecialchars((string) $request->input('base_price', ''));
                $html .= "<tr><td>{$display}</td><td><input name='variant_price_{$key}' class='form-control form-control-sm' value='{$priceVal}'></td>
                    <td><input name='variant_discount_{$key}' class='form-control form-control-sm'></td>
                    <td><input name='variant_stock_{$key}' class='form-control form-control-sm' value='0'></td>
                    <td><input name='variant_images_{$key}[]' class='form-control form-control-sm' multiple></td></tr>";
            }
            $html .= '</tbody></table><input type="hidden" name="variant_selections" value="' . htmlspecialchars(is_string($request->input('selections')) ? $request->input('selections') : json_encode($request->input('selections')) ) . '">';
            return response($html);
        } catch (\Throwable $e) {
            Log::error('previewVariants failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString(), 'request' => $request->all()]);
            return response("<div class='alert alert-danger'>Could not generate preview. Try again.</div>", 500);
        }
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

    /**
     * Generate SKU based on product attributes
     */
    private function generateSKU(Product $product, int $attempt): string
    {
        // Load relationships if not already loaded
        $product->load(['category', 'brand']);

        $cat = $this->getCode($product->category->code ?? $product->category->name ?? 'GEN');
        $brand = $this->getCode($product->brand->code ?? $product->brand->name ?? 'GEN');
        $variant = $this->mapSize($product->size) . $this->hashDigit($product->id);
        $unique = $this->generateUniqueID($product->id, $attempt);
        $checksum = $this->crc16Checksum($cat . $brand . $variant . $unique);

        return $cat . $brand . $variant . $unique . $checksum;
    }

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
