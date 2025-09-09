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

							<div class="main-image-container position-relative mb-3" style="display: flex; align-items: center; justify-content: center;">
								@if($product_detail->images->isNotEmpty())
								<img src="{{ asset($product_detail->images[0]->image_path) }}"
									alt="{{$product_detail->title}}"
									class="main-image img-fluid"
									id="mainImage"
									tabindex="15"
									style="max-height: 500px; object-fit: contain; border-radius: 10px; width: 100%; min-width: 300px; border: 2px solid #eee;">
								@else
								<img src="{{ asset('images/no-image.png') }}"
									alt="No Image"
									class="main-image img-fluid"
									id="mainImage"
									tabindex="15"
									style="max-height: 500px; object-fit: contain; border-radius: 10px; width: 100%; min-width: 300px; border: 2px solid #eee;">
								@endif
							</div>

							<!-- Thumbnail Carousel -->

							<div class="thumbnail-carousel mt-2">
								<div class="thumbnails d-flex flex-row flex-nowrap">
									@forelse($product_detail->images as $index => $image)
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset($image->image_path) }}"
											alt="Thumbnail {{$index + 1}}"
											tabindex="{{ 16 + $index }}"
											class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid <?php echo ($index == 0) ? '#2874f0' : '#eee'; ?>;"
											data-index="{{ $index }}">
									</div>
									@empty
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset('images/no-image.png') }}"
											alt="No Image"
											tabindex="16"
											class="img-fluid thumbnail-image active"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid #2874f0;"
											data-index="0">
									</div>
									@endforelse
								</div>
							</div>
						</div>
						<!-- Auto Slider Script -->

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
								<h4 tabindex="21">Size
									<a href="#" class="size-guide-link" data-toggle="modal" data-target="#sizeGuideModal">
										<i class="ti-ruler-alt"></i> Size Guide
									</a>
								</h4>
								<div class="size-selector">
									@php
									$sizes = explode(',', $product_detail->size);
									@endphp
									@foreach($sizes as $index => $size)
									<label class="size-option {{ $index == 0 ? 'active' : '' }}">
										<input type="radio"
											name="size"
											value="{{ trim($size) }}"
											{{ $index == 0 ? 'checked' : '' }}
											class="d-none size-input"
											data-size="{{ trim($size) }}">
										<span tabindex="{{ 22 + $index }}">{{ trim($size) }}</span>
									</label>
									@endforeach
									@error('size')
									<span class="text-danger size-error">{{ $message }}</span>
									@enderror
								</div>

								<!-- Selected size display -->
								<div class="selected-size-info mt-2">
									<small class="text-muted">Selected: <strong id="selectedSizeDisplay">{{ trim(explode(',', $product_detail->size)[0]) }}</strong></small>
								</div>
							</div>

							<!-- Size Guide Modal (Optional) -->
							<div class="modal fade" id="sizeGuideModal" tabindex="-1" role="dialog" aria-labelledby="sizeGuideModalLabel">
								<div class="modal-dialog modal-lg" role="document">
									<div class="modal-content">
										<div class="modal-header">
											<h5 class="modal-title" id="sizeGuideModalLabel">Size Guide</h5>
											<button type="button" class="close" data-dismiss="modal" aria-label="Close">
												<span aria-hidden="true">&times;</span>
											</button>
										</div>
										<div class="modal-body">
											<div class="size-chart">
												<table class="table table-bordered">
													<thead class="thead-light">
														<tr>
															<th>Size</th>
															<th>Chest (inches)</th>
															<th>Waist (inches)</th>
															<th>Length (inches)</th>
														</tr>
													</thead>
													<tbody>
														<tr>
															<td><strong>XS</strong></td>
															<td>32-34</td>
															<td>28-30</td>
															<td>26</td>
														</tr>
														<tr>
															<td><strong>S</strong></td>
															<td>34-36</td>
															<td>30-32</td>
															<td>27</td>
														</tr>
														<tr>
															<td><strong>M</strong></td>
															<td>36-38</td>
															<td>32-34</td>
															<td>28</td>
														</tr>
														<tr>
															<td><strong>L</strong></td>
															<td>38-40</td>
															<td>34-36</td>
															<td>29</td>
														</tr>
														<tr>
															<td><strong>XL</strong></td>
															<td>40-42</td>
															<td>36-38</td>
															<td>30</td>
														</tr>
														<tr>
															<td><strong>XXL</strong></td>
															<td>42-44</td>
															<td>38-40</td>
															<td>31</td>
														</tr>
													</tbody>
												</table>
											</div>
										</div>
									</div>
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

<!-- Related Products Section (Flipkart/Amazon Style Carousel) -->
<div class="related-carousel-section">
	<div class="container">
		<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
			<div>
				<h2 class="carousel-title mb-0">Similar Products</h2>
			</div>
			<div class="carousel-nav-btns">
				<button type="button" class="carousel-nav-btn" id="carouselPrev" aria-label="Previous products">
					<i class="ti-angle-left"></i>
				</button>
				<button type="button" class="carousel-nav-btn" id="carouselNext" aria-label="Next products">
					<i class="ti-angle-right"></i>
				</button>
			</div>
		</div>
		<div class="carousel-viewport position-relative">
			<div class="carousel-track flipkart-carousel" id="relatedCarousel">
				@if($related_products && count($related_products))
				@foreach($related_products as $product)
				@php
				$inWishlist = Helper::isProductInWishlist($product->slug);
				$tabindex = 47;
				@endphp
				<div class="carousel-item flipkart-card" tabindex="{{$tabindex}}">
					<div class="flipkart-card-img-wrap">
						<a href="{{ route('product-detail', $product->slug) }}">
							<img src="{{ $product->images->first() ? asset($product->images->first()->image_path) : asset('images/no-image.png') }}" alt="{{ $product->title }}" class="flipkart-card-img" loading="lazy">
						</a>
						@if($product->discount > 0)
						<span class="flipkart-discount-badge">{{ $product->discount }}% OFF</span>
						@endif
						<div class="flipkart-card-icons">
							<a href="{{ route('add-to-wishlist', $product->slug) }}" class="flipkart-icon-btn" title="Add to Wishlist">
								<i class="ti-heart" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i>
							</a>
							<!-- <a href="#" class="flipkart-icon-btn" title="Quick View" onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');"><i class="ti-eye"></i></a> -->
						</div>
					</div>
					<div class="flipkart-card-body">
						<a href="{{ route('product-detail', $product->slug) }}" class="flipkart-card-title">{{ Str::limit($product->title, 40) }}</a>
						<div class="flipkart-card-price">
							@if($product->discount > 0)
							<span class="flipkart-price-discounted">${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
							<span class="flipkart-price-original">${{ number_format($product->price, 2) }}</span>
							@else
							<span class="flipkart-price-discounted">${{ number_format($product->price, 2) }}</span>
							@endif
						</div>
						<div class="add-to-cart mt-2 d-flex align-items-center gap-2">
							<a href="{{ route('add-to-cart', $product->slug) }}" class="btn btn-sm btn-dark text-uppercase text-center {{ $product->stock <= 0 ? 'disabled' : '' }}">
								<i class="ti-shopping-cart"></i> {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
							</a>
						</div>
					</div>
				</div>
				@php
				$tabindex++;
				@endphp
				@endforeach
				@else
				<div class="carousel-item text-center" style="min-width:180px;max-width:180px;opacity:0.7;">
					<div class="p-4">No related products found.</div>
				</div>
				@endif
			</div>
		</div>
	</div>
</div>
<!-- End Related Products Carousel -->

<!-- Optional: Login Modal (Bootstrap) -->
<div class="modal fade" id="loginPromptModal" tabindex="-1" role="dialog" aria-labelledby="loginPromptModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="loginPromptModalLabel">Login Required</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				Please <a href="{{ route('login.form') }}">login</a> to add products to your wishlist.
			</div>
			<div class="modal-footer">
				<a href="{{ route('login.form') }}" class="btn btn-primary">Login</a>
				<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>

@endsection

@push('styles')
<style>
	/* Modern Size Selector - Flipkart/Amazon Style */
	.flipkart-card-icons {
		opacity: 0;
		pointer-events: none;
		/* Prevent blocking when hidden */
		transition: opacity 0.2s;
		z-index: 20;
	}

	.flipkart-icon-btn:hover i {
		color: #ff6161 !important;
	}

	.flipkart-card:hover .flipkart-card-icons,
	.flipkart-card:focus-within .flipkart-card-icons {
		opacity: 1;
		pointer-events: auto;
		/* Enable clicks when visible */
	}

	.flipkart-card-icons .flipkart-icon-btn {
		pointer-events: auto;
		z-index: 21;
		position: relative;
	}

	.size {
		margin-top: 20px;
		margin-bottom: 20px;
	}

	.size h4 {
		font-size: 16px;
		font-weight: 600;
		color: #212121;
		margin-bottom: 12px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.size-selector {
		display: flex;
		flex-wrap: wrap;
		gap: 8px;
		align-items: center;
	}

	.size-option {
		position: relative;
		cursor: pointer;
		margin: 0;
		display: inline-block;
	}

	.size-option input[type="radio"] {
		position: absolute;
		opacity: 0;
		pointer-events: none;
	}

	.size-option span {
		display: inline-flex;
		align-items: center;
		justify-content: center;
		min-width: 48px;
		height: 40px;
		padding: 8px 16px;
		border: 1.5px solid #d4d5d9;
		border-radius: 6px;
		background-color: #fff;
		font-size: 14px;
		font-weight: 500;
		color: #282c3f;
		text-transform: uppercase;
		letter-spacing: 0.3px;
		transition: all 0.2s ease;
		user-select: none;
		white-space: nowrap;
		position: relative;
	}

	.size-option span:hover {
		border-color: #2874f0;
		background-color: #f8f9ff;
		transform: translateY(-1px);
		box-shadow: 0 2px 8px rgba(40, 116, 240, 0.15);
	}

	.size-option.active span,
	.size-option input[type="radio"]:checked+span {
		border-color: #2874f0;
		background-color: #2874f0;
		color: #fff;
		font-weight: 600;
		box-shadow: 0 2px 12px rgba(40, 116, 240, 0.25);
		transform: translateY(-1px);
	}

	.size-option.active span:before,
	.size-option input[type="radio"]:checked+span:before {
		content: "✓";
		position: absolute;
		top: -8px;
		right: -8px;
		width: 18px;
		height: 18px;
		background: #388e3c;
		color: white;
		border-radius: 50%;
		font-size: 10px;
		font-weight: bold;
		display: flex;
		align-items: center;
		justify-content: center;
		border: 2px solid #fff;
		box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
	}

	/* Focus states for accessibility */
	.size-option span:focus,
	.size-option input[type="radio"]:focus+span {
		outline: none;
		border-color: #2874f0;
		box-shadow: 0 0 0 3px rgba(40, 116, 240, 0.1);
	}

	/* Disabled state */
	.size-option.disabled span,
	.size-option input[type="radio"]:disabled+span {
		background-color: #f5f5f5;
		border-color: #e0e0e0;
		color: #9e9e9e;
		cursor: not-allowed;
		position: relative;
	}

	.size-option.disabled span:before,
	.size-option input[type="radio"]:disabled+span:before {
		content: "";
		position: absolute;
		top: 50%;
		left: 50%;
		width: 1px;
		height: 100%;
		background: #e0e0e0;
		transform: translate(-50%, -50%) rotate(45deg);
	}

	/* Size guide link (optional) */
	.size-guide-link {
		margin-left: 12px;
		color: #2874f0;
		font-size: 13px;
		text-decoration: none;
		font-weight: 500;
		display: inline-flex;
		align-items: center;
		gap: 4px;
	}

	.size-guide-link:hover {
		text-decoration: underline;
		color: #1a5db8;
	}

	/* Responsive Design */
	@media (max-width: 768px) {
		.size h4 {
			font-size: 15px;
			margin-bottom: 10px;
		}

		.size-option span {
			min-width: 44px;
			height: 36px;
			padding: 6px 12px;
			font-size: 13px;
		}

		.size-selector {
			gap: 6px;
		}
	}

	@media (max-width: 480px) {
		.size-option span {
			min-width: 40px;
			height: 32px;
			padding: 4px 10px;
			font-size: 12px;
		}

		.size-selector {
			gap: 5px;
		}
	}

	.btn {
		position: relative;
		/* font-weight: 500; */
		font-size: 14px;
		color: #fff;
		background: #333;
		-webkit-transition: .4s;
		-moz-transition: .4s;
		transition: .4s;
		z-index: 5;
		display: inline-block;
		padding: 13px 20px;
		border-radius: 0;
		text-transform: uppercase;
	}

	.related-carousel-section {
		background: #f8f9fa;
		padding: 32px 0 24px 0;
		margin-top: 32px;
		border-radius: 0 0 18px 18px;
		box-shadow: 0 2px 16px rgba(0, 0, 0, 0.04);
	}

	.carousel-title {
		font-size: 1.5rem;
		font-weight: 700;
		color: #222;
		margin-bottom: 0;
	}

	.carousel-subtitle {
		color: #6c757d;
		font-size: 1rem;
		margin-top: 2px;
	}

	.carousel-nav-btns {
		display: flex;
		gap: 8px;
	}

	.carousel-nav-btn {
		width: 38px;
		height: 38px;
		border-radius: 50%;
		border: none;
		background: #fff;
		color: #444;
		font-size: 1.3rem;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
		display: flex;
		align-items: center;
		justify-content: center;
		transition: background 0.2s, color 0.2s, box-shadow 0.2s;
		z-index: 10;
	}

	.carousel-nav-btn:disabled {
		opacity: 0.4;
		pointer-events: none;
	}

	.carousel-nav-btn:hover {
		background: #f1f3f6;
		color: #ff6b6b;
		box-shadow: 0 4px 16px rgba(255, 107, 107, 0.13);
	}


	.carousel-viewport {
		overflow-x: hidden;
		/* Hide horizontal scrollbar */
		overflow-y: visible;
		width: 100%;
		position: relative;
		padding-bottom: 8px;
		scrollbar-width: none;
		/* Firefox */
	}

	.carousel-viewport::-webkit-scrollbar {
		display: none;
		/* Chrome, Safari, Opera */
	}

	/* Prevent mouse/touch scroll */
	.carousel-viewport {
		pointer-events: auto;
	}

	.carousel-viewport,
	.carousel-viewport * {
		-ms-overflow-style: none;
		overscroll-behavior-x: contain;
		touch-action: none;
	}

	.flipkart-carousel {
		display: flex;
		gap: 16px;
		transition: transform 0.5s cubic-bezier(.4, 0, .2, 1);
		will-change: transform;
		user-select: none;
		touch-action: pan-x;
		padding-bottom: 8px;
		white-space: nowrap;
		/* force horizontal layout */
		background: linear-gradient(90deg, #f8fafc 60%, #e3e9f7 100%);
		box-shadow: 0 2px 12px rgba(40, 116, 240, 0.06);
		border: none;
	}

	.flipkart-card {
		flex: 0 0 180px;
		max-width: 180px;
		min-width: 160px;
		background: #fff;
		border-radius: 8px;
		box-shadow: 0 1px 8px rgba(0, 0, 0, 0.07);
		display: flex;
		flex-direction: column;
		align-items: stretch;
		margin-bottom: 0;
		position: relative;
		border: 1px solid #f1f1f1;
		overflow: hidden;
		transition: box-shadow 0.2s, transform 0.2s;
	}

	.flipkart-card:hover {
		box-shadow: 0 4px 24px rgba(0, 0, 0, 0.13);
		transform: translateY(-2px) scale(1.03);
	}

	.flipkart-card-img-wrap {
		position: relative;
		width: 100%;
		aspect-ratio: 1/1;
		background: #f6f7fa;
		display: flex;
		align-items: center;
		justify-content: center;
		overflow: hidden;
	}

	.flipkart-card-img {
		width: 100%;
		height: 100%;
		object-fit: contain;
		background: #fff;
		border-radius: 0;
		transition: transform 0.3s;
	}

	.flipkart-card:hover .flipkart-card-img {
		transform: scale(1.07);
	}

	.flipkart-discount-badge {
		position: absolute;
		top: 8px;
		left: 8px;
		background: #2874f0;
		color: #fff;
		font-size: 0.8rem;
		font-weight: 600;
		padding: 3px 10px;
		border-radius: 12px;
		z-index: 2;
		box-shadow: 0 2px 8px rgba(255, 97, 97, 0.13);
	}

	.flipkart-card-icons {
		position: absolute;
		top: 8px;
		right: 8px;
		display: flex;
		flex-direction: column;
		gap: 7px;
		z-index: 2;
	}

	.flipkart-icon-btn {
		width: 32px;
		height: 32px;
		background: rgba(255, 255, 255, 0.95);
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
		color: #666;
		font-size: 1.1rem;
		border: none;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
		transition: background 0.2s, color 0.2s;
		margin-bottom: 0;
		text-decoration: none;
	}

	.flipkart-icon-btn:hover {
		background: #fff;
		color: #ff6161;
	}

	.flipkart-card-body {
		padding: 12px 10px 10px 10px;
		display: flex;
		flex-direction: column;
		align-items: stretch;
		flex: 1 1 auto;
	}

	.flipkart-card-title {
		font-size: 1rem;
		font-weight: 600;
		color: #222;
		text-decoration: none;
		display: block;
		line-height: 1.3;
		min-height: 2.2em;
		transition: color 0.2s;
	}

	.flipkart-card-title:hover {
		color: #2874f0;
	}

	.flipkart-card-price {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.flipkart-price-discounted {
		font-size: 1.1rem;
		font-weight: 700;
		color: #007bff;
	}

	.flipkart-price-original {
		font-size: 0.95rem;
		color: #888;
		text-decoration: line-through;
	}

	.flipkart-cart-btn {
		width: 100%;
		background: linear-gradient(90deg, #2874f0 60%, #0f9d58 100%);
		color: #fff;
		border: none;
		padding: 8px 0;
		border-radius: 18px;
		font-weight: 600;
		font-size: 0.95rem;
		margin-top: 4px;
		transition: background 0.2s, box-shadow 0.2s;
		text-transform: uppercase;
		letter-spacing: 0.2px;
		text-align: center;
		text-decoration: none;
		display: block;
	}

	.flipkart-cart-btn:hover {
		background: linear-gradient(90deg, #0f9d58 60%, #2874f0 100%);
		color: #fff;
		box-shadow: 0 4px 16px rgba(40, 116, 240, 0.13);
	}

	.flipkart-cart-btn.disabled,
	.flipkart-cart-btn[disabled] {
		background: #e0e0e0;
		color: #aaa;
		pointer-events: none;
		cursor: not-allowed;
	}

	@media (max-width: 992px) {
		.flipkart-card {
			flex-basis: 150px;
			max-width: 150px;
		}
	}

	@media (max-width: 768px) {
		.carousel-title {
			font-size: 1.1rem;
		}

		.flipkart-card {
			flex-basis: 120px;
			max-width: 120px;
		}

		.related-carousel-section {
			padding: 18px 0 10px 0;
		}
	}

	@media (max-width: 576px) {
		.flipkart-card {
			flex-basis: 100px;
			max-width: 100px;
		}

		.flipkart-carousel {
			gap: 7px;
		}
	}
</style>
@endpush

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {

		/* ===============================
			WISHLIST LOGIN PROMPT
		=============================== */
		document.querySelectorAll('.wishlist-login-prompt').forEach(btn => {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				if (typeof $ !== 'undefined' && $('#loginPromptModal').length) {
					$('#loginPromptModal').modal('show');
				} else {
					window.location.href = btn.getAttribute('href');
				}
			});
		});

		/* ===============================
			SIZE SELECTOR
		=============================== */
		const sizeOptions = document.querySelectorAll('.size-option');
		const selectedField = document.getElementById('selectedSize');
		const sizeDisplay = document.getElementById('selectedSizeDisplay');

		function selectSize(index, value) {
			sizeOptions.forEach(opt => {
				opt.classList.remove('active');
				opt.querySelector('input').checked = false;
			});

			const selected = sizeOptions[index];
			selected.classList.add('active');
			selected.querySelector('input').checked = true;

			if (selectedField) selectedField.value = value;
			if (sizeDisplay) sizeDisplay.textContent = value;

			// Animation feedback
			const span = selected.querySelector('span');
			span.style.transform = 'scale(0.95)';
			setTimeout(() => span.style.transform = 'scale(1)', 150);
		}

		sizeOptions.forEach((option, index) => {
			const input = option.querySelector('input');
			const span = option.querySelector('span');

			option.addEventListener('click', e => {
				e.preventDefault();
				selectSize(index, input.value);
			});

			span.addEventListener('keydown', e => {
				if (['Enter', ' '].includes(e.key)) {
					e.preventDefault();
					selectSize(index, input.value);
				}
				if (['ArrowRight', 'ArrowDown', 'ArrowLeft', 'ArrowUp'].includes(e.key)) {
					e.preventDefault();
					let newIndex = index;
					if (['ArrowRight', 'ArrowDown'].includes(e.key)) newIndex = (index + 1) % sizeOptions.length;
					if (['ArrowLeft', 'ArrowUp'].includes(e.key)) newIndex = (index - 1 + sizeOptions.length) % sizeOptions.length;
					sizeOptions[newIndex].querySelector('span').focus();
				}
			});
		});

		// Default selection
		if (!document.querySelector('.size-option.active') && sizeOptions.length > 0) {
			selectSize(0, sizeOptions[0].querySelector('input').value);
		}


		/* ===============================
			IMAGE THUMBNAILS + AUTO SLIDE
		=============================== */
		const mainImage = document.getElementById('mainImage');
		const thumbnails = Array.from(document.querySelectorAll('.thumbnail-image'));
		const images = thumbnails.map(t => t.src);
		let currentIndex = thumbnails.findIndex(t => t.classList.contains('active')) || 0;

		function updateImage() {
			if (!mainImage) return;
			mainImage.src = images[currentIndex];
			thumbnails.forEach((t, i) => {
				t.classList.toggle('active', i === currentIndex);
				t.style.border = i === currentIndex ? '2px solid #2874f0' : '2px solid #eee';
			});
		}

		thumbnails.forEach((thumb, idx) => {
			thumb.addEventListener('click', () => {
				currentIndex = idx;
				updateImage();
			});
		});

		if (images.length > 1) {
			setInterval(() => {
				currentIndex = (currentIndex + 1) % images.length;
				updateImage();
			}, 3000);
		}

		updateImage();


		/* ===============================
			MAIN IMAGE SCROLL + AUTO SLIDE
		=============================== */
		const mainImageContainer = document.querySelector('.main-image-scroll-container');
		const scrollImages = mainImageContainer ? mainImageContainer.querySelectorAll('.main-image') : [];
		let activeIndex = 0;

		function setActiveImage(index) {
			scrollImages.forEach((img, i) => {
				img.style.border = i === index ? '2px solid #2874f0' : '2px solid #eee';
				img.style.opacity = i === index ? '1' : '0.6';
			});
			thumbnails.forEach((thumb, i) => thumb.classList.toggle('active', i === index));
			activeIndex = index;
			if (scrollImages[index]) {
				scrollImages[index].scrollIntoView({
					behavior: 'smooth',
					inline: 'center'
				});
			}
		}

		thumbnails.forEach((thumb, i) => {
			thumb.addEventListener('click', () => setActiveImage(i));
		});

		if (scrollImages.length > 1 && mainImageContainer) {
			let autoScrollTimer;
			const autoScrollDelay = 4000;

			function startAutoScroll() {
				clearInterval(autoScrollTimer);
				autoScrollTimer = setInterval(() => {
					setActiveImage((activeIndex + 1) % scrollImages.length);
				}, autoScrollDelay);
			}

			function stopAutoScroll() {
				clearInterval(autoScrollTimer);
			}

			mainImageContainer.addEventListener('mouseenter', stopAutoScroll);
			mainImageContainer.addEventListener('mouseleave', startAutoScroll);
			startAutoScroll();

			// Drag-to-scroll support
			let isDown = false,
				startX = 0,
				scrollLeft = 0;
			mainImageContainer.addEventListener('pointerdown', e => {
				isDown = true;
				startX = e.clientX;
				scrollLeft = mainImageContainer.scrollLeft;
				mainImageContainer.setPointerCapture?.(e.pointerId);
				mainImageContainer.style.cursor = 'grabbing';
			});
			mainImageContainer.addEventListener('pointermove', e => {
				if (!isDown) return;
				mainImageContainer.scrollLeft = scrollLeft - (e.clientX - startX);
			});
			['pointerup', 'pointercancel', 'pointerleave'].forEach(evt => {
				mainImageContainer.addEventListener(evt, e => {
					if (!isDown) return;
					isDown = false;
					mainImageContainer.releasePointerCapture?.(e.pointerId);
					mainImageContainer.style.cursor = 'grab';
				});
			});
			mainImageContainer.style.cursor = 'grab';
		}


		/* ===============================
			RELATED PRODUCTS CAROUSEL
		=============================== */
		const track = document.getElementById('relatedCarousel');
		const viewport = track?.closest('.carousel-viewport');
		const prevBtn = document.getElementById('carouselPrev');
		const nextBtn = document.getElementById('carouselNext');

		function getItemWidth() {
			const item = track?.querySelector('.carousel-item');
			if (!item) return 180;
			const style = window.getComputedStyle(item);
			return item.offsetWidth + parseInt(style.marginRight || 0) + parseInt(style.marginLeft || 0);
		}

		function scrollByCard(dir = 1) {
			if (viewport) {
				viewport.scrollBy({
					left: dir * getItemWidth(),
					behavior: 'smooth'
				});
			}
		}

		if (prevBtn) prevBtn.addEventListener('click', () => scrollByCard(-1));
		if (nextBtn) nextBtn.addEventListener('click', () => scrollByCard(1));


		/* ===============================
			QUANTITY PLUS/MINUS
		=============================== */
		const qtyInput = document.querySelector('.input-number');
		const minusBtn = document.querySelector('.button.minus .btn-number');
		const plusBtn = document.querySelector('.button.plus .btn-number');

		function updateMinusState() {
			if (minusBtn && qtyInput) {
				let min = parseInt(qtyInput.getAttribute('data-min')) || 1;
				qtyInput.value <= min ?
					minusBtn.setAttribute('disabled', 'disabled') :
					minusBtn.removeAttribute('disabled');
			}
		}

		if (plusBtn && qtyInput) {
			plusBtn.addEventListener('click', () => {
				let max = parseInt(qtyInput.getAttribute('data-max')) || 1000;
				let val = parseInt(qtyInput.value) || 1;
				if (val < max) {
					qtyInput.value = val + 1;
					updateMinusState();
				}
			});
		}
		if (minusBtn && qtyInput) {
			minusBtn.addEventListener('click', () => {
				let min = parseInt(qtyInput.getAttribute('data-min')) || 1;
				let val = parseInt(qtyInput.value) || 1;
				if (val > min) qtyInput.value = val - 1;
				updateMinusState();
			});
		}
		updateMinusState();

	});
</script>
@endpush