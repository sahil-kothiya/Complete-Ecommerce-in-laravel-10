@extends('frontend.layouts.master')
@section('title', 'Checkout page')

@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{route('home')}}">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0)">Checkout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Breadcrumbs -->

<!-- Start Checkout -->
<section class="shop checkout section">
    <div class="container">
        <form class="form" method="POST" action="{{ route('cart.order') }}" id="checkout-form">
            @csrf
            <div class="row">

                <!-- LEFT: Billing & Shipping Form -->
                <div class="col-lg-8 col-12">
                    <div class="checkout-form">
                        <h2>Make Your Checkout Here</h2>
                        <p>Please register in order to checkout more quickly</p>

                        <div class="row">
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>First Name<span>*</span></label>
                                    <input type="text" name="first_name" value="{{ old('first_name') }}" required>
                                    @error('first_name')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Last Name<span>*</span></label>
                                    <input type="text" name="last_name" value="{{ old('last_name') }}" required>
                                    @error('last_name')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Email Address<span>*</span></label>
                                    <input type="email" name="email" value="{{ old('email') }}" required>
                                    @error('email')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Phone Number <span>*</span></label>
                                    <input type="text" name="phone" value="{{ old('phone') }}" required>
                                    @error('phone')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Country<span>*</span></label>
                                    <select name="country" class="form-control" required>
                                        <option value="IN" selected>India</option>
                                        <!-- Keep your full list -->
                                        @foreach([
                                            'AF'=>'Afghanistan','AX'=>'Åland Islands','AL'=>'Albania','DZ'=>'Algeria',
                                            'US'=>'United States','CA'=>'Canada','GB'=>'United Kingdom'
                                            // ... add more as needed
                                        ] as $code => $name)
                                            <option value="{{ $code }}" {{ old('country') == $code ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Address Line 1<span>*</span></label>
                                    <input type="text" name="address1" value="{{ old('address1') }}" required>
                                    @error('address1')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Address Line 2</label>
                                    <input type="text" name="address2" value="{{ old('address2') }}">
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Postal Code</label>
                                    <input type="text" name="post_code" value="{{ old('post_code') }}">
                                </div>
                            </div>

                            <!-- SINGLE SHIPPING SELECT (used for form + summary) -->
                            <div class="col-lg-6 col-md-6 col-12">
                                <div class="form-group">
                                    <label>Shipping Method <span>*</span></label>
                                    <select name="shipping" id="shipping_select" class="form-control" required>
                                        <option value="">-- Select Shipping --</option>
                                        @foreach(\App\Models\Shipping::where('status', 'active')->get() as $ship)
                                            <option value="{{ $ship->id }}"
                                                    data-price="{{ $ship->price }}"
                                                    {{ old('shipping') == $ship->id ? 'selected' : '' }}>
                                                {{ $ship->type }} - ${{ number_format($ship->price, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('shipping')<span class="text-danger">{{ $message }}</span>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Order Summary -->
                <div class="col-lg-4 col-12">
                    <div class="order-details">

                        <!-- Cart Totals -->
                        <div class="single-widget">
                            <h2>CART TOTALS</h2>
                            <div class="content">
                                <ul>
                                    <li class="order_subtotal" data-price="{{ Helper::totalCartPrice() }}">
                                        Cart Subtotal <span>${{ number_format(Helper::totalCartPrice(), 2) }}</span>
                                    </li>

                                    <li class="shipping_line">
                                        Shipping Cost <span id="shipping_cost">$0.00</span>
                                    </li>

                                    @php
                                        $coupon = session('coupon');
                                        $couponValue = $coupon['value'] ?? 0;
                                    @endphp

                                    @if($couponValue > 0)
                                        <li class="coupon_price" data-price="{{ $couponValue }}">
                                            You Save <span>-${{ number_format($couponValue, 2) }}</span>
                                        </li>
                                    @endif

                                    <li class="last" id="order_total_price">
                                        Total <span>${{ number_format(Helper::totalCartPrice() - $couponValue, 2) }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Payment Methods -->
                        <div class="single-widget">
                            <h2>Payments</h2>
                            <div class="content">
                                <div class="checkbox">
                                    <label><input type="radio" name="payment_method" value="cod" required> Cash On Delivery</label><br>
                                    <label><input type="radio" name="payment_method" value="paypal"> PayPal</label><br>
                                    <label><input type="radio" name="payment_method" value="stripe"> Stripe</label><br>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="single-widget get-button">
                            <div class="content">
                                <button type="submit" class="btn" id="checkout-btn">Proceed to Checkout</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</section>
@endsection

@push('styles')
<style>
    .form-group label span { color: red; font-weight: bold; }
    .form-control { height: 45px; }
    #checkout-btn { width: 100%; }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const $subtotal    = $('.order_subtotal');
    const $shippingSel = $('#shipping_select');
    const $shippingCost= $('#shipping_cost');
    const $totalSpan   = $('#order_total_price span');
    const $coupon      = $('.coupon_price');

    const subtotal = parseFloat($subtotal.data('price')) || 0;
    const coupon   = parseFloat($coupon.data('price')) || 0;

    const updateTotal = () => {
        const shipping = parseFloat($shippingSel.find(':selected').data('price')) || 0;
        const total = subtotal + shipping - coupon;

        $shippingCost.text('$' + shipping.toFixed(2));
        $totalSpan.text('$' + total.toFixed(2));
    };

    // Initial update
    updateTotal();

    // Update on shipping change
    $shippingSel.on('change', updateTotal);

    // Update button text on payment change
    $('input[name="payment_method"]').on('change', function () {
        const texts = {
            cod: 'Place Order (COD)',
            paypal: 'Pay with PayPal',
            stripe: 'Pay with Card'
        };
        $('#checkout-btn').text(texts[this.value] || 'Proceed to Checkout');
    });

    // Form validation
    $('#checkout-form').on('submit', function (e) {
        if (!$shippingSel.val()) {
            e.preventDefault();
            alert('Please select a shipping method.');
            $shippingSel.focus();
            return false;
        }
        if (!$('input[name="payment_method"]:checked').length) {
            e.preventDefault();
            alert('Please select a payment method.');
            return false;
        }

        const $btn = $('#checkout-btn');
        $btn.prop('disabled', true).text('Processing...');
    });
});
</script>
@endpush