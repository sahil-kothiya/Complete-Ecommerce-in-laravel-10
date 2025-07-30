<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Mollie\Api\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Helper;
use Mollie\Laravel\Facades\Mollie;

class MollieController extends Controller
{
    /**
     * Create Mollie payment
     */
    public function payment(Request $request = null)
    {
        $checkoutData = session('checkout_data') ?? $request?->all();
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found.');
        }

        // Check if cart is empty
        if (Helper::cartCount() <= 0) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        $cart = Cart::where('user_id', Auth::id())
            ->where('order_id', null)
            ->get()
            ->toArray();

        if (empty($cart)) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        try {
            DB::beginTransaction();

            // Calculate totals
            $subtotal = Helper::totalCartPrice();
            $couponDiscount = session('coupon')['value'] ?? 0;
            $shippingCost = $this->calculateShippingCost($checkoutData['shipping_id'] ?? null);
            $finalTotal = $subtotal + $shippingCost - $couponDiscount;

            // Validate minimum amount (Mollie minimum is €0.01)
            if ($finalTotal < 0.01) {
                DB::rollBack();
                return redirect()->back()->with('error', 'Order total must be at least €0.01');
            }

            // Generate order number and create order
            $orderNumber = Order::generateOrderNumber();
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => Auth::id(),
                'sub_total' => $subtotal,
                'coupon' => $couponDiscount,
                'total_amount' => $finalTotal,
                'quantity' => Helper::cartCount(),
                'payment_method' => Order::PAYMENT_METHOD_MOLLIE,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                'status' => Order::STATUS_NEW,
                'first_name' => $checkoutData['first_name'] ?? Auth::user()->first_name ?? 'N/A',
                'last_name' => $checkoutData['last_name'] ?? Auth::user()->last_name ?? 'N/A',
                'email' => $checkoutData['email'] ?? Auth::user()->email,
                'phone' => $checkoutData['phone'] ?? Auth::user()->phone ?? 'N/A',
                'country' => $checkoutData['country'] ?? 'N/A',
                'address1' => $checkoutData['address1'] ?? 'N/A',
                'address2' => $checkoutData['address2'] ?? null,
                'post_code' => $checkoutData['post_code'] ?? null,
                'shipping_id' => $checkoutData['shipping_id'] ?? null,
            ]);

            // Move cart items to order
            $this->moveCartItemsToOrder($order->id);

            // Create Mollie payment
            $payment = Mollie::api()->payments->create([
                'amount' => [
                    'currency' => 'EUR', // You can make this configurable
                    'value' => number_format($finalTotal, 2, '.', ''),
                ],
                'description' => "Order #{$orderNumber}",
                'redirectUrl' => route('mollie.success', ['order_id' => $order->id]),
                'cancelUrl' => route('mollie.cancel', ['order_id' => $order->id]),
                'webhookUrl' => route('mollie.webhook'),
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'user_id' => Auth::id(),
                ],
                'method' => null, // Let customer choose payment method
                // Uncomment below to restrict to specific methods
                // 'method' => ['ideal', 'creditcard', 'bancontact'],
            ]);

            // Update order with Mollie payment ID
            $order->update(['transaction_id' => $payment->id]);

            DB::commit();

            // Store order ID in session for cancellation handling
            session(['mollie_order_id' => $order->id]);

            Log::info('Mollie payment created', [
                'order_id' => $order->id,
                'mollie_payment_id' => $payment->id,
                'amount' => $finalTotal,
                'user_id' => Auth::id()
            ]);

            // Redirect to Mollie checkout
            return redirect($payment->getCheckoutUrl());
        } catch (ApiException $e) {
            DB::rollBack();
            Log::error('Mollie API Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Mollie Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mollie Payment Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Payment Error: ' . $e->getMessage());
        }
    }

    /**
     * Handle successful payment
     */
    public function success(Request $request): RedirectResponse
    {
        $orderId = $request->query('order_id');

        if (!$orderId) {
            return redirect()->route('home')->with('error', 'Invalid order reference.');
        }

        try {
            $order = Order::findOrFail($orderId);

            // Verify the order belongs to the current user (if logged in)
            if (Auth::check() && $order->user_id !== Auth::id()) {
                return redirect()->route('home')->with('error', 'Unauthorized access to order.');
            }

            if (!$order->transaction_id) {
                return redirect()->route('home')->with('error', 'Invalid payment reference.');
            }

            // Fetch payment status from Mollie
            $payment = Mollie::api()->payments->get($order->transaction_id);

            if ($payment->isPaid()) {
                DB::beginTransaction();

                // Update order status
                $order->update([
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'status' => Order::STATUS_PROCESS
                ]);

                DB::commit();

                // Clear sessions
                session()->forget(['cart', 'coupon', 'checkout_data', 'mollie_order_id']);

                Log::info('Mollie payment successful', [
                    'order_id' => $order->id,
                    'mollie_payment_id' => $payment->id,
                    'user_id' => Auth::id()
                ]);

                return redirect()->route('home')
                    ->with('success', 'Payment completed successfully! Thank you for your purchase.');
            }

            if ($payment->isFailed()) {
                Log::warning('Mollie payment failed', [
                    'order_id' => $order->id,
                    'mollie_payment_id' => $payment->id,
                    'status' => $payment->status
                ]);

                return redirect()->route('home')
                    ->with('error', 'Payment failed. Please try again or choose a different payment method.');
            }

            // Payment is still pending or has another status
            Log::info('Mollie payment pending', [
                'order_id' => $order->id,
                'mollie_payment_id' => $payment->id,
                'status' => $payment->status
            ]);

            return redirect()->route('home')
                ->with('info', 'Your payment is being processed. You will receive a confirmation email once completed.');
        } catch (ApiException $e) {
            Log::error('Mollie API Error in success: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('home')
                ->with('error', 'Payment verification failed. Please contact support.');
        } catch (\Exception $e) {
            Log::error('Mollie Success Error: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->route('home')
                ->with('error', 'An error occurred while processing your payment.');
        }
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request): RedirectResponse
    {
        $orderId = $request->query('order_id');

        if ($orderId) {
            try {
                $order = Order::find($orderId);
                if ($order && $order->payment_status === Order::PAYMENT_STATUS_UNPAID) {
                    // Keep order but mark as cancelled if needed
                    // Or you might want to delete it depending on your business logic
                    $order->update(['status' => Order::STATUS_NEW]);

                    Log::info('Mollie payment cancelled', [
                        'order_id' => $order->id,
                        'user_id' => Auth::id()
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error handling Mollie cancellation: ' . $e->getMessage(), [
                    'order_id' => $orderId
                ]);
            }
        }

        // Clear session
        session()->forget('mollie_order_id');

        return redirect()->route('home')
            ->with('error', 'Your payment has been cancelled. You can try again or choose a different payment method.');
    }

    /**
     * Handle Mollie webhooks
     */
    public function webhook(Request $request)
    {
        $paymentId = $request->input('id');

        if (!$paymentId) {
            Log::warning('Mollie webhook received without payment ID');
            return response('Invalid webhook data', 400);
        }

        try {
            // Fetch the payment from Mollie
            $payment = Mollie::api()->payments->get($paymentId);

            // Find the order
            $order = Order::where('transaction_id', $paymentId)->first();

            if (!$order) {
                Log::warning('Mollie webhook: Order not found', ['payment_id' => $paymentId]);
                return response('Order not found', 404);
            }

            DB::beginTransaction();

            // Handle different payment statuses
            if ($payment->isPaid() && $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                $order->update([
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'status' => Order::STATUS_PROCESS
                ]);

                Log::info('Mollie webhook: Payment confirmed', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
            } elseif ($payment->isFailed()) {
                // Mark as failed but keep the order for potential retry
                Log::warning('Mollie webhook: Payment failed', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId,
                    'status' => $payment->status
                ]);
            } elseif ($payment->isCanceled()) {
                Log::info('Mollie webhook: Payment cancelled', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
            } elseif ($payment->isExpired()) {
                Log::info('Mollie webhook: Payment expired', [
                    'order_id' => $order->id,
                    'payment_id' => $paymentId
                ]);
            }

            DB::commit();

            return response('OK', 200);
        } catch (ApiException $e) {
            DB::rollBack();
            Log::error('Mollie webhook API error: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'trace' => $e->getTraceAsString()
            ]);
            return response('API Error', 500);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Mollie webhook error: ' . $e->getMessage(), [
                'payment_id' => $paymentId,
                'trace' => $e->getTraceAsString()
            ]);
            return response('Internal Error', 500);
        }
    }

    /**
     * Calculate shipping cost
     */
    private function calculateShippingCost(?string $shippingId): float
    {
        if (!$shippingId) {
            return 0.0;
        }

        $shipping = \App\Models\Shipping::find($shippingId);
        return $shipping ? (float) $shipping->price : 0.0;
    }

    /**
     * Move cart items to order
     */
    private function moveCartItemsToOrder(int $orderId): void
    {
        Cart::where('user_id', Auth::id())
            ->whereNull('order_id')
            ->update(['order_id' => $orderId]);
    }

    /**
     * Get supported payment methods
     */
    public function getPaymentMethods()
    {
        try {
            $methods = Mollie::api()->methods->allActive();
            return response()->json($methods);
        } catch (ApiException $e) {
            Log::error('Error fetching Mollie payment methods: ' . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch payment methods'], 500);
        }
    }

    /**
     * Create refund for an order
     */
    public function refund(Order $order, float $amount = null)
    {
        if (!$order->transaction_id || $order->payment_status !== Order::PAYMENT_STATUS_PAID) {
            throw new \Exception('Order is not eligible for refund');
        }

        try {
            $payment = Mollie::api()->payments->get($order->transaction_id);

            $refundData = [
                'description' => "Refund for order #{$order->order_number}",
            ];

            if ($amount) {
                $refundData['amount'] = [
                    'currency' => 'EUR',
                    'value' => number_format($amount, 2, '.', ''),
                ];
            }

            $refund = $payment->refund($refundData);

            Log::info('Mollie refund created', [
                'order_id' => $order->id,
                'refund_id' => $refund->id,
                'amount' => $amount ?? $order->total_amount
            ]);

            return $refund;
        } catch (ApiException $e) {
            Log::error('Mollie refund error: ' . $e->getMessage(), [
                'order_id' => $order->id
            ]);
            throw $e;
        }
    }
}
