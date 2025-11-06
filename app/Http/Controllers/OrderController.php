<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Response;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Shipping;
use App\Models\Product;
use App\User;
use PDF;
use Notification;
use Helper;
use Illuminate\Support\Str;
use App\Notifications\StatusNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $orders = Order::with(['user', 'shipping'])
            ->orderBy('id', 'DESC')
            ->paginate(10);

        return view('backend.order.index', compact('orders'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        // Implementation if needed
        return view('backend.order.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'email'          => 'required|email|max:255',
            'phone'          => 'required|string|max:20',
            'country'        => 'required|string|max:100',
            'address1'       => 'required|string|max:255',
            'address2'       => 'nullable|string|max:255',
            'post_code'      => 'nullable|string|max:20',
            'payment_method' => 'required|in:' . implode(',', Order::getValidPaymentMethods()),
            'shipping'       => 'nullable|exists:shippings,id',
        ]);

        if (Helper::cartCount() <= 0) {
            return back()->with('error', 'Your cart is empty.');
        }

        // --- GUEST USER: create or login ---
        $user = Auth::check()
            ? Auth::user()
            : $this->getOrCreateGuestUser($validated['email']);

        // --- STORE CHECKOUT DATA ---
        session([
            'checkout_data' => array_merge($validated, [
                'user_id' => $user->id,
            ])
        ]);

        return match ($validated['payment_method']) {
            Order::PAYMENT_METHOD_PAYPAL => redirect()->route('payment.paypal'),
            Order::PAYMENT_METHOD_STRIPE => app(StripeController::class)->initiate(),
            Order::PAYMENT_METHOD_SQUARE => app(SquareController::class)->initiate(),
            Order::PAYMENT_METHOD_MOLLIE => app(MollieController::class)->initiate(),
            Order::PAYMENT_METHOD_COD   => $this->processCODOrder($user),
            default                     => back()->with('error', 'Invalid payment method.'),
        };
    }

    /**
     * Get or create a guest user (for COD / guest checkout)
     */
    private function getOrCreateGuestUser(string $email): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => 'Guest',
                'password' => bcrypt(Str::random(16)),
                'role'     => 'customer',
                'status'   => 'active',
            ]
        );

        Auth::login($user);
        return $user;
    }

    /**
     * Process Cash on Delivery – now works for both logged-in and guest users
     */
    private function processCODOrder(User $user)
    {
        try {
            DB::beginTransaction();

            $subtotal      = Helper::totalCartPrice($user->id);
            $couponValue   = session('coupon')['value'] ?? 0;
            $shippingCost  = $this->calculateShippingCost(session('checkout_data')['shipping'] ?? null);
            $totalAmount   = $subtotal + $shippingCost - $couponValue;

            if ($totalAmount <= 0) {
                DB::rollBack();
                return back()->with('error', 'Order total cannot be zero.');
            }

            $order = Order::create([
                'user_id'        => $user->id,
                'order_number'   => null, // auto-generated in boot()
                'sub_total'      => $subtotal,
                'coupon'         => $couponValue,
                'shipping_cost'  => $shippingCost,
                'total_amount'   => $totalAmount,
                'quantity'       => Helper::cartCount($user->id),
                'payment_method' => Order::PAYMENT_METHOD_COD,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                'status'         => Order::STATUS_NEW,
                'first_name'     => session('checkout_data')['first_name'],
                'last_name'      => session('checkout_data')['last_name'],
                'email'          => session('checkout_data')['email'],
                'phone'          => session('checkout_data')['phone'],
                'country'        => session('checkout_data')['country'],
                'address1'       => session('checkout_data')['address1'],
                'address2'       => session('checkout_data')['address2'] ?? null,
                'post_code'      => session('checkout_data')['post_code'] ?? null,
                'shipping_id'    => session('checkout_data')['shipping'] ?? null,
            ]);

            $this->moveCartItemsToOrder($order->id, $user->id);

            DB::commit();

            session()->forget(['cart', 'coupon', 'checkout_data']);
            if (!Auth::check()) {
                Auth::logout();
            }

            return redirect()->route('order.success', $order->order_number)
                ->with('success', 'Order placed! #' . $order->order_number);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('COD Order Failed', ['error' => $e->getMessage()]);
            return back()->with('error', 'Failed to place order.');
        }
    }

    /**
     * Calculate shipping cost
     */
    private function calculateShippingCost($shippingId): float
    {
        if (!$shippingId) return 0.0;
        $shipping = Shipping::find($shippingId);
        return $shipping ? (float) $shipping->price : 0.0;
    }

    /**
     * Move cart items (user or guest session) to order
     */
    private function moveCartItemsToOrder(int $orderId, int $userId): void
    {
        Cart::where('user_id', $userId)
            ->whereNull('order_id')
            ->update(['order_id' => $orderId]);

        // Handle guest session cart
        $guestCart = session('cart', []);
        if ($guestCart) {
            foreach ($guestCart as $item) {
                Cart::updateOrCreate(
                    [
                        'user_id'    => $userId,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'] ?? null,
                    ],
                    [
                        'quantity'  => $item['quantity'],
                        'order_id'  => $orderId,
                    ]
                );
            }
            session()->forget('cart');
        }
    }

    public function success(string $orderNumber)
    {
        $order = Order::where('order_number', $orderNumber)
            ->with(['cart_info.product', 'shipping'])
            ->firstOrFail();

        return view('frontend.pages.order-success', compact('order'));
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): View
    {
        $order = Order::with(['cart_info.product', 'user', 'shipping'])
            ->findOrFail($id);

        return view('backend.order.show', compact('order'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $order = Order::findOrFail($id);
        $validStatuses = Order::getValidStatuses();

        return view('backend.order.edit', compact('order', 'validStatuses'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Order::getValidStatuses())
        ]);

        try {
            DB::beginTransaction();

            $oldStatus = $order->status;
            $order->update(['status' => $validated['status']]);

            // Handle status-specific logic
            $this->handleStatusChange($order, $oldStatus, $validated['status']);

            DB::commit();

            Log::info('Order status updated', [
                'order_id' => $order->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status']
            ]);

            return redirect()->route('order.index')
                ->with('success', 'Order status updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order update error: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Failed to update order status.');
        }
    }

    /**
     * Handle status change logic
     */
    private function handleStatusChange(Order $order, string $oldStatus, string $newStatus): void
    {
        // If order is being marked as delivered, update product stock
        if ($newStatus === Order::STATUS_DELIVERED && $oldStatus !== Order::STATUS_DELIVERED) {
            $this->updateProductStock($order);

            // Also mark payment as paid for delivered orders
            if ($order->payment_status === Order::PAYMENT_STATUS_UNPAID) {
                $order->update(['payment_status' => Order::PAYMENT_STATUS_PAID]);
            }
        }

        // If order is being cancelled, restore product stock if it was previously delivered
        if ($newStatus === Order::STATUS_CANCELLED && $oldStatus === Order::STATUS_DELIVERED) {
            $this->restoreProductStock($order);
        }
    }

    /**
     * Update product stock when order is delivered
     */
    private function updateProductStock(Order $order): void
    {
        foreach ($order->cart_info as $cartItem) {
            if ($cartItem->product) {
                $product = $cartItem->product;
                $product->decrement('stock', $cartItem->quantity);

                Log::info('Product stock updated', [
                    'product_id' => $product->id,
                    'quantity_reduced' => $cartItem->quantity,
                    'new_stock' => $product->stock
                ]);
            }
        }
    }

    /**
     * Restore product stock when delivered order is cancelled
     */
    private function restoreProductStock(Order $order): void
    {
        foreach ($order->cart_info as $cartItem) {
            if ($cartItem->product) {
                $product = $cartItem->product;
                $product->increment('stock', $cartItem->quantity);

                Log::info('Product stock restored', [
                    'product_id' => $product->id,
                    'quantity_restored' => $cartItem->quantity,
                    'new_stock' => $product->stock
                ]);
            }
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $order = Order::findOrFail($id);

            // Check if order can be deleted
            if ($order->status === Order::STATUS_DELIVERED) {
                return back()->with('error', 'Cannot delete delivered orders.');
            }

            DB::beginTransaction();

            // If order was delivered, restore stock before deletion
            if ($order->status === Order::STATUS_DELIVERED) {
                $this->restoreProductStock($order);
            }

            // Reset cart items order_id to null so they can be used again
            Cart::where('order_id', $order->id)->update(['order_id' => null]);

            $order->delete();

            DB::commit();

            Log::info('Order deleted', ['order_id' => $id]);

            return redirect()->route('order.index')
                ->with('success', 'Order deleted successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Order deletion error: ' . $e->getMessage(), [
                'order_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return back()->with('error', 'Order not found or cannot be deleted.');
        }
    }

    /**
     * Show order tracking page
     */
    public function orderTrack(): View
    {
        return view('frontend.pages.order-track');
    }

    /**
     * Track specific order
     */
    public function productTrackOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string'
        ]);

        $order = Order::where('user_id', Auth::id())
            ->where('order_number', $validated['order_number'])
            ->first();

        if (!$order) {
            return back()->with('error', 'Invalid order number. Please try again.');
        }

        $message = match ($order->status) {
            Order::STATUS_NEW => 'Your order has been placed. Please wait.',
            Order::STATUS_PROCESS => 'Your order is under processing. Please wait.',
            Order::STATUS_DELIVERED => 'Your order has been successfully delivered.',
            Order::STATUS_CANCELLED => 'Your order has been cancelled. Please contact support if you have questions.',
            default => 'Order status: ' . $order->status_label
        };

        $type = $order->status === Order::STATUS_CANCELLED ? 'error' : 'success';

        return redirect()->route('home')->with($type, $message);
    }

    /**
     * Generate PDF for order
     */
    // public function pdf(Request $request): Response
    // {
    //     $validated = $request->validate([
    //         'id' => 'required|integer|exists:orders,id'
    //     ]);

    //     $order = Order::getAllOrder($validated['id']);

    //     if (!$order) {
    //         abort(404, 'Order not found');
    //     }

    //     $fileName = $order->order_number . '-' . $order->first_name . '.pdf';
    //     $pdf = PDF::loadView('backend.order.pdf', compact('order'));

    //     return $pdf->download($fileName);
    // }

    /**
     * Get income chart data
     */
    public function incomeChart(Request $request): array
    {
        $year = $request->input('year', Carbon::now()->year);

        $orders = Order::with('cart_info')
            ->whereYear('created_at', $year)
            ->where('status', Order::STATUS_DELIVERED)
            ->where('payment_status', Order::PAYMENT_STATUS_PAID)
            ->get()
            ->groupBy(function ($order) {
                return Carbon::parse($order->created_at)->format('m');
            });

        $monthlyData = [];

        foreach ($orders as $month => $monthOrders) {
            $monthlyRevenue = $monthOrders->sum('total_amount');
            $monthlyData[intval($month)] = $monthlyRevenue;
        }

        // Format data for all 12 months
        $chartData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthName = Carbon::createFromFormat('m', $i)->format('F');
            $chartData[$monthName] = number_format($monthlyData[$i] ?? 0, 2, '.', '');
        }

        return $chartData;
    }

    /**
     * Get order statistics
     */
    public function getOrderStats(): array
    {
        return [
            'total_orders' => Order::count(),
            'pending_orders' => Order::whereIn('status', [Order::STATUS_NEW, Order::STATUS_PROCESS])->count(),
            'delivered_orders' => Order::where('status', Order::STATUS_DELIVERED)->count(),
            'cancelled_orders' => Order::where('status', Order::STATUS_CANCELLED)->count(),
            'total_revenue' => Order::getTotalRevenue(),
            'unpaid_orders' => Order::where('payment_status', Order::PAYMENT_STATUS_UNPAID)->count(),
        ];
    }
}
