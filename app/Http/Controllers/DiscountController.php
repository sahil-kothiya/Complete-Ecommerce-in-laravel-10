<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscountController extends Controller
{
    public function index()
    {
        $discounts = Discount::orderBy('starts_at', 'desc')->paginate(10);
        return view('backend.discount.index', compact('discounts'));
    }

    public function create()
    {
        $categories = Category::select('id', 'title')->get();
        return view('backend.discount.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title'        => 'required|string|max:255',
            'type'         => 'required|in:percentage,amount',
            'value'        => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->type === 'percentage' && $value > 100) {
                        $fail('Percentage discount cannot be more than 100.');
                    }
                },
            ],
            'starts_at'    => 'required|date',
            'ends_at'      => 'required|date|after:starts_at',
            'categories'   => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'is_active'    => 'nullable|boolean',
        ]);

        $discount = Discount::create([
            'title'      => $validated['title'],
            'type'       => $validated['type'],
            'value'      => $validated['value'],
            'starts_at'  => $validated['starts_at'],
            'ends_at'    => $validated['ends_at'],
            'is_active'  => $request->has('is_active'),
        ]);

        $discount->categories()->sync($validated['categories']);

        return redirect()->route('discount.index')->with('success', 'Discount created successfully!');
    }

    public function edit(Discount $discount)
    {
        $discount->load('categories');
        $categories = Category::where('status', 'active')->orderBy('title')->get();
        $selectedCategoryIds = $discount->categories->pluck('id')->toArray();

        return view('backend.discount.edit', compact('discount', 'categories', 'selectedCategoryIds'));
    }

    public function update(Request $request, Discount $discount)
    {
        try {
            // Validate the request
            $validated = $request->validate([
                'title'        => 'required|string|max:255',
                'type'         => 'required|in:percentage,amount',
                'value'        => [
                    'required',
                    'numeric',
                    'min:0',
                    function ($attribute, $value, $fail) use ($request) {
                        if ($request->input('type') === 'percentage' && $value > 100) {
                            $fail('Percentage discount cannot be more than 100.');
                        }
                    },
                ],
                'starts_at'    => 'required|date|before_or_equal:ends_at',
                'ends_at'      => 'required|date|after_or_equal:starts_at',
                'categories'   => 'required|array|min:1',
                'categories.*' => 'exists:categories,id',
                'is_active'    => 'nullable|boolean',
            ]);

            // Log the validated data for debugging
            Log::info('Validated data:', $validated);

            // Update the discount
            $discount->update([
                'title'      => $validated['title'],
                'type'       => $validated['type'],
                'value'      => $validated['value'],
                'starts_at'  => $validated['starts_at'],
                'ends_at'    => $validated['ends_at'],
                'is_active'  => $request->input('is_active', 0), // Default to 0 if not provided
            ]);

            // Sync categories
            $discount->categories()->sync($validated['categories']);

            return redirect()->route('discount.index')->with('success', 'Discount updated successfully.');
        } catch (\Exception $e) {
            // Log the error
            Log::error('Discount update failed: ' . $e->getMessage());
            return redirect()->back()->withErrors(['error' => 'Failed to update discount. Please try again.'])->withInput();
        }
    }
    public function destroy(Discount $discount)
    {
        $discount->delete();
        return redirect()->route('discount.index')->with('success', 'Discount deleted successfully.');
    }
}
