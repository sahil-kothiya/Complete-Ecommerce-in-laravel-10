<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RazorpayController extends Controller
{
    private $api;

    public function __construct()
    {
        $this->api = new Api(
            config('services.razorpay.key'),
            config('services.razorpay.secret')
        );
    }

    public function payment(Request $request = null)
    {
        $checkoutData = session('checkout_data') ?? $request?->all();
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found.');
        }

        $cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->get()->toArray();
        if (empty($cart)) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        $subtotal = 0;
        $items = [];
        foreach ($cart as $item) {
            $product = Product::find($item['product_id']);
            $subtotal += $item['price'] * $item['quantity'];
            $items[] = [
                'name' => $product->title ?? 'Product',
                'price' => $item['price'],
                'quantity' => $item['quantity']
            ];
        }

        $couponDiscount = session('coupon')['value'] ?? 0;
        $finalTotal = $subtotal - $couponDiscount;

        if ($finalTotal < 1) {
            return redirect()->back()->with('error', 'Order total must be at least ₹1');
        }

        // Convert to paise (Razorpay uses paise)
        $amountInPaise = round($finalTotal * 100);

        try {
            DB::beginTransaction();

            $orderNumber = Order::generateOrderNumber();
            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => auth()->user()->id,
                'sub_total' => $subtotal,
                'coupon' => $couponDiscount,
                'total_amount' => $finalTotal,
                'quantity' => array_sum(array_column($items, 'quantity')),
                'payment_method' => 'razorpay',
                'payment_status' => 'unpaid',
                'status' => 'new',
                'first_name' => $checkoutData['first_name'] ?? auth()->user()->first_name ?? 'N/A',
                'last_name' => $checkoutData['last_name'] ?? auth()->user()->last_name ?? 'N/A',
                'email' => $checkoutData['email'] ?? auth()->user()->email,
                'phone' => $checkoutData['phone'] ?? auth()->user()->phone ?? 'N/A',
                'country' => 'IN', // Force India for test
                'address1' => $checkoutData['address1'] ?? 'N/A',
                'address2' => $checkoutData['address2'] ?? null,
                'post_code' => $checkoutData['post_code'] ?? null,
                'shipping_id' => $checkoutData['shipping_id'] ?? null,
            ]);

            Cart::where('user_id', auth()->user()->id)
                ->where('order_id', null)
                ->update(['order_id' => $order->id]);

            // Create Razorpay order with proper configuration
            $razorpayOrder = $this->api->order->create([
                'receipt' => $orderNumber,
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'payment_capture' => 1, // Auto capture
                'notes' => [
                    'order_id' => $order->id,
                    'user_id' => auth()->user()->id,
                ]
            ]);

            $order->update(['transaction_id' => $razorpayOrder['id']]);
            DB::commit();

            session(['razorpay_order_id' => $order->id]);

            return view('frontend.pages.razorpay-checkout', [
                'razorpayOrder' => $razorpayOrder,
                'order' => $order,
                'items' => $items,
                'razorpayKey' => config('services.razorpay.key'),
                'user' => auth()->user()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Razorpay Order Creation Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payment Error: ' . $e->getMessage());
        }
    }

    public function success(Request $request)
    {
        $razorpayPaymentId = $request->input('razorpay_payment_id');
        $razorpayOrderId = $request->input('razorpay_order_id');
        $razorpaySignature = $request->input('razorpay_signature');

        if (!$razorpayPaymentId || !$razorpayOrderId || !$razorpaySignature) {
            return redirect()->route('payment.cancel')->with('error', 'Invalid Razorpay response.');
        }

        try {
            // Verify signature
            $attributes = [
                'razorpay_order_id' => $razorpayOrderId,
                'razorpay_payment_id' => $razorpayPaymentId,
                'razorpay_signature' => $razorpaySignature
            ];

            $this->api->utility->verifyPaymentSignature($attributes);

            // Fetch payment details
            $payment = $this->api->payment->fetch($razorpayPaymentId);

            if ($payment->status === 'captured') {
                $order = Order::where('transaction_id', $razorpayOrderId)->first();
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'process'
                    ]);

                    session()->forget(['cart', 'coupon', 'razorpay_order_id']);
                    return redirect()->route('home')->with('success', 'Payment completed successfully! Thank you for your purchase.');
                }
                return redirect()->route('payment.cancel')->with('error', 'Order not found.');
            }

            return redirect()->route('payment.cancel')->with('error', 'Payment was not completed. Status: ' . $payment->status);

        } catch (SignatureVerificationError $e) {
            return redirect()->route('payment.cancel')->with('error', 'Payment verification failed: Invalid signature.');
        } catch (\Exception $e) {
            Log::error('Razorpay Success Error: ' . $e->getMessage());
            return redirect()->route('payment.cancel')->with('error', 'Payment verification failed.');
        }
    }

    public function cancel(Request $request)
    {
        $orderId = session()->get('razorpay_order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => 'unpaid',
                    'status' => 'new'
                ]);
                session()->forget('razorpay_order_id');
            } catch (\Exception $e) {
                // Silent catch
            }
        }
        return redirect()->route('home')->with('error', 'Your Razorpay payment has been cancelled. You can try again or choose a different payment method.');
    }

    public function webhook(Request $request)
    {
        $webhookSecret = config('services.razorpay.webhook_secret');
        $webhookSignature = $request->header('X-Razorpay-Signature');
        $webhookBody = $request->getContent();

        try {
            $this->api->utility->verifyWebhookSignature($webhookBody, $webhookSignature, $webhookSecret);
        } catch (SignatureVerificationError $e) {
            return response('Invalid signature', 400);
        }

        $data = $request->all();

        switch ($data['event']) {
            case 'payment.captured':
                $this->handlePaymentCaptured($data['payload']['payment']['entity']);
                break;
            case 'payment.failed':
                $this->handlePaymentFailed($data['payload']['payment']['entity']);
                break;
        }

        return response('Success', 200);
    }

    private function handlePaymentCaptured($payment)
    {
        $order = Order::where('transaction_id', $payment['order_id'])->first();
        if ($order && $order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'process'
            ]);
            Log::info('Razorpay Payment Captured via Webhook', ['order_id' => $order->id]);
        }
    }

    private function handlePaymentFailed($payment)
    {
        $order = Order::where('transaction_id', $payment['order_id'])->first();
        if ($order) {
            $order->update([
                'payment_status' => 'unpaid',
                'status' => 'new'
            ]);
            Log::info('Razorpay Payment Failed via Webhook', ['order_id' => $order->id]);
        }
    }
}