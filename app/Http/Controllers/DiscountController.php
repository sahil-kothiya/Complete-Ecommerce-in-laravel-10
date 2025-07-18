<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $discounts = Discount::orderBy('starts_at', 'desc')->paginate(10);
        return view('backend.discount.index', compact('discounts'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $categories = Category::select('id', 'title')->get();
        return view('backend.discount.create', compact('categories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:percentage,amount',
            'value'       => 'required|numeric|min:0',
            'starts_at'   => 'required|date',
            'ends_at'     => 'required|date|after:starts_at',
            'categories'  => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'is_active'   => 'nullable|boolean',
        ]);

        // Create discount
        $discount = Discount::create([
            'title'      => $validated['title'],
            'type'       => $validated['type'],
            'value'      => $validated['value'],
            'starts_at'  => $validated['starts_at'],
            'ends_at'    => $validated['ends_at'],
            'is_active'  => $request->has('is_active'),
        ]);

        // Attach categories
        $discount->categories()->sync($validated['categories']);

        $discount->categories()->syncWithPivotValues($validated['categories'], [
            'created_at' => now(),
        ]);

        return redirect()->route('discount.index')->with('success', 'Discount created successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Discount $discount)
    {
        // Load the categories related to the discount
        $discount->load('categories');

        // Get all categories for the multi-select
        $categories = Category::where('status', 'active')->orderBy('title')->get();

        // Extract selected category IDs
        $selectedCategoryIds = $discount->categories->pluck('id')->toArray();

        // Pass data to the edit view
        return view('backend.discount.edit', compact('discount', 'categories', 'selectedCategoryIds'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Discount $discount)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:percentage,amount',
            'value'        => 'required|numeric|min:0',
            'starts_at'    => 'required|date|before_or_equal:ends_at',
            'ends_at'      => 'required|date|after_or_equal:starts_at',
            'is_active'    => 'sometimes|boolean',
            'categories'   => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
        ]);

        $validated['is_active'] = $request->has('is_active');

        $discount->update($validated);

        // Sync updated categories
        $discount->categories()->syncWithPivotValues($validated['categories'], [
            'updated_at' => now(),
        ]);

        return redirect()->route('discount.index')->with('success', 'Discount updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Discount $discount)
    {
        $discount->delete();
        return redirect()->route('discount.index')->with('success', 'Discount deleted successfully.');
    }
}
