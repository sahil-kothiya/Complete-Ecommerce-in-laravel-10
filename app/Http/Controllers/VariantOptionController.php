<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductVariantOption;
use App\Models\ProductVariantType;
use Illuminate\Http\Request;

class VariantOptionController extends Controller
{
    public function index() {
        $options = ProductVariantOption::with('variantType')->orderBy('variant_type_id')->paginate(15);
        return view('backend.variants.options.index', compact('options'));
    }

    public function create() {
        $types = ProductVariantType::active()->orderBy('sort_order')->get();
        return view('backend.variants.options.create', compact('types'));
    }

    public function store(Request $request) {
        $validated = $request->validate([
            'variant_type_id' => 'required|exists:product_variant_types,id',
            'value' => 'required|string|unique:product_variant_options,value',
            'display_value' => 'required|string|max:255',
            'hex_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'integer|min:0'
        ]);
        ProductVariantOption::create($validated);
        return redirect()->route('variant-option.index')->with('success', 'Option created successfully');
    }

    public function edit(ProductVariantOption $variantOption)  // Model binding: {variantOption} from route resolves here
    {
        $option = $variantOption;  // Alias for view consistency
        $types = ProductVariantType::active()->orderBy('sort_order')->get();  // Fetch active types for select
        return view('backend.variants.options.edit', compact('option', 'types'));  // Pass $option and $types
    }

    public function update(Request $request, ProductVariantOption $variantOption) {
        $validated = $request->validate([
            'variant_type_id' => 'required|exists:product_variant_types,id',
            'value' => 'required|string|unique:product_variant_options,value,' . $variantOption->id,
            'display_value' => 'required|string|max:255',
            'hex_color' => 'nullable|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'integer|min:0'
        ]);
        $variantOption->update($validated);
        return redirect()->route('variant-option.index')->with('success', 'Option updated successfully');
    }

    public function destroy(ProductVariantOption $variantOption) {
        $variantOption->delete();
        return redirect()->route('variant-option.index')->with('success', 'Option deleted successfully');
    }

    // API for options by type
    public function apiOptions($typeId) {
        return response()->json(ProductVariantOption::where('variant_type_id', $typeId)->active()->orderBy('sort_order')->get(['id', 'display_value', 'hex_color']));
    }
}