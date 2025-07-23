<?php

namespace App\Http\Controllers;

use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use DB;

class PaypalController extends Controller
{
    public function payment()
    {
        $cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->get()->toArray();

        if (empty($cart)) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        $data = [];

        // Prepare items for PayPal
        $data['items'] = array_map(function ($item) {
            $product = Product::find($item['product_id']);
            return [
                'name' => $product->title ?? 'Product',
                'price' => $item['price'],
                'desc'  => 'Thank you for using PayPal',
                'qty' => $item['quantity']
            ];
        }, $cart);

        $data['invoice_id'] = 'ORD-' . strtoupper(uniqid());
        $data['invoice_description'] = "Order #{$data['invoice_id']} Invoice";
        $data['return_url'] = route('payment.success');
        $data['cancel_url'] = route('payment.cancel');

        // Calculate total
        $total = 0;
        foreach ($data['items'] as $item) {
            $total += $item['price'] * $item['qty'];
        }

        $data['total'] = $total;

        // Apply coupon discount if available
        if (session('coupon')) {
            $data['shipping_discount'] = session('coupon')['value'];
        }

        // Create order record first
        $order = Order::create([
            'order_number' => $data['invoice_id'],
            'user_id' => auth()->user()->id,
            'sub_total' => $total,
            'coupon' => session('coupon')['value'] ?? 0,
            'total_amount' => $total - (session('coupon')['value'] ?? 0),
            'quantity' => array_sum(array_column($data['items'], 'qty')),
            'payment_method' => 'paypal',
            'payment_status' => 'unpaid',
            'status' => 'new',
            // Add required address fields - you'll need to get these from user input
            'first_name' => auth()->user()->first_name ?? 'N/A',
            'last_name' => auth()->user()->last_name ?? 'N/A',
            'email' => auth()->user()->email,
            'phone' => auth()->user()->phone ?? 'N/A',
            'country' => 'N/A', // Get from form
            'address1' => 'N/A', // Get from form
        ]);

        // Update cart items with order_id
        Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->update(['order_id' => $order->id]);

        try {
            $provider = new PayPalClient;

            // Direct environment variable access
            $provider->setApiCredentials([
                'mode' => env('PAYPAL_MODE', 'sandbox'),
                'sandbox' => [
                    'client_id' => env('PAYPAL_SANDBOX_CLIENT_ID'),
                    'client_secret' => env('PAYPAL_SANDBOX_CLIENT_SECRET'),
                ],
                'live' => [
                    'client_id' => env('PAYPAL_LIVE_CLIENT_ID'),
                    'client_secret' => env('PAYPAL_LIVE_CLIENT_SECRET'),
                ],
                'payment_action' => 'Sale',
                'currency' => 'USD',
                'validate_ssl' => true,
            ]);

            $paypalToken = $provider->getAccessToken();

            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('payment.success'),
                    "cancel_url" => route('payment.cancel')
                ],
                "purchase_units" => [
                    0 => [
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => number_format($data['total'], 2, '.', '')
                        ]
                    ]
                ]
            ]);

            if (isset($response['id']) && $response['id'] != null) {
                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }

                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'Something went wrong.');
            } else {
                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'Something went wrong.');
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'PayPal Error: ' . $e->getMessage());
        }
    }

    public function cancel()
    {
        return redirect()->route('home')->with('error', 'Your payment has been cancelled.');
    }

    public function success(Request $request)
    {
        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($request['token']);

            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                // Update order status
                $orderId = session()->get('order_id'); // You'll need to store this during payment creation
                if ($orderId) {
                    Order::where('id', $orderId)->update([
                        'payment_status' => 'paid',
                        'status' => 'process'
                    ]);
                }

                request()->session()->flash('success', 'You successfully paid via PayPal! Thank You');
                session()->forget('cart');
                session()->forget('coupon');
                return redirect()->route('home');
            } else {
                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'Something went wrong during payment verification.');
            }
        } catch (\Exception $e) {
            return redirect()
                ->route('payment.cancel')
                ->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }
}
