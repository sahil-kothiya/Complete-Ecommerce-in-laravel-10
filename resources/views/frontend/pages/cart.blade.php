@extends('frontend.layouts.master')

@section('title','Cart Page')

@section('main-content')

<!-- Breadcrumbs -->
<div class="breadcrumbs">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="bread-inner">
					<ul class="bread-list">
						<li><a href="{{ route('home') }}">Home<i class="ti-arrow-right"></i></a></li>
						<li class="active"><a href="">Cart</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- End Breadcrumbs -->

<!-- Shopping Cart -->
<div class="shopping-cart section">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<!-- Shopping Summery -->
				<table class="table shopping-summery">
					<thead>
						<tr class="main-hading">
							<th>PRODUCT</th>
							<th>NAME</th>
							<th class="text-center">UNIT PRICE</th>
							<th class="text-center">QUANTITY</th>
							<th class="text-center">TOTAL</th>
							<th class="text-center"><i class="ti-trash remove-icon"></i></th>
						</tr>
					</thead>
					<tbody id="cart_item_list">
						<form action="{{ route('cart.update') }}" method="POST" id="cartUpdateForm">
							@csrf
							@php $allCartItems = Helper::getAllProductFromCart(); @endphp
							@if($allCartItems->isNotEmpty())
							@foreach($allCartItems as $key => $cart)
							@php
							// Determine price and variant details based on variant or base product
							$originalPrice = 0;
							$discount = 0;

							if ($cart->variant) {
							    $originalPrice = $cart->variant->price ?? 0;
							    $discount = $cart->variant->discount ?? 0;
							    $variantDetails = $cart->variant->display_name; // Use display_name attribute for proper variant details
							} else {
							    $originalPrice = $cart->product->base_price ?? 0;
							    $discount = $cart->product->base_discount ?? 0;
							    $variantDetails = null; // No variant, so no details
							}
							$discountedPrice = $originalPrice * (1 - ($discount / 100));
							$isDiscounted = $discountedPrice < $originalPrice;
							$total = $discountedPrice * ($cart->quantity ?? 1);
							@endphp
							<tr data-cart-id="{{ $cart->id }}">
								<td class="image product-slider" data-title="No">
									{{-- Use variant images if available, fallback to product images --}}
									@php
										$images = ($cart->variant && $cart->variant->images && $cart->variant->images->isNotEmpty()) 
											? $cart->variant->images 
											: ($cart->product->images ?? collect());
									@endphp
									<div class="slider-wrapper" data-images="{{ $images->count() ?? 0 }}">
										<div class="slider-container">
											@forelse($images as $index => $image)
											<img src="{{ $image->url ?? asset('default.jpg') }}" alt="{{ $cart->product->title ?? 'Product' }}" class="slider-img" data-index="{{ $index }}">
											@empty
											<img src="{{ asset('default.jpg') }}" alt="{{ $cart->product->title ?? 'Product' }}" class="slider-img" data-index="0">
											@endforelse
										</div>
										@if($images->count() > 1)
										<div class="slider-indicators">
											@foreach($images as $index => $image)
											<span class="indicator {{ $index == 0 ? 'active' : '' }}" data-index="{{ $index }}"></span>
											@endforeach
										</div>
										@endif
									</div>
								</td>

								<td class="product-des" data-title="Description">
									<p class="product-name">
										<a href="{{ route('product-detail', $cart->product->slug ?? '') }}" target="_blank">{{ $cart->product->title ?? 'Product' }}</a>
									</p>
									<p class="product-des">{!! ($cart->product->summary ?? '') !!}</p>
									@if($cart->variant && $variantDetails && $variantDetails !== 'Variant #' . $cart->variant->id)
										<small class="text-muted">Variant: {{ $variantDetails }}</small>
									@endif
								</td>

								<td class="price" data-title="Price" data-original-price="{{ $originalPrice }}" data-discounted-price="{{ $discountedPrice }}" data-discount="{{ $discount }}">
									<div class="price-info">
										@if($isDiscounted && $originalPrice > 0)
										<span class="text-danger font-weight-bold">${{ number_format($discountedPrice, 2) }}</span><br>
										<small><del class="text-muted">${{ number_format($originalPrice, 2) }}</del></small>
										<small class="text-success ml-2">{{ $discount }}% off</small>
										<span class="price-tooltip" data-toggle="tooltip" data-placement="top" title="Price may vary due to: 
											- Discounts or offers applied
											- Different variants (e.g., {{ $variantDetails ?? 'Standard' }})
											- Seasonal promotions
											- Stock availability">
											<i class="ti-info-alt"></i>
										</span>
										@else
										<span>${{ number_format($discountedPrice, 2) }}</span>
										<span class="price-tooltip" data-toggle="tooltip" data-placement="top" title="Price may vary due to: 
											- Different variants (e.g., {{ $variantDetails ?? 'Standard' }})
											- Stock availability">
											<i class="ti-info-alt"></i>
										</span>
										@endif
									</div>
								</td>

								<td class="qty" data-title="Qty">
									<div class="input-group">
										<div class="button minus">
											<button type="button" class="btn btn-primary btn-number" data-type="minus" data-field="quant[{{ $key }}]" data-cart-id="{{ $cart->id }}">
												<i class="ti-minus"></i>
											</button>
										</div>
										<input type="text" name="quant[{{ $key }}]" class="input-number" data-min="1" data-max="100" value="{{ $cart->quantity ?? 1 }}" data-cart-id="{{ $cart->id }}">
										<input type="hidden" name="qty_id[]" value="{{ $cart->id }}">
										<div class="button plus">
											<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[{{ $key }}]" data-cart-id="{{ $cart->id }}">
												<i class="ti-plus"></i>
											</button>
										</div>
									</div>
								</td>

								<td class="total-amount cart_single_price" data-title="Total">
									<span class="money">${{ number_format($total, 2) }}</span>
								</td>

								<td class="action" data-title="Remove">
									<a href="javascript:void(0)" class="remove-cart-item" data-cart-id="{{ $cart->id }}"><i class="ti-trash remove-icon"></i></a>
								</td>
							</tr>
							@endforeach
							@else
							<tr>
								<td class="text-center" colspan="6">
									There are no any carts available. <a href="{{ route('home') }}" style="color:blue;">Continue shopping</a>
								</td>
							</tr>
							@endif
						</form>
					</tbody>
				</table>
				<!--/ End Shopping Summery -->
			</div>
		</div>
		<div class="row">
			<div class="col-12">
				<!-- Total Amount -->
				<div class="total-amount">
					<div class="row">
						<div class="col-lg-8 col-md-5 col-12">
							<div class="left">
								<div class="coupon">
									<form action="{{ route('coupon-apply') }}" method="POST">
										@csrf
										<input name="code" placeholder="Enter Your Coupon">
										<button class="btn">Apply</button>
									</form>
								</div>
							</div>
						</div>
						<div class="col-lg-4 col-md-7 col-12">
							<div class="right">
								@php
								$cartSubtotal = 0;
								$categorySaved = 0;
								foreach($allCartItems as $cartItem) {
								    if ($cartItem->variant) {
								        $itemOriginal = $cartItem->variant->price ?? 0;
								        $itemDiscount = $cartItem->variant->discount ?? 0;
								    } else {
								        $itemOriginal = $cartItem->product->base_price ?? 0;
								        $itemDiscount = $cartItem->product->base_discount ?? 0;
								    }
								    $itemDiscounted = $itemOriginal * (1 - ($itemDiscount / 100));
								    $itemTotal = $itemDiscounted * ($cartItem->quantity ?? 1);
								    $cartSubtotal += $itemTotal;
								    $categorySaved += ($itemOriginal * ($cartItem->quantity ?? 1)) - $itemTotal;
								}
								// Get coupon value from session
								$couponValue = session('coupon')['value'] ?? 0;

								// Validate coupon: ensure subtotal > 0 and coupon is valid and not exceeding subtotal
								$validCoupon = $cartSubtotal > 0 && $couponValue > 0 && $couponValue <= $cartSubtotal;

								// Apply coupon discount only if valid
								$couponDiscount = $validCoupon ? min($couponValue, $cartSubtotal) : 0;

								$finalAmount = max(0, $cartSubtotal - $couponDiscount); // Ensure no negative amount

								// Clear invalid coupon from session
								if (!$validCoupon && session()->has('coupon')) {
									session()->forget('coupon');
								}
								@endphp

								<ul>
									<li class="order_subtotal">
										Cart Subtotal
										<span class="subtotal-value">${{ number_format($cartSubtotal, 2) }}</span>
									</li>

									@if($categorySaved > 0)
									<li class="discount-item">
										Discounts
										<span class="text-success">- ${{ number_format($categorySaved, 2) }}</span>
									</li>
									@endif

									@if($couponDiscount > 0)
									<li class="coupon_price">
										Coupon Applied
										<span class="text-success coupon-value">- ${{ number_format($couponDiscount, 2) }}</span>
									</li>
									@endif

									<li class="last" id="order_total_price">
										You Pay
										<span class="final-amount">${{ number_format($finalAmount, 2) }}</span>
									</li>
								</ul>

								<div class="button5">
									<a href="{{ route('checkout') }}" class="btn">Checkout</a>
									<a href="{{ route('product-grids') }}" class="btn">Continue Shopping</a>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!--/ End Total Amount -->
			</div>
		</div>
	</div>
</div>
<!--/ End Shopping Cart -->

<!-- Start Shop Services Area  -->
<section class="shop-services section">
	<div class="container">
		<div class="row">
			<div class="col-lg-3 col-md-6 col-12">
				<!-- Start Single Service -->
				<div class="single-service">
					<i class="ti-rocket"></i>
					<h4>Free shiping</h4>
					<p>Orders over $100</p>
				</div>
				<!-- End Single Service -->
			</div>
			<div class="col-lg-3 col-md-6 col-12">
				<!-- Start Single Service -->
				<div class="single-service">
					<i class="ti-reload"></i>
					<h4>Free Return</h4>
					<p>Within 30 days returns</p>
				</div>
				<!-- End Single Service -->
			</div>
			<div class="col-lg-3 col-md-6 col-12">
				<!-- Start Single Service -->
				<div class="single-service">
					<i class="ti-lock"></i>
					<h4>Sucure Payment</h4>
					<p>100% secure payment</p>
				</div>
				<!-- End Single Service -->
			</div>
			<div class="col-lg-3 col-md-6 col-12">
				<!-- Start Single Service -->
				<div class="single-service">
					<i class="ti-tag"></i>
					<h4>Best Peice</h4>
					<p>Guaranteed price</p>
				</div>
				<!-- End Single Service -->
			</div>
		</div>
	</div>
</section>
<!-- End Shop Newsletter -->

<!-- Start Shop Newsletter  -->
@include('frontend.layouts.newsletter')
<!-- End Shop Newsletter -->

@endsection

@push('styles')
<style>
	.slider-wrapper {
		position: relative;
		width: 80px;
		height: 80px;
		overflow: hidden;
		border-radius: 4px;
		cursor: pointer;
		background: #f8f9fa;
		border: 1px solid #dee2e6;
	}

	.slider-container {
		position: relative;
		width: 100%;
		height: 100%;
		display: flex;
		transition: transform 0.3s ease-in-out;
	}

	.slider-img {
		flex: 0 0 100%;
		width: 80px;
		height: 80px;
		object-fit: cover;
		display: block;
	}

	.slider-indicators {
		position: absolute;
		bottom: 4px;
		left: 50%;
		transform: translateX(-50%);
		display: flex;
		gap: 2px;
		z-index: 10;
	}

	.indicator {
		width: 6px;
		height: 6px;
		border-radius: 50%;
		background: rgba(255, 255, 255, 0.5);
		cursor: pointer;
		transition: background 0.3s ease;
	}

	.indicator.active {
		background: rgba(255, 255, 255, 0.9);
	}

	.slider-wrapper:hover .indicator {
		background: rgba(255, 255, 255, 0.7);
	}

	.slider-wrapper:hover .indicator.active {
		background: #fff;
	}

	/* Hide indicators if only one image */
	.slider-wrapper[data-images="1"] .slider-indicators {
		display: none;
	}

	li.shipping {
		display: inline-flex;
		width: 100%;
		font-size: 14px;
	}

	li.shipping .input-group-icon {
		width: 100%;
		margin-left: 10px;
	}

	.input-group-icon .icon {
		position: absolute;
		left: 20px;
		top: 0;
		line-height: 40px;
		z-index: 3;
	}

	.form-select {
		height: 30px;
		width: 100%;
	}

	.form-select .nice-select {
		border: none;
		border-radius: 0px;
		height: 40px;
		background: #f6f6f6 !important;
		padding-left: 45px;
		padding-right: 40px;
		width: 100%;
	}

	.list li {
		margin-bottom: 0 !important;
	}

	.list li:hover {
		background: #F7941D !important;
		color: white !important;
	}

	.form-select .nice-select::after {
		top: 14px;
	}

	/* Price Tooltip Styling (Flipkart/Amazon-like) */
	.price-info {
		position: relative;
	}
	.price-tooltip {
		margin-left: 5px;
		color: #555;
		cursor: pointer;
		font-size: 14px;
	}
	.price-tooltip:hover {
		color: #007bff;
	}
	.tooltip-inner {
		max-width: 250px;
		background-color: #fff;
		color: #000;
		border: 1px solid #ccc;
		border-radius: 4px;
		padding: 8px;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	}
	.bs-tooltip-top .arrow::before {
		border-top-color: #ccc;
	}
</style>
@endpush

@push('scripts')
<script src="{{ asset('frontend/js/nice-select/js/jquery.nice-select.min.js') }}"></script>
<script src="{{ asset('frontend/js/select2/js/select2.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script> <!-- Added for tooltip -->
<script>
	$(document).ready(function() {
		$("select.select2").select2();

		// Initialize Bootstrap tooltips
		$('[data-toggle="tooltip"]').tooltip();

		// Handle quantity change via buttons
		$('.btn-number').on('click', function(e) {
			e.preventDefault();
			var $button = $(this);
			var cartId = $button.data('cart-id');
			var $input = $button.closest('.input-group').find('.input-number');
			var currentVal = parseInt($input.val());
			var minVal = parseInt($input.data('min'));
			var maxVal = parseInt($input.data('max'));
			var type = $button.data('type');
			var newVal;

			if (type === 'minus') {
				newVal = currentVal > minVal ? currentVal - 1 : minVal;
			} else if (type === 'plus') {
				newVal = currentVal < maxVal ? currentVal + 1 : maxVal;
			}

			if (newVal !== currentVal) {
				$input.val(newVal).trigger('change');
				updateCartItem(cartId, newVal);
			}
		});

		// Handle manual input change
		$('.input-number').on('change', function() {
			var $input = $(this);
			var cartId = $input.data('cart-id');
			var newVal = parseInt($input.val());
			var minVal = parseInt($input.data('min'));
			var maxVal = parseInt($input.data('max'));

			if (isNaN(newVal) || newVal < minVal) {
				$input.val(minVal);
				newVal = minVal;
			} else if (newVal > maxVal) {
				$input.val(maxVal);
				newVal = maxVal;
			}

			updateCartItem(cartId, newVal);
		});

		// AJAX function to update cart item
		function updateCartItem(cartId, quantity) {
			$.ajax({
				url: '{{ route('cart.update') }}',
				type: 'POST',
				data: {
					_token: $('meta[name="csrf-token"]').attr('content'),
					qty_id: [cartId],
					quant: { [cartId]: quantity }
				},
				success: function(response) {
					if (response.success) {
						// Update individual row total
						var $row = $('tr[data-cart-id="' + cartId + '"]');
						var $priceCell = $row.find('.price');
						var originalPrice = parseFloat($priceCell.data('original-price'));
						var discount = parseFloat($priceCell.data('discount'));
						var discountedPrice = originalPrice * (1 - (discount / 100));
						var newTotal = discountedPrice * quantity;
						$row.find('.cart_single_price .money').text('$' + number_format(newTotal, 2));

						// Update subtotal, discounts, and final amount
						updateSummary(response.cartSubtotal, response.categorySaved, response.couponDiscount, response.finalAmount);
					} else {
						alert('Failed to update cart. Please try again.');
					}
				},
				error: function(xhr, status, error) {
					console.error('Error:', status, error);
					console.log(xhr.responseText);
					alert('An error occurred. Please try again.');
				}
			});
		}

		// Update summary totals
		function updateSummary(subtotal, categorySaved, couponDiscount, finalAmount) {
			$('.subtotal-value').text('$' + number_format(subtotal, 2));
			if (categorySaved > 0) {
				$('.discount-item').find('span').text('- $' + number_format(categorySaved, 2));
			} else {
				$('.discount-item').remove();
			}
			if (couponDiscount > 0) {
				$('.coupon-value').text('- $' + number_format(couponDiscount, 2));
			} else {
				$('.coupon_price').remove();
			}
			$('.final-amount').text('$' + number_format(finalAmount, 2));
		}

		// Utility function to format number
		function number_format(number, decimals) {
			return number.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
		}

		// Handle remove cart item directly (no confirmation)
		$('.remove-cart-item').on('click', function(e) {
			e.preventDefault();
			var cartId = $(this).data('cart-id');
			$.ajax({
				url: '{{ route('cart-delete', ['id' => ':id']) }}'.replace(':id', cartId),
				type: 'POST',
				data: {
					_token: $('meta[name="csrf-token"]').attr('content'),
					id: cartId
				},
				success: function(response) {
					if (response.success) {
						$('tr[data-cart-id="' + cartId + '"]').remove();
						updateSummary(response.cartSubtotal, response.categorySaved, response.couponDiscount, response.finalAmount);
						if ($('#cart_item_list tr').length <= 2) { // 2 for header and empty row
							$('#cart_item_list').html('<tr><td class="text-center" colspan="6">There are no any carts available. <a href="{{ route('product-grids') }}" style="color:blue;">Continue shopping</a></td></tr>');
						}
					} else {
						alert('Failed to remove item. Please try again.');
					}
				},
				error: function(xhr, status, error) {
					console.error('Error:', status, error);
					console.log(xhr.responseText);
					alert('An error occurred. Please try again.');
				}
			});
		});
	});
</script>
@endpush