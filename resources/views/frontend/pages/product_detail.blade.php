@extends('frontend.layouts.master')

@section('meta')
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name='copyright' content=''>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="keywords" content="online shop, purchase, cart, ecommerce site, best online shopping">
<meta name="description" content="{{$product_detail->summary}}">
<meta property="og:url" content="{{route('product-detail',$product_detail->slug)}}">
<meta property="og:type" content="article">
<meta property="og:title" content="{{$product_detail->title}}">
<meta property="og:image" content="{{$product_detail->photo}}">
<meta property="og:description" content="{{$product_detail->description}}">
@endsection

@section('title','E-SHOP || PRODUCT DETAIL')

@section('main-content')

<!-- Breadcrumbs -->
<div class="breadcrumbs">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="bread-inner">
					<ul class="bread-list">
						<li><a href="{{route('home')}}" tabindex="13">Home<i class="ti-arrow-right"></i></a></li>
						<li class="active"><a href="" tabindex="14">Shop Details</a></li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- End Breadcrumbs -->

<!-- Shop Single -->
<section class="shop single section">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="row">
					<div class="col-lg-6 col-12">
						<!-- Product Gallery -->
						<div class="product-gallery">
							<!-- Main Image Viewer -->
							<div class="main-image-container position-relative mb-3">
								@if($product_detail->images->isNotEmpty())
								<img src="{{ asset($product_detail->images->first()->image_path) }}"
									alt="{{$product_detail->title}}"
									class="main-image img-fluid w-100"
									id="mainImage"
									tabindex="15"
									style="max-height: 500px; object-fit: contain; border-radius: 10px;">
								<!-- Zoom Lens -->
								<div class="zoom-lens d-none"></div>
								<!-- Zoom Result -->
								<div class="zoom-result d-none position-absolute" style="width: 300px; height: 300px; right: -320px; top: 0; border-radius: 10px; overflow: hidden;"></div>
								@else
								<img src="{{ asset('images/no-image.png') }}"
									alt="No Image"
									class="main-image img-fluid w-100"
									id="mainImage"
									tabindex="15"
									style="max-height: 500px; object-fit: contain; border-radius: 10px;">
								@endif
							</div>

							<!-- Thumbnail Carousel -->
							<div class="thumbnail-carousel">
								<div class="thumbnails d-flex flex-row flex-nowrap overflow-auto">
									@forelse($product_detail->images as $index => $image)
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset($image->image_path) }}"
											alt="Thumbnail {{$index + 1}}"
											tabindex="{{ 16 + $index }}"
											class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer;"
											data-image="{{ asset($image->image_path) }}">
									</div>
									@empty
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset('images/no-image.png') }}"
											alt="No Image"
											tabindex="16"
											class="img-fluid thumbnail-image active"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer;"
											data-image="{{ asset('images/no-image.png') }}">
									</div>
									@endforelse
								</div>
							</div>
						</div>
						<!-- End Product Gallery -->
					</div>
					<div class="col-lg-6 col-12">
						<div class="product-des">
							<!-- Description -->
							<div class="short">
								<h4 tabindex="17">{{$product_detail->title}}</h4>
								<div class="rating-main">
									<ul class="rating">
										@php
										$rate = ceil($product_detail->getReview->avg('rate'));
										@endphp
										@for($i = 1; $i <= 5; $i++)
											@if($rate>= $i)
											<li><i class="fa fa-star"></i></li>
											@else
											<li><i class="fa fa-star-o"></i></li>
											@endif
											@endfor
									</ul>
									<a href="#" class="total-review" tabindex="18">({{$product_detail['getReview']->count()}}) Review</a>
								</div>
								@php
								$originalPrice = $product_detail->price;
								$discounts = $discountService->getEffectiveDiscounts($product_detail);
								$discountedPrice = $discountService->applyAllDiscounts($originalPrice, $discounts);
								$isDiscounted = $discountedPrice < $originalPrice;
									@endphp

									<p class="price" tabindex="19">
									@if($isDiscounted)
									<span class="text-danger font-weight-bold"
										title="@foreach($discounts as $d){{ $d['title'] ?? ucfirst($d['source']) }}: {{ $d['type'] === 'percentage' ? $d['value'].'%' : '$'.number_format($d['value'], 0) }}{{ !$loop->last ? ', ' : '' }}@endforeach">
										${{ number_format($discountedPrice, 2) }}
									</span><br>
									<small><s class="text-muted">${{ number_format($originalPrice, 2) }}</s></small>

									@if(count($discounts))
									<br>
									<small class="text-muted">
										@foreach($discounts as $d)
										• {{ $d['title'] ?? ucfirst($d['source']) }}:
										@if($d['type'] === 'percentage')
										{{ $d['value'] }}% off
										@elseif($d['type'] === 'amount')
										${{ number_format($d['value'], 0) }} off
										@endif
										<br>
										@endforeach
									</small>
									@endif
									@else
									<span>${{ number_format($originalPrice, 2) }}</span>
									@endif
									</p>

									<p class="description" tabindex="20">{!! $product_detail->summary !!}</p>
							</div>
							<!--/ End Description -->
							<!-- Size -->
							@if($product_detail->size)
							<div class="size mt-4">
								<h4 tabindex="21">Size</h4>
								<div class="size-selector">
									@php
									$sizes = explode(',', $product_detail->size);
									@endphp
									@foreach($sizes as $index => $size)
									<label class="size-option {{ $index == 0 ? 'active' : '' }}">
										<input type="radio" name="size" value="{{ $size }}" {{ $index == 0 ? 'checked' : '' }} class="d-none">
										<span tabindex="{{ 22 + $index }}">{{ $size }}</span>
									</label>
									@endforeach
									@error('size')
									<span class="text-danger">{{ $message }}</span>
									@enderror
								</div>
							</div>
							@endif
							<!--/ End Size -->
							<!-- Product Buy -->
							<div class="product-buy">
								<form action="{{route('single-add-to-cart')}}" method="POST">
									@csrf
									<input type="hidden" name="size" id="selectedSize" value="{{ $product_detail->size ? explode(',', $product_detail->size)[0] : '' }}">
									<div class="quantity">
										<h6 tabindex="32">Quantity:</h6>
										<div class="input-group">
											<div class="button minus">
												<button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]" tabindex="33">
													<i class="ti-minus"></i>
												</button>
											</div>
											<input type="hidden" name="slug" value="{{$product_detail->slug}}">
											<input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1" id="quantity" tabindex="34">
											<div class="button plus">
												<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]" tabindex="35">
													<i class="ti-plus"></i>
												</button>
											</div>
										</div>
									</div>
									<div class="add-to-cart mt-4">
										<button type="submit" class="btn" tabindex="36">Add to cart</button>
										<a href="{{route('add-to-wishlist',$product_detail->slug)}}" class="btn min" tabindex="37"><i class="ti-heart"></i></a>
									</div>
								</form>

								<p class="cat" tabindex="38">Category: <a href="{{route('product-cat',$product_detail->cat_info['slug'])}}" tabindex="39">{{$product_detail->cat_info['title']}}</a></p>
								@if($product_detail->sub_cat_info)
								<p class="cat mt-1" tabindex="40">Sub Category: <a href="{{route('product-cat',[$product_detail->cat_info['slug'],$product_detail->sub_cat_info['slug']])}}" tabindex="41">{{$product_detail->sub_cat_info['title']}}</a></p>
								@endif
								<p class="availability" tabindex="42">Sku: {{$product_detail->sku ?? 'N/A'}}</p>
								<p class="availability" tabindex="43">Stock: @if($product_detail->stock > 0)<span class="badge badge-success">{{$product_detail->stock}}</span>@else <span class="badge badge-danger">{{$product_detail->stock}}</span> @endif</p>
							</div>
							<!--/ End Product Buy -->
						</div>
					</div>
				</div>
				<div class="row">
					<div class="col-12">
						<div class="product-info">
							<div class="nav-main">
								<ul class="nav nav-tabs" id="myTab" role="tablist">
									<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#description" role="tab" tabindex="44">Description</a></li>
									<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#reviews" role="tab" tabindex="45">Reviews</a></li>
								</ul>
							</div>
							<div class="tab-content" id="myTabContent">
								<!-- Description Tab -->
								<div class="tab-pane fade show active" id="description" role="tabpanel">
									<div class="tab-single">
										<div class="row">
											<div class="col-12">
												<div class="single-des">
													<p tabindex="46">{!! $product_detail->description !!}</p>
												</div>
											</div>
										</div>
									</div>
								</div>
								<!--/ End Description Tab -->
								<!-- Reviews Tab -->
								<div class="tab-pane fade" id="reviews" role="tabpanel">
									<div class="tab-single review-panel">
										<div class="row">
											<div class="col-12">
												<!-- Review -->
												<div class="comment-review">
													<div class="add-review">
														<h5 tabindex="47">Add A Review</h5>
														<p tabindex="48">Your email address will not be published. Required fields are marked</p>
													</div>
													<h4 tabindex="49">Your Rating <span class="text-danger">*</span></h4>
													<div class="review-inner">
														@auth
														<form class="form" method="post" action="{{route('review.store',$product_detail->slug)}}">
															@csrf
															<input type="hidden" name="slug" value="{{$product_detail->slug}}">
															<div class="row">
																<div class="col-lg-12 col-12">
																	<div class="rating_box">
																		<div class="star-rating">
																			<div class="star-rating__wrap">
																				<input class="star-rating__input" id="star-rating-5" type="radio" name="rate" value="5" tabindex="50">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-5" title="5 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-4" type="radio" name="rate" value="4" tabindex="51">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-4" title="4 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-3" type="radio" name="rate" value="3" tabindex="52">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-3" title="3 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-2" type="radio" name="rate" value="2" tabindex="53">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-2" title="2 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-1" type="radio" name="rate" value="1" tabindex="54">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-1" title="1 out of 5 stars"></label>
																				@error('rate')
																				<span class="text-danger">{{ $message }}</span>
																				@enderror
																			</div>
																		</div>
																	</div>
																</div>
																<div class="col-lg-12 col-12">
																	<div class="form-group">
																		<label for="review-text" tabindex="55">Write a review</label>
																		<textarea name="review" id="review-text" rows="6" placeholder="" tabindex="56"></textarea>
																	</div>
																</div>
																<div class="col-lg-12 col-12">
																	<div class="form-group button5">
																		<button type="submit" class="btn" tabindex="57">Submit</button>
																	</div>
																</div>
															</div>
														</form>
														@else
														<p class="text-center p-5" tabindex="58">
															You need to <a href="{{route('login.form')}}" style="color:rgb(54, 54, 204)" tabindex="59">Login</a> OR <a style="color:blue" href="{{route('register.form')}}" tabindex="60">Register</a>
														</p>
														@endauth
													</div>
												</div>

												<div class="ratting-main">
													<div class="avg-ratting">
														<h4 tabindex="61">{{ceil($product_detail->getReview->avg('rate'))}} <span>(Overall)</span></h4>
														<span tabindex="62">Based on {{$product_detail->getReview->count()}} Comments</span>
													</div>
													@foreach($product_detail['getReview'] as $index => $data)
													<div class="single-rating">
														<div class="rating-author">
															@if($data->user_info['photo'])
															<img src="{{$data->user_info['photo']}}" alt="{{$data->user_info['photo']}}" tabindex="{{ 63 + $index * 4 }}">
															@else
															<img src="{{asset('backend/img/avatar.png')}}" alt="Profile.jpg" tabindex="{{ 63 + $index * 4 }}">
															@endif
														</div>
														<div class="rating-des">
															<h6 tabindex="{{ 64 + $index * 4 }}">{{$data->user_info['name']}}</h6>
															<div class="ratings">
																<ul class="rating">
																	@for($i = 1; $i <= 5; $i++)
																		@if($data->rate >= $i)
																		<li><i class="fa fa-star"></i></li>
																		@else
																		<li><i class="fa fa-star-o"></i></li>
																		@endif
																		@endfor
																</ul>
																<div class="rate-count" tabindex="{{ 65 + $index * 4 }}">(<span>{{$data->rate}}</span>)</div>
															</div>
															<p tabindex="{{ 66 + $index * 4 }}">{{$data->review}}</p>
														</div>
													</div>
													@endforeach
												</div>
											</div>
										</div>
									</div>
								</div>
								<!--/ End Reviews Tab -->
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>
<!--/ End Shop Single -->

<div class="product-recommendations">
	<div class="container">
		<div class="row">
			<div class="col-12">
				<div class="recommendations-title">
					<h2>Related Products</h2>
					<p>Discover similar items you might love</p>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-12">
				<div class="position-relative">
					<!-- Navigation Arrows -->
					<button type="button" class="slider-nav slider-nav-prev" id="prevBtn" aria-label="Previous products">
						<i class="ti-angle-left" aria-hidden="true"></i>
					</button>
					<button type="button" class="slider-nav slider-nav-next" id="nextBtn" aria-label="Next products">
						<i class="ti-angle-right" aria-hidden="true"></i>
					</button>

					<!-- Products Slider -->
					<div class="recommendations-slider" id="productsSlider">
						@foreach($related_products as $product)
						@include('frontend.partials.product-card', ['product' => $product])
						@endforeach
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<!-- End Most Popular Area -->

@endsection

@push('styles')
<style>
	/* Ensure parent allows absolute arrows to be visible */
	.position-relative {
		overflow: visible !important;
	}

	/* Ensure arrows are always on top & clickable */
	.slider-nav {
		pointer-events: auto;
		z-index: 9999;
	}

	/* Slightly increase touch target on mobiles */
	@media (max-width: 768px) {
		.slider-nav {
			width: 48px;
			height: 48px;
		}
	}

	/* Recommendations Section Styles */
	.product-recommendations {
		background: #f8f9fa;
		padding: 80px 0;
		position: relative;
		overflow: hidden;
	}

	.product-recommendations::before {
		content: '';
		position: absolute;
		top: 0;
		left: 0;
		right: 0;
		height: 1px;
		background: linear-gradient(90deg, transparent, #e9ecef, transparent);
	}

	.recommendations-title {
		text-align: center;
		margin-bottom: 60px;
		position: relative;
	}

	.recommendations-title h2 {
		font-size: 2.5rem;
		font-weight: 700;
		color: #2c3e50;
		margin-bottom: 15px;
		position: relative;
		display: inline-block;
	}

	.recommendations-title h2::after {
		content: '';
		position: absolute;
		bottom: -10px;
		left: 50%;
		transform: translateX(-50%);
		width: 60px;
		height: 4px;
		background: linear-gradient(45deg, #ff6b6b, #4ecdc4);
		border-radius: 2px;
	}

	.recommendations-title p {
		color: #6c757d;
		font-size: 1.1rem;
		margin: 0;
	}

	/* Recommendations Slider Container */
	.recommendations-slider {
		display: flex;
		gap: 25px;
		padding: 20px 0;
		overflow-x: auto;
		overflow-y: hidden;
		scroll-behavior: smooth;
		scrollbar-width: none;
		-ms-overflow-style: none;
	}

	.recommendations-slider::-webkit-scrollbar {
		display: none;
	}

	/* Individual Recommendation Card */
	.recommendation-card {
		flex: 0 0 280px;
		background: #ffffff;
		border-radius: 15px;
		overflow: hidden;
		box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
		transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
		position: relative;
		border: 1px solid rgba(0, 0, 0, 0.05);
	}

	.recommendation-card:hover {
		transform: translateY(-8px);
		box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
	}

	/* Recommendation Image Container */
	.recommendation-image-wrapper {
		position: relative;
		height: 220px;
		overflow: hidden;
		background: #f8f9fa;
	}

	.recommendation-image {
		width: 100%;
		height: 100%;
		object-fit: cover;
		transition: transform 0.5s ease;
	}

	.recommendation-card:hover .recommendation-image {
		transform: scale(1.1);
	}

	/* Discount Label */
	.discount-label {
		position: absolute;
		top: 15px;
		left: 15px;
		background: linear-gradient(45deg, #ff6b6b, #ee5a52);
		color: white;
		padding: 8px 12px;
		border-radius: 20px;
		font-size: 0.85rem;
		font-weight: 600;
		z-index: 10;
		box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
	}

	/* Recommendation Actions Overlay */
	.recommendation-actions {
		position: absolute;
		top: 15px;
		right: 15px;
		display: flex;
		flex-direction: column;
		gap: 8px;
		opacity: 0;
		transform: translateX(20px);
		transition: all 0.3s ease;
	}

	.recommendation-card:hover .recommendation-actions {
		opacity: 1;
		transform: translateX(0);
	}

	.action-button {
		width: 40px;
		height: 40px;
		background: rgba(255, 255, 255, 0.95);
		border: none;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #666;
		font-size: 16px;
		transition: all 0.3s ease;
		box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
		backdrop-filter: blur(10px);
	}

	.action-button:hover {
		background: #fff;
		color: #ff6b6b;
		transform: scale(1.1);
		box-shadow: 0 6px 20px rgba(255, 107, 107, 0.3);
	}

	/* Recommendation Content */
	.recommendation-content {
		padding: 25px 20px;
		text-align: center;
	}

	.recommendation-title {
		font-size: 1.1rem;
		font-weight: 600;
		color: #2c3e50;
		margin-bottom: 12px;
		text-decoration: none;
		display: block;
		line-height: 1.4;
		transition: color 0.3s ease;
	}

	.recommendation-title:hover {
		color: #ff6b6b;
		text-decoration: none;
	}

	/* Price Section */
	.recommendation-price {
		margin-bottom: 20px;
	}

	.price-current {
		font-size: 1.8rem;
		font-weight: bold;
		color: #e74c3c;
	}

	.nav-tabs .nav-link {
		border: none;
		font-weight: 600;
		color: #555;
		transition: 0.3s;
	}

	.nav-tabs .nav-link.active {
		color: #fff;
		background: linear-gradient(45deg, #667eea, #764ba2);
		border-radius: 20px;
		padding: 8px 20px;
	}

	.price-old {
		font-size: 1rem;
		text-decoration: line-through;
		color: #95a5a6;
		margin-left: 8px;
	}

	.discount-tag {
		background: #ff4757;
		color: #fff;
		padding: 3px 8px;
		font-size: 0.8rem;
		border-radius: 4px;
		margin-left: 10px;
	}

	.price-previous {
		font-size: 1rem;
		color: #95a5a6;
		text-decoration: line-through;
	}

	.price-current.price-single {
		color: #2c3e50;
	}

	/* Add to Cart Button */
	.cart-button {
		width: 100%;
		background: linear-gradient(45deg, #667eea, #764ba2);
		color: white;
		border: none;
		padding: 12px 20px;
		border-radius: 25px;
		font-weight: 600;
		font-size: 0.95rem;
		transition: all 0.3s ease;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.cart-button:hover {
		transform: translateY(-2px);
		box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
		background: linear-gradient(45deg, #5a67d8, #6b46c1);
	}

	/* Navigation Arrows */
	.slider-nav {
		position: absolute;
		top: 50%;
		transform: translateY(-50%);
		background: rgba(255, 255, 255, 0.95);
		border: none;
		width: 50px;
		height: 50px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #666;
		font-size: 18px;
		transition: all 0.3s ease;
		box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
		z-index: 100;
		backdrop-filter: blur(10px);
	}

	.slider-nav:hover {
		background: #fff;
		color: #ff6b6b;
		transform: translateY(-50%) scale(1.1);
		box-shadow: 0 8px 25px rgba(255, 107, 107, 0.3);
	}

	.slider-nav-prev {
		left: -25px;
	}

	.slider-nav-next {
		right: -25px;
	}

	/* Responsive Design */
	@media (max-width: 768px) {
		.recommendations-title h2 {
			font-size: 2rem;
		}

		.product-recommendations {
			padding: 50px 0;
		}

		.recommendation-card {
			flex: 0 0 250px;
		}

		.slider-nav {
			display: none;
		}
	}

	@media (max-width: 576px) {
		.recommendation-card {
			flex: 0 0 220px;
		}

		.recommendations-slider {
			gap: 15px;
		}
	}
</style>
@endpush

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const slider = document.getElementById('productsSlider');
		const prevBtn = document.getElementById('prevBtn');
		const nextBtn = document.getElementById('nextBtn');

		if (slider && prevBtn && nextBtn) {
			// Dynamically detect one card width (including margin/gap)
			const getCardWidth = () => {
				const card = slider.querySelector('.recommendation-card, .product-card-container');
				if (!card) return 300;
				const style = window.getComputedStyle(card);
				return card.offsetWidth + parseInt(style.marginRight || 0);
			};

			prevBtn.addEventListener('click', () => {
				slider.scrollBy({
					left: -getCardWidth(),
					behavior: 'smooth'
				});
			});

			nextBtn.addEventListener('click', () => {
				slider.scrollBy({
					left: getCardWidth(),
					behavior: 'smooth'
				});
			});
		}
	});
	document.addEventListener('DOMContentLoaded', function() {

		// compute card width (first visible card + gap)
		const getCardWidth = () => {
			const firstCard = slider.querySelector('.recommendation-card') || slider.firstElementChild;
			const style = window.getComputedStyle(slider);
			const gap = parseFloat(style.gap || style.columnGap) || 25;
			if (!firstCard) return Math.min(300, slider.clientWidth);
			const rect = firstCard.getBoundingClientRect();
			return Math.round(rect.width + gap);
		};

		const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

		const scrollByAmount = (amount) => {
			// calculate target and clamp to bounds
			const maxLeft = Math.max(0, slider.scrollWidth - slider.clientWidth);
			const target = clamp(Math.round(slider.scrollLeft + amount), 0, maxLeft);
			slider.scrollTo({
				left: target,
				behavior: 'smooth'
			});
		};

		// click handlers
		if (prevBtn) prevBtn.addEventListener('click', (e) => {
			e.preventDefault();
			scrollByAmount(-getCardWidth());
		});
		if (nextBtn) nextBtn.addEventListener('click', (e) => {
			e.preventDefault();
			scrollByAmount(getCardWidth());
		});

		// update UI for buttons (disabled or faded)
		const updateNavButtons = () => {
			if (!prevBtn || !nextBtn) return;
			const maxLeft = Math.max(0, slider.scrollWidth - slider.clientWidth);
			prevBtn.disabled = slider.scrollLeft <= 0;
			nextBtn.disabled = slider.scrollLeft >= (maxLeft - 1);
			prevBtn.style.opacity = prevBtn.disabled ? '0.3' : '1';
			nextBtn.style.opacity = nextBtn.disabled ? '0.3' : '1';
		};

		// throttle update with rAF
		slider.addEventListener('scroll', () => {
			window.requestAnimationFrame(updateNavButtons);
		});
		window.addEventListener('resize', () => {
			window.requestAnimationFrame(updateNavButtons);
		});

		// initial state
		setTimeout(updateNavButtons, 100);

		// keyboard accessibility
		[prevBtn, nextBtn].forEach(btn => {
			if (!btn) return;
			btn.addEventListener('keydown', (e) => {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					btn.click();
				}
			});
		});

		// Pointer (drag) support for desktop & touch:
		// uses pointer events so it works with mouse, touch, stylus
		let isDown = false;
		let startX = 0;
		let startScroll = 0;

		// set initial cursor
		slider.style.cursor = 'grab';

		slider.addEventListener('pointerdown', (e) => {
			isDown = true;
			startX = e.clientX;
			startScroll = slider.scrollLeft;
			slider.setPointerCapture && slider.setPointerCapture(e.pointerId);
			slider.style.cursor = 'grabbing';
		});

		slider.addEventListener('pointermove', (e) => {
			if (!isDown) return;
			const dx = e.clientX - startX;
			slider.scrollLeft = startScroll - dx;
		});

		const releasePointer = (e) => {
			if (!isDown) return;
			isDown = false;
			try {
				slider.releasePointerCapture && slider.releasePointerCapture(e.pointerId);
			} catch (err) {}
			slider.style.cursor = 'grab';
			// small timeout to update buttons after natural momentum
			setTimeout(updateNavButtons, 100);
		};

		slider.addEventListener('pointerup', releasePointer);
		slider.addEventListener('pointercancel', releasePointer);
		slider.addEventListener('pointerleave', releasePointer);

		// Helpful debug logs if things still misbehave
		// (remove in production)
		// console.log('[slider] initialized', { scrollWidth: slider.scrollWidth, clientWidth: slider.clientWidth });
	});
</script>
@endpush