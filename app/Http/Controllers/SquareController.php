<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use App\Models\Order;
use Square\SquareClient;
use Square\Environments;
use Square\Exceptions\SquareApiException;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\Money;
use Square\Types\Currency;
use Square\Utils\WebhooksHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Helper;

class SquareController extends Controller
{
    protected SquareClient $client;
    protected string $environment;

    public function __construct()
    {
        $this->debugSquareSDK();
        dd('asdgasd');
        $accessToken = config('services.square.access_token');
        $this->environment = config('services.square.environment', 'sandbox');

        if (empty($accessToken)) {
            throw new \InvalidArgumentException('Square access token is required.');
        }

        // Try different initialization patterns based on your SDK version:

        // Pattern 1: New SDK with no parameters (uses environment variables)
        try {
            $this->client = new SquareClient();
            if (method_exists($this->client, 'getPaymentsApi')) {
                // Success with default constructor
                return;
            }
        } catch (\Exception $e) {
            // Continue to next pattern
        }

        // Pattern 2: With options array using named parameter
        try {
            $this->client = new SquareClient(options: [
                'accessToken' => $accessToken,
                'environment' => $this->environment,
            ]);
            if (method_exists($this->client, 'getPaymentsApi')) {
                return;
            }
        } catch (\Exception $e) {
            // Continue to next pattern
        }

        // Pattern 3: With baseUrl (as seen in packagist)
        try {
            $baseUrl = $this->environment === 'production'
                ? 'https://connect.squareup.com'
                : 'https://connect.squareupsandbox.com';

            $this->client = new SquareClient(options: [
                'accessToken' => $accessToken,
                'baseUrl' => $baseUrl,
            ]);
            if (method_exists($this->client, 'getPaymentsApi')) {
                return;
            }
        } catch (\Exception $e) {
            // Continue to next pattern
        }

        // Pattern 4: Legacy style with array directly
        try {
            $this->client = new SquareClient(options: [
                'accessToken' => $accessToken,
                'environment' => $this->environment === 'production' ? Environments::Production : Environments::Sandbox,
            ]);
            if (method_exists($this->client, 'getPaymentsApi')) {
                return;
            }
        } catch (\Exception $e) {
            // Continue to next pattern
        }

        // Pattern 5: Simple string constructor
        try {
            $this->client = new SquareClient($accessToken);
            if (method_exists($this->client, 'getPaymentsApi')) {
                return;
            }
        } catch (\Exception $e) {
            // All patterns failed
        }

        // If we get here, none of the patterns worked
        throw new \Exception('Unable to initialize Square client. SDK version might be incompatible.');
    }


    public function debugSquareSDK()
    {
        echo "<h2>Square SDK Debug Information</h2>";

        // Check if SquareClient class exists
        if (!class_exists('Square\SquareClient')) {
            dd('SquareClient class does not exist. Please install: composer require squareup/square');
        }

        echo "<p>✅ SquareClient class exists</p>";

        // Check constructor parameters
        $reflection = new \ReflectionClass('Square\SquareClient');
        $constructor = $reflection->getConstructor();

        echo "<h3>Constructor Parameters:</h3>";
        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                echo "<p>Parameter: " . $param->getName() .
                    " | Type: " . ($param->getType() ? $param->getType()->getName() : 'mixed') .
                    " | Optional: " . ($param->isOptional() ? 'Yes' : 'No') . "</p>";
            }
        }

        // Try to create instance with no parameters
        echo "<h3>Testing No Parameters:</h3>";
        try {
            $client = new SquareClient();
            echo "<p>✅ No parameters constructor works</p>";
            echo "<p>Available methods: " . implode(', ', array_slice(get_class_methods($client), 0, 10)) . "...</p>";
            $this->client = $client;
            return;
        } catch (\Exception $e) {
            echo "<p>❌ No parameters failed: " . $e->getMessage() . "</p>";
        }

        // Try with environment variables
        echo "<h3>Testing with Environment Variables:</h3>";
        putenv('SQUARE_ACCESS_TOKEN=' . config('services.square.access_token'));
        putenv('SQUARE_ENVIRONMENT=' . config('services.square.environment', 'sandbox'));

        try {
            $client = new SquareClient();
            echo "<p>✅ Environment variables constructor works</p>";
            echo "<p>Available methods: " . implode(', ', array_slice(get_class_methods($client), 0, 10)) . "...</p>";
            $this->client = $client;
            return;
        } catch (\Exception $e) {
            echo "<p>❌ Environment variables failed: " . $e->getMessage() . "</p>";
        }

        // Try with string parameter
        echo "<h3>Testing with Access Token String:</h3>";
        try {
            $client = new SquareClient(config('services.square.access_token'));
            echo "<p>✅ String parameter constructor works</p>";
            echo "<p>Available methods: " . implode(', ', array_slice(get_class_methods($client), 0, 10)) . "...</p>";
            $this->client = $client;
            return;
        } catch (\Exception $e) {
            echo "<p>❌ String parameter failed: " . $e->getMessage() . "</p>";
        }

        // Check what classes are available in Square namespace
        echo "<h3>Available Square Classes:</h3>";
        $squareClasses = [];
        foreach (get_declared_classes() as $class) {
            if (strpos($class, 'Square\\') === 0) {
                $squareClasses[] = $class;
            }
        }
        echo "<p>" . implode('<br>', array_slice($squareClasses, 0, 20)) . "</p>";

        dd('Please check the output above to understand your Square SDK structure');
    }


    /**
     * Handle payment initialization and display checkout page
     */
    public function payment(Request $request = null)
    {
        // Retrieve checkout data from session
        $checkoutData = session('checkout_data');
        if (!$checkoutData) {
            return redirect()->back()->with('error', 'Checkout data not found. Please try again.');
        }

        // Fetch cart items for authenticated user
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

        // Ensure minimum order amount for Square
        if ($finalTotal < 1.00) {
            return redirect()->back()->with('error', 'Order total must be at least $1.00');
        }

        // Convert total to cents for Square API
        $amountInCents = round($finalTotal * 100);

        // Prepare cart items for display
        $items = $cart->map(fn($item) => [
            'name' => $item->product->title ?? 'Product',
            'price' => $item->price,
            'quantity' => $item->quantity
        ])->toArray();

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

            // Update cart items with order ID
            Cart::where('user_id', Auth::id())
                ->whereNull('order_id')
                ->update(['order_id' => $order->id]);

            // Store order ID in session
            session(['square_order_id' => $order->id]);

            DB::commit();

            Log::info('Square Payment View Data', [
                'application_id' => config('services.square.application_id'),
                'location_id' => config('services.square.location_id'),
                'environment' => $this->environment,
            ]);

            // Return checkout view
            return view('frontend.pages.square-checkout', [
                'order' => $order,
                'items' => $items,
                'amount' => $amountInCents,
                'applicationId' => config('services.square.application_id'),
                'locationId' => config('services.square.location_id'),
                'environment' => $this->environment, // Use class property
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Square Payment Initialization Error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect()->back()->with('error', 'Payment initialization failed. Please try again.');
        }
    }

    public function processPayment(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'sourceId' => 'required|string',
        ]);

        // Retrieve order ID from session
        $orderId = session('square_order_id');
        if (!$orderId) {
            return response()->json(['success' => false, 'error' => 'Order session expired.'], 400);
        }

        // Fetch order
        $order = Order::find($orderId);
        if (!$order) {
            return response()->json(['success' => false, 'error' => 'Order not found.'], 404);
        }

        try {
            // Prepare money
            $amountInCents = (int) round($order->total_amount * 100);
            $money = new Money();
            $money->setAmount($amountInCents);
            $money->setCurrency('USD');

            // Create payment request
            $paymentRequest = new CreatePaymentRequest($validated['sourceId'], uniqid());
            $paymentRequest->setAmountMoney($money);

            // Optional: set location ID
            $locationId = config('services.square.location_id');
            if ($locationId) {
                $paymentRequest->setLocationId($locationId);
            }


            // Get the payments API and create payment
            $paymentsApi = $this->client->getPaymentsApi();
            $apiResponse = $paymentsApi->createPayment($paymentRequest);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();

                // Update order
                $order->update([
                    'transaction_id' => $payment->getId(),
                    'payment_status' => Order::PAYMENT_STATUS_PAID,
                    'status' => Order::STATUS_PROCESS,
                ]);

                // Clear session
                session()->forget(['cart', 'coupon', 'checkout_data', 'square_order_id']);

                Log::info('Square payment successful', [
                    'order_id' => $order->id,
                    'payment_id' => $payment->getId(),
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => true,
                    'redirect_url' => route('home'),
                ]);
            } else {
                $errors = $apiResponse->getErrors();
                Log::error('Square API Error', [
                    'order_id' => $order->id,
                    'errors' => $errors,
                    'user_id' => Auth::id()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => $this->formatSquareErrors($errors),
                ], 400);
            }
        } catch (ApiException $e) {
            Log::error('Square API Exception', [
                'order_id' => $order->id,
                'errors' => $e->getResponseBody(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Payment processing failed. Please try again.',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected Payment Error', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'An unexpected error occurred. Please try again.',
            ], 500);
        }
    }


    // /**
    //  * Process Square payment
    //  */
    // public function processPayment(Request $request)
    // {
    //     // Validate request
    //     $validated = $request->validate([
    //         'sourceId' => 'required|string',
    //     ]);

    //     // Retrieve order ID from session
    //     $orderId = session('square_order_id');
    //     if (!$orderId) {
    //         return response()->json(['success' => false, 'error' => 'Order session expired.'], 400);
    //     }

    //     // Fetch order
    //     $order = Order::find($orderId);
    //     if (!$order) {
    //         return response()->json(['success' => false, 'error' => 'Order not found.'], 404);
    //     }

    //     try {
    //         // Prepare payment amount
    //         $amountInCents = (int) round($order->total_amount * 100);
    //         $money = new Money([
    //             'amount' => $amountInCents,
    //             'currency' => Currency::Usd->value,
    //         ]);

    //         // Create payment request
    //         $paymentRequest = new CreatePaymentRequest([
    //             'sourceId' => $validated['sourceId'],
    //             'idempotencyKey' => uniqid(),
    //             'amountMoney' => $money,
    //         ]);

    //         // Set location ID if available
    //         $locationId = config('services.square.location_id');
    //         if ($locationId) {
    //             $paymentRequest->setLocationId($locationId);
    //         }

    //         // Execute payment
    //         $response = $this->client->payments->create(
    //             request: $paymentRequest,
    //             options: ['timeout' => 5.0] // Set timeout for request
    //         );

    //         if ($response->isSuccess()) {
    //             $payment = $response->getResult()->getPayment();

    //             // Update order status
    //             $order->update([
    //                 'transaction_id' => $payment->getId(),
    //                 'payment_status' => Order::PAYMENT_STATUS_PAID,
    //                 'status' => Order::STATUS_PROCESS,
    //             ]);

    //             // Clear session data
    //             session()->forget(['cart', 'coupon', 'checkout_data', 'square_order_id']);

    //             Log::info('Square payment successful', [
    //                 'order_id' => $order->id,
    //                 'payment_id' => $payment->getId(),
    //                 'user_id' => Auth::id()
    //             ]);

    //             return response()->json([
    //                 'success' => true,
    //                 'redirect_url' => route('home'),
    //             ]);
    //         }

    //         // Handle API errors
    //         $errors = $response->getErrors();
    //         Log::error('Square payment failed', [
    //             'order_id' => $order->id,
    //             'errors' => $errors,
    //             'user_id' => Auth::id()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => $this->formatSquareErrors($errors),
    //         ], 400);
    //     } catch (SquareApiException $e) {
    //         Log::error('Square API Error: ' . $e->getMessage(), [
    //             'order_id' => $order->id,
    //             'status_code' => $e->getCode(),
    //             'response_body' => $e->getBody(),
    //             'user_id' => Auth::id()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => 'Payment processing failed. Please try again.'
    //         ], 500);
    //     } catch (\Exception $e) {
    //         Log::error('Unexpected Payment Error: ' . $e->getMessage(), [
    //             'order_id' => $order->id,
    //             'user_id' => Auth::id(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'error' => 'An unexpected error occurred. Please try again.'
    //         ], 500);
    //     }
    // }

    /**
     * Handle successful payment
     */
    public function success(Request $request)
    {
        return redirect()->route('home')
            ->with('success', 'Payment completed successfully! Thank you for your purchase.');
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request)
    {
        $orderId = session('square_order_id');
        if ($orderId) {
            try {
                Order::where('id', $orderId)->update([
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                    'status' => Order::STATUS_NEW
                ]);
                session()->forget('square_order_id');
            } catch (\Exception $e) {
                Log::error('Error updating order status on cancellation: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'user_id' => Auth::id()
                ]);
            }
        }

        return redirect()->route('home')
            ->with('error', 'Your Square payment has been cancelled. You can try again or choose a different payment method.');
    }

    /**
     * Handle Square webhook events
     */
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $signatureHeader = $request->header('x-square-hmacsha256-signature');
        $signatureKey = config('services.square.webhook_signature_key');
        $notificationUrl = config('services.square.webhook_url');

        // Verify webhook signature
        if ($signatureKey && $notificationUrl) {
            try {
                $isValidSignature = WebhooksHelper::verifySignature(
                    requestBody: $payload,
                    signatureHeader: $signatureHeader,
                    signatureKey: $signatureKey,
                    notificationUrl: $notificationUrl
                );

                if (!$isValidSignature) {
                    Log::warning('Invalid Square webhook signature received', [
                        'payload' => $payload
                    ]);
                    return response('Invalid signature', 401);
                }
            } catch (\Exception $e) {
                Log::error('Square webhook signature verification failed: ' . $e->getMessage(), [
                    'payload' => $payload
                ]);
                return response('Signature verification failed', 401);
            }
        }

        try {
            $event = json_decode($payload, true);
            if (!$event || !isset($event['type'])) {
                Log::warning('Invalid Square webhook payload received', [
                    'payload' => $payload
                ]);
                return response('Invalid payload', 400);
            }

            Log::info('Square webhook received', [
                'event_type' => $event['type'],
                'merchant_id' => $event['merchant_id'] ?? 'unknown'
            ]);

            switch ($event['type']) {
                case 'payment.created':
                case 'payment.updated':
                    $this->handlePaymentEvent($event);
                    break;
                default:
                    Log::info('Received unhandled Square event type: ' . $event['type']);
            }

            return response('Success', 200);
        } catch (\Exception $e) {
            Log::error('Square webhook processing error: ' . $e->getMessage(), [
                'payload' => $payload
            ]);
            return response('Webhook error', 500);
        }
    }

    /**
     * Handle payment events from Square webhook
     */
    private function handlePaymentEvent(array $event): void
    {
        $payment = $event['data']['object']['payment'] ?? null;

        if (!$payment || !isset($payment['id'])) {
            Log::warning('Invalid payment data in Square webhook event', [
                'event' => $event
            ]);
            return;
        }

        $order = Order::where('transaction_id', $payment['id'])->first();
        if (!$order) {
            Log::warning('Order not found for Square payment ID: ' . $payment['id']);
            return;
        }

        switch ($payment['status'] ?? '') {
            case 'COMPLETED':
                if ($order->payment_status !== Order::PAYMENT_STATUS_PAID) {
                    $order->update([
                        'payment_status' => Order::PAYMENT_STATUS_PAID,
                        'status' => Order::STATUS_PROCESS
                    ]);
                    Log::info('Order payment confirmed via Square webhook', [
                        'order_id' => $order->id,
                        'payment_id' => $payment['id']
                    ]);
                }
                break;

            case 'FAILED':
            case 'CANCELED':
                $order->update([
                    'payment_status' => Order::PAYMENT_STATUS_UNPAID,
                    'status' => Order::STATUS_NEW
                ]);
                Log::info('Order payment failed via Square webhook', [
                    'order_id' => $order->id,
                    'status' => $payment['status']
                ]);
                break;

            default:
                Log::info('Unhandled Square payment status via webhook', [
                    'order_id' => $order->id,
                    'status' => $payment['status']
                ]);
        }
    }

    /**
     * Calculate shipping cost based on shipping ID
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
     * Format Square API errors for user-friendly display
     */
    private function formatSquareErrors(array $errors): string
    {
        if (empty($errors)) {
            return 'Unknown payment error occurred.';
        }

        $errorMessages = array_map(function ($error) {
            return $error['detail'] ?? $error['code'] ?? 'Unknown error';
        }, $errors);

        return implode('. ', $errorMessages);
    }
}
