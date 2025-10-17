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
							@php
								$mainImagePath = null;
								if ($product_detail->images->isNotEmpty()) {
									$mainImagePath = $product_detail->images[0]->image_path;
									// Ensure the path is served from storage (avoid double-prefix)
									if ($mainImagePath && strpos($mainImagePath, 'storage/') !== 0) {
										$mainImagePath = 'storage/' . ltrim($mainImagePath, '/');
									}
								}
							@endphp

							<div class="main-image-container position-relative mb-3" style="display: flex; align-items: center; justify-content: center;">
								<div id="imageLoadingOverlay" class="loading-overlay d-none position-absolute" style="top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 10;">
									<div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;"></div>
								</div>
								<img src="{{ $mainImagePath ? asset($mainImagePath) : asset('images/no-image.png') }}"
									alt="{{$product_detail->title}}"
									class="main-image img-fluid"
									id="mainImage"
									tabindex="15"
									style="max-height: 500px; object-fit: contain; border-radius: 10px; width: 100%; min-width: 300px; border: 2px solid #eee; transition: opacity 0.3s ease;"
									data-original-src="{{ $mainImagePath ? asset($mainImagePath) : asset('images/no-image.png') }}">
							</div>

							<!-- Thumbnail Carousel -->
							<div class="thumbnail-carousel mt-2">
								<div class="thumbnails d-flex flex-row flex-nowrap" id="thumbnailContainer">
									@forelse($product_detail->images as $index => $image)
									@php
										$imgPath = $image->image_path ?? '';
										if ($imgPath) {
											$imgPath = (strpos($imgPath, 'storage/') === 0) ? $imgPath : 'storage/' . ltrim($imgPath, '/');
										}
									@endphp
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ $imgPath ? asset($imgPath) : asset('images/no-image.png') }}"
											alt="Thumbnail {{$index + 1}}"
											tabindex="{{ 16 + $index }}"
											class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid {{ $index == 0 ? '#2874f0' : '#eee' }}; transition: all 0.2s ease;"
											data-index="{{ $index }}">
									</div>
									@empty
									<div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
										<img src="{{ asset('images/no-image.png') }}"
											alt="No Image"
											tabindex="16"
											class="img-fluid thumbnail-image active"
											style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid #2874f0; transition: all 0.2s ease;"
											data-index="0">
									</div>
									@endforelse
								</div>
							</div>
						</div>
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
											@if($rate >= $i)
											<li><i class="fa fa-star"></i></li>
											@else
											<li><i class="fa fa-star-o"></i></li>
											@endif
										@endfor
									</ul>
									<a href="#reviews" class="total-review" tabindex="18">({{$product_detail['getReview']->count()}}) Review</a>
								</div>

								<!-- Price Display -->
								<div class="price-container" id="priceContainer">
									<p class="price" tabindex="19">
										<span class="text-danger font-weight-bold" id="displayPrice">
											${{ number_format($product_detail->discounted_price, 2) }}
										</span>
										@if($product_detail->discount_percentage > 0)
										<br>
										<small><s class="text-muted" id="originalPrice">${{ number_format($product_detail->original_price, 2) }}</s></small>
										<small class="text-success ml-2" id="discountBadge">{{ $product_detail->discount_percentage }}% off</small>
										@endif
									</p>
								</div>

								<p class="description" tabindex="20">{!! $product_detail->summary !!}</p>
							</div>

							{{-- Updated Variant Selection Section --}}
							@if($product_detail->has_variants && $product_detail->variants->count() > 0)
							<div class="variant-selection-container mt-4" id="variantContainer">
								@php $tabindex = 21; @endphp
								@foreach($variantTypes as $type)
								<div class="variant-group mb-3">
									<h6 class="mb-2">{{ $type->display_name }}</h6>
									<div class="variant-options d-flex flex-wrap gap-2">
										@foreach($type->options as $option)
										@php
											$isColor = strtolower($type->name) === 'color';
											$colorCode = $option->hex_color;
											// Fallback map if no hex_color
											$fallbackMap = [
												'red' => '#ff0000', 'black' => '#000000', 'white' => '#ffffff', 'blue' => '#0000ff',
												'green' => '#00ff00', 'yellow' => '#ffff00', 'pink' => '#ffc0cb', 'gray' => '#808080',
												'brown' => '#a52a2a', 'nude' => '#e3c7a6', 'coral' => '#ff7f50', 'mauve' => '#ba55d3',
												// Add more lipstick-specific shades as needed
											];
											if (!$colorCode && isset($fallbackMap[strtolower($option->value)])) {
												$colorCode = $fallbackMap[strtolower($option->value)];
											}
										@endphp
										@if($isColor && $colorCode)
										{{-- Color Swatch UI --}}
										<div class="color-swatch position-relative" tabindex="{{$tabindex++}}">
											<input type="radio" id="color-{{ $type->id }}-{{ $option->id }}" name="{{ $type->name }}" value="{{ $option->value }}" class="sr-only" data-variant-type="{{ $type->name }}" data-variant-value="{{ $option->value }}">
											<label for="color-{{ $type->id }}-{{ $option->id }}" class="color-swatch-label" style="background-color: {{ $colorCode }}; border: 2px solid #fff;">
												<span class="sr-only">{{ $option->display_value ?? $option->value }}</span>
											</label>
											<div class="color-swatch-checkmark d-none position-absolute">
												<i class="fa fa-check"></i>
											</div>
										</div>
										@else
										{{-- Text Button UI (for size, shade, etc.) --}}
										<button type="button" 
											class="variant-option-btn btn btn-outline-secondary"
											data-variant-type="{{ $type->name }}"
											data-variant-value="{{ $option->value }}"
											style="min-width: 80px; padding: 8px 16px; border-radius: 4px; font-size: 14px; position: relative; overflow: hidden;"
											tabindex="{{$tabindex++}}">
											{{ $option->display_value ?? $option->value }}
										</button>
										@endif
										@endforeach
									</div>
								</div>
								@endforeach
							</div>
							@endif

							<!-- Stock Availability Alert -->
							<div id="stockAlert" class="alert alert-danger d-none mt-3" role="alert">
								<i class="fa fa-exclamation-circle"></i> 
								<span id="stockAlertMessage">This variant is currently out of stock</span>
							</div>

							<!-- Product Buy -->
							<div class="product-buy">
								<form action="{{route('single-add-to-cart')}}" method="POST" id="addToCartForm">
									@csrf
									<input type="hidden" name="slug" value="{{$product_detail->slug}}">
									<input type="hidden" name="variant_id" id="selectedVariantId" value="">
									
									<div class="quantity" id="quantitySection">
										<h6 tabindex="32">Quantity:</h6>
										<div class="input-group">
											<div class="button minus">
												<button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]" tabindex="33">
													<i class="ti-minus"></i>
												</button>
											</div>
											<input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1" id="quantity" tabindex="34">
											<div class="button plus">
												<button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]" tabindex="35">
													<i class="ti-plus"></i>
												</button>
											</div>
										</div>
									</div>
									
									<div class="add-to-cart mt-4">
										<button type="submit" class="btn" id="addToCartBtn" tabindex="36">Add to cart</button>
										<a href="{{route('add-to-wishlist',$product_detail->slug)}}" class="btn min" tabindex="37"><i class="ti-heart"></i></a>
									</div>
								</form>

								<p class="cat" tabindex="38">Category: <a href="{{route('product-cat',$product_detail->cat_info['slug'])}}" tabindex="39">{{$product_detail->cat_info['title']}}</a></p>
								@if($product_detail->sub_cat_info)
								<p class="cat mt-1" tabindex="40">Sub Category: <a href="{{route('product-cat',[$product_detail->cat_info['slug'],$product_detail->sub_cat_info['slug']])}}" tabindex="41">{{$product_detail->sub_cat_info['title']}}</a></p>
								@endif
								<p class="availability" tabindex="42">SKU: <span id="displaySku">{{$product_detail->current_sku}}</span></p>
								<p class="availability" tabindex="43">Stock: <span id="displayStock">
									@if($product_detail->current_stock > 0)
									<span class="badge badge-success">{{$product_detail->current_stock}}</span>
									@else
									<span class="badge badge-danger">Out of Stock</span>
									@endif
								</span></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Rest of the page (Reviews, Description tabs) -->
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
								<div class="tab-pane fade" id="reviews" role="tabpanel">
									<div class="tab-single review-panel">
										<div class="row">
											<div class="col-12">
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
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- Pass variants data to JavaScript -->
<script>
	window.productVariants = @json($processedVariants);
	window.productSlug = "{{ $product_detail->slug }}";
	window.hasVariants = {{ $product_detail->has_variants ? 'true' : 'false' }};
</script>

@endsection

@push('styles')
<style>
	/* Product Variant Selection Styles - Enhanced Flipkart Style */
	.variant-selection-container {
		background: #fff;
		padding: 15px 0;
		border-top: 1px solid #f0f0f0;
		border-bottom: 1px solid #f0f0f0;
	}

	.variant-group {
		margin-bottom: 20px;
	}

	.variant-group h6 {
		font-size: 14px;
		font-weight: 600;
		color: #212121;
		margin-bottom: 12px;
		text-transform: uppercase;
		letter-spacing: 0.5px;
	}

	.variant-options {
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}

	/* Text Button Styles */
	.variant-option-btn {
		position: relative;
		min-width: 80px;
		padding: 10px 20px;
		border: 1px solid #c2c2c2;
		border-radius: 2px;
		background: #fff;
		color: #212121;
		font-size: 14px;
		font-weight: 500;
		cursor: pointer;
		transition: all 0.2s ease;
		text-align: center;
		outline: none;
		overflow: hidden;
	}

	.variant-option-btn::before {
		content: '';
		position: absolute;
		top: 0;
		left: -100%;
		width: 100%;
		height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
		transition: left 0.5s;
	}

	.variant-option-btn:hover:not(.disabled):not(.active)::before {
		left: 100%;
	}

	.variant-option-btn:hover:not(.disabled):not(.active) {
		border-color: #2874f0;
		box-shadow: 0 2px 4px rgba(40, 116, 240, 0.1);
		transform: translateY(-1px);
	}

	.variant-option-btn.active {
		border: 2px solid #2874f0;
		background: #e8f0fe;
		color: #2874f0;
		font-weight: 600;
	}

	.variant-option-btn.active::after {
		content: '';
		position: absolute;
		top: -1px;
		right: -1px;
		width: 0;
		height: 0;
		border-style: solid;
		border-width: 0 20px 20px 0;
		border-color: transparent #2874f0 transparent transparent;
		z-index: 1;
	}

	.variant-option-btn.active::before {
		content: '✓';
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		color: #2874f0;
		font-size: 12px;
		font-weight: bold;
		z-index: 2;
		background: #fff;
		width: 16px;
		height: 16px;
		border-radius: 50%;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.variant-option-btn.disabled {
		opacity: 0.4;
		cursor: not-allowed;
		position: relative;
		background: #fafafa;
		color: #878787;
	}

	.variant-option-btn.disabled::after {
		content: '';
		position: absolute;
		top: 50%;
		left: 10%;
		right: 10%;
		height: 1px;
		background: #878787;
		transform: translateY(-50%);
	}

	.variant-option-btn:focus {
		outline: 2px solid #2874f0;
		outline-offset: 2px;
	}

	/* Color Swatch Styles */
	.color-swatch {
		position: relative;
		width: 32px;
		height: 32px;
	}

	.color-swatch-label {
		display: block;
		width: 100%;
		height: 100%;
		border-radius: 50%;
		cursor: pointer;
		border: 2px solid #fff;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
		transition: all 0.2s ease;
		position: relative;
	}

	.color-swatch input:checked + .color-swatch-label {
		border-color: #2874f0;
		box-shadow: 0 0 0 2px #2874f0;
		transform: scale(1.1);
	}

	.color-swatch input:checked ~ .color-swatch-checkmark {
		display: block;
	}

	.color-swatch-checkmark {
		position: absolute;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%);
		color: #fff;
		font-size: 10px;
		font-weight: bold;
		z-index: 2;
	}

	.color-swatch:hover .color-swatch-label:not(.disabled) {
		transform: scale(1.1);
		box-shadow: 0 4px 8px rgba(0,0,0,0.2);
	}

	.color-swatch.disabled .color-swatch-label {
		opacity: 0.5;
		cursor: not-allowed;
		position: relative;
	}

	.color-swatch.disabled .color-swatch-label::after {
		content: '';
		position: absolute;
		top: 50%;
		left: 0;
		right: 0;
		height: 1px;
		background: #fff;
		transform: translateY(-50%);
	}

	.sr-only {
		position: absolute;
		width: 1px;
		height: 1px;
		padding: 0;
		margin: -1px;
		overflow: hidden;
		clip: rect(0, 0, 0, 0);
		white-space: nowrap;
		border: 0;
	}

	/* Stock Alert Styles */
	#stockAlert {
		background: #fff3cd;
		border: 1px solid #ffeaa7;
		border-radius: 4px;
		padding: 12px 16px;
		color: #856404;
		font-size: 14px;
		display: flex;
		align-items: center;
		gap: 10px;
		animation: slideDown 0.3s ease;
	}

	@keyframes slideDown {
		from { opacity: 0; transform: translateY(-10px); }
		to { opacity: 1; transform: translateY(0); }
	}

	#stockAlert i {
		font-size: 18px;
		color: #ff6b6b;
	}

	#stockAlert.d-none {
		display: none !important;
	}

	/* Price Display Styles */
	.price-container {
		margin: 15px 0;
	}

	.price-container .price {
		margin: 0;
		line-height: 1.4;
		transition: all 0.3s ease;
	}

	.price-container .price span {
		font-size: 28px;
		font-weight: 500;
	}

	.price-container .price small {
		font-size: 16px;
		margin-left: 8px;
	}

	.price-container .price .text-success {
		background: #388e3c;
		color: white;
		padding: 2px 8px;
		border-radius: 2px;
		font-size: 12px;
		font-weight: 600;
		animation: pulse 0.5s ease;
	}

	@keyframes pulse {
		0% { transform: scale(1); }
		50% { transform: scale(1.05); }
		100% { transform: scale(1); }
	}

	/* Product Gallery Enhancements */
	.main-image-container {
		position: relative;
		background: #fafafa;
		padding: 20px;
		border-radius: 4px;
		min-height: 400px;
	}

	.main-image {
		transition: transform 0.3s ease, opacity 0.3s ease;
	}

	.main-image.loading {
		opacity: 0.5;
	}

	.thumbnail-carousel {
		overflow-x: auto;
		scrollbar-width: thin;
		scrollbar-color: #c2c2c2 #f0f0f0;
	}

	.thumbnail-carousel::-webkit-scrollbar {
		height: 6px;
	}

	.thumbnail-carousel::-webkit-scrollbar-track {
		background: #f0f0f0;
		border-radius: 3px;
	}

	.thumbnail-carousel::-webkit-scrollbar-thumb {
		background: #c2c2c2;
		border-radius: 3px;
	}

	.thumbnail-carousel::-webkit-scrollbar-thumb:hover {
		background: #a0a0a0;
	}

	.thumbnail-image {
		transition: all 0.2s ease;
	}

	.thumbnail-image:hover {
		transform: scale(1.05);
		border-color: #2874f0 !important;
		box-shadow: 0 2px 4px rgba(0,0,0,0.1);
	}

	.thumbnail-image.active {
		box-shadow: 0 2px 8px rgba(40, 116, 240, 0.3);
	}

	/* Add to Cart Button Styles */
	.add-to-cart .btn {
		background: #ff9f00;
		border: none;
		color: #fff;
		padding: 12px 40px;
		font-size: 16px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.5px;
		border-radius: 2px;
		transition: all 0.2s ease;
		position: relative;
		overflow: hidden;
	}

	.add-to-cart .btn::before {
		content: '';
		position: absolute;
		top: 0;
		left: -100%;
		width: 100%;
		height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
		transition: left 0.5s;
	}

	.add-to-cart .btn:hover:not(:disabled)::before {
		left: 100%;
	}

	.add-to-cart .btn:hover:not(:disabled) {
		background: #e68a00;
		box-shadow: 0 4px 8px rgba(255, 159, 0, 0.3);
		transform: translateY(-1px);
	}

	.add-to-cart .btn:disabled {
		background: #c2c2c2;
		cursor: not-allowed;
		opacity: 0.6;
	}

	.add-to-cart .btn.min {
		background: #fff;
		border: 1px solid #c2c2c2;
		color: #212121;
		padding: 12px 16px;
		margin-left: 10px;
	}

	.add-to-cart .btn.min:hover {
		border-color: #ff6b6b;
		color: #ff6b6b;
		transform: scale(1.1);
	}

	/* Quantity Selector Enhancement */
	.quantity {
		margin: 20px 0;
		opacity: 1;
		transition: opacity 0.3s ease;
	}

	.quantity.hidden {
		opacity: 0;
		pointer-events: none;
	}

	.quantity h6 {
		font-size: 14px;
		font-weight: 600;
		color: #212121;
		margin-bottom: 10px;
	}

	.quantity .input-group {
		display: inline-flex;
		align-items: center;
		border: 1px solid #c2c2c2;
		border-radius: 2px;
		overflow: hidden;
	}

	.quantity .button {
		margin: 0;
	}

	.quantity .btn-number {
		background: #fff;
		border: none;
		color: #2874f0;
		padding: 8px 12px;
		font-size: 18px;
		cursor: pointer;
		transition: background 0.2s;
	}

	.quantity .btn-number:hover:not(:disabled) {
		background: #f0f0f0;
	}

	.quantity .btn-number:disabled {
		color: #c2c2c2;
		cursor: not-allowed;
	}

	.quantity .input-number {
		width: 60px;
		text-align: center;
		border: none;
		border-left: 1px solid #f0f0f0;
		border-right: 1px solid #f0f0f0;
		padding: 8px;
		font-size: 16px;
		font-weight: 500;
		color: #212121;
	}

	.quantity .input-number:focus {
		outline: none;
	}

	/* Product Info Meta */
	.product-des .cat,
	.product-des .availability {
		font-size: 14px;
		color: #878787;
		margin: 10px 0;
		transition: color 0.3s ease;
	}

	.product-des .cat a {
		color: #2874f0;
		text-decoration: none;
		font-weight: 500;
	}

	.product-des .cat a:hover {
		text-decoration: underline;
	}

	/* Badge Styles */
	.badge {
		font-size: 12px;
		padding: 4px 8px;
		font-weight: 600;
		border-radius: 2px;
		transition: all 0.3s ease;
	}

	.badge-success {
		background: #388e3c;
	}

	.badge-danger {
		background: #ff6b6b;
		animation: shake 0.5s ease;
	}

	@keyframes shake {
		0%, 100% { transform: translateX(0); }
		25% { transform: translateX(-5px); }
		75% { transform: translateX(5px); }
	}

	/* Responsive Styles */
	@media (max-width: 991px) {
		.variant-option-btn {
			min-width: 70px;
			padding: 8px 16px;
			font-size: 13px;
		}
		
		.color-swatch {
			width: 28px;
			height: 28px;
		}
		
		.price-container .price span {
			font-size: 24px;
		}
		
		.main-image-container {
			min-height: 300px;
		}
	}

	@media (max-width: 767px) {
		.variant-selection-container {
			padding: 10px 0;
		}
		
		.variant-option-btn {
			min-width: 60px;
			padding: 6px 12px;
			font-size: 12px;
		}
		
		.color-swatch {
			width: 24px;
			height: 24px;
		}
		
		.price-container .price span {
			font-size: 20px;
		}
		
		.add-to-cart .btn {
			width: 100%;
			margin-bottom: 10px;
		}
		
		.add-to-cart .btn.min {
			width: auto;
			margin-left: 0;
		}
	}

	/* Loading Animation */
	@keyframes shimmer {
		0% {
			background-position: -468px 0;
		}
		100% {
			background-position: 468px 0;
		}
	}

	.loading-shimmer {
		animation: shimmer 1.2s infinite;
		background: linear-gradient(to right, #f0f0f0 8%, #e0e0e0 18%, #f0f0f0 33%);
		background-size: 800px 104px;
	}
</style>
@endpush

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		/* ===============================
        VARIANT SELECTION SYSTEM (ENHANCED FOR COLOR SWATCHES)
    ============================== */
    const variants = window.productVariants || [];
    const hasVariants = window.hasVariants || false;
    const productSlug = window.productSlug;
    
    let selectedVariantOptions = {};
    let currentVariant = null;
    let isUpdating = false;
    
    // Initialize variant system
    if (hasVariants && variants.length > 0) {
        initializeVariantSystem();
    }
    
    function initializeVariantSystem() {
        // Handle text buttons
        const textButtons = document.querySelectorAll('.variant-option-btn');
        textButtons.forEach(btn => {
            btn.addEventListener('click', handleVariantSelection);
        });
        
        // Handle color swatches (radio inputs)
        const colorInputs = document.querySelectorAll('input[type="radio"][data-variant-type]');
        colorInputs.forEach(input => {
            input.addEventListener('change', handleVariantSelectionFromInput);
        });
        
        // Pre-select cheapest in-stock variant
        preselectCheapestVariant();
    }
    
    function handleVariantSelection(e) {
        if (isUpdating) return;
        
        const target = e.currentTarget;
        const variantType = target.dataset.variantType;
        const variantValue = target.dataset.variantValue;
        
        if (target.classList.contains('disabled')) {
            return;
        }
        
        // Update selection
        selectedVariantOptions[variantType] = variantValue;
        
        // Update UI for this group
        document.querySelectorAll(`[data-variant-type="${variantType}"].variant-option-btn`).forEach(b => {
            b.classList.remove('active');
        });
        target.classList.add('active');
        
        // Trigger update
        updateVariantWithLoading();
    }
    
    function handleVariantSelectionFromInput(e) {
        if (isUpdating) return;
        
        const input = e.target;
        const variantType = input.dataset.variantType || input.name;
        const variantValue = input.value;
        
        const parentSwatch = input.closest('.color-swatch');
        if (parentSwatch && parentSwatch.classList.contains('disabled')) {
            return;
        }
        
        // Update selection
        selectedVariantOptions[variantType] = variantValue;
        
        // Trigger update
        updateVariantWithLoading();
    }
    
    async function updateVariantWithLoading() {
        isUpdating = true;
        showLoadingStates();
        
        // Client-side match first (partial or full, active only, allow stock=0 for out-of-stock display)
        let matchingVariant = findMatchingVariant();
        
        if (matchingVariant) {
            currentVariant = matchingVariant;
            updateProductDisplay(matchingVariant);
            updateAvailableOptions();
        } else {
            // API fallback for exact match
            try {
                const response = await fetch(`/product/${productSlug}/check-variant`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ options: selectedVariantOptions })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    currentVariant = {
                        id: data.data.id,
                        sku: data.data.sku,
                        price: parseFloat(data.data.price),
                        discount: data.data.discount,
                        stock: data.data.stock,
                        images: data.data.images.map(img => ({ 
                            image_path: img.image_path,
                            is_primary: img.is_primary 
                        }))
                    };
                    updateProductDisplay(currentVariant);
                    updateAvailableOptions();
                } else {
                    handleNoVariantFound();
                }
            } catch (error) {
                console.error('Variant check failed:', error);
                handleNoVariantFound();
            }
        }
        
        hideLoadingStates();
        isUpdating = false;
    }
    
    function findMatchingVariant() {
        return variants.find(v => {
            if (v.status !== 'active') return false;
            
            const variantValues = typeof v.variant_values === 'string' ? JSON.parse(v.variant_values) : v.variant_values;
            
            return Object.entries(selectedVariantOptions).every(([type, value]) => {
                return variantValues[type] === value;
            });
        });
    }
    
    function preselectCheapestVariant() {
        const inStockVariants = variants.filter(v => v.status === 'active' && v.stock > 0);
        
        if (inStockVariants.length > 0) {
            // Sort by discounted price ascending
            const sortedVariants = [...inStockVariants].sort((a, b) => {
                const discA = a.price * (1 - (a.discount || 0) / 100);
                const discB = b.price * (1 - (b.discount || 0) / 100);
                return discA - discB;
            });
            
            const firstVariant = sortedVariants[0];
            const variantValues = typeof firstVariant.variant_values === 'string' ? JSON.parse(firstVariant.variant_values) : firstVariant.variant_values;
            
            Object.entries(variantValues).forEach(([type, value]) => {
                selectedVariantOptions[type] = value;
                
                // Select text button
                const btn = document.querySelector(`[data-variant-type="${type}"][data-variant-value="${value}"].variant-option-btn`);
                if (btn) {
                    btn.classList.add('active');
                }
                
                // Select color input
                const input = document.querySelector(`input[data-variant-type="${type}"][value="${value}"]`);
                if (input) {
                    input.checked = true;
                }
            });
            
            currentVariant = firstVariant;
            updateProductDisplay(firstVariant);
            updateAvailableOptions();
        } else {
            // Fallback to first active variant (even if out of stock)
            const firstActive = variants.find(v => v.status === 'active');
            if (firstActive) {
                // Similar selection logic as above...
                const variantValues = typeof firstActive.variant_values === 'string' ? JSON.parse(firstActive.variant_values) : firstActive.variant_values;
                Object.entries(variantValues).forEach(([type, value]) => {
                    selectedVariantOptions[type] = value;
                    const btn = document.querySelector(`[data-variant-type="${type}"][data-variant-value="${value}"].variant-option-btn`);
                    if (btn) btn.classList.add('active');
                    const input = document.querySelector(`input[data-variant-type="${type}"][value="${value}"]`);
                    if (input) input.checked = true;
                });
                currentVariant = firstActive;
                updateProductDisplay(firstActive);
                updateAvailableOptions();
            }
        }
    }
		
		function updateProductDisplay(variant) {
        // Animate price update
        const priceContainer = document.getElementById('priceContainer');
        priceContainer.classList.add('loading-shimmer');
        
        setTimeout(() => {
            // Update price
            const displayPrice = document.getElementById('displayPrice');
            const originalPriceEl = document.getElementById('originalPrice');
            const discountBadge = document.getElementById('discountBadge');
            
            const price = parseFloat(variant.price);
            const discount = parseFloat(variant.discount || 0);
            const discountedPrice = price * (1 - discount / 100);
            
            if (displayPrice) {
                displayPrice.textContent = '$' + discountedPrice.toFixed(2);
            }
            
            if (originalPriceEl && discount > 0) {
                originalPriceEl.innerHTML = '<s class="text-muted">$' + price.toFixed(2) + '</s>';
                originalPriceEl.style.display = 'inline';
            } else if (originalPriceEl) {
                originalPriceEl.style.display = 'none';
            }
            
            if (discountBadge && discount > 0) {
                discountBadge.textContent = discount + '% off';
                discountBadge.style.display = 'inline';
            } else if (discountBadge) {
                discountBadge.style.display = 'none';
            }
            
            priceContainer.classList.remove('loading-shimmer');
        }, 150);
        
        // Update SKU
        const displaySku = document.getElementById('displaySku');
        if (displaySku) {
            displaySku.textContent = variant.sku || 'N/A';
        }
        
        // Update stock and cart state
        const displayStock = document.getElementById('displayStock');
        const stockAlert = document.getElementById('stockAlert');
        const addToCartBtn = document.getElementById('addToCartBtn');
        const quantitySection = document.getElementById('quantitySection');
        const quantityInput = document.getElementById('quantity');
        
        const stock = parseInt(variant.stock || 0);
        if (stock > 0) {
            if (displayStock) {
                displayStock.innerHTML = '<span class="badge badge-success">' + stock + '</span>';
            }
            if (stockAlert && !stockAlert.classList.contains('d-none')) {
                stockAlert.classList.add('d-none');
            }
            if (addToCartBtn) {
                addToCartBtn.disabled = false;
                addToCartBtn.textContent = 'Add to cart';
                addToCartBtn.classList.remove('btn-secondary');
                addToCartBtn.classList.add('btn-warning');
            }
            if (quantitySection) {
                quantitySection.classList.remove('hidden');
            }
            if (quantityInput) {
                quantityInput.setAttribute('data-max', stock);
                updateQuantityMax();
            }
        } else {
            if (displayStock) {
                displayStock.innerHTML = '<span class="badge badge-danger">Out of Stock</span>';
            }
            if (stockAlert) {
                stockAlert.classList.remove('d-none');
                document.getElementById('stockAlertMessage').textContent = 'This variant is currently out of stock';
            }
            if (addToCartBtn) {
                addToCartBtn.disabled = true;
                addToCartBtn.textContent = 'Out of Stock';
                addToCartBtn.classList.remove('btn-warning');
                addToCartBtn.classList.add('btn-secondary');
            }
            if (quantitySection) {
                quantitySection.classList.add('hidden');
            }
        }
        
        // Update hidden variant ID
        const variantIdInput = document.getElementById('selectedVariantId');
        if (variantIdInput) {
            variantIdInput.value = variant.id;
        }
        
        // Update images with smooth transition
        updateVariantImages(variant);
    }
		
		function updateVariantImages(variant) {
			if (!variant.images || variant.images.length === 0) return;
			
			const mainImage = document.getElementById('mainImage');
			const thumbnailContainer = document.getElementById('thumbnailContainer');
			const imageLoadingOverlay = document.getElementById('imageLoadingOverlay');
			
			// Show loading
			imageLoadingOverlay.classList.remove('d-none');
			mainImage.classList.add('loading');
			
			// Update main image
			const firstImage = variant.images.find(img => img.is_primary) || variant.images[0];
			mainImage.src = firstImage.image_path;
			
			mainImage.onload = () => {
				imageLoadingOverlay.classList.add('d-none');
				mainImage.classList.remove('loading');
			};
			
			mainImage.onerror = () => {
				imageLoadingOverlay.classList.add('d-none');
				mainImage.classList.remove('loading');
				mainImage.src = mainImage.dataset.originalSrc;
			};
			
			// Rebuild thumbnails with fade
			const newThumbnails = variant.images.map((img, index) => {
				const thumbDiv = document.createElement('div');
				thumbDiv.className = 'thumbnail-item mx-1';
				thumbDiv.style.cssText = 'flex: 0 0 auto; opacity: 0; transition: opacity 0.3s ease;';
				
				const thumbImg = document.createElement('img');
				thumbImg.src = img.image_path;
				thumbImg.alt = `Thumbnail ${index + 1}`;
				thumbImg.className = `img-fluid thumbnail-image ${index === 0 ? 'active' : ''}`;
				thumbImg.style.cssText = `width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid ${index === 0 ? '#2874f0' : '#eee'}; transition: all 0.2s ease;`;
				thumbImg.dataset.index = index;
				
				thumbImg.addEventListener('click', function() {
					document.querySelectorAll('.thumbnail-image').forEach(t => {
						t.classList.remove('active');
						t.style.border = '2px solid #eee';
					});
					this.classList.add('active');
					this.style.border = '2px solid #2874f0';
					if (mainImage) {
						imageLoadingOverlay.classList.remove('d-none');
						mainImage.classList.add('loading');
						mainImage.src = this.src;
						mainImage.onload = () => {
							imageLoadingOverlay.classList.add('d-none');
							mainImage.classList.remove('loading');
						};
					}
				});
				
				thumbDiv.appendChild(thumbImg);
				return thumbDiv;
			});
			
			thumbnailContainer.innerHTML = '';
			newThumbnails.forEach((thumb, index) => {
				thumbnailContainer.appendChild(thumb);
				setTimeout(() => thumb.style.opacity = '1', index * 50);
			});
		}
		
		function updateAvailableOptions() {
			const allSelectors = document.querySelectorAll('.variant-option-btn, .color-swatch');
			
			allSelectors.forEach(selector => {
				let variantType, variantValue;
				if (selector.classList.contains('variant-option-btn')) {
					variantType = selector.dataset.variantType;
					variantValue = selector.dataset.variantValue;
				} else {
					const input = selector.querySelector('input');
					if (input) {
						variantType = input.dataset.variantType || input.name;
						variantValue = input.value;
					}
				}
				
				if (!variantType || !variantValue) return;
				
				// Temp select to check
				const originalValue = selectedVariantOptions[variantType];
				selectedVariantOptions[variantType] = variantValue;
				
				const tempVariant = findMatchingVariant();
				
				// Restore
				if (originalValue !== undefined) {
					selectedVariantOptions[variantType] = originalValue;
				} else {
					delete selectedVariantOptions[variantType];
				}
				
				if (!tempVariant && !selector.classList.contains('active')) {
					selector.classList.add('disabled');
				} else {
					selector.classList.remove('disabled');
				}
			});
		}
		
		function handleNoVariantFound() {
			const stockAlert = document.getElementById('stockAlert');
			if (stockAlert) {
				stockAlert.classList.remove('d-none');
				document.getElementById('stockAlertMessage').textContent = 'No matching variant available. Please select different options.';
			}
		}
		
		function showLoadingStates() {
			const priceContainer = document.getElementById('priceContainer');
			if (priceContainer) priceContainer.classList.add('loading-shimmer');
			
			document.querySelectorAll('.variant-option-btn, .color-swatch').forEach(el => el.style.pointerEvents = 'none');
		}
		
		function hideLoadingStates() {
			const priceContainer = document.getElementById('priceContainer');
			if (priceContainer) priceContainer.classList.remove('loading-shimmer');
			
			document.querySelectorAll('.variant-option-btn, .color-swatch').forEach(el => el.style.pointerEvents = 'auto');
		}

		/* ===============================
			QUANTITY CONTROLS
		=============================== */
		const qtyInput = document.getElementById('quantity');
		const minusBtn = document.querySelector('.button.minus .btn-number');
		const plusBtn = document.querySelector('.button.plus .btn-number');

		function updateQuantityMax() {
			if (!qtyInput || !currentVariant) return;
			const max = currentVariant.stock;
			qtyInput.setAttribute('data-max', max);
			const val = parseInt(qtyInput.value) || 1;
			if (val > max) {
				qtyInput.value = max;
			}
			updateMinusState();
		}

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
				if (val > min) {
					qtyInput.value = val - 1;
					updateMinusState();
				}
			});
		}
		
		if (qtyInput) {
			qtyInput.addEventListener('change', function() {
				let min = parseInt(this.getAttribute('data-min')) || 1;
				let max = parseInt(this.getAttribute('data-max')) || 1000;
				let val = parseInt(this.value) || 1;
				if (val < min) val = min;
				if (val > max) val = max;
				this.value = val;
				updateMinusState();
			});
		}
		
		updateMinusState();
		
		/* ===============================
			INITIAL IMAGE GALLERY
		=============================== */
		const initialMainImage = document.getElementById('mainImage');
		const initialThumbnails = document.querySelectorAll('.thumbnail-image');
		
		initialThumbnails.forEach((thumb) => {
			thumb.addEventListener('click', function() {
				initialThumbnails.forEach(t => {
					t.classList.remove('active');
					t.style.border = '2px solid #eee';
				});
				this.classList.add('active');
				this.style.border = '2px solid #2874f0';
				if (initialMainImage) {
					const loadingOverlay = document.getElementById('imageLoadingOverlay');
					loadingOverlay.classList.remove('d-none');
					initialMainImage.classList.add('loading');
					initialMainImage.src = this.src;
					initialMainImage.onload = () => {
						loadingOverlay.classList.add('d-none');
						initialMainImage.classList.remove('loading');
					};
				}
			});
		});
	});
</script>
@endpush