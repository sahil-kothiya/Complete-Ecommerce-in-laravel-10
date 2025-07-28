@extends('frontend.layouts.master')

@section('title','Stripe Payment')

@section('main-content')

<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{route('home')}}">Home<i class="ti-arrow-right"></i></a></li>
                        <li><a href="javascript:void(0)">Checkout<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0)">Stripe Payment</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Breadcrumbs -->

<!-- Start Stripe Payment -->
<section class="shop checkout section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="checkout-form">
                    <h2>Complete Your Payment</h2>
                    <p>Order #{{ $order->order_number }} - Total: ${{ number_format($order->total_amount, 2) }}</p>

                    <!-- Order Summary -->
                    <div class="order-summary mb-4">
                        <h4>Order Summary</h4>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($items as $item)
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td>{{ $item['quantity'] }}</td>
                                        <td>${{ number_format($item['price'], 2) }}</td>
                                        <td>${{ number_format($item['price'] * $item['quantity'], 2) }}</td>
                                    </tr>
                                    @endforeach
                                    @if($order->coupon > 0)
                                    <tr>
                                        <td colspan="3"><strong>Discount:</strong></td>
                                        <td><strong>-${{ number_format($order->coupon, 2) }}</strong></td>
                                    </tr>
                                    @endif
                                    <tr class="table-active">
                                        <td colspan="3"><strong>Total:</strong></td>
                                        <td><strong>${{ number_format($order->total_amount, 2) }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Stripe Payment Form -->
                    <div class="payment-form">
                        <form id="payment-form">
                            <div id="card-element">
                                <!-- A Stripe Element will be inserted here. -->
                            </div>

                            <!-- Used to display form errors. -->
                            <div id="card-errors" role="alert" class="text-danger mt-2"></div>

                            <div class="mt-4">
                                <button id="submit-payment" class="btn btn-primary btn-lg btn-block">
                                    <span id="button-text">Pay ${{ number_format($order->total_amount, 2) }}</span>
                                    <div id="spinner" class="spinner-border spinner-border-sm ms-2" role="status" style="display: none;">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </button>
                            </div>

                            <div class="mt-3 text-center">
                                <a href="{{ route('cart') }}" class="btn btn-secondary">
                                    <i class="ti-arrow-left"></i> Back to Cart
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!--/ End Stripe Payment -->

@endsection

@push('styles')
<style>
    .StripeElement {
        box-sizing: border-box;
        height: 40px;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        background-color: white;
        box-shadow: 0 1px 3px 0 #e6ebf1;
        transition: box-shadow 150ms ease;
    }

    .StripeElement--focus {
        box-shadow: 0 1px 3px 0 #cfd7df;
    }

    .StripeElement--invalid {
        border-color: #fa755a;
    }

    .StripeElement--webkit-autofill {
        background-color: #fefde5 !important;
    }

    .order-summary {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
    }

    .payment-form {
        background: white;
        padding: 30px;
        border-radius: 5px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    }
</style>
@endpush

@push('scripts')
<script src="https://js.stripe.com/v3/"></script>
<script>
    // Create a Stripe client
    const stripe = Stripe('{{ $stripeKey }}');

    // Create an instance of Elements
    const elements = stripe.elements();

    // Custom styling can be passed to options when creating an Element
    const style = {
        base: {
            color: '#32325d',
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            fontSmoothing: 'antialiased',
            fontSize: '16px',
            '::placeholder': {
                color: '#aab7c4'
            }
        },
        invalid: {
            color: '#fa755a',
            iconColor: '#fa755a'
        }
    };

    // Create an instance of the card Element
    const card = elements.create('card', {
        style: style
    });

    // Add an instance of the card Element into the `card-element` <div>
    card.mount('#card-element');

    // Handle real-time validation errors from the card Element
    card.on('change', function(event) {
        const displayError = document.getElementById('card-errors');
        if (event.error) {
            displayError.textContent = event.error.message;
        } else {
            displayError.textContent = '';
        }
    });

    // Handle form submission
    const form = document.getElementById('payment-form');
    const submitButton = document.getElementById('submit-payment');
    const buttonText = document.getElementById('button-text');
    const spinner = document.getElementById('spinner');

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        // Disable the submit button and show spinner
        submitButton.disabled = true;
        buttonText.style.display = 'none';
        spinner.style.display = 'inline-block';

        const {
            token,
            error
        } = await stripe.createToken(card);

        if (error) {
            // Show error to customer
            const errorElement = document.getElementById('card-errors');
            errorElement.textContent = error.message;

            // Re-enable the submit button
            submitButton.disabled = false;
            buttonText.style.display = 'inline';
            spinner.style.display = 'none';
        } else {
            // Confirm the payment
            const clientSecret = '{{ $clientSecret }}';

            const {
                error: confirmError
            } = await stripe.confirmCardPayment(clientSecret, {
                payment_method: {
                    card: card,
                    billing_details: {
                        name: '{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}',
                        email: '{{ auth()->user()->email }}',
                    }
                }
            });

            if (confirmError) {
                // Show error to customer
                const errorElement = document.getElementById('card-errors');
                errorElement.textContent = confirmError.message;

                // Re-enable the submit button
                submitButton.disabled = false;
                buttonText.style.display = 'inline';
                spinner.style.display = 'none';
            } else {
                // Payment succeeded, redirect to success page
                window.location.href = "{{ route('stripe.success') }}?payment_intent={{ $paymentIntent->id }}";
            }
        }
    });
</script>
@endpush