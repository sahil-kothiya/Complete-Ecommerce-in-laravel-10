<?php

namespace App\Http\Controllers;

use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use App\Models\Shipping;
use Illuminate\Support\Facades\DB;

class PaypalController extends Controller
{
    public function payment()
    {
        $checkoutData = session('checkout_data');
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found. Please try again.');
        }

        $cart = Cart::where('user_id', auth()->user()->id)->where('order_id', null)->get();
        if ($cart->isEmpty()) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        $items = [];
        $subtotal = 0;
        foreach ($cart as $cartItem) {
            $product = Product::find($cartItem->product_id);
            $items[] = [
                'name' => $product->title ?? 'Product',
                'price' => $cartItem->price,
                'desc' => 'Thank you for using PayPal',
                'qty' => $cartItem->quantity
            ];
            $subtotal += $cartItem->price * $cartItem->quantity;
        }

        $shippingCost = 0;
        if ($checkoutData['shipping_id']) {
            $shipping = Shipping::find($checkoutData['shipping_id']);
            $shippingCost = $shipping ? $shipping->price : 0;
        }

        $couponDiscount = session('coupon')['value'] ?? 0;
        $finalTotal = $subtotal + $shippingCost - $couponDiscount;

        if ($finalTotal < 1) {
            return redirect()->back()->with('error', 'Order total must be at least $1.00');
        }

        $orderNumber = Order::generateOrderNumber();

        try {
            DB::beginTransaction();

            $order = Order::create([
                'order_number' => $orderNumber,
                'user_id' => auth()->user()->id,
                'sub_total' => $subtotal,
                'coupon' => $couponDiscount,
                'total_amount' => $finalTotal,
                'quantity' => $cart->sum('quantity'),
                'payment_method' => Order::PAYMENT_METHOD_PAYPAL,
                'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                'status' => Order::STATUS_NEW,
                'first_name' => $checkoutData['first_name'],
                'last_name' => $checkoutData['last_name'],
                'email' => $checkoutData['email'],
                'phone' => $checkoutData['phone'],
                'country' => $checkoutData['country'],
                'address1' => $checkoutData['address1'],
                'address2' => $checkoutData['address2'] ?? null,
                'post_code' => $checkoutData['post_code'] ?? null,
                'shipping_id' => $checkoutData['shipping_id'] ?? null,
            ]);

            Cart::where('user_id', auth()->user()->id)
                ->where('order_id', null)
                ->update(['order_id' => $order->id]);

            session(['paypal_order_id' => $order->id]);

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $paypalToken = $provider->getAccessToken();

            if (!$paypalToken) {
                throw new \Exception('Failed to get PayPal access token');
            }

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
                        "reference_id" => $orderNumber,
                        "description" => "Order #{$orderNumber} Invoice",
                        "amount" => [
                            "currency_code" => "USD",
                            "value" => number_format($finalTotal, 2, '.', ''),
                            "breakdown" => [
                                "item_total" => [
                                    "currency_code" => "USD",
                                    "value" => number_format($subtotal, 2, '.', '')
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
                        }, $items)
                    ]
                ]
            ];

            if ($shippingCost > 0) {
                $orderData["purchase_units"][0]["amount"]["breakdown"]["shipping"] = [
                    "currency_code" => "USD",
                    "value" => number_format($shippingCost, 2, '.', '')
                ];
            }

            if ($couponDiscount > 0) {
                $orderData["purchase_units"][0]["amount"]["breakdown"]["discount"] = [
                    "currency_code" => "USD",
                    "value" => number_format($couponDiscount, 2, '.', '')
                ];
            }

            $response = $provider->createOrder($orderData);

            if (isset($response['id']) && $response['id']) {
                $order->update(['transaction_id' => $response['id']]);
                DB::commit();

                foreach ($response['links'] as $link) {
                    if ($link['rel'] == 'approve') {
                        return redirect()->away($link['href']);
                    }
                }

                throw new \Exception('No approval link found in PayPal response');
            }

            throw new \Exception('Failed to create PayPal order: ' . ($response['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'PayPal Error: ' . $e->getMessage());
        }
    }

    public function success(Request $request)
    {
        if (!$request->has('token')) {
            return redirect()->route('payment.cancel')->with('error', 'Invalid PayPal response. Missing payment token.');
        }

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();

            $response = $provider->capturePaymentOrder($request['token']);

            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                $orderId = session()->get('paypal_order_id');
                $order = $orderId ? Order::find($orderId) : Order::where('transaction_id', $request['token'])->first();

                if ($order) {
                    $order->update([
                        'payment_status' => 'paid',
                        'status' => 'process'
                    ]);

                    session()->forget(['cart', 'coupon', 'paypal_order_id', 'checkout_data']);
                    return redirect()->route('home')->with('success', 'Payment completed successfully! Thank you for your purchase.');
                }

                return redirect()->route('payment.cancel')->with('error', 'Order not found.');
            }

            return redirect()->route('payment.cancel')->with('error', 'Payment was not completed. Status: ' . ($response['status'] ?? 'unknown'));
        } catch (\Exception $e) {
            return redirect()->route('payment.cancel')->with('error', 'Payment verification failed: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request)
    {
        $orderId = session()->get('paypal_order_id');
        if ($orderId) {
            try {
                $order = Order::find($orderId);
                if ($order) {
                    $order->update([
                        'payment_status' => 'unpaid',
                        'status' => 'cancelled'
                    ]);
                    Cart::where('order_id', $orderId)->update(['order_id' => null]);
                }
                session()->forget(['paypal_order_id', 'checkout_data']);
            } catch (\Exception $e) {
                // Handle exception silently
            }
        }

        return redirect()->route('home')->with('error', 'Your PayPal payment has been cancelled. You can try again or choose a different payment method.');
    }
}
