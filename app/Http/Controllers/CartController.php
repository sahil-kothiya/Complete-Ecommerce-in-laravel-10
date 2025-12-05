<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    protected $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Add a product or variant to the cart (Amazon/Flipkart style).
     * If item exists, update quantity. If stock available, add silently.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function singleAddToCart(Request $request)
    {
        try {
            // Check if user is authenticated
            if (!Auth::check()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please login to add items to cart.',
                        'redirect' => route('login')
                    ], 401);
                }
                return redirect()->route('login')->with('error', 'Please login to add items to cart.');
            }

            // Validate request
            $validated = $request->validate([
                'slug' => 'required|exists:products,slug',
                'quantity' => 'required|integer|min:1|max:1000',
                'variant_id' => 'nullable|integer|exists:product_variants,id',
            ]);

            $product = Product::where('slug', $validated['slug'])->firstOrFail();
            $quantity = (int) $validated['quantity'];
            $variant = null;
            $price = 0;
            $stock = 0;

            // Handle variant products
            if ($product->has_variants) {
                if (empty($validated['variant_id'])) {
                    return $this->jsonOrRedirect(
                        $request,
                        false,
                        'Please select a product variant.',
                        422
                    );
                }

                $variant = ProductVariant::where('id', $validated['variant_id'])
                    ->where('product_id', $product->id)
                    ->where('status', 'active')
                    ->first();

                if (!$variant) {
                    return $this->jsonOrRedirect(
                        $request,
                        false,
                        'Invalid product variant.',
                        422
                    );
                }

                // Get variant stock
                $stock = (int) $variant->stock;

                // Check if requested quantity exceeds stock
                if ($stock < $quantity) {
                    return $this->jsonOrRedirect(
                        $request,
                        false,
                        "Only {$stock} item(s) available for the selected variant.",
                        422
                    );
                }

                // Calculate variant price
                $price = $variant->price * (1 - ($variant->discount ?? 0) / 100);
            } else {
                // Handle non-variant products
                $stock = (int) $product->base_stock;

                if ($stock < $quantity) {
                    return $this->jsonOrRedirect(
                        $request,
                        false,
                        "Only {$stock} item(s) available.",
                        422
                    );
                }

                // Calculate base price
                $price = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);
            }

            // Use transaction for data integrity
            DB::beginTransaction();

            try {
                // Check for existing cart item
                $cart = Cart::where('user_id', Auth::id())
                    ->whereNull('order_id')
                    ->where('product_id', $product->id)
                    ->when($variant, function ($query) use ($variant) {
                        return $query->where('variant_id', $variant->id);
                    })
                    ->when(!$variant, function ($query) {
                        return $query->whereNull('variant_id');
                    })
                    ->lockForUpdate() // Prevent race conditions
                    ->first();

                if ($cart) {
                    // Item already in cart - Amazon/Flipkart style update
                    $newQuantity = $cart->quantity + $quantity;

                    // Check if new quantity exceeds stock
                    if ($stock < $newQuantity) {
                        // Calculate how many more can be added
                        $canAddMore = $stock - $cart->quantity;

                        if ($canAddMore <= 0) {
                            DB::rollBack();
                            return $this->jsonOrRedirect(
                                $request,
                                false,
                                "You already have maximum available quantity ({$cart->quantity}) in cart. Only {$stock} item(s) in stock.",
                                422
                            );
                        }

                        // Add only what's available
                        $cart->quantity = $stock;
                        $cart->amount = $price * $stock;
                        $cart->save();

                        DB::commit();

                        return $this->jsonOrRedirect(
                            $request,
                            true,
                            "Only {$canAddMore} more item(s) available. Cart updated to maximum quantity ({$stock})."
                        );
                    }

                    // Stock is sufficient - update cart
                    $cart->quantity = $newQuantity;
                    $cart->amount = $price * $newQuantity;
                    $cart->price = $price; // Update price in case it changed
                    $cart->save();

                    DB::commit();

                    return $this->jsonOrRedirect(
                        $request,
                        true,
                        "Cart updated! Now you have {$newQuantity} item(s) in cart."
                    );
                } else {
                    // Create new cart item
                    $cart = Cart::create([
                        'user_id' => Auth::id(),
                        'product_id' => $product->id,
                        'variant_id' => $variant ? $variant->id : null,
                        'price' => $price,
                        'quantity' => $quantity,
                        'amount' => $price * $quantity,
                        'status' => 'new',
                    ]);

                    // Update wishlist if exists
                    Wishlist::where('user_id', Auth::id())
                        ->whereNull('cart_id')
                        ->where('product_id', $product->id)
                        ->when($variant, fn($query) => $query->where('variant_id', $variant->id))
                        ->update(['cart_id' => $cart->id]);

                    DB::commit();

                    return $this->jsonOrRedirect(
                        $request,
                        true,
                        'Product successfully added to cart!'
                    );
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->jsonOrRedirect(
                $request,
                false,
                $e->validator->errors()->first(),
                422
            );
        } catch (\Exception $e) {
            Log::error('Failed to add to cart: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'slug' => $request->slug ?? null,
                'quantity' => $request->quantity ?? null,
                'variant_id' => $request->variant_id ?? null,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonOrRedirect(
                $request,
                false,
                'An error occurred while adding to cart. Please try again.',
                500
            );
        }
    }

    /**
     * Add a single quantity of a product to the cart via GET (route: /add-to-cart/{slug}).
     * For products with variants, require variant selection.
     *
     * @param Request $request
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function addToCart(Request $request, $slug)
    {
        try {
            $product = Product::where('slug', $slug)->firstOrFail();

            // Disallow simple GET add for variant products - user must select variant
            if ($product->has_variants) {
                return $this->jsonOrRedirect(
                    $request,
                    false,
                    'Please select a product variant.',
                    422
                );
            }

            $quantity = 1;

            $stock = (int) $product->base_stock;
            if ($stock < $quantity) {
                return $this->jsonOrRedirect(
                    $request,
                    false,
                    "Only {$stock} item(s) available.",
                    422
                );
            }

            $price = $product->base_price * (1 - ($product->base_discount ?? 0) / 100);

            DB::beginTransaction();

            try {
                $cart = Cart::where('user_id', Auth::id())
                    ->whereNull('order_id')
                    ->where('product_id', $product->id)
                    ->whereNull('variant_id')
                    ->lockForUpdate()
                    ->first();

                if ($cart) {
                    $newQuantity = $cart->quantity + $quantity;

                    if ($stock < $newQuantity) {
                        $canAddMore = $stock - $cart->quantity;

                        if ($canAddMore <= 0) {
                            DB::rollBack();
                            return $this->jsonOrRedirect(
                                $request,
                                false,
                                "You already have maximum available quantity ({$cart->quantity}) in cart. Only {$stock} item(s) in stock.",
                                422
                            );
                        }

                        $cart->quantity = $stock;
                        $cart->amount = $price * $stock;
                        $cart->save();

                        DB::commit();

                        return $this->jsonOrRedirect(
                            $request,
                            true,
                            "Only {$canAddMore} more item(s) available. Cart updated to maximum quantity ({$stock})."
                        );
                    }

                    $cart->quantity = $newQuantity;
                    $cart->amount = $price * $newQuantity;
                    $cart->price = $price;
                    $cart->save();

                    DB::commit();

                    return $this->jsonOrRedirect(
                        $request,
                        true,
                        "Cart updated! Now you have {$newQuantity} item(s) in cart."
                    );
                }

                // Create new cart item
                $cart = Cart::create([
                    'user_id' => Auth::id(),
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'price' => $price,
                    'quantity' => $quantity,
                    'amount' => $price * $quantity,
                    'status' => 'new',
                ]);

                Wishlist::where('user_id', Auth::id())
                    ->whereNull('cart_id')
                    ->where('product_id', $product->id)
                    ->update(['cart_id' => $cart->id]);

                DB::commit();

                return $this->jsonOrRedirect(
                    $request,
                    true,
                    'Product successfully added to cart!'
                );

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Failed to add to cart (GET): ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'slug' => $slug,
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonOrRedirect(
                $request,
                false,
                'An error occurred while adding to cart. Please try again.',
                500
            );
        }
    }

    /**
     * Remove a cart item.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cartDelete(Request $request, $id)
    {
        try {
            $cart = Cart::where('id', $id)
                ->where('user_id', Auth::id())
                ->whereNull('order_id')
                ->first();

            if (!$cart) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cart item not found.'
                ], 404);
            }

            $cart->delete();

            // Recalculate cart totals
            $cartSummary = $this->calculateCartSummary();

            // Check if request is AJAX
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Item removed from cart.',
                    ...$cartSummary
                ]);
            }

            // For regular form submission, redirect back with success message
            return redirect()->back()->with('success', 'Item removed from cart.');

        } catch (\Exception $e) {
            Log::error('Cart delete failed: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'cart_id' => $id
            ]);

            // Check if request is AJAX
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'An error occurred while removing item.'
                ], 500);
            }

            // For regular form submission, redirect back with error message
            return redirect()->back()->with('error', 'An error occurred while removing item.');
        }
    }

    /**
     * Update cart quantities.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cartUpdate(Request $request)
    {
        try {
            $validated = $request->validate([
                'qty_id' => 'required|array',
                'qty_id.*' => 'required|integer|exists:carts,id',
                'quant' => 'required|array',
                'quant.*' => 'required|integer|min:1|max:100',
            ]);

            $userId = Auth::id();
            $cartIds = $validated['qty_id'];
            $quantities = $validated['quant'];

            DB::beginTransaction();

            try {
                foreach ($cartIds as $index => $cartId) {
                    $cart = Cart::where('id', $cartId)
                        ->where('user_id', $userId)
                        ->whereNull('order_id')
                        ->lockForUpdate()
                        ->first();

                    if (!$cart) {
                        continue;
                    }

                    $quantity = isset($quantities[$cartId])
                        ? max(1, min(100, (int)$quantities[$cartId]))
                        : 1;

                    // Get stock limit
                    if ($cart->variant_id) {
                        $variant = ProductVariant::find($cart->variant_id);
                        if (!$variant) {
                            continue;
                        }

                        $stock = (int) $variant->stock;

                        if ($stock < $quantity) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Insufficient stock for variant. Only {$stock} available."
                            ], 422);
                        }

                        $originalPrice = $variant->price ?? 0;
                        $discount = $variant->discount ?? 0;
                    } else {
                        $product = $cart->product;
                        if (!$product) {
                            continue;
                        }

                        $stock = (int) $product->base_stock;

                        if ($stock < $quantity) {
                            DB::rollBack();
                            return response()->json([
                                'success' => false,
                                'message' => "Insufficient stock. Only {$stock} available."
                            ], 422);
                        }

                        $originalPrice = $product->base_price ?? 0;
                        $discount = $product->base_discount ?? 0;
                    }

                    // Calculate discounted price
                    $discountedPrice = $originalPrice * (1 - ($discount / 100));

                    // Update cart
                    $cart->quantity = $quantity;
                    $cart->price = $discountedPrice;
                    $cart->amount = $discountedPrice * $quantity;
                    $cart->save();
                }

                DB::commit();

                // Recalculate cart summary
                $cartSummary = $this->calculateCartSummary();

                return response()->json([
                    'success' => true,
                    'message' => 'Cart updated successfully.',
                    ...$cartSummary
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Cart update failed: ' . $e->getMessage(), [
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating cart.'
            ], 500);
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

    /**
     * Calculate cart summary with totals and discounts.
     *
     * @return array
     */
    private function calculateCartSummary(): array
    {
        $cartItems = Cart::with(['product', 'variant'])
            ->where('user_id', Auth::id())
            ->whereNull('order_id')
            ->get();

        $cartSubtotal = $cartItems->sum('amount');

        $categorySaved = $cartItems->sum(function ($item) {
            $original = $item->variant
                ? ($item->variant->price ?? 0)
                : ($item->product->base_price ?? 0);

            $discount = $item->variant
                ? ($item->variant->discount ?? 0)
                : ($item->product->base_discount ?? 0);

            $discounted = $original * (1 - $discount / 100);
            $savings = ($original - $discounted) * $item->quantity;

            return $savings;
        });

        $couponDiscount = session('coupon')['value'] ?? 0;
        $finalAmount = max(0, $cartSubtotal - min($couponDiscount, $cartSubtotal));

        return [
            'cartSubtotal' => $cartSubtotal,
            'categorySaved' => $categorySaved,
            'couponDiscount' => $couponDiscount,
            'finalAmount' => $finalAmount,
            'itemCount' => $cartItems->count()
        ];
    }

    /**
     * Helper to return JSON or redirect based on request type.
     *
     * @param Request $request
     * @param bool $success
     * @param string $message
     * @param int $statusCode
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    private function jsonOrRedirect(Request $request, bool $success, string $message, int $statusCode = 200)
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => $success,
                'message' => $message
            ], $success ? 200 : $statusCode);
        }

        return back()->with(
            $success ? 'success' : 'error',
            $message
        );
    }
}
