<?php

namespace App\Http\Controllers;

use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use DB;
use Illuminate\Support\Facades\Log;

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
        $couponDiscount = session('coupon')['value'] ?? 0;
        $finalTotal = $total - $couponDiscount;

        // Ensure minimum amount for PayPal (usually $1.00)
        if ($finalTotal < 1) {
            return redirect()->back()->with('error', 'Order total must be at least $1.00');
        }

        // Create order record first
        $order = Order::create([
            'order_number' => $data['invoice_id'],
            'user_id' => auth()->user()->id,
            'sub_total' => $total,
            'coupon' => $couponDiscount,
            'total_amount' => $finalTotal,
            'quantity' => array_sum(array_column($data['items'], 'qty')),
            'payment_method' => 'paypal',
            'payment_status' => 'unpaid', // Use valid status
            'status' => 'new', // Use valid status
            'first_name' => auth()->user()->first_name ?? 'N/A',
            'last_name' => auth()->user()->last_name ?? 'N/A',
            'email' => auth()->user()->email,
            'phone' => auth()->user()->phone ?? 'N/A',
            'country' => 'N/A',
            'address1' => 'N/A',
        ]);

        // Update cart items with order_id
        Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->update(['order_id' => $order->id]);

        // Store order_id in session for success callback
        session(['order_id' => $order->id]);

        try {
            $provider = new PayPalClient;

            // Get PayPal config and disable SSL verification for local development
            $config = config('paypal');

            Log::info('PayPal Config:', $config);

            $provider->setApiCredentials($config);

            $paypalToken = $provider->getAccessToken();

            if (!$paypalToken) {
                Log::error('Failed to get PayPal access token');
                return redirect()->back()->with('error', 'PayPal authentication failed. Please check your credentials.');
            }

            Log::info('PayPal Token obtained successfully');

            $orderData = [
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('payment.success'),
                    "cancel_url" => route('payment.cancel'),
                    "brand_name" => config('app.name', 'Your Store'),
                    "landing_page" => "BILLING",
                    "user_action" => "PAY_NOW"
                ],
                "purchase_units" => [
                    [
                        "reference_id" => $data['invoice_id'],
                        "description" => $data['invoice_description'],
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => number_format($finalTotal, 2, '.', ''),
                            "breakdown" => [
                                "item_total" => [
                                    "currency_code" => "USD",
                                    "value" => number_format($total, 2, '.', '')
                                ]
                            ]
                        ],
                        "items" => array_map(function ($item) {
                            return [
                                "name" => $item['name'],
                                "description" => $item['desc'],
                                "unit_amount" => [
                                    "currency_code" => "USD",
                                    "value" => number_format($item['price'], 2, '.', '')
                                ],
                                "quantity" => (string)$item['qty'],
                                "category" => "PHYSICAL_GOODS"
                            ];
                        }, $data['items'])
                    ]
                ]
            ];

            // Add discount if applicable
            if ($couponDiscount > 0) {
                $orderData["purchase_units"][0]["amount"]["breakdown"]["discount"] = [
                    "currency_code" => "USD",
                    "value" => number_format($couponDiscount, 2, '.', '')
                ];
            }

            Log::info('Creating PayPal order with data:', $orderData);

            $response = $provider->createOrder($orderData);

            Log::info('PayPal Response:', $response);

            if (isset($response['id']) && $response['id'] != null) {
                // Store PayPal order ID for reference
                $order->update(['transaction_id' => $response['id']]);

                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        Log::info('Redirecting to PayPal approval URL: ' . $links['href']);
                        return redirect()->away($links['href']);
                    }
                }

                Log::error('No approval link found in PayPal response');
                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'PayPal approval link not found.');
            } else {
                Log::error('PayPal order creation failed:', $response);
                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'Failed to create PayPal order: ' . ($response['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error('PayPal Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'PayPal Error: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request)
    {
        Log::info('PayPal payment cancelled', ['request' => $request->all()]);

        // Clean up the order if it exists - use valid status values
        $orderId = session()->get('order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => 'unpaid', // Use valid status instead of 'cancelled'
                    'status' => 'new' // Use valid status instead of 'cancelled'
                ]);
                session()->forget('order_id');
            } catch (\Exception $e) {
                Log::error('Error updating order status: ' . $e->getMessage());
            }
        }

        return redirect()->route('home')->with('error', 'Your PayPal payment has been cancelled. You can try again or choose a different payment method.');
    }

    public function success(Request $request)
    {
        Log::info('PayPal success callback', ['request' => $request->all()]);

        if (!$request->has('token')) {
            Log::error('No PayPal token in success callback');
            return redirect()
                ->route('payment.cancel')
                ->with('error', 'Invalid PayPal response. Missing payment token.');
        }

        try {
            $provider = new PayPalClient;

            // Get PayPal config and disable SSL verification for local development
            $config = config('paypal');

            $provider->setApiCredentials($config);

            $provider->getAccessToken();

            $response = $provider->capturePaymentOrder($request['token']);

            Log::info('PayPal capture response:', $response);

            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                // Update order status
                $orderId = session()->get('order_id');
                if ($orderId) {
                    Order::where('id', $orderId)->update([
                        'payment_status' => 'paid',
                        'status' => 'process'
                    ]);

                    Log::info('Order updated successfully', ['order_id' => $orderId]);
                } else {
                    Log::warning('No order ID found in session during success callback');
                }

                // Clear sessions
                session()->forget(['cart', 'coupon', 'order_id']);

                return redirect()->route('home')->with('success', 'Payment completed successfully! Thank you for your purchase.');
            } else {
                Log::error('PayPal payment not completed', ['status' => $response['status'] ?? 'unknown']);
                return redirect()
                    ->route('payment.cancel')
                    ->with('error', 'Payment was not completed. Status: ' . ($response['status'] ?? 'unknown'));
            }
        } catch (\Exception $e) {
            Log::error('PayPal success callback error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()
                ->route('payment.cancel')
                ->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }
}
