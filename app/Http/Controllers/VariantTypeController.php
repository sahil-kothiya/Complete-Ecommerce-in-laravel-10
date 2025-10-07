<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductVariantType;
use Illuminate\Http\Request;

class VariantTypeController extends Controller
{
    public function index() {
        $types = ProductVariantType::orderBy('sort_order')->paginate(15);
        return view('backend.variants.types.index', compact('types'));
    }

    public function create() { return view('backend.variants.types.create'); }

    public function store(Request $request) {
        $validated = $request->validate([
            'name' => 'required|unique:product_variant_types,name',
            'display_name' => 'required|string|max:255',
            'sort_order' => 'integer|min:0'
        ]);
        ProductVariantType::create($validated);
        return redirect()->route('variant-type.index')->with('success', 'Type created successfully');
    }

    public function show(ProductVariantType $variantType)  // Model binding: {variantType} from route resolves here
    {
        $type = $variantType;  // Alias for view consistency
        $type->load('options');  // Eager load options to avoid N+1 queries
        return view('backend.variants.types.show', compact('type'));  // Pass as $type
    }

    public function edit(ProductVariantType $variantType) {
        return view('backend.variants.types.edit', compact('variantType'));
    }

    public function update(Request $request, ProductVariantType $variantType) {
        $validated = $request->validate([
            'name' => 'required|unique:product_variant_types,name,' . $variantType->id,
            'display_name' => 'required|string|max:255',
            'sort_order' => 'integer|min:0'
        ]);
        $variantType->update($validated);
        return redirect()->route('variant-type.index')->with('success', 'Type updated successfully');
    }

    public function destroy(ProductVariantType $variantType) {
        dd('here');
        $variantType->delete();
        return redirect()->route('variant-type.index')->with('success', 'Type deleted successfully');
    }

    // API for dynamic load in product form
    public function apiIndex() {
        return response()->json(ProductVariantType::active()->orderBy('sort_order')->get(['id', 'name', 'display_name']));
    }
}