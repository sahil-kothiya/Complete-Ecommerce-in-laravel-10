<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Exceptions\SquareException;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Utils\WebhooksHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Helper;

class SquareController extends Controller
{
    protected $client;

    public function __construct()
    {
        // Get configuration values
        $accessToken = config('services.square.access_token');
        $environment = config('services.square.environment', 'sandbox');

        // Validate access token
        if (empty($accessToken)) {
            throw new \InvalidArgumentException('Square access token is required. Please set SQUARE_ACCESS_TOKEN in your .env file.');
        }

        // Set environment - use Environment constants from Square SDK
        $squareEnvironment = $environment === 'production'
            ? Environments::Production
            : Environments::Sandbox;

        // Initialize Square client with proper configuration
        $this->client = new SquareClient(options: [
            'accessToken' => $accessToken,
            'environment' => $squareEnvironment,
        ]);
    }

    public function payment(Request $request = null)
    {
        // Get checkout data from session
        $checkoutData = session('checkout_data');
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found. Please try again.');
        }

        // Get cart items
        $cart = Cart::where('user_id', Auth::id())
            ->whereNull('order_id')
            ->get();

        if ($cart->isEmpty()) {
            return redirect()->back()->with('error', 'Your cart is empty!');
        }

        // Calculate totals
        $subtotal = Helper::totalCartPrice();
        $couponDiscount = session('coupon')['value'] ?? 0;
        $shippingCost = $this->calculateShippingCost($checkoutData['shipping_id'] ?? null);
        $finalTotal = $subtotal + $shippingCost - $couponDiscount;

        // Ensure minimum amount for Square (usually $1.00)
        if ($finalTotal < 1.00) {
            return redirect()->back()->with('error', 'Order total must be at least $1.00');
        }

        // Convert to cents for Square (Square uses base currency units)
        $amountInCents = round($finalTotal * 100);

        // Prepare items for display
        $items = [];
        foreach ($cart as $item) {
            $items[] = [
                'name' => $item->product->title ?? 'Product',
                'price' => $item->price,
                'quantity' => $item->quantity
            ];
        }

        try {
            DB::beginTransaction();

            // Create order record
            $order = Order::create([
                'user_id' => Auth::id(),
                'sub_total' => $subtotal,
                'coupon' => $couponDiscount,
                'total_amount' => $finalTotal,
                'quantity' => Helper::cartCount(),
                'payment_method' => Order::PAYMENT_METHOD_SQUARE,
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

            // Update cart items with order_id
            Cart::where('user_id', Auth::id())
                ->whereNull('order_id')
                ->update(['order_id' => $order->id]);

            // Store order_id in session for payment processing
            session(['square_order_id' => $order->id]);

            DB::commit();

            return view('frontend.pages.square-checkout', [
                'order' => $order,
                'items' => $items,
                'amount' => $amountInCents,
                'applicationId' => config('services.square.application_id'),
                'locationId' => config('services.square.location_id'),
                'environment' => config('services.square.environment', 'sandbox'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Square Payment Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Payment initialization failed. Please try again.');
        }
    }

    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'sourceId' => 'required|string',
        ]);

        $sourceId = $validated['sourceId'];
        $orderId = session('square_order_id');

        if (!$orderId) {
            return response()->json([
                'success' => false,
                'error' => 'Order session expired. Please try again.'
            ], 400);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found.'
            ], 404);
        }

        try {
            $amountInCents = round($order->total_amount * 100);

            // Create Money object
            $amountMoney = new Money();
            $amountMoney->setAmount($amountInCents);
            $amountMoney->setCurrency('USD');

            // Create payment request
            $createPaymentRequest = new CreatePaymentRequest(
                $sourceId, // source_id
                uniqid(), // idempotency_key
                $amountMoney
            );

            // Set location ID if available
            $locationId = config('services.square.location_id');
            if ($locationId) {
                $createPaymentRequest->setLocationId($locationId);
            }

            // Make the payment request
            $paymentsApi = $this->client->getPaymentsApi();
            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();

                $order->update([
                    'transaction_id' => $payment->getId(),
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'status' => Order::STATUS_PROCESS
                ]);

                // Clear sessions
                session()->forget(['cart', 'coupon', 'square_order_id', 'checkout_data']);

                Log::info('Square payment successful', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->getId(),
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'redirect_url' => route('home')
                ]);
            } else {
                $errors = $response->getErrors();
                Log::error('Square payment failed', [
                    'order_id' => $order->id,
                    'errors' => $errors,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $this->formatSquareErrors($errors)
                ], 400);
            }
        } catch (SquareApiException  $e) {
            Log::error('Square API Error: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'status_code' => $e->getCode(),
                'response_body' => $e->getBody(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed. Please try again.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('General Payment Error: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }

    public function success(Request $request)
    {
        return redirect()->route('home')
            ->with('success', 'Payment completed successfully! Thank you for your purchase.');
    }

    public function cancel(Request $request)
    {
        $orderId = session()->get('square_order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                    'status' => Order::STATUS_NEW
                ]);
                session()->forget('square_order_id');
            } catch (\Exception $e) {
                Log::error('Error updating order status on cancellation: ' . $e->getMessage());
            }
        }

        return redirect()->route('home')
            ->with('error', 'Your Square payment has been cancelled. You can try again or choose a different payment method.');
    }

    // public function webhook(Request $request)
    // {
    //     $payload = $request->getContent();
    //     $signatureHeader = $request->header('x-square-hmacsha256-signature');
    //     $signatureKey = config('services.square.webhook_signature_key');
    //     $notificationUrl = config('services.square.webhook_url');

    //     // Verify webhook signature if signature key is configured
    //     if ($signatureKey && $notificationUrl) {
    //         try {
    //             $isValidSignature = WebhooksHelper::isValidWebhookEventSignature(
    //                 $payload,
    //                 $signatureHeader,
    //                 $signatureKey,
    //                 $notificationUrl
    //             );

    //             if (!$isValidSignature) {
    //                 Log::warning('Invalid Square webhook signature received');
    //                 return response('Invalid signature', 401);
    //             }
    //         } catch (\Exception $e) {
    //             Log::error('Square webhook signature verification failed: ' . $e->getMessage());
    //             return response('Signature verification failed', 401);
    //         }
    //     }

    //     try {
    //         $event = json_decode($payload, true);

    //         if (!$event || !isset($event['type'])) {
    //             Log::warning('Invalid Square webhook payload received');
    //             return response('Invalid payload', 400);
    //         }

    //         Log::info('Square webhook received', [
    //             'event_type' => $event['type'],
    //             'merchant_id' => $event['merchant_id'] ?? 'unknown'
    //         ]);

    //         $eventType = $event['type'];

    //         switch ($eventType) {
    //             case 'payment.created':
    //             case 'payment.updated':
    //                 $this->handlePaymentEvent($event);
    //                 break;
    //             default:
    //                 Log::info('Received unhandled Square event type: ' . $eventType);
    //         }

    //         return response('Success', 200);
    //     } catch (\Exception $e) {
    //         Log::error('Square webhook processing error: ' . $e->getMessage());
    //         return response('Webhook error', 500);
    //     }
    // }

    // /**
    //  * Handle payment events from Square webhook
    //  */
    // private function handlePaymentEvent(array $event): void
    // {
    //     $payment = $event['data']['object']['payment'] ?? null;

    //     if (!$payment || !isset($payment['id'])) {
    //         Log::warning('Invalid payment data in Square webhook event');
    //         return;
    //     }

    //     $order = Order::where('transaction_id', $payment['id'])->first();

    //     if (!$order) {
    //         Log::warning('Order not found for Square payment ID: ' . $payment['id']);
    //         return;
    //     }

    //     $status = $payment['status'] ?? '';

    //     switch ($status) {
    //         case 'COMPLETED':
    //             if ($order->payment_status !== Order::PAYMENT_STATUS_PAID) {
    //                 $order->update([
    //                     'payment_status' => Order::PAYMENT_STATUS_PAID,
    //                     'status' => Order::STATUS_PROCESS
    //                 ]);
    //                 Log::info('Order payment confirmed via Square webhook', ['order_id' => $order->id]);
    //             }
    //             break;

    //         case 'FAILED':
    //         case 'CANCELED':
    //             $order->update([
    //                 'payment_status' => Order::PAYMENT_STATUS_UNPAID,
    //                 'status' => Order::STATUS_NEW
    //             ]);
    //             Log::info('Order payment failed via Square webhook', [
    //                 'order_id' => $order->id,
    //                 'status' => $status
    //             ]);
    //             break;

    //         default:
    //             Log::info('Unhandled Square payment status via webhook', [
    //                 'order_id' => $order->id,
    //                 'status' => $status
    //             ]);
    //     }
    // }

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
     * Format Square errors for user display
     */
    private function formatSquareErrors(array $errors): string
    {
        if (empty($errors)) {
            return 'Unknown payment error occurred.';
        }

        $errorMessages = [];
        foreach ($errors as $error) {
            if (isset($error['detail'])) {
                $errorMessages[] = $error['detail'];
            } elseif (isset($error['code'])) {
                $errorMessages[] = $error['code'];
            }
        }

        return implode('. ', $errorMessages);
    }
}
