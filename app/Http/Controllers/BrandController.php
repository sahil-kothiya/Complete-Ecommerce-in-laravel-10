<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use Illuminate\Support\Str;
use App\Helpers\helpers;

class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $brands = Brand::latest('id')->paginate();
        return view('backend.brand.index', compact('brands'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('backend.brand.create');
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
            'status' => 'required|in:active,inactive',
        ]);

        $slug = generateUniqueSlug($request->title, Brand::class);
        $validatedData['slug'] = $slug;

        // Generate unique code
        $existingCodes = Brand::pluck('code')->toArray();
        $generatedCode = generateUniqueCode($request->title, $existingCodes);

        $validatedData['code'] = $generatedCode;
        $validatedData['code_locked'] = false;
        $validatedData['code_generated_at'] = now();

        $brand = Brand::create($validatedData);

        return redirect()->route('brand.index')->with(
            $brand ? 'success' : 'error',
            $brand ? 'Brand successfully created' : 'Error, Please try again'
        );
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return redirect()->back()->with('error', 'Brand not found');
        }

        return view('backend.brand.edit', compact('brand'));
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
        $brand = Brand::find($id);

        if (!$brand) {
            return redirect()->back()->with('error', 'Brand not found');
        }

        $validatedData = $request->validate([
            'title' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $wasLocked = $brand->code_locked;
        $isLockedNow = $request->has('code_locked');
        $validatedData['code_locked'] = $isLockedNow ? 1 : 0;

        // Regenerate code only if now unlocked and title changed
        if (!$isLockedNow && $brand->title !== $request->title) {
            $existingCodes = Brand::where('id', '!=', $brand->id)->pluck('code')->toArray();
            $generatedCode = generateUniqueCode($request->title, $existingCodes);
            $validatedData['code'] = $generatedCode;
            $validatedData['code_generated_at'] = now();
        }

        $status = $brand->update($validatedData);

        return redirect()->route('brand.index')->with(
            $status ? 'success' : 'error',
            $status ? 'Brand successfully updated' : 'Error, Please try again'
        );
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return redirect()->back()->with('error', 'Brand not found');
        }

        $status = $brand->delete();

        $message = $status
            ? 'Brand successfully deleted'
            : 'Error, Please try again';

        return redirect()->route('brand.index')->with(
            $status ? 'success' : 'error',
            $message
        );
    }
}
