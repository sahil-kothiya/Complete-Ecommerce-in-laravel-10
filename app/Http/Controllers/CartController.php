<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Add a product or variant to the cart.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function singleAddToCart(Request $request)
    {
        $request->validate([
            'slug' => 'required|exists:products,slug',
            'quant' => 'required|array',
            'quant.1' => 'required|integer|min:1',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $product = Product::where('slug', $request->slug)->first();
        $quantity = (int) $request->quant[1];
        $variant = null;

        // If a variant_id is provided, use the variant's stock and price
        if ($request->filled('variant_id')) {
            $variant = ProductVariant::find($request->variant_id);
            if (!$variant || $variant->product_id !== $product->id || $variant->status !== 'active') {
                return back()->with('error', 'Invalid product variant.');
            }
            if ($variant->stock < $quantity) {
                return back()->with('error', 'Out of stock for the selected variant.');
            }
            $price = $variant->discounted_price;
        } else {
            // Use base product stock and price if no variant is selected
            if ($product->has_variants) {
                return back()->with('error', 'Please select a product variant.');
            }
            if ($product->stock < $quantity) {
                return back()->with('error', 'Out of stock for this product.');
            }
            $price = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);
        }

        // Check for existing cart item
        $cart = Cart::where('user_id', Auth::id())
            ->whereNull('order_id')
            ->where('product_id', $product->id)
            ->when($variant, fn($query) => $query->where('variant_id', $variant->id))
            ->when(!$variant, fn($query) => $query->whereNull('variant_id'))
            ->first();

        try {
            if ($cart) {
                // Update existing cart item
                $newQuantity = $cart->quantity + $quantity;
                $stock = $variant ? $variant->stock : $product->stock;
                if ($stock < $newQuantity) {
                    return back()->with('error', 'Insufficient stock available.');
                }
                $cart->quantity = $newQuantity;
                $cart->amount = $price * $newQuantity;
                $cart->save();
            } else {
                // Create new cart item
                $cart = new Cart();
                $cart->fill([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'variant_id' => $variant ? $variant->id : null,
                    'price' => $price,
                    'quantity' => $quantity,
                    'amount' => $price * $quantity,
                    'status' => 'new',
                ]);
                $cart->save();
            }

            // Update wishlist if exists
            Wishlist::where('user_id', Auth::id())
                ->whereNull('cart_id')
                ->where('product_id', $product->id)
                ->update(['cart_id' => $cart->id]);

            return back()->with('success', 'Product successfully added to cart.');
        } catch (\Exception $e) {
            Log::error('Failed to add to cart: ' . $e->getMessage());
            return back()->with('error', 'An error occurred while adding to cart. Please try again.');
        }
    }

    /**
     * Remove a cart item.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cartDelete(Request $request, $id)
    {
        try {
            $cart = Cart::where('id', $id)->where('user_id', Auth::id())->where('order_id', null)->first();

            if ($cart) {
                $cart->delete();

                $cartItems = Cart::with('product', 'variant')
                    ->where('user_id', Auth::id())
                    ->where('order_id', null)
                    ->get();
                $cartSubtotal = $cartItems->sum('amount');
                $categorySaved = $cartItems->sum(function ($item) {
                    $original = $item->variant ? $item->variant->price : $item->product->base_price;
                    return ($original - ($original * (1 - ($item->variant ? $item->variant->discount : $item->product->base_discount) / 100))) * $item->quantity;
                });
                $couponDiscount = session('coupon')['value'] ?? 0;
                $finalAmount = max(0, $cartSubtotal - min($couponDiscount, $cartSubtotal));

                return response()->json([
                    'success' => true,
                    'cartSubtotal' => $cartSubtotal,
                    'categorySaved' => $categorySaved,
                    'couponDiscount' => $couponDiscount,
                    'finalAmount' => $finalAmount
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Cart item not found.'], 404);
        } catch (\Exception $e) {
            \Log::error('Cart delete failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }

    /**
     * Update cart quantities.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cartUpdate(Request $request)
    {
        try {
            $userId = Auth::id();
            $cartIds = $request->input('qty_id', []);
            $quantities = $request->input('quant', []);

            foreach ($cartIds as $index => $cartId) {
                $cart = Cart::where('id', $cartId)->where('user_id', $userId)->where('order_id', null)->first();

                if ($cart) {
                    $quantity = $quantities[$cartId] ?? 1;
                    $quantity = max(1, min(100, (int)$quantity)); // Validate quantity

                    $cart->quantity = $quantity;
                    $cart->save();

                    // Recalculate total (example logic)
                    if ($cart->variant) {
                        $originalPrice = $cart->variant->price ?? 0;
                        $discount = $cart->variant->discount ?? 0;
                    } else {
                        $originalPrice = $cart->product->base_price ?? 0;
                        $discount = $cart->product->base_discount ?? 0;
                    }
                    $discountedPrice = $originalPrice * (1 - ($discount / 100));
                    $cart->amount = $discountedPrice * $quantity;
                    $cart->save();
                }
            }

            // Recalculate cart summary
            $cartItems = Cart::with('product', 'variant')
                ->where('user_id', $userId)
                ->where('order_id', null)
                ->get();
            $cartSubtotal = $cartItems->sum('amount');
            $categorySaved = $cartItems->sum(function ($item) {
                $original = $item->variant ? $item->variant->price : $item->product->base_price;
                return ($original - ($original * (1 - ($item->variant ? $item->variant->discount : $item->product->base_discount) / 100))) * $item->quantity;
            });
            $couponDiscount = session('coupon')['value'] ?? 0;
            $finalAmount = max(0, $cartSubtotal - min($couponDiscount, $cartSubtotal));

            return response()->json([
                'success' => true,
                'cartSubtotal' => $cartSubtotal,
                'categorySaved' => $categorySaved,
                'couponDiscount' => $couponDiscount,
                'finalAmount' => $finalAmount
            ]);
        } catch (\Exception $e) {
            \Log::error('Cart update failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred.'], 500);
        }
    }

    /**
     * Display the checkout page.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function checkout(Request $request)
    {
        return view('frontend.pages.checkout');
    }
}