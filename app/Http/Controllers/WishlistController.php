<?php

namespace App\Http\Controllers;
use Auth;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use Helper;

class WishlistController extends Controller
{
    protected $product = null;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function index()
    {
        $wishlistItems = Helper::getAllProductFromWishlist();   // same query, but now in the controller
        return view('frontend.pages.wishlist', compact('wishlistItems'));
    }

    public function wishlist(Request $request, $slug)
    {
        $product = Product::where('slug', $slug)->first();
        if (empty($product)) {
            session()->flash('error', 'Invalid Product');
            return back();
        }

        $userId = auth()->user()->id;
        $variantId = $request->get('variant_id'); // Handle variant from query param (updated in JS)
        $isVariantProduct = $product->has_variants && $variantId;

        // Determine the target for already exists check and price/stock
        $alreadyWishlist = null;
        $price = $product->base_price - ($product->base_price * $product->base_discount / 100);
        $stockCheck = $product->base_stock; // Default to base for non-variants

        if ($isVariantProduct) {
            $variant = ProductVariant::find($variantId);
            if (!$variant || $variant->product_id != $product->id || $variant->status !== 'active') {
                session()->flash('error', 'Invalid Variant');
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
            session()->flash('error', 'You already placed this item in wishlist');
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

        session()->flash('success', 'Product successfully added to wishlist');
        return back();
    }

    public function wishlistDelete($id)
    {
        $wishlist = Wishlist::find($id);
        if ($wishlist && $wishlist->user_id === auth()->user()->id) { // Security: ensure user owns it
            $wishlist->delete();
            
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => 'Wishlist successfully removed'
                ]);
            }
            
            session()->flash('success', 'Wishlist successfully removed');
            return back();
        }
        
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'error' => 'Error please try again'
            ], 400);
        }
        
        session()->flash('error', 'Error please try again');
        return back();
    }

    public function toggle(Request $request, $slug) {
        $product = Product::where('slug', $slug)->first();
        if (empty($product)) {
            return response()->json(['success' => false, 'message' => 'Invalid Product'], 404);
        }

        $userId = auth()->id();
        $variantId = $request->get('variant_id');
        $isVariantProduct = $product->has_variants && $variantId;

        if ($isVariantProduct) {
            $variant = ProductVariant::find($variantId);
            if (!$variant || $variant->product_id != $product->id || $variant->status !== 'active') {
                return response()->json(['success' => false, 'message' => 'Invalid Variant'], 400);
            }

            $existing = Wishlist::where('user_id', $userId)
                ->whereNull('cart_id')
                ->where('product_id', $product->id)
                ->where('variant_id', $variantId)
                ->first();
        } else {
            $existing = Wishlist::where('user_id', $userId)
                ->whereNull('cart_id')
                ->where('product_id', $product->id)
                ->whereNull('variant_id')
                ->first();
        }

        if ($existing) {
            $existing->delete();
            $added = false;
        } else {
            $price = $isVariantProduct
                ? $variant->discounted_price
                : ($product->base_price - ($product->base_price * $product->base_discount / 100));

            $wishlist = new Wishlist;
            $wishlist->user_id = $userId;
            $wishlist->product_id = $product->id;
            $wishlist->variant_id = $isVariantProduct ? $variantId : null;
            $wishlist->price = $price;
            $wishlist->quantity = 1;
            $wishlist->amount = $price * $wishlist->quantity;
            $wishlist->save();

            $added = true;
        }

        return response()->json([
            'success' => true,
            'action' => $added ? 'added' : 'removed',
            'cart_count' => Helper::getAllProductFromCart()->count(),
            'wishlist_count' => Helper::getAllProductFromWishlist()->count()
        ]);
    }

    public function check(Request $request) {
        $inWishlist = Wishlist::where('product_slug', $request->slug)
            ->where('variant_id', $request->variant_id)
            ->where('user_id', auth()->id())
            ->exists();
        return response()->json(['in_wishlist' => $inWishlist]);
    }
}