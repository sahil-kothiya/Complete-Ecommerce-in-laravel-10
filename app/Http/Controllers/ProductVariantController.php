<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantCombination;
use App\Models\ProductVariantOption;

class ProductVariantController extends Controller
{
    /**
     * Generate variants for a product from selected option sets.
     *
     * Expected JSON payload:
     * {
     *   "options": {
     *     "<type_id>": [<option_id>, <option_id2>, ...],
     *     "<type_id2>": [...]
     *   },
     *   "base_price": 1000,
     *   "stock": 10
     * }
     */
    public function generate(Request $request, $productId)
    {
        $data = $request->validate([
            'options' => 'required|array|min:1',
            'base_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
        ]);

        $product = Product::findOrFail($productId);

        // Normalize input: type_id => [option_ids]
        $optionSets = $data['options'];

        // Validate option ids exist and belong to the provided types
        $allOptionIds = collect($optionSets)->flatten()->unique()->values()->all();
        $foundCount = ProductVariantOption::whereIn('id', $allOptionIds)->count();
        if ($foundCount !== count($allOptionIds)) {
            return response()->json(['message' => 'Some option ids are invalid'], 422);
        }

        // Build arrays for Cartesian product while retaining type order
        $typeIds = array_keys($optionSets);
        $arraysForProduct = array_values($optionSets);

        $combos = $this->cartesianProduct($arraysForProduct); // returns arrays of option ids in same order as typeIds

        if (empty($combos)) {
            return response()->json(['message' => 'No combinations generated.'], 422);
        }

        $created = [];
        DB::beginTransaction();
        try {
            foreach ($combos as $combo) {
                // build variant_values mapping type_id => option_id
                $variantValues = [];
                foreach ($combo as $i => $optionId) {
                    $variantValues[$typeIds[$i]] = (int)$optionId;
                }

                // Generate SKU — customizable
                $sku = $this->generateSku($product, $variantValues);

                // create variant
                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $sku,
                    'price' => $data['base_price'] ?? ($product->price ?? 0),
                    'discount' => null,
                    'stock' => $data['stock'] ?? 0,
                    'variant_values' => $variantValues,
                    'images' => null,
                    'status' => 'active'
                ]);

                // create pivot entries for each option
                foreach ($combo as $optionId) {
                    ProductVariantCombination::create([
                        'product_variant_id' => $variant->id,
                        'variant_option_id' => $optionId
                    ]);
                }

                $created[] = $variant;
            }

            // flip has_variants on product and set base_price
            $product->update([
                'has_variants' => true,
                'base_price' => $data['base_price'] ?? $product->base_price
            ]);

            DB::commit();

            return response()->json(['created' => $created], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create variants', 'error' => $e->getMessage()], 500);
        }
    }

    protected function cartesianProduct(array $input)
    {
        // Input is array of arrays; return Cartesian product as array of arrays.
        $result = [[]];
        foreach ($input as $key => $values) {
            $append = [];
            foreach ($result as $product) {
                foreach ($values as $v) {
                    $tmp = $product;
                    $tmp[] = $v;
                    $append[] = $tmp;
                }
            }
            $result = $append;
        }
        return $result;
    }

    protected function generateSku(Product $product, array $variantValues): string
    {
        // Example SKU: P{product_id}-{optionIdsJoined}-{8charHash}
        $ids = implode('-', array_values($variantValues));
        $hash = substr(md5($product->id . $ids . microtime(true)), 0, 8);
        return strtoupper("P{$product->id}-{$ids}-{$hash}");
    }
}