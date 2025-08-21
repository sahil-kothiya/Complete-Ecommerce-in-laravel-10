<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Helper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $all_cats = Category::orderBy('title', 'ASC')->get();
        $brands = \App\Models\Brand::orderBy('title')->get();
        $available_filters = [
            'price' => 'Price Range',
            'brand' => 'Brands',
            'rating' => 'Customer Ratings',
            'discount' => 'Discounts',
        ];
        return view('backend.category.create', compact('all_cats', 'brands', 'available_filters'));
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
            'summary' => 'nullable|string',
            'photo' => 'nullable|string',
            'status' => 'required|in:active,inactive',
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'boolean',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'enabled_filters' => 'nullable|array',
            'enabled_filters.*' => 'string|in:price,brand,rating,discount',
            'brands' => 'nullable|array',
            'brands.*' => 'exists:brands,id',
        ]);

        $validatedData['slug'] = generateUniqueSlug($request->title, Category::class);

        // Auto-generate unique 3-letter code
        $existingCodes = Category::pluck('code')->toArray();
        $generatedCode = generateUniqueCode($request->title, $existingCodes);

        // Add code fields to data array
        $validatedData['code'] = $generatedCode;
        $validatedData['code_generated_at'] = now();
        $validatedData['code_locked'] = false;

        // Set added_by
        $validatedData['added_by'] = Auth::user()->id;

        // Defaults
        $validatedData['has_children'] = false;
        $validatedData['children_count'] = 0;
        $validatedData['products_count'] = 0;
        $validatedData['is_featured'] = $request->input('is_featured', false);

        $validatedData['enabled_filters'] = $request->input('enabled_filters', []);

        // Process photo to store relative path
        if ($request->filled('photo')) {
            $validatedData['photo'] = $this->convertToRelativePath($request->photo);
        }

        // Compute level, path, sort_order
        $parentId = $request->parent_id;
        if ($parentId) {
            $parent = Category::findOrFail($parentId);
            $validatedData['level'] = $parent->level + 1;
            $validatedData['path'] = $parent->path ? $parent->path . '/' . $parent->id : (string)$parent->id;

            // Auto sort_order: max of siblings + 1
            if (!$request->has('sort_order')) {
                $maxSort = Category::where('parent_id', $parentId)->max('sort_order') ?? 0;
                $validatedData['sort_order'] = $maxSort + 1;
            }

            // Update parent
            $parent->update([
                'children_count' => $parent->children_count + 1,
                'has_children' => true,
            ]);
        } else {
            $validatedData['level'] = 0;
            $validatedData['path'] = null;

            // Auto sort_order for root: max of roots + 1
            if (!$request->has('sort_order')) {
                $maxSort = Category::whereNull('parent_id')->max('sort_order') ?? 0;
                $validatedData['sort_order'] = $maxSort + 1;
            }
        }

        $category = Category::create($validatedData);
        $category->brands()->sync($request->input('brands', []));

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
        $all_cats = Category::orderBy('title', 'ASC')->get();
        $brands = \App\Models\Brand::orderBy('title')->get();
        $available_filters = [
            'price' => 'Price Range',
            'brand' => 'Brands',
            'rating' => 'Customer Ratings',
            'discount' => 'Discounts',
        ];
        return view('backend.category.edit', compact('category', 'all_cats', 'brands', 'available_filters'));
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
            'parent_id' => 'nullable|exists:categories,id',
            'sort_order' => 'nullable|integer',
            'is_featured' => 'boolean',
            'seo_title' => 'nullable|string',
            'seo_description' => 'nullable|string',
            'enabled_filters' => 'nullable|array',
            'enabled_filters.*' => 'string|in:price,brand,rating,discount',
            'brands' => 'nullable|array',
            'brands.*' => 'exists:brands,id',
        ]);

        $validatedData['code_locked'] = $request->has('code_locked') ? 1 : 0;
        $validatedData['is_featured'] = $request->input('is_featured', false);

        // Determine if title was changed AND code is not locked
        $isLockedNow = $request->has('code_locked');
        if (!$isLockedNow && $request->title !== $category->title) {
            $existingCodes = Category::where('id', '!=', $category->id)
                ->pluck('code')
                ->toArray();

            $newCode = generateUniqueCode($request->title, $existingCodes);

            $validatedData['code'] = $newCode;
            $validatedData['code_generated_at'] = now();
        }

        // Process photo to store relative path
        if ($request->filled('photo')) {
            $validatedData['photo'] = $this->convertToRelativePath($request->photo);
        }

        $validatedData['enabled_filters'] = $request->input('enabled_filters', []);

        // Handle parent change
        $newParentId = $request->parent_id;
        $oldParentId = $category->parent_id;
        if ($newParentId != $oldParentId) {
            // Prevent setting itself or descendant as parent
            if ($newParentId && Category::find($newParentId)->path && str_contains(Category::find($newParentId)->path, (string)$category->id)) {
                return back()->with('error', 'Cannot set a descendant as parent.');
            }

            // Update old parent
            if ($oldParentId) {
                $oldParent = Category::find($oldParentId);
                if ($oldParent) {
                    $oldParent->children_count = max(0, $oldParent->children_count - 1);
                    $oldParent->has_children = $oldParent->children_count > 0;
                    $oldParent->save();
                }
            }

            // Update new parent
            if ($newParentId) {
                $newParent = Category::find($newParentId);
                $validatedData['level'] = $newParent->level + 1;
                $validatedData['path'] = $newParent->path ? $newParent->path . '/' . $newParent->id : (string)$newParent->id;
                $newParent->children_count = $newParent->children_count + 1;
                $newParent->has_children = true;
                $newParent->save();
            } else {
                $validatedData['level'] = 0;
                $validatedData['path'] = null;
            }
        }

        $category->update($validatedData);

        $category->brands()->sync($request->input('brands', []));

        return redirect()->route('category.index')->with(
            'success',
            'Category successfully updated'
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

        if ($status) {
            if (count($child_cat_id) > 0) {
                Category::whereIn('id', $child_cat_id)->update(['parent_id' => null, 'level' => 0, 'path' => null]);
            }
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

    /**
     * Convert a full URL to a relative storage path.
     *
     * @param string $path
     * @return string
     */
    protected function convertToRelativePath($path)
    {
        // If the path is already relative (starts with /storage), return it
        if (str_starts_with($path, '/storage')) {
            return $path;
        }

        // Strip the domain and base URL to get the relative path
        $baseUrl = config('app.url');
        return str_replace($baseUrl, '', $path);
    }

    public function tree()
    {
        return view('backend.category.tree');
    }

    /**
     * Get category tree data for the frontend
     */
    public function getTreeData()
    {
        try {
            $categories = Category::with(['children' => function ($query) {
                $query->orderBy('sort_order', 'asc');
            }])
                ->whereNull('parent_id')
                ->orderBy('sort_order', 'asc')
                ->get();

            $treeData = $this->buildTreeStructure($categories);

            return response()->json([
                'success' => true,
                'data' => $treeData,
                'total_count' => Category::count()
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching category tree data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching category data'
            ], 500);
        }
    }

    public function updateTree(Request $request)
    {
        try {
            $categories = $request->input('categories', []);

            // Validate the incoming data
            if (!is_array($categories) || empty($categories)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or empty category data'
                ], 400);
            }

            // Begin a transaction to ensure data consistency
            DB::beginTransaction();

            foreach ($categories as $categoryData) {
                $category = Category::find($categoryData['id']);

                if (!$category) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => "Category with ID {$categoryData['id']} not found"
                    ], 404);
                }

                // Update parent_id, level, and sort_order
                $oldParentId = $category->parent_id;
                $newParentId = $categoryData['parent_id'] ? (int)$categoryData['parent_id'] : null;
                $newLevel = (int)$categoryData['level'];
                $newSortOrder = (int)$categoryData['sort_order'];

                // Update the category
                $category->parent_id = $newParentId;
                $category->level = $newLevel;
                $category->sort_order = $newSortOrder;

                // Update the path
                if ($newParentId) {
                    $parent = Category::findOrFail($newParentId);
                    $category->path = $parent->path ? $parent->path . '/' . $parent->id : (string)$parent->id;
                } else {
                    $category->path = null;
                }

                $category->save();

                // Update parent counts if parent changed
                if ($oldParentId !== $newParentId) {
                    // Decrease old parent's children_count
                    if ($oldParentId) {
                        $oldParent = Category::find($oldParentId);
                        if ($oldParent) {
                            $oldParent->children_count = max(0, $oldParent->children_count - 1);
                            $oldParent->has_children = $oldParent->children_count > 0;
                            $oldParent->save();
                        }
                    }

                    // Increase new parent's children_count
                    if ($newParentId) {
                        $newParent = Category::find($newParentId);
                        if ($newParent) {
                            $newParent->children_count = $newParent->children_count + 1;
                            $newParent->has_children = true;
                            $newParent->save();
                        }
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Category hierarchy updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating category tree: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category tree'
            ], 500);
        }
    }

    /**
     * Build tree structure for categories
     *
     * @param  \Illuminate\Support\Collection  $categories
     * @return array
     */
    protected function buildTreeStructure($categories)
    {
        return $categories->map(function ($category) {
            return [
                'id' => $category->id,
                'title' => $category->title,
                'slug' => $category->slug,
                'level' => $category->level,
                'status' => $category->status,
                'is_featured' => $category->is_featured,
                'children_count' => $category->children_count,
                'parent_id' => $category->parent_id,
                'sort_order' => $category->sort_order,
                'children' => $this->buildTreeStructure($category->children)
            ];
        })->toArray();
    }
}
