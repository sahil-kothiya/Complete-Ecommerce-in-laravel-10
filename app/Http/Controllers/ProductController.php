<?php

namespace App\Http\Controllers;

use App\Helpers\RedisHelper;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
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
        $categories = Category::where('is_parent', 1)->get();
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
            'is_featured' => 'sometimes|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        // dd($validatedData);

        // Step 1: Split and clean the photo URLs
        $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'] ?? '')));
        unset($validatedData['photo']);

        $webpPaths = [];

        // dd($rawPaths);
        foreach ($rawPaths as $index => $url) {
            // Step 2: Extract relative path by removing asset('storage')
            // $publicPath = str_replace(asset('storage') . '/', '', $url);
            $parsed = parse_url($url, PHP_URL_PATH); // gets only /storage/photos/...
            $publicPath = ltrim(str_replace('/storage/', '', $parsed), '/'); // now: photos/1/Products/filename.webp

            // Step 3: Convert to actual storage path
            $fullPath = storage_path("app/public/{$publicPath}");
            // dd($fullPath);
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
            $product = Product::create($validatedData);

            foreach ($webpPaths as $index => $path) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }

            DB::commit();

            // RedisHelper::forgetMany(['e_shop_database_cache.home_page.product_lists']);

            return redirect()->route('product.index')->with('success', 'Product added successfully with optimized WebP images.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('product.index')->with('error', 'Product creation failed.');
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
        $categories = Category::where('is_parent', 1)->get();
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
            'is_featured' => 'sometimes|boolean',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ]);

        // ✅ Extract and process photo paths (if any)
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

        // ✅ Handle slug regeneration
        if ($product->title !== $validatedData['title']) {
            $validatedData['slug'] = generateUniqueSlug($validatedData['title'], Product::class, 'slug', $product->id);
        }

        $validatedData['is_featured'] = $request->boolean('is_featured');
        $validatedData['size'] = $request->has('size') ? implode(',', $validatedData['size']) : 'M';

        DB::beginTransaction();

        try {
            $product->update($validatedData);

            // ✅ Only delete and re-create images if new ones are provided
            if (!empty($webpPaths)) {
                $oldImages = ProductImage::where('product_id', $product->id)->get();
                foreach ($oldImages as $img) {
                    $path = str_replace('storage/', 'public/', $img->image_path);
                    Storage::delete($path);
                }

                ProductImage::where('product_id', $product->id)->delete();

                foreach ($webpPaths as $index => $path) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0,
                        'sort_order' => $index + 1,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('product.index')->with('success', 'Product updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('product.index')->with('error', 'Update failed. Please try again.');
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
}
