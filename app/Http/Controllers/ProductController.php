<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
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
            'summary' => 'nullable|string',
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

        // 1. Parse photo URLs (from file manager)
        $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
        unset($validatedData['photo']);

        $webpPaths = [];
        foreach ($rawPaths as $index => $url) {
            $relativePath = str_replace(asset('storage') . '/', '', $url);
            $storagePath = storage_path("app/public/{$relativePath}");

            if (file_exists($storagePath)) {
                $image = Image::make($storagePath)->encode('webp', 75);

                $webpFilename = 'product_' . uniqid() . '_' . $index . '.webp';
                $webpPath = 'public/products/' . $webpFilename;
                Storage::put($webpPath, (string) $image);

                $webpPaths[] = 'storage/products/' . $webpFilename;
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
            'photo' => 'required|string',
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

        $rawPaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
        unset($validatedData['photo']);

        $webpPaths = [];

        foreach ($rawPaths as $index => $url) {
            $relativePath = str_replace(asset('storage') . '/', '', $url);
            $storagePath = storage_path("app/public/{$relativePath}");

            if (file_exists($storagePath)) {
                $image = Image::make($storagePath)->encode('webp', 75);

                $webpFilename = 'product_' . uniqid() . '_' . $index . '.webp';
                $webpPath = 'public/products/' . $webpFilename;
                Storage::put($webpPath, (string) $image);

                $webpPaths[] = 'storage/products/' . $webpFilename;
            }
        }

        // If title changed, regenerate slug
        if ($product->title !== $validatedData['title']) {
            $validatedData['slug'] = generateUniqueSlug($validatedData['title'], Product::class, 'slug', $product->id);
        }

        $validatedData['is_featured'] = $request->boolean('is_featured');
        $validatedData['size'] = $request->has('size') ? implode(',', $validatedData['size']) : 'M';

        DB::beginTransaction();

        try {
            $product->update($validatedData);

            // 1. Fetch and delete old images from storage
            $oldImages = ProductImage::where('product_id', $product->id)->get();
            foreach ($oldImages as $img) {
                $path = str_replace('storage/', 'public/', $img->image_path); // convert to Storage path
                Storage::delete($path);
            }

            // 2. Delete old image records
            ProductImage::where('product_id', $product->id)->delete();

            // 3. Save new images
            foreach ($webpPaths as $index => $path) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }

            DB::commit();
            return redirect()->route('product.index')->with('success', 'Product updated with WebP images and old files cleaned.');
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
