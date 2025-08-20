<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Banner;
use App\Models\Discount;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BannerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $banners = Banner::latest('id')->paginate(10);
        return view('backend.banner.index', compact('banners'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $discounts = Discount::all();
        return view('backend.banner.create', compact('discounts'));
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
            'title'        => 'required|string|max:50',
            'description'  => 'nullable|string',
            'photo'        => 'required|string',
            'status'       => 'required|in:active,inactive',
            'discount_id'  => 'nullable|exists:discounts,id',
            'link_type'    => 'nullable|in:product,category,url,discount',
            'link'         => [
                'nullable',
                'string',
                function ($attribute, $value) use ($request) {
                    if ($request->link_type && empty($value)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'link' => 'The link field is required when link type is selected.'
                        ]);
                    }
                }
            ],
        ]);

        $validatedData['slug'] = $this->generateUniqueSlug($request->title);

        $banner = Banner::create($validatedData);

        // Handle single discount relationship if needed
        if ($request->has('discount_id') && $request->discount_id) {
            // If you have a belongsTo relationship
            // The discount_id is already included in $validatedData

            // If you have a many-to-many relationship, use:
            $banner->discounts()->sync([$request->discount_id]);
        }

        return redirect()
            ->route('banner.index')
            ->with($banner ? 'success' : 'error', 
                $banner ? 'Banner successfully added' : 'Error occurred while adding banner'
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
        $banner = Banner::with('discounts')->findOrFail($id);
        return view('backend.banner.show', compact('banner'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $banner = Banner::with('discounts')->findOrFail($id);
        // dd($banner);

        $discounts = Discount::active()->get();
        return view('backend.banner.edit', compact('banner', 'discounts'));
    }

    public function update(Request $request, $id)
    {
        $banner = Banner::findOrFail($id);

        $validatedData = $request->validate([
            'title'        => 'required|string|max:50',
            'description'  => 'nullable|string',
            'photo'        => 'required|string',
            'status'       => 'required|in:active,inactive',
            'discount_id'  => 'nullable|exists:discounts,id',
            'link_type'    => 'nullable|in:product,category,url,discount',
            'link'         => [
                'nullable',
                'string',
                function ($attribute, $value) use ($request) {
                    if ($request->link_type && empty($value)) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'link' => 'The link field is required when link type is selected.'
                        ]);
                    }
                }
            ],
        ]);

        // Generate new slug only if title has changed
        if ($request->title !== $banner->title) {
            $validatedData['slug'] = $this->generateUniqueSlug($request->title, $banner->id);
        }
        // dd($request->all(), $id, $validatedData);

        $banner->update($validatedData);

        // Handle single discount relationship if needed
        if ($request->has('discount_id') && $request->discount_id) {
            // If you have a belongsTo relationship
            // The discount_id is already included in $validatedData

            // If you have a many-to-many relationship, use:
            $banner->discounts()->sync([$request->discount_id]);
        }


        return redirect()
        ->route('banner.index')
        ->with('success', 'Banner successfully updated');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $banner = Banner::findOrFail($id);

        if ($banner->photo && Storage::disk('public')->exists($banner->photo)) {
            Storage::disk('public')->delete($banner->photo);
        }

        $status = $banner->delete();

        $message = $status
            ? 'Banner successfully deleted'
            : 'Error occurred while deleting banner';

        return redirect()->route('banner.index')->with(
            $status ? 'success' : 'error',
            $message
        );
    }

    /**
     * Generate a unique slug for the banner.
     *
     * @param  string  $title
     * @return string
     */
    private function generateUniqueSlug($title)
    {
        $slug = Str::slug($title);
        $count = Banner::where('slug', $slug)->count();

        if ($count > 0) {
            $slug = $slug . '-' . date('ymdis') . '-' . rand(0, 999);
        }

        return $slug;
    }
}
