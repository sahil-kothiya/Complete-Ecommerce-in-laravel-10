<?php

namespace App\Http\Controllers;
use Auth;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;

class WishlistController extends Controller
{
    protected $product = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function wishlist($slug)
    {
        $product = Product::where('slug', $slug)->first();
        if (empty($product)) {
            request()->session()->flash('error', 'Invalid Product');
            return back();
        }

        $userId = auth()->user()->id;
        $variantId = request()->get('variant_id'); // Handle variant from query param (updated in JS)
        $isVariantProduct = $product->has_variants && $variantId;

        // Determine the target for already exists check and price/stock
        $alreadyWishlist = null;
        $price = $product->base_price - ($product->base_price * $product->base_discount / 100);
        $stockCheck = $product->base_stock; // Default to base for non-variants

        if ($isVariantProduct) {
            $variant = ProductVariant::find($variantId);
            if (!$variant || $variant->product_id != $product->id || $variant->status !== 'active') {
                request()->session()->flash('error', 'Invalid Variant');
                return back();
            }
            $price = $variant->discounted_price;
            $stockCheck = $variant->stock;

            // Check already exists with variant_id
            $alreadyWishlist = Wishlist::where('user_id', $userId)
                ->whereNull('cart_id')
                ->where('product_id', $product->id)
                ->where('variant_id', $variantId)
                ->first();
        } else {
            // For non-variant or base product
            $alreadyWishlist = Wishlist::where('user_id', $userId)
                ->whereNull('cart_id')
                ->where('product_id', $product->id)
                ->whereNull('variant_id') // Ensure no variant for base
                ->first();
        }

        if ($alreadyWishlist) {
            request()->session()->flash('error', 'You already placed this item in wishlist');
            return back();
        }

        // NO STOCK CHECK FOR WISHLIST - Allow adding even if out of stock (wishlist is for future)
        // Removed: if ($stockCheck < 1 || $stockCheck <= 0) return back()->with('error','Stock not sufficient!.');

        $wishlist = new Wishlist;
        $wishlist->user_id = $userId;
        $wishlist->product_id = $product->id;
        $wishlist->variant_id = $isVariantProduct ? $variantId : null; // Set variant_id if applicable
        $wishlist->price = $price;
        $wishlist->quantity = 1;
        $wishlist->amount = $wishlist->price * $wishlist->quantity;

        $wishlist->save();

        request()->session()->flash('success', 'Product successfully added to wishlist');
        return back();
    }

    public function wishlistDelete(Request $request)
    {
        $wishlist = Wishlist::find($request->id);
        if ($wishlist && $wishlist->user_id === auth()->user()->id) { // Security: ensure user owns it
            $wishlist->delete();
            request()->session()->flash('success', 'Wishlist successfully removed');
            return back();
        }
        request()->session()->flash('error', 'Error please try again');
        return back();
    }
}