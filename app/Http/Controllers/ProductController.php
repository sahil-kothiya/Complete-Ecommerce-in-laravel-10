<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::getAllProduct();
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
            'title' => 'required|string',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'photo' => 'required|string',
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'cat_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
        ]);

        $imagePaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
        unset($validatedData['photo']); // no longer store this in products table

        $validatedData['slug'] = generateUniqueSlug($request->title, Product::class);
        $validatedData['is_featured'] = $request->input('is_featured', 0);
        $validatedData['size'] = $request->has('size') ? implode(',', $request->input('size')) : '';

        DB::beginTransaction();

        try {
            $product = Product::create($validatedData);

            foreach ($imagePaths as $index => $path) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('product.index')->with('success', 'Product Successfully added');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('product.index')->with('error', 'Error while adding product. Please try again!');
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
            'title' => 'required|string',
            'summary' => 'required|string',
            'description' => 'nullable|string',
            'photo' => 'required|string', // comma-separated paths
            'size' => 'nullable',
            'stock' => 'required|numeric',
            'cat_id' => 'required|exists:categories,id',
            'child_cat_id' => 'nullable|exists:categories,id',
            'is_featured' => 'sometimes|in:1',
            'brand_id' => 'nullable|exists:brands,id',
            'status' => 'required|in:active,inactive',
            'condition' => 'required|in:default,new,hot',
            'price' => 'required|numeric',
            'discount' => 'nullable|numeric',
        ]);

        // Extract and clean photo paths
        $imagePaths = array_filter(array_map('trim', explode(',', $validatedData['photo'])));
        unset($validatedData['photo']); // no longer directly stored in products table

        // Generate slug if title changed
        if ($product->title !== $request->title) {
            $validatedData['slug'] = generateUniqueSlug($request->title, Product::class, $id);
        }

        $validatedData['is_featured'] = $request->input('is_featured', 0);
        $validatedData['size'] = $request->has('size') ? implode(',', $request->input('size')) : '';

        DB::beginTransaction();

        try {
            $product->update($validatedData);

            // Remove old images
            ProductImage::where('product_id', $product->id)->delete();

            // Add new images
            foreach ($imagePaths as $index => $path) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $path,
                    'is_primary' => $index === 0,
                    'sort_order' => $index + 1,
                ]);
            }

            DB::commit();

            return redirect()->route('product.index')->with('success', 'Product Successfully updated');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            return redirect()->route('product.index')->with('error', 'Error while updating product. Please try again!');
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
}
