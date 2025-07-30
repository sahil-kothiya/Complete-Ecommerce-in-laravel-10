@extends('frontend.layouts.master')
@section('title','Wishlist Page')
@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="bread-inner">
					<ul class="bread-list">
						<li><a href="{{('home')}}">Home<i class="ti-arrow-right"></i></a></li>
						<li class="active"><a href="javascript:void(0);">Wishlist</a></li>
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
							<th class="text-center">TOTAL</th>
							<th class="text-center">ADD TO CART</th>
							<th class="text-center"><i class="ti-trash remove-icon"></i></th>
						</tr>
					</thead>
					<tbody>
						@if(Helper::getAllProductFromWishlist())
						@foreach(Helper::getAllProductFromWishlist() as $key => $wishlist)
						<tr>
							@php
							$images = $wishlist->product->images;
							$firstImage = $images->first();
							$imagePath = $firstImage ? $firstImage->image_path : 'default.jpg';
							@endphp
							<td class="image product-slider" data-title="No">
								@php
								$images = $wishlist->product->images;
								$firstImage = $images->first();
								$imagePath = $firstImage ? $firstImage->image_path : 'default.jpg';
								@endphp
								<div class="slider-wrapper" data-images="{{ $images->count() }}">
									<div class="slider-container">
										@foreach($images as $index => $image)
										<img src="{{ asset($image->image_path) }}" alt="{{ $wishlist->product['title'] }}" class="slider-img" data-index="{{ $index }}">
										@endforeach
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
									<a href="{{ route('product-detail', $wishlist->product['slug']) }}">
										{{ $wishlist->product['title'] }}
									</a>
								</p>
								<p class="product-des">{!! $wishlist['summary'] !!}</p>
							</td>
							<td class="total-amount" data-title="Total"><span>${{ $wishlist['amount'] }}</span></td>
							<td>
								<a href="{{ route('add-to-cart', $wishlist->product['slug']) }}" class="btn text-white">Add To Cart</a>
							</td>
							<td class="action" data-title="Remove">
								<a href="{{ route('wishlist-delete', $wishlist->id) }}"><i class="ti-trash remove-icon"></i></a>
							</td>
						</tr>
						@endforeach
						@else
						<tr>
							<td class="text-center">
								There are no any wishlist available.
								<a href="{{ route('product-grids') }}" style="color:blue;">Continue shopping</a>
							</td>
						</tr>
						@endif



					</tbody>
				</table>
				<!--/ End Shopping Summery -->
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

@include('frontend.layouts.newsletter')



<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span class="ti-close" aria-hidden="true"></span></button>
			</div>
			<div class="modal-body">
				<div class="row no-gutters">
					<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
						<!-- Product Slider -->
						<!-- <div class="product-gallery">
							<div class="quickview-slider-active">
								<div class="single-slider">
									<img src="images/modal1.jpg" alt="#">
								</div>
								<div class="single-slider">
									<img src="images/modal2.jpg" alt="#">
								</div>
								<div class="single-slider">
									<img src="images/modal3.jpg" alt="#">
								</div>
								<div class="single-slider">
									<img src="images/modal4.jpg" alt="#">
								</div>
							</div>
						</div> -->
						<!-- End Product slider -->
					</div>
					<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
						<div class="quickview-content">
							<h2>Flared Shift Dress</h2>
							<div class="quickview-ratting-review">
								<div class="quickview-ratting-wrap">
									<div class="quickview-ratting">
										<i class="yellow fa fa-star"></i>
										<i class="yellow fa fa-star"></i>
										<i class="yellow fa fa-star"></i>
										<i class="yellow fa fa-star"></i>
										<i class="fa fa-star"></i>
									</div>
									<a href="#"> (1 customer review)</a>
								</div>
								<div class="quickview-stock">
									<span><i class="fa fa-check-circle-o"></i> in stock</span>
								</div>
							</div>
							<h3>$29.00</h3>
							<div class="quickview-peragraph">
								<p>Lorem ipsum dolor sit amet, consectetur adipisicing elit. Mollitia iste laborum ad impedit pariatur esse optio tempora sint ullam autem deleniti nam in quos qui nemo ipsum numquam.</p>
							</div>
							<div class="size">
								<div class="row">
									<div class="col-lg-6 col-12">
										<h5 class="title">Size</h5>
										<select>
											<option selected="selected">s</option>
											<option>m</option>
											<option>l</option>
											<option>xl</option>
										</select>
									</div>
									<div class="col-lg-6 col-12">
										<h5 class="title">Color</h5>
										<select>
											<option selected="selected">orange</option>
											<option>purple</option>
											<option>black</option>
											<option>pink</option>
										</select>
									</div>
								</div>
							</div>
							<div class="quantity">
								<!-- Input Order -->
								<div class="input-group">
									<div class="button minus">
										<button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]">
											<i class="ti-minus"></i>
										</button>
									</div>
									<input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1">
									<div class="button plus">
										<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]">
											<i class="ti-plus"></i>
										</button>
									</div>
								</div>
								<!--/ End Input Order -->
							</div>
							<div class="add-to-cart">
								<a href="#" class="btn">Add to cart</a>
								<a href="#" class="btn min"><i class="ti-heart"></i></a>
								<a href="#" class="btn min"><i class="fa fa-compress"></i></a>
							</div>
							<div class="default-social">
								<h4 class="share-now">Share:</h4>
								<ul>
									<li><a class="facebook" href="#"><i class="fa fa-facebook"></i></a></li>
									<li><a class="twitter" href="#"><i class="fa fa-twitter"></i></a></li>
									<li><a class="youtube" href="#"><i class="fa fa-pinterest-p"></i></a></li>
									<li><a class="dribbble" href="#"><i class="fa fa-google-plus"></i></a></li>
								</ul>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- Modal end -->

@endsection
@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
@endpush
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
<script src="{{secure_asset('frontend/js/nice-select/js/jquery.nice-select.min.js')}}"></script>
<script src="{{ secure_asset('frontend/js/select2/js/select2.min.js') }}"></script>
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