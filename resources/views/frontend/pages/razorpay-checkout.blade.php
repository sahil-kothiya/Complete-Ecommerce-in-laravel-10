@extends('frontend.layouts.master')
@section('title', 'Razorpay Payment')

@section('main-content')
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4>Complete Your Payment</h4>
                </div>
                <div class="card-body">
                    <div class="order-summary mb-4">
                        <h5>Order Summary</h5>
                        <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
                        <p><strong>Total Amount:</strong> ₹{{ number_format($order->total_amount, 2) }}</p>
                    </div>

                    <button id="rzp-button" class="btn btn-primary btn-lg btn-block">
                        Pay ₹{{ number_format($order->total_amount, 2) }} with Razorpay
                    </button>

                    <div class="mt-3">
                        <a href="{{ route('payment.cancel') }}" class="btn btn-secondary">Cancel Payment</a>
                    </div>

                    <!-- Test Card Details for Development -->
                    @if(config('app.env') === 'local')
                    <div class="alert alert-info mt-3">
                        <h6>Test Card Details:</h6>
                        <p><strong>Card Number:</strong> 4111 1111 1111 1111</p>
                        <p><strong>CVV:</strong> 123</p>
                        <p><strong>Expiry:</strong> Any future date</p>
                        <p><strong>Name:</strong> Any name</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzp-button').onclick = function(e) {
    var options = {
        "key": "{{ $razorpayKey }}",
        "amount": {{ $razorpayOrder['amount'] }},
        "currency": "{{ $razorpayOrder['currency'] }}",
        "name": "{{ config('app.name') }}",
        "description": "Order #{{ $order->order_number }}",
        "order_id": "{{ $razorpayOrder['id'] }}",
        "handler": function (response) {
            // Create form and submit
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = "{{ route('razorpay.success') }}";
            
            // Add CSRF token
            var csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = '_token';
            csrfInput.value = "{{ csrf_token() }}";
            form.appendChild(csrfInput);

            // Add payment details
            var paymentId = document.createElement('input');
            paymentId.type = 'hidden';
            paymentId.name = 'razorpay_payment_id';
            paymentId.value = response.razorpay_payment_id;
            form.appendChild(paymentId);

            var orderId = document.createElement('input');
            orderId.type = 'hidden';
            orderId.name = 'razorpay_order_id';
            orderId.value = response.razorpay_order_id;
            form.appendChild(orderId);

            var signature = document.createElement('input');
            signature.type = 'hidden';
            signature.name = 'razorpay_signature';
            signature.value = response.razorpay_signature;
            form.appendChild(signature);

            document.body.appendChild(form);
            form.submit();
        },
        "prefill": {
            "name": "{{ $user->first_name ?? '' }} {{ $user->last_name ?? '' }}",
            "email": "{{ $user->email }}",
            "contact": "{{ str_replace('+91', '', $user->phone ?? '') }}"
        },
        "theme": {
            "color": "#3399cc"
        },
        "modal": {
            "ondismiss": function() {
                window.location.href = "{{ route('payment.cancel') }}";
            }
        },
        "config": {
            "display": {
                "blocks": {
                    "banks": {
                        "name": "All payment methods",
                        "instruments": [
                            {
                                "method": "card"
                            },
                            {
                                "method": "netbanking"
                            },
                            {
                                "method": "wallet"
                            },
                            {
                                "method": "upi"
                            }
                        ]
                    }
                },
                "sequence": ["block.banks"],
                "preferences": {
                    "show_default_blocks": true
                }
            }
        }
    };
    var rzp = new Razorpay(options);
    rzp.on('payment.failed', function (response){
        alert('Payment Failed: ' + response.error.description);
        window.location.href = "{{ route('payment.cancel') }}";
    });
    rzp.open();
    e.preventDefault();
}
</script>
@endsection