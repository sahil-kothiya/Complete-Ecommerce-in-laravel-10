<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;
use Illuminate\Support\Facades\DB;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
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

        if ($finalTotal < 0.50) {
            return redirect()->back()->with('error', 'Order total must be at least $0.50');
        }

        $amountInCents = round($finalTotal * 100);

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
                'payment_method' => 'stripe',
                'payment_status' => 'unpaid',
                'status' => 'new',
                'first_name' => $checkoutData['first_name'] ?? auth()->user()->first_name ?? 'N/A',
                'last_name' => $checkoutData['last_name'] ?? auth()->user()->last_name ?? 'N/A',
                'email' => $checkoutData['email'] ?? auth()->user()->email,
                'phone' => $checkoutData['phone'] ?? auth()->user()->phone ?? 'N/A',
                'country' => $checkoutData['country'] ?? 'N/A',
                'address1' => $checkoutData['address1'] ?? 'N/A',
                'address2' => $checkoutData['address2'] ?? null,
                'post_code' => $checkoutData['post_code'] ?? null,
                'shipping_id' => $checkoutData['shipping_id'] ?? null,
            ]);

            Cart::where('user_id', auth()->user()->id)
                ->where('order_id', null)
                ->update(['order_id' => $order->id]);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'user_id' => auth()->user()->id,
                ],
                'description' => "Order #{$orderNumber}",
            ]);

            $order->update(['transaction_id' => $paymentIntent->id]);
            DB::commit();

            session(['stripe_order_id' => $order->id]);

            return view('frontend.pages.stripe-checkout', [
                'paymentIntent' => $paymentIntent,
                'order' => $order,
                'items' => $items,
                'clientSecret' => $paymentIntent->client_secret,
                'stripeKey' => config('services.stripe.key')
            ]);
        } catch (ApiErrorException $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Stripe Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Payment Error: ' . $e->getMessage());
        }
    }

    public function success(Request $request)
    {
        $paymentIntentId = $request->input('payment_intent');
        if (!$paymentIntentId) {
            return redirect()->route('payment.cancel')->with('error', 'Invalid Stripe response. Missing payment intent.');
        }

        try {
            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            if ($paymentIntent->status === 'succeeded') {
                $order = Order::where('transaction_id', $paymentIntentId)->first();
                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'process'
                    ]);

                    session()->forget(['cart', 'coupon', 'stripe_order_id']);
                    return redirect()->route('home')->with('success', 'Payment completed successfully! Thank you for your purchase.');
                }
                return redirect()->route('payment.cancel')->with('error', 'Order not found.');
            }
            return redirect()->route('payment.cancel')->with('error', 'Payment was not completed. Status: ' . $paymentIntent->status);
        } catch (ApiErrorException $e) {
            return redirect()->route('payment.cancel')->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request)
    {
        $orderId = session()->get('stripe_order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => 'unpaid',
                    'status' => 'new'
                ]);
                session()->forget('stripe_order_id');
            } catch (\Exception $e) {
                // Silent catch
            }
        }
        return redirect()->route('home')->with('error', 'Your Stripe payment has been cancelled. You can try again or choose a different payment method.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook.secret');

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            return response('Invalid payload or signature', 400);
        }

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->handlePaymentSucceeded($event->data->object);
                break;
            case 'payment_intent.payment_failed':
                $this->handlePaymentFailed($event->data->object);
                break;
        }

        return response('Success', 200);
    }

    private function handlePaymentSucceeded($paymentIntent)
    {
        $order = Order::where('transaction_id', $paymentIntent->id)->first();
        if ($order && $order->payment_status !== 'paid') {
            $order->update([
                'payment_status' => 'paid',
                'status' => 'process'
            ]);
        }
    }

    private function handlePaymentFailed($paymentIntent)
    {
        $order = Order::where('transaction_id', $paymentIntent->id)->first();
        if ($order) {
            $order->update([
                'payment_status' => 'unpaid',
                'status' => 'new'
            ]);
        }
    }
}
