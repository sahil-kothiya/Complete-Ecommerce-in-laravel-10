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
						<li><a href="{{('home')}}">Home<i class="ti-arrow-right"></i></a></li>
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
						<form action="{{route('cart.update')}}" method="POST">
							@csrf
							@if(Helper::getAllProductFromCart())
							@foreach(Helper::getAllProductFromCart() as $key => $cart)
							<tr>
								@php
								$firstImage = $cart->product->images->first(); // Get first related image
								$imagePath = $firstImage ? $firstImage->image_path : 'default.jpg'; // Fallback if no image
								@endphp
								<td class="image product-slider" data-title="No">
									<div class="slider-wrapper" data-images="{{ $cart->product->images->count() }}">
										<div class="slider-container">
											@foreach($cart->product->images as $index => $image)
											<img src="{{ asset($image->image_path) }}" alt="{{ $cart->product['title'] }}" class="slider-img" data-index="{{ $index }}">
											@endforeach
										</div>
										@if($cart->product->images->count() > 1)
										<div class="slider-indicators">
											@foreach($cart->product->images as $index => $image)
											<span class="indicator {{ $index == 0 ? 'active' : '' }}" data-index="{{ $index }}"></span>
											@endforeach
										</div>
										@endif
									</div>
								</td>

								<td class="product-des" data-title="Description">
									<p class="product-name">
										<a href="{{ route('product-detail', $cart->product['slug']) }}" target="_blank">{{ $cart->product['title'] }}</a>
									</p>
									<p class="product-des">{!! $cart['summary'] !!}</p>
								</td>

								@php
								$discount = $discountService->getEffectiveDiscount($cart->product);
								$originalPrice = $cart['price'];
								$discountedPrice = $originalPrice;
								$isDiscounted = false;

								if ($discount) {
								$discountedPrice = $discountService->calculateDiscountedPrice($originalPrice, $discount);
								$isDiscounted = $discountedPrice < $originalPrice;
									}

									$total=$discountedPrice * $cart->quantity;
									@endphp

									<td class="price" data-title="Price">
										@if($isDiscounted)
										<span class="text-danger font-weight-bold">${{ number_format($discountedPrice, 2) }}</span><br>
										<small><del class="text-muted">${{ number_format($originalPrice, 2) }}</del></small>
										@else
										<span>${{ number_format($originalPrice, 2) }}</span>
										@endif
									</td>

									<td class="qty" data-title="Qty">
										<div class="input-group">
											<div class="button minus">
												<button type="button" class="btn btn-primary btn-number" data-type="minus" data-field="quant[{{ $key }}]">
													<i class="ti-minus"></i>
												</button>
											</div>
											<input type="text" name="quant[{{ $key }}]" class="input-number" data-min="1" data-max="100" value="{{ $cart->quantity }}">
											<input type="hidden" name="qty_id[]" value="{{ $cart->id }}">
											<div class="button plus">
												<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[{{ $key }}]">
													<i class="ti-plus"></i>
												</button>
											</div>
										</div>
									</td>

									<td class="total-amount cart_single_price" data-title="Total">
										<span class="money">${{ number_format($total, 2) }}</span>
									</td>

									<td class="action" data-title="Remove">
										<a href="{{ route('cart-delete', $cart->id) }}"><i class="ti-trash remove-icon"></i></a>
									</td>
							</tr>
							@endforeach

							<track>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
							<td></td>
							<td class="float-right">
								<button class="btn float-right" type="submit">Update</button>
							</td>
							</track>
							@else
							<tr>
								<td class="text-center">
									There are no any carts available. <a href="{{route('product-grids')}}" style="color:blue;">Continue shopping</a>

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
								<!-- <div class="coupon">
									<form action="{{route('coupon-store')}}" method="POST">
										@csrf
										<input name="code" placeholder="Enter Your Coupon">
										<button class="btn">Apply</button>
									</form>
								</div> -->
								{{-- <div class="checkbox">`
										@php
											$shipping=DB::table('shippings')->where('status','active')->limit(1)->get();
										@endphp
										<label class="checkbox-inline" for="2"><input name="news" id="2" type="checkbox" onchange="showMe('shipping');"> Shipping</label>
									</div> --}}
							</div>
						</div>
						<div class="col-lg-4 col-md-7 col-12">
							<div class="right">
								@php
								$cartSummary = Helper::totalCartPriceWithBreakdown();
								$cartSubtotal = $cartSummary['total'];
								$categorySaved = $cartSummary['saved'];
								$couponDiscount = session('coupon')['value'] ?? 0;
								$finalAmount = $cartSubtotal - $couponDiscount;
								@endphp

								<ul>
									<li class="order_subtotal">Cart Subtotal<span>${{ number_format($cartSubtotal + $categorySaved, 2) }}</span></li>

									@if($categorySaved > 0)
									<li class="category_discount">Category Discount<span class="text-success">- ${{ number_format($categorySaved, 2) }}</span></li>
									@endif

									@if($couponDiscount)
									<li class="coupon_price">Coupon Applied<span class="text-success">- ${{ number_format($couponDiscount, 2) }}</span></li>
									@endif

									<li class="last" id="order_total_price">You Pay<span>${{ number_format($finalAmount, 2) }}</span></li>
								</ul>

								<div class="button5">
									<a href="{{route('checkout')}}" class="btn">Checkout</a>
									<a href="{{route('product-grids')}}" class="btn">Continue shopping</a>
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
</style>
@endpush
@push('scripts')
<script src="{{asset('frontend/js/nice-select/js/jquery.nice-select.min.js')}}"></script>
<script src="{{ asset('frontend/js/select2/js/select2.min.js') }}"></script>
<script>
	$(document).ready(function() {
		$("select.select2").select2();
	});
	// $('select.nice-select').niceSelect();
</script>
<script>
	$(document).ready(function() {
		$('.shipping select[name=shipping]').change(function() {
			let cost = parseFloat($(this).find('option:selected').data('price')) || 0;
			let subtotal = parseFloat($('.order_subtotal').data('price'));
			let coupon = parseFloat($('.coupon_price').data('price')) || 0;
			// alert(coupon);
			$('#order_total_price span').text('$' + (subtotal + cost - coupon).toFixed(2));
		});

	});
</script>

<script>
	// Image Slider Functionality
	$(document).ready(function() {
		let sliderIntervals = {};

		$('.slider-wrapper').each(function() {
			const wrapper = $(this);
			const container = wrapper.find('.slider-container');
			const images = wrapper.find('.slider-img');
			const indicators = wrapper.find('.indicator');
			const totalImages = images.length;

			if (totalImages <= 1) return; // Skip if only one image

			let currentIndex = 0;

			function showImage(index) {
				const translateX = -index * 100;
				container.css('transform', `translateX(${translateX}%)`);

				indicators.removeClass('active');
				indicators.eq(index).addClass('active');

				currentIndex = index;
			}

			function nextImage() {
				const nextIndex = (currentIndex + 1) % totalImages;
				showImage(nextIndex);
			}

			function prevImage() {
				const prevIndex = (currentIndex - 1 + totalImages) % totalImages;
				showImage(prevIndex);
			}

			function startAutoSlide() {
				const wrapperId = wrapper.closest('tr').index();
				sliderIntervals[wrapperId] = setInterval(nextImage, 1500);
			}

			function stopAutoSlide() {
				const wrapperId = wrapper.closest('tr').index();
				if (sliderIntervals[wrapperId]) {
					clearInterval(sliderIntervals[wrapperId]);
					delete sliderIntervals[wrapperId];
				}
			}

			// Click on indicators to show specific image
			indicators.on('click', function() {
				const index = $(this).data('index');
				showImage(index);
			});

			// Mouse events for auto-slide
			wrapper.on('mouseenter', function() {
				startAutoSlide();
			});

			wrapper.on('mouseleave', function() {
				stopAutoSlide();
				showImage(0); // Return to first image
			});

			// Touch/swipe support for mobile
			let startX = 0;
			let startY = 0;
			let isSwipe = false;

			wrapper.on('touchstart', function(e) {
				startX = e.originalEvent.touches[0].clientX;
				startY = e.originalEvent.touches[0].clientY;
				isSwipe = false;
			});

			wrapper.on('touchmove', function(e) {
				if (!startX || !startY) return;

				const currentX = e.originalEvent.touches[0].clientX;
				const currentY = e.originalEvent.touches[0].clientY;

				const diffX = startX - currentX;
				const diffY = startY - currentY;

				if (Math.abs(diffX) > Math.abs(diffY)) {
					isSwipe = true;
					e.preventDefault();
				}
			});

			wrapper.on('touchend', function(e) {
				if (!isSwipe) return;

				const currentX = e.originalEvent.changedTouches[0].clientX;
				const diffX = startX - currentX;

				if (Math.abs(diffX) > 30) { // Minimum swipe distance
					if (diffX > 0) {
						nextImage();
					} else {
						prevImage();
					}
				}

				startX = 0;
				startY = 0;
				isSwipe = false;
			});

			// Keyboard support
			wrapper.on('keydown', function(e) {
				if (e.which === 37) { // Left arrow
					prevImage();
				} else if (e.which === 39) { // Right arrow
					nextImage();
				}
			});

			// Make wrapper focusable for keyboard events
			wrapper.attr('tabindex', '0');
		});
	});
</script>

@endpush