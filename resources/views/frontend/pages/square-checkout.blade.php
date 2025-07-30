<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Square Payment - {{ config('app.name') }}</title>
    @if(config('services.square.environment') === 'sandbox')
    <script type="text/javascript" src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    echo "local";
    @else
    <script type="text/javascript" src="https://web.squarecdn.com/v1/square.js"></script>
    echo "live";
    @endif
    <style>
        /* Your existing styles remain unchanged */
        .payment-form {
            max-width: 550px;
            margin: 50px auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        #card-container {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 12px;
            background-color: #fff;
            min-height: 100px;
        }

        .btn {
            width: 100%;
            padding: 12px 30px;
            background: #0066cc;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 4px;
            font-size: 16px;
            font-weight: bold;
        }

        .btn:hover {
            background: #0052a3;
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-cancel {
            background: #dc3545;
            margin-top: 10px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-cancel:hover {
            background: #c82333;
        }

        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }

        .error-message {
            color: #dc3545;
            margin-top: 10px;
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            display: none;
        }

        .loading {
            display: none;
            text-align: center;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="payment-form">
        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Order #:</strong> {{ $order->order_number }}</p>
            <p><strong>Total:</strong> ${{ number_format($order->total_amount, 2) }}</p>
            <h4>Items:</h4>
            @foreach($items as $item)
            <p>{{ $item['name'] }} x {{ $item['quantity'] }} = ${{ number_format($item['price'] * $item['quantity'], 2) }}</p>
            @endforeach
        </div>

        <form id="payment-form">
            @csrf
            <div class="form-group">
                <label>Card Details</label>
                <div id="card-container"></div>
            </div>

            <div id="error-message" class="error-message"></div>

            <button id="pay-button" class="btn" type="button">
                Pay ${{ number_format($order->total_amount, 2) }}
            </button>

            <div class="loading" id="loading">
                <p>Processing payment...</p>
            </div>
        </form>

        <div style="margin-top: 20px;">
            <a href="{{ route('square.cancel') }}" class="btn btn-cancel">Cancel Payment</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', async function() {
            if (!window.Square) {
                console.error('Square.js failed to load properly');
                showError('Unable to load payment system. Please check your internet connection and try again.');
                return;
            }

            const appId = '{{ $applicationId }}';
            const locationId = '{{ $locationId }}';

            let payments;
            try {
                payments = window.Square.payments(appId, locationId);
            } catch (e) {
                console.error('Square Payments initialization failed', e);
                if (e.name === 'WebSdkEmbedError') {
                    showError('This page must be accessed over HTTPS. Please use a secure connection or contact support.');
                } else {
                    showError('Failed to initialize payment system. Please try again or contact support.');
                }
                return;
            }

            let card;
            try {
                card = await payments.card();
                await card.attach('#card-container');
            } catch (e) {
                console.error('Initializing Card failed', e);
                showError('Failed to initialize payment form. Please refresh the page.');
                return;
            }

            // Handle payment
            const payButton = document.getElementById('pay-button');
            payButton.addEventListener('click', async function(event) {
                event.preventDefault();

                // Disable button and show loading
                payButton.disabled = true;
                document.getElementById('loading').style.display = 'block';
                hideError();

                try {
                    const result = await card.tokenize();
                    if (result.status === 'OK') {
                        // Send token to your server
                        await processPayment(result.token);
                    } else {
                        let errorMessage = 'Tokenization failed';
                        if (result.errors) {
                            errorMessage = result.errors.map(error => error.message).join(', ');
                        }
                        throw new Error(errorMessage);
                    }
                } catch (e) {
                    console.error('Payment failed:', e);
                    showError(e.message || 'Payment failed. Please try again.');
                } finally {
                    // Re-enable button and hide loading
                    payButton.disabled = false;
                    document.getElementById('loading').style.display = 'none';
                }
            });

            async function processPayment(token) {
                const response = await fetch("https://d01e8b158038.ngrok-free.app/square/process-payment", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({
                        sourceId: token
                    })
                });

                if (!response.ok) {
                    const text = await response.text();
                    console.error('Raw response:', text);
                    throw new Error('Server error or wrong endpoint. Contact support.');
                }

                const data = await response.json();
                if (data.success) {
                    window.location.href = data.redirect_url;
                } else {
                    throw new Error(data.error || 'Payment processing failed');
                }
            }

            function showError(message) {
                const errorDiv = document.getElementById('error-message');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
            }

            function hideError() {
                document.getElementById('error-message').style.display = 'none';
            }
        });
    </script>
</body>

</html>