<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Helper;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $categories = Category::getAllCategory();
        return view('backend.category.index', compact('categories'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $parent_cats = Category::where('is_parent', 1)->orderBy('title', 'ASC')->get();
        return view('backend.category.create', compact('parent_cats'));
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
            'title'     => 'required|string',
            'summary'   => 'nullable|string',
            'photo'     => 'nullable|string',
            'status'    => 'required|in:active,inactive',
            'is_parent' => 'sometimes|in:1',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $validatedData['slug'] = generateUniqueSlug($request->title, Category::class);
        $validatedData['is_parent'] = $request->input('is_parent', 0);

        // Auto-generate unique 3-letter code
        $existingCodes = Category::pluck('code')->toArray();
        $generatedCode = generateUniqueCode($request->title, $existingCodes);

        // Add code fields to data array
        $validatedData['code'] = $generatedCode;
        $validatedData['code_generated_at'] = now();
        $validatedData['code_locked'] = false;

        $category = Category::create($validatedData);

        return redirect()->route('category.index')->with(
            $category ? 'success' : 'error',
            $category ? 'Category successfully added' : 'Error occurred, please try again!'
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
        $category = Category::findOrFail($id);
        $parent_cats = Category::where('is_parent', 1)->get();
        return view('backend.category.edit', compact('category', 'parent_cats'));
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
        $category = Category::findOrFail($id);

        $validatedData = $request->validate([
            'title' => 'required|string',
            'summary' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'is_parent' => 'sometimes|in:1',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $validatedData['is_parent'] = $request->input('is_parent', 0);
        $validatedData['code_locked'] = $request->has('code_locked') ? 1 : 0;

        // Determine if title was changed AND code is not locked (in DB or in current request)
        $wasLockedBefore = $category->code_locked; // value from DB
        $isLockedNow = $request->has('code_locked'); // checkbox value

        $validatedData['code_locked'] = $isLockedNow ? 1 : 0;
        $validatedData['is_parent'] = $request->input('is_parent', 0);

        // Regenerate only if it's currently *not locked* AND title has changed
        if (!$isLockedNow && $request->title !== $category->title) {
            $existingCodes = Category::where('id', '!=', $category->id)
                ->pluck('code')
                ->toArray();

            $newCode = generateUniqueCode($request->title, $existingCodes);

            $validatedData['code'] = $newCode;
            $validatedData['code_generated_at'] = now();
        }

        $status = $category->update($validatedData);

        $message = $status
            ? 'Category successfully updated'
            : 'Error occurred, Please try again!';

        return redirect()->route('category.index')->with(
            $status ? 'success' : 'error',
            $message
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
        $category = Category::findOrFail($id);
        $child_cat_id = Category::where('parent_id', $id)->pluck('id');

        $status = $category->delete();

        if ($status && $child_cat_id->count() > 0) {
            Category::shiftChild($child_cat_id);
        }

        $message = $status
            ? 'Category successfully deleted'
            : 'Error while deleting category';

        return redirect()->route('category.index')->with(
            $status ? 'success' : 'error',
            $message
        );
    }

    /**
     * Get child categories by parent ID.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChildByParent(Request $request)
    {
        $category = Category::findOrFail($request->id);
        $child_cat = Category::getChildByParentID($request->id);

        if ($child_cat->count() <= 0) {
            return response()->json(['status' => false, 'msg' => '', 'data' => null]);
        }

        return response()->json(['status' => true, 'msg' => '', 'data' => $child_cat]);
    }
}
