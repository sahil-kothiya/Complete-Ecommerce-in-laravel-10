<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Square\SquareClient;
use Square\Models\CreatePaymentRequest;
use Square\Models\Money;
use Square\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Square\Environments;

class SquareController extends Controller
{
    private $client;

    public function __construct()
    {
        dd(Environments::SANDBOX); // Should output "sandbox"

        $environment = config('services.square.environment') === 'production'
            ? Environments::PRODUCTION
            : Environments::SANDBOX;

        // Fixed constructor - pass array with proper keys
        $this->client = new SquareClient([
            'accessToken' => config('services.square.access_token'),
            'environment' => $environment,
            'customUrl' => '', // Optional
            'squareVersion' => '2023-10-18' // Use latest API version
        ]);
    }

    public function payment(Request $request = null)
    {
        $checkoutData = session('checkout_data') ?? $request?->all();
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found.');
        }

        $cart = Cart::where('user_id', auth()->user()->id)
            ->where('order_id', null)
            ->get()
            ->toArray();

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

        if ($finalTotal < 1.00) {
            return redirect()->back()->with('error', 'Order total must be at least $1.00');
        }

        // Square uses cents, so multiply by 100
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
                'payment_method' => 'square',
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

            DB::commit();

            session(['square_order_id' => $order->id]);

            return view('frontend.pages.square-checkout', [
                'order' => $order,
                'items' => $items,
                'amountInCents' => $amountInCents,
                'applicationId' => config('services.square.application_id'),
                'locationId' => config('services.square.location_id'),
                'environment' => config('services.square.environment')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Square Payment Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Payment Error: ' . $e->getMessage());
        }
    }

    public function processPayment(Request $request)
    {
        $request->validate([
            'sourceId' => 'required|string',
        ]);

        $orderId = session('square_order_id');
        if (!$orderId) {
            return response()->json(['error' => 'Order not found'], 400);
        }

        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 400);
        }

        try {
            $paymentsApi = $this->client->getPaymentsApi();

            // Create Money object properly
            $money = new Money();
            $money->setAmount(round($order->total_amount * 100)); // Convert to cents
            $money->setCurrency('USD');

            // Create payment request with proper idempotency key
            $idempotencyKey = Str::uuid()->toString();
            $createPaymentRequest = new CreatePaymentRequest($request->sourceId, $idempotencyKey);
            $createPaymentRequest->setAmountMoney($money);
            $createPaymentRequest->setLocationId(config('services.square.location_id'));
            $createPaymentRequest->setNote("Order #{$order->order_number}");

            // Optional: Add reference ID for tracking
            $createPaymentRequest->setReferenceId($order->order_number);

            $response = $paymentsApi->createPayment($createPaymentRequest);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();

                DB::beginTransaction();

                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'process',
                    'transaction_id' => $payment->getId()
                ]);

                DB::commit();

                session()->forget(['cart', 'coupon', 'square_order_id', 'checkout_data']);

                return response()->json([
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'redirect_url' => route('square.success', ['payment_id' => $payment->getId()])
                ]);
            } else {
                $errors = $response->getErrors();
                $errorMessage = 'Payment failed';
                if (!empty($errors)) {
                    $errorMessage .= ': ' . $errors[0]->getDetail();
                }
                Log::error('Square Payment Failed: ' . $errorMessage);

                return response()->json(['error' => $errorMessage], 400);
            }
        } catch (ApiException $e) {
            Log::error('Square API Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Payment processing failed: ' . $e->getMessage()], 500);
        } catch (\Exception $e) {
            Log::error('Square Payment Exception: ' . $e->getMessage());
            return response()->json(['error' => 'Payment processing failed'], 500);
        }
    }

    public function success(Request $request)
    {
        $paymentId = $request->get('payment_id');

        if (!$paymentId) {
            return redirect()->route('home')->with('error', 'Invalid payment response.');
        }

        try {
            $paymentsApi = $this->client->getPaymentsApi();
            $response = $paymentsApi->getPayment($paymentId);

            if ($response->isSuccess()) {
                $payment = $response->getResult()->getPayment();

                if ($payment->getStatus() === 'COMPLETED') {
                    return redirect()->route('home')->with('success', 'Payment completed successfully! Thank you for your purchase.');
                } else {
                    return redirect()->route('square.cancel')->with('error', 'Payment was not completed. Status: ' . $payment->getStatus());
                }
            } else {
                return redirect()->route('square.cancel')->with('error', 'Payment verification failed.');
            }
        } catch (ApiException $e) {
            Log::error('Square Payment Verification Failed: ' . $e->getMessage());
            return redirect()->route('square.cancel')->with('error', 'Payment verification failed.');
        } catch (\Exception $e) {
            Log::error('Square Payment Verification Exception: ' . $e->getMessage());
            return redirect()->route('square.cancel')->with('error', 'Payment verification failed.');
        }
    }

    public function cancel(Request $request)
    {
        $orderId = session()->get('square_order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => 'unpaid',
                    'status' => 'new'
                ]);
                session()->forget('square_order_id');
            } catch (\Exception $e) {
                Log::error('Square Cancel Error: ' . $e->getMessage());
            }
        }

        return redirect()->route('home')->with('error', 'Your Square payment has been cancelled. You can try again or choose a different payment method.');
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Square-Signature');
        $webhookSecret = config('services.square.webhook.secret');

        // Verify webhook signature
        if (!$this->verifyWebhookSignature($payload, $signature, $webhookSecret)) {
            Log::warning('Square webhook signature verification failed');
            return response('Invalid signature', 400);
        }

        $event = json_decode($payload, true);

        if (!$event || !isset($event['type'])) {
            Log::warning('Invalid Square webhook payload');
            return response('Invalid payload', 400);
        }

        try {
            switch ($event['type']) {
                case 'payment.updated':
                    if (isset($event['data']['object']['payment'])) {
                        $this->handlePaymentUpdated($event['data']['object']['payment']);
                    }
                    break;
                default:
                    Log::info('Unhandled Square webhook event type: ' . $event['type']);
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Square webhook processing error: ' . $e->getMessage());
            return response('Processing error', 500);
        }

        return response('Success', 200);
    }

    private function verifyWebhookSignature(string $payload, ?string $signature, string $secret): bool
    {
        if (!$signature) {
            return false;
        }

        $expectedSignature = base64_encode(hash_hmac('sha1', $payload, $secret, true));
        return hash_equals($expectedSignature, $signature);
    }

    private function handlePaymentUpdated(array $payment): void
    {
        if (!isset($payment['id'])) {
            Log::warning('Payment updated webhook missing payment ID');
            return;
        }

        $order = Order::where('transaction_id', $payment['id'])->first();

        if (!$order) {
            Log::warning('Order not found for payment ID: ' . $payment['id']);
            return;
        }

        try {
            DB::beginTransaction();

            if ($payment['status'] === 'COMPLETED') {
                $order->update([
                    'payment_status' => 'paid',
                    'status' => 'process'
                ]);
                Log::info('Order payment completed: ' . $order->order_number);
            } elseif ($payment['status'] === 'FAILED') {
                $order->update([
                    'payment_status' => 'unpaid',
                    'status' => 'new'
                ]);
                Log::info('Order payment failed: ' . $order->order_number);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating order from webhook: ' . $e->getMessage());
        }
    }
}
