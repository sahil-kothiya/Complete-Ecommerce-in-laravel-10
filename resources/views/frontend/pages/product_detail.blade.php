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
						<li><a href="{{route('home')}}">Home<i class="ti-arrow-right"></i></a></li>
						<li class="active"><a href="">Shop Details</a></li>
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
											class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer;"
											data-image="{{ asset($image->image_path) }}">
									</div>
									@empty
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset('images/no-image.png') }}"
											alt="No Image"
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
								<h4>{{$product_detail->title}}</h4>
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
									<a href="#" class="total-review">({{$product_detail['getReview']->count()}}) Review</a>
								</div>
								@php
								$originalPrice = $product_detail->price;
								$discounts = $discountService->getEffectiveDiscounts($product_detail);
								$discountedPrice = $discountService->applyAllDiscounts($originalPrice, $discounts);
								$isDiscounted = $discountedPrice < $originalPrice;
									@endphp

									<p class="price">
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

									<p class="description">{!! $product_detail->summary !!}</p>
							</div>
							<!--/ End Description -->
							<!-- Size -->
							@if($product_detail->size)
							<div class="size mt-4">
								<h4>Size</h4>
								<div class="size-selector">
									@php
									$sizes = explode(',', $product_detail->size);
									@endphp
									@foreach($sizes as $index => $size)
									<label class="size-option {{ $index == 0 ? 'active' : '' }}">
										<input type="radio" name="size" value="{{ $size }}" {{ $index == 0 ? 'checked' : '' }} class="d-none">
										<span>{{ $size }}</span>
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
										<h6>Quantity:</h6>
										<div class="input-group">
											<div class="button minus">
												<button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]">
													<i class="ti-minus"></i>
												</button>
											</div>
											<input type="hidden" name="slug" value="{{$product_detail->slug}}">
											<input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1" id="quantity">
											<div class="button plus">
												<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]">
													<i class="ti-plus"></i>
												</button>
											</div>
										</div>
									</div>
									<div class="add-to-cart mt-4">
										<button type="submit" class="btn">Add to cart</button>
										<a href="{{route('add-to-wishlist',$product_detail->slug)}}" class="btn min"><i class="ti-heart"></i></a>
									</div>
								</form>

								<p class="cat">Category: <a href="{{route('product-cat',$product_detail->cat_info['slug'])}}">{{$product_detail->cat_info['title']}}</a></p>
								@if($product_detail->sub_cat_info)
								<p class="cat mt-1">Sub Category: <a href="{{route('product-sub-cat',[$product_detail->cat_info['slug'],$product_detail->sub_cat_info['slug']])}}">{{$product_detail->sub_cat_info['title']}}</a></p>
								@endif
								<p class="availability">Sku: {{$product_detail->sku ?? 'N/A'}}</p>
								<p class="availability">Stock: @if($product_detail->stock > 0)<span class="badge badge-success">{{$product_detail->stock}}</span>@else <span class="badge badge-danger">{{$product_detail->stock}}</span> @endif</p>
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
									<li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#description" role="tab">Description</a></li>
									<li class="nav-item"><a class="nav-link" data-toggle="tab" href="#reviews" role="tab">Reviews</a></li>
								</ul>
							</div>
							<div class="tab-content" id="myTabContent">
								<!-- Description Tab -->
								<div class="tab-pane fade show active" id="description" role="tabpanel">
									<div class="tab-single">
										<div class="row">
											<div class="col-12">
												<div class="single-des">
													<p>{!! $product_detail->description !!}</p>
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
														<h5>Add A Review</h5>
														<p>Your email address will not be published. Required fields are marked</p>
													</div>
													<h4>Your Rating <span class="text-danger">*</span></h4>
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
																				<input class="star-rating__input" id="star-rating-5" type="radio" name="rate" value="5">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-5" title="5 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-4" type="radio" name="rate" value="4">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-4" title="4 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-3" type="radio" name="rate" value="3">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-3" title="3 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-2" type="radio" name="rate" value="2">
																				<label class="star-rating__ico fa fa-star-o" for="star-rating-2" title="2 out of 5 stars"></label>
																				<input class="star-rating__input" id="star-rating-1" type="radio" name="rate" value="1">
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
																		<label>Write a review</label>
																		<textarea name="review" rows="6" placeholder=""></textarea>
																	</div>
																</div>
																<div class="col-lg-12 col-12">
																	<div class="form-group button5">
																		<button type="submit" class="btn">Submit</button>
																	</div>
																</div>
															</div>
														</form>
														@else
														<p class="text-center p-5">
															You need to <a href="{{route('login.form')}}" style="color:rgb(54, 54, 204)">Login</a> OR <a style="color:blue" href="{{route('register.form')}}">Register</a>
														</p>
														@endauth
													</div>
												</div>

												<div class="ratting-main">
													<div class="avg-ratting">
														<h4>{{ceil($product_detail->getReview->avg('rate'))}} <span>(Overall)</span></h4>
														<span>Based on {{$product_detail->getReview->count()}} Comments</span>
													</div>
													@foreach($product_detail['getReview'] as $data)
													<div class="single-rating">
														<div class="rating-author">
															@if($data->user_info['photo'])
															<img src="{{$data->user_info['photo']}}" alt="{{$data->user_info['photo']}}">
															@else
															<img src="{{asset('backend/img/avatar.png')}}" alt="Profile.jpg">
															@endif
														</div>
														<div class="rating-des">
															<h6>{{$data->user_info['name']}}</h6>
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
																<div class="rate-count">(<span>{{$data->rate}}</span>)</div>
															</div>
															<p>{{$data->review}}</p>
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

@endsection

@push('styles')
<style>
	.product-gallery-section {
		padding: 20px 0;
	}

	.main-image-container {
		border: 1px solid #e5e5e5;
		padding: 10px;
		background: #fff;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		border-radius: 10px;
	}

	.main-image {
		transition: opacity 0.3s ease;
	}

	.thumbnail-carousel {
		margin-top: 10px;
	}

	.thumbnail-item {
		transition: all 0.3s ease;
	}

	.thumbnail-image {
		border: 2px solid transparent;
		opacity: 0.7;
	}

	.thumbnail-image.active,
	.thumbnail-image:hover {
		border-color: #007bff;
		opacity: 1;
	}

	.thumbnails {
		scrollbar-width: thin;
		scrollbar-color: #888 #f5f5f5;
	}

	.thumbnails::-webkit-scrollbar {
		height: 8px;
	}

	.thumbnails::-webkit-scrollbar-track {
		background: #f5f5f5;
		border-radius: 4px;
	}

	.thumbnails::-webkit-scrollbar-thumb {
		background: #888;
		border-radius: 4px;
	}

	.thumbnails::-webkit-scrollbar-thumb:hover {
		background: #555;
	}

	/* Zoom Effect */
	.zoom-lens {
		position: absolute;
		border: 1px solid #d4d4d4;
		background: rgba(0, 0, 0, 0.1);
		width: 100px;
		height: 100px;
		pointer-events: none;
	}

	.zoom-result {
		background-color: #fff;
		border: 1px solid #e5e5e5;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
		z-index: 1000;
	}

	@media (max-width: 991px) {
		.zoom-result {
			display: none !important;
		}
	}

	/* Star Rating Styles */
	.star-rating__wrap {
		direction: rtl;
		display: inline-flex;
	}

	.star-rating__input {
		display: none;
	}

	.star-rating__ico {
		font-size: 24px;
		color: #ccc;
		cursor: pointer;
		transition: color 0.2s;
	}

	.star-rating__input:checked~.star-rating__ico,
	.star-rating__ico:hover,
	.star-rating__ico:hover~.star-rating__ico {
		color: orange;
	}

	.rating_box {
		display: inline-flex;
	}

	.star-rating {
		font-size: 0;
		padding-left: 10px;
		padding-right: 10px;
	}

	.star-rating__wrap {
		display: inline-block;
		font-size: 1rem;
	}

	.star-rating__wrap:after {
		content: "";
		display: table;
		clear: both;
	}

	.star-rating__ico {
		float: right;
		padding-left: 2px;
		cursor: pointer;
		color: #F7941D;
		font-size: 16px;
		margin-top: 5px;
	}

	.star-rating__ico:last-child {
		padding-left: 0;
	}

	/* Size Selection Styles */
	.size-selector {
		display: flex;
		gap: 10px;
		flex-wrap: wrap;
	}

	.size-option {
		display: inline-block;
		padding: 8px 15px;
		border: 2px solid #e5e5e5;
		border-radius: 5px;
		cursor: pointer;
		transition: all 0.3s ease;
		background: #fff;
	}

	.size-option.active {
		border-color: #007bff;
		background: #e7f3ff;
		color: #007bff;
		font-weight: bold;
	}

	.size-option:hover {
		border-color: #0056b3;
		background: #f0f8ff;
	}

	.size-option input {
		display: none;
	}

	.size-option span {
		font-size: 16px;
	}
</style>
@endpush

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		// Thumbnail click handler
		const thumbnails = document.querySelectorAll('.thumbnail-image');
		const mainImage = document.querySelector('#mainImage');

		thumbnails.forEach(thumb => {
			thumb.addEventListener('click', function() {
				thumbnails.forEach(t => t.classList.remove('active'));
				this.classList.add('active');
				mainImage.src = this.dataset.image;
			});
		});

		// Image Zoom Functionality
		const mainImageContainer = document.querySelector('.main-image-container');
		const zoomLens = document.querySelector('.zoom-lens');
		const zoomResult = document.querySelector('.zoom-result');

		mainImageContainer.addEventListener('mouseenter', function() {
			if (window.innerWidth > 991) { // Disable zoom on mobile
				zoomLens.classList.remove('d-none');
				zoomResult.classList.remove('d-none');
				zoomResult.style.backgroundImage = `url(${mainImage.src})`;
				zoomResult.style.backgroundSize = `${mainImage.width * 2}px ${mainImage.height * 2}px`;
			}
		});

		mainImageContainer.addEventListener('mouseleave', function() {
			zoomLens.classList.add('d-none');
			zoomResult.classList.add('d-none');
		});

		mainImageContainer.addEventListener('mousemove', function(e) {
			if (window.innerWidth > 991) {
				const rect = mainImage.getBoundingClientRect();
				const lensSize = 100; // Size of the zoom lens
				let x = e.clientX - rect.left - lensSize / 2;
				let y = e.clientY - rect.top - lensSize / 2;

				// Keep lens within image bounds
				x = Math.max(0, Math.min(x, rect.width - lensSize));
				y = Math.max(0, Math.min(y, rect.height - lensSize));

				zoomLens.style.left = `${x}px`;
				zoomLens.style.top = `${y}px`;

				// Calculate background position for zoom
				const bgX = (x / rect.width) * (mainImage.width * 2);
				const bgY = (y / rect.height) * (mainImage.height * 2);
				zoomResult.style.backgroundPosition = `-${bgX}px -${bgY}px`;
			}
		});

		// Star Rating Handler
		document.querySelectorAll('.star-rating__input').forEach(radio => {
			radio.addEventListener('change', function() {
				let allLabels = document.querySelectorAll('.star-rating__ico');
				allLabels.forEach(label => label.classList.remove('fa-star'));
				allLabels.forEach(label => label.classList.add('fa-star-o'));

				let val = parseInt(this.value);
				for (let i = 1; i <= val; i++) {
					document.querySelector('label[for="star-rating-' + i + '"]').classList.add('fa-star');
					document.querySelector('label[for="star-rating-' + i + '"]').classList.remove('fa-star-o');
				}
			});
		});

		// Size Selection Handler
		const sizeOptions = document.querySelectorAll('.size-option');
		const selectedSizeInput = document.querySelector('#selectedSize');

		sizeOptions.forEach(option => {
			option.addEventListener('click', function() {
				sizeOptions.forEach(opt => opt.classList.remove('active'));
				this.classList.add('active');
				const selectedSize = this.querySelector('input').value;
				selectedSizeInput.value = selectedSize;
			});
		});
	});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
@endpush