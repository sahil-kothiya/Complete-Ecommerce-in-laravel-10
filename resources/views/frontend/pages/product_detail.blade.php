@extends('frontend.layouts.master')

@section('meta')
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name='copyright' content=''>
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta name="keywords" content="online shop, purchase, cart, ecommerce site, best online shopping">
<meta name="description" content="{{$product_detail->summary}}">
<meta property="og:url" content="{{route('product-detail',$product_detail->slug)}}">
<meta property="og:type" content="article">
<meta property="og:title" content="{{$product_detail->title}}">
@php
	$ogImage = $product_detail->photo ?? null;
	if ($ogImage) {
		$ogImage = ltrim($ogImage, '/');
		if (strpos($ogImage, 'storage/') !== 0) {
			$ogImage = 'storage/' . $ogImage;
		}
	}
@endphp
<meta property="og:image" content="{{ asset($ogImage) }}">
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
							@php
								// $product_detail->images now returns the correct collection (see accessor)
								$allImages = $product_detail->images ?? collect();

								// Primary image path (with correct storage prefix)
								$primary = $product_detail->primary_image;
								$mainImagePath = $primary?->image_path ?? ($allImages->first()?->image_path ?? null);
								if ($mainImagePath) {
									$mainImagePath = ltrim($mainImagePath, '/');
									if (strpos($mainImagePath, 'storage/') !== 0) {
										$mainImagePath = 'storage/' . $mainImagePath;
									}
								}
							@endphp

							<div class="main-image-container position-relative mb-3" style="display:flex;align-items:center;justify-content:center;">
								<div id="imageLoadingOverlay" class="loading-overlay d-none position-absolute" style="top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;z-index:10;">
									<div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div>
								</div>
								<img src="{{ $mainImagePath ? asset($mainImagePath) : asset('images/no-image.png') }}"
									alt="{{ $product_detail->title }}"
									class="main-image img-fluid"
									id="mainImage"
									tabindex="15"
									style="max-height:500px;object-fit:contain;border-radius:10px;width:100%;min-width:300px;border:2px solid #eee;transition:opacity .3s ease;"
									data-original-src="{{ $mainImagePath ? asset($mainImagePath) : asset('images/no-image.png') }}">
							</div>

							<div class="thumbnail-carousel mt-2">
								<div class="thumbnails d-flex flex-row flex-nowrap" id="thumbnailContainer">
									@if($allImages->isEmpty())
										<div class="thumbnail-item mx-1" style="flex:0 0 auto;">
											<img src="{{ asset('images/no-image.png') }}" alt="No Image"
												class="img-fluid thumbnail-image active"
												style="width:80px;height:80px;object-fit:cover;border-radius:5px;border:2px solid #2874f0;">
										</div>
									@else
										@foreach($allImages as $index => $image)
											@php
												$thumbPath = $image->thumbnail_path ?? $image->image_path;
												if ($thumbPath) {
													$thumbPath = ltrim($thumbPath, '/');
													if (strpos($thumbPath, 'storage/') !== 0) {
														$thumbPath = 'storage/' . $thumbPath;
													}
												}
											@endphp
											<div class="thumbnail-item mx-1" style="flex:0 0 auto;">
													<img src="{{ $thumbPath ? asset($thumbPath) : asset('images/no-image.png') }}"
													alt="Thumbnail {{ $index + 1 }}"
													class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
													style="width:80px;height:80px;object-fit:cover;border-radius:5px;cursor:pointer;border:2px solid {{ $index == 0 ? '#2874f0' : '#eee' }};"
													data-index="{{ $index }}"
													data-full="{{ $image->image_path ? asset($image->image_path) : asset('images/no-image.png') }}">
											</div>
										@endforeach
									@endif
								</div>
							</div>
						</div>
					</div>
					<div class="col-lg-6 col-12">
						<div class="product-des">
							<!-- Description -->
							<div class="short">
								<h4 tabindex="17">{{$product_detail->title}}</h4>
								<div class="rating-main" id="productRatingSummary">
									<ul class="rating" id="productRatingStars">
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
									<a href="#reviews" class="total-review" tabindex="18" id="totalReviewCount">({{$product_detail['getReview']->count()}}) Review</a>
								</div>

								{{-- Price, SKU, Stock – works for simple & variant products --}}
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

								<p class="availability" tabindex="42">SKU: <span id="displaySku">{{ $product_detail->current_sku }}</span></p>
								<p class="availability" tabindex="43">Stock:
									<span id="displayStock">
										@if($product_detail->current_stock > 0)
											<span class="badge badge-success">{{ $product_detail->current_stock }}</span>
										@else
											<span class="badge badge-danger">Out of Stock</span>
										@endif
									</span>
								</p>

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
											<input type="radio"
												id="color-{{ $type->id }}-{{ $option->id }}"
												name="{{ $type->name }}"
												value="{{ $option->value }}"
												class="sr-only"
												data-variant-type="{{ $type->name }}"
												data-variant-value="{{ $option->value }}">
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
										<h6 class="text-center" tabindex="32">Quantity:</h6>
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
										<a href="{{route('add-to-wishlist',$product_detail->slug)}}" class="btn min" id="wishlistBtn" tabindex="37"><i class="ti-heart"></i> Add to Wishlist</a>
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
									<li class="nav-item"><a class="nav-link active" id="description-tab" data-toggle="tab" href="#description" role="tab" tabindex="44">Description</a></li>
									<li class="nav-item"><a class="nav-link" id="reviews-tab" data-toggle="tab" href="#reviews" role="tab" tabindex="45">Reviews</a></li>
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
														<p tabindex="48">Your email address will not be published. Required fields are marked <span class="text-danger">*</span></p>
													</div>
													<h4 tabindex="49">Your Rating <span class="text-danger">*</span></h4>
													<div class="review-inner">
														@auth
														<form class="form review-form" method="post" 
															action="{{ route('review.store', ['slug' => $product_detail->slug]) }}" 
															id="reviewForm">
															@csrf
															<input type="hidden" name="slug" value="{{$product_detail->slug}}">
															<div class="row">
																<div class="col-lg-12 col-12">
																	<div class="rating_box">
																		<div class="star-rating">
																			<div class="star-rating__wrap">
																				<!-- Stars in reverse order (5 to 1) for left-to-right fill -->
																				<input class="star-rating__input" id="star-rating-5" type="radio" name="rate" value="5" tabindex="50">
																				<label class="star-rating__ico fa fa-star" for="star-rating-5" title="5 out of 5 stars"></label>
																				
																				<input class="star-rating__input" id="star-rating-4" type="radio" name="rate" value="4" tabindex="51">
																				<label class="star-rating__ico fa fa-star" for="star-rating-4" title="4 out of 5 stars"></label>
																				
																				<input class="star-rating__input" id="star-rating-3" type="radio" name="rate" value="3" tabindex="52">
																				<label class="star-rating__ico fa fa-star" for="star-rating-3" title="3 out of 5 stars"></label>
																				
																				<input class="star-rating__input" id="star-rating-2" type="radio" name="rate" value="2" tabindex="53">
																				<label class="star-rating__ico fa fa-star" for="star-rating-2" title="2 out of 5 stars"></label>
																				
																				<input class="star-rating__input" id="star-rating-1" type="radio" name="rate" value="1" tabindex="54">
																				<label class="star-rating__ico fa fa-star" for="star-rating-1" title="1 out of 5 stars"></label>
																			</div>
																		</div>
																		@error('rate')
																		<span class="text-danger d-block mt-2">{{ $message }}</span>
																		@enderror
																	</div>
																</div>
																<div class="col-lg-12 col-12">
																	<div class="form-group">
																		<label for="review-text" tabindex="55">Write a review <span class="text-danger">*</span></label>
																		<textarea name="review" id="review-text" rows="6" placeholder="Share your experience with this product..." tabindex="56" required></textarea>
																		@error('review')
																		<span class="text-danger">{{ $message }}</span>
																		@enderror
																	</div>
																</div>
																<div class="col-lg-12 col-12">
																	<div class="form-group button5">
																		<button type="submit" class="btn btn-primary" id="submitReviewBtn" tabindex="57">Submit Review</button>
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

												<div class="ratting-main" id="reviewsContainer">
													<div class="avg-ratting" id="avgRatingSection">
														<h4 tabindex="61">{{ceil($product_detail->getReview->avg('rate'))}} <span>(Overall)</span></h4>
														<span tabindex="62">Based on {{$product_detail->getReview->count()}} Comments</span>
													</div>
													@forelse($product_detail['getReview'] as $index => $data)
													<div class="single-rating">
														<div class="rating-author">
															@if($data->user_info['photo'])
															<img src="{{ $data->user_info['photo'] ?? asset('backend/img/avatar.webp') }}"
															alt="{{ $data->user_info['name'] }}"
															loading="lazy">
															@else
															<img src="{{ asset('backend/img/avatar.webp') }}" alt="Default Avatar" loading="lazy">
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
																<div class="rate-count" tabindex="{{ 65 + $index * 4 }}">({{$data->rate}})</div>
															</div>
															<p tabindex="{{ 66 + $index * 4 }}">{{$data->review}}</p>
															<small class="text-muted" style="font-size: 12px;">{{ $data->created_at->format('M d, Y') }}</small>
														</div>
													</div>
													@empty
													<div class="text-center py-4">
														<p>No reviews yet. Be the first to share your thoughts!</p>
													</div>
													@endforelse
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

<x-similar-products-carousel
	:products="$related_products"
	title="Similar Products"
	carousel-id="relatedCarousel"
	no-products-message="No related products found."
	starting-tab-index="47"
	default-background-color="#28a745"
	default-text-color="white"
	default-auto-scroll-speed="200"
	default-scroll-amount="3"
	default-shimmer="true" />

@if(isset($recent_products) && count($recent_products) > 0)
	<x-recently-viewed-carousel
		:products="$recent_products"
		title="Recently Viewed Products"
		carousel-id="customRecentCarousel"
		no-products-message="No recent views yet!"
		default-background-color="#ff6b35"
		default-text-color="white"
		default-auto-scroll-speed="100"
		default-scroll-amount="10"
		default-shimmer="false" />    
@endif


<!-- Pass variants data to JavaScript -->
<script>
	window.productVariants = @json($processedVariants ?? []);
	window.productSlug = "{{ $product_detail->slug }}";
	window.hasVariants = {{ $product_detail->has_variants ? 'true' : 'false' }};
	// Pass server-side variant type order (fallback ensures order defined on server is used)
	// JSON is encoded as a string and parsed in JS to avoid Blade parsing edge-cases in various editors
	try {
		const serverOrderJson = '{{ addslashes(json_encode($variantTypes->pluck("name")->toArray() ?? [])) }}';
		if (serverOrderJson) {
			window.variantTypeOrder = JSON.parse(serverOrderJson);
		}
	} catch (e) {
		window.variantTypeOrder = [];
	}
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
		background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
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
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		transition: all 0.2s ease;
		position: relative;
	}

	/* Active/selected swatch visible state */
	.color-swatch-label.active {
		border-color: #2874f0;
		box-shadow: 0 0 0 2px #2874f0;
		transform: scale(1.08);
	}

	/* Disabled swatch visual state */
	.color-swatch-label.disabled {
		opacity: 0.5;
		pointer-events: none;
	}

	.color-swatch input:checked+.color-swatch-label {
		border-color: #2874f0;
		box-shadow: 0 0 0 2px #2874f0;
		transform: scale(1.1);
	}

	.color-swatch input:checked~.color-swatch-checkmark {
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
		box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	#stockAlert i {
		font-size: 18px;
		color: #ff6b6b;
	}

	#stockAlert.d-none {
		display: none !important;
	}

	/* FIXED: Star Rating System Styles */
	.rating_box {
		border: 1px solid #e0e0e0;
		padding: 20px;
		border-radius: 8px;
		background: #fafafa;
		margin-bottom: 20px;
	}

	.star-rating {
		display: inline-block;
	}

	.star-rating__wrap {
		display: inline-flex;
		flex-direction: row-reverse; /* This makes stars fill from left to right */
		justify-content: flex-end;
		gap: 5px;
	}

	/* Hide radio buttons completely */
	.star-rating__input {
		position: absolute;
		opacity: 0;
		width: 0;
		height: 0;
		pointer-events: none;
	}

	/* Star icon styles */
	.star-rating__ico {
		font-size: 28px;
		color: #ddd;
		cursor: pointer;
		transition: all 0.2s ease;
		text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
	}

	.star-rating__ico:hover {
		transform: scale(1.15);
	}

	/* When hovering a star, color it and all stars to its right (which appear left visually) */
	.star-rating__ico:hover,
	.star-rating__ico:hover ~ .star-rating__ico {
		color: #ffd700;
		text-shadow: 0 2px 4px rgba(255, 215, 0, 0.4);
	}

	/* When a radio is checked, color its label and all labels after it */
	.star-rating__input:checked ~ .star-rating__ico {
		color: #ffd700;
		text-shadow: 0 2px 4px rgba(255, 215, 0, 0.4);
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
		color: white !important;
		padding: 2px 8px;
		border-radius: 2px;
		font-size: 12px;
		font-weight: 600;
		animation: pulse 0.5s ease;
	}

	@keyframes pulse {
		0% {
			transform: scale(1);
		}
		50% {
			transform: scale(1.05);
		}
		100% {
			transform: scale(1);
		}
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
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
	}

	.thumbnail-image.active {
		box-shadow: 0 2px 8px rgba(40, 116, 240, 0.3);
	}

	/* Add to Cart Button Styles */
	.add-to-cart .btn::before {
		content: '';
		position: absolute;
		top: 0;
		left: -100%;
		width: 100%;
		height: 100%;
		background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
		0%, 100% {
			transform: translateX(0);
		}
		25% {
			transform: translateX(-5px);
		}
		75% {
			transform: translateX(5px);
		}
	}

	/* Review Form Enhancements */
	.review-form textarea {
		border: 1px solid #ddd;
		border-radius: 5px;
		padding: 12px;
		resize: vertical;
		width: 100%;
		font-size: 14px;
		transition: border-color 0.3s ease, box-shadow 0.3s ease;
	}

	.review-form textarea:focus {
		border-color: #2874f0;
		box-shadow: 0 0 0 0.2rem rgba(40, 116, 240, 0.25);
		outline: none;
	}

	.review-form .form-group.has-error .form-control,
	.review-form .form-group.has-error textarea {
		border-color: #dc3545;
		box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
	}

	#submitReviewBtn {
		background: #2874f0;
		border: none;
		color: white;
		padding: 12px 30px;
		border-radius: 4px;
		font-size: 16px;
		font-weight: 600;
		cursor: pointer;
		transition: all 0.3s ease;
	}

	#submitReviewBtn:hover:not(:disabled) {
		background: #1c5bb8;
		transform: translateY(-2px);
		box-shadow: 0 4px 8px rgba(40, 116, 240, 0.3);
	}

	#submitReviewBtn:disabled {
		opacity: 0.6;
		cursor: not-allowed;
		background: #c2c2c2;
	}

	.review-message {
		margin-top: 15px;
		padding: 12px 16px;
		border-radius: 4px;
		display: none;
		animation: slideDown 0.3s ease;
	}

	.review-message.success {
		background: #d4edda;
		color: #155724;
		border: 1px solid #c3e6cb;
	}

	.review-message.error {
		background: #f8d7da;
		color: #721c24;
		border: 1px solid #f5c6cb;
	}

	.single-rating {
		animation: fadeInUp 0.5s ease;
		margin-bottom: 20px;
		padding: 20px;
		border: 1px solid #eee;
		border-radius: 8px;
		background: #fafafa;
		transition: all 0.3s ease;
	}

	.single-rating:hover {
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
		transform: translateY(-2px);
	}

	@keyframes fadeInUp {
		from {
			opacity: 0;
			transform: translateY(20px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.single-rating .rating-author {
		float: left;
		margin-right: 20px;
	}

	.single-rating .rating-author img {
		width: 60px;
		height: 60px;
		border-radius: 50%;
		object-fit: cover;
		border: 2px solid #e0e0e0;
	}

	.single-rating .rating-des h6 {
		font-size: 16px;
		margin-bottom: 8px;
		color: #333;
		font-weight: 600;
	}

	.single-rating .ratings {
		display: flex;
		align-items: center;
		margin-bottom: 10px;
	}

	.single-rating .ratings ul.rating {
		margin-bottom: 0;
		padding-left: 0;
		list-style: none;
		display: inline-flex;
		gap: 2px;
	}

	.single-rating .ratings ul.rating li {
		display: inline-block;
	}

	.single-rating .ratings ul.rating li i {
		color: #ffd700;
		font-size: 14px;
	}

	.single-rating .ratings ul.rating li i.fa-star-o {
		color: #ddd;
	}

	.single-rating .rate-count {
		font-size: 14px;
		color: #666;
		margin-left: 8px;
		font-weight: 500;
	}

	.single-rating p {
		margin-bottom: 8px;
		line-height: 1.6;
		color: #555;
		font-size: 14px;
	}

	.single-rating small {
		color: #999;
		font-style: italic;
	}

	/* Fix for Tab Scroll Issue */
	.nav-tabs .nav-link[href="#reviews"] {
		scroll-margin-top: 100px;
	}

	.tab-content .tab-pane {
		scroll-margin-top: 100px;
	}

	/* Smooth scroll for tabs */
	html {
		scroll-behavior: smooth;
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

		.star-rating__ico {
			font-size: 24px;
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

		.star-rating__ico {
			font-size: 22px;
		}

		.single-rating .rating-author {
			float: none;
			margin-bottom: 15px;
			text-align: center;
		}
	}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    /* ===============================
        GLOBAL VARIABLES
    ============================== */
    const variants = window.productVariants || [];
    const hasVariants = window.hasVariants || false;
    const productSlug = window.productSlug;

    let selectedVariantOptions = {};
    let currentVariant = null;
    let isUpdating = false;
    let lastChangedType = null;

    // Normalize a value to comparable string
    function normalizeVal(v) {
        if (v === null || v === undefined) return '';
        return String(v).trim().toLowerCase();
    }

    // Normalize an options object
    function normalizeOptions(obj) {
        const out = {};
        Object.entries(obj || {}).forEach(([k, v]) => {
            out[k.toLowerCase()] = normalizeVal(v);
        });
        return out;
    }

    function getVariantValuesNormalized(v) {
        const raw = typeof v.variant_values === 'string' ? 
            JSON.parse(v.variant_values) : (v.variant_values || {});
        const norm = {};
        Object.entries(raw).forEach(([k, val]) => {
            norm[k.toLowerCase()] = normalizeVal(val);
        });
        return norm;
    }

    // DOM Elements
    const qtyInput = document.getElementById('quantity');
    const minusBtn = document.querySelector('.button.minus .btn-number');
    const plusBtn = document.querySelector('.button.plus .btn-number');
    const mainImage = document.getElementById('mainImage');
    const thumbnailContainer = document.getElementById('thumbnailContainer');
    const imageLoadingOverlay = document.getElementById('imageLoadingOverlay');
    const priceContainer = document.getElementById('priceContainer');
    const displayPrice = document.getElementById('displayPrice');
    const originalPriceEl = document.getElementById('originalPrice');
    const discountBadge = document.getElementById('discountBadge');
    const displaySku = document.getElementById('displaySku');
    const displayStock = document.getElementById('displayStock');
    const stockAlert = document.getElementById('stockAlert');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const quantitySection = document.getElementById('quantitySection');
    const variantIdInput = document.getElementById('selectedVariantId');
    const addToCartForm = document.getElementById('addToCartForm');
    const wishlistBtn = document.getElementById('wishlistBtn');

    /* ===============================
        REVIEW AJAX HANDLER
    ============================== */
    const reviewForm = document.getElementById('reviewForm');
    const submitReviewBtn = document.getElementById('submitReviewBtn');
    const reviewTextarea = document.getElementById('review-text');
    const productRatingStars = document.getElementById('productRatingStars');
    const totalReviewCount = document.getElementById('totalReviewCount');
    const avgRatingSection = document.getElementById('avgRatingSection');
    const reviewsContainer = document.getElementById('reviewsContainer');

    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!document.querySelector('input[name="rate"]:checked')) {
                showReviewMessage('Please select a rating.', 'error');
                return;
            }

            const formData = new FormData(reviewForm);

            submitReviewBtn.disabled = true;
            submitReviewBtn.textContent = 'Submitting...';

            fetch(reviewForm.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json',
                },
            })
            .then(response => {
                if (!response.ok) throw new Error('Network error');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showReviewMessage(data.message, 'success');
                    appendNewReview(data.new_review);
                    updateRatings(data.avg_rating, data.total_reviews);
                    reviewForm.reset();
                    document.querySelectorAll('.star-rating__input').forEach(el => el.checked = false);
                } else {
                    showReviewMessage(data.message || 'Error', 'error');
                }
            })
            .catch(err => {
                console.error(err);
                showReviewMessage('Failed to submit review.', 'error');
            })
            .finally(() => {
                submitReviewBtn.disabled = false;
                submitReviewBtn.textContent = 'Submit Review';
            });
        });
    }

    function showReviewMessage(message, type) {
        let messageEl = document.querySelector('.review-message');
        if (!messageEl) {
            messageEl = document.createElement('div');
            messageEl.className = 'review-message';
            reviewForm.parentNode.insertBefore(messageEl, reviewForm.nextSibling);
        }
        messageEl.textContent = message;
        messageEl.className = `review-message ${type}`;
        messageEl.style.display = 'block';
        setTimeout(() => {
            messageEl.style.display = 'none';
        }, 5000);
    }

    function appendNewReview(reviewData) {
        if (!reviewsContainer) return;

        const singleRating = document.createElement('div');
        singleRating.className = 'single-rating';
        singleRating.innerHTML = `
            <div class="rating-author">
                <img src="${reviewData.user_photo}" alt="${reviewData.user_name}" loading="lazy">
            </div>
            <div class="rating-des">
                <h6>${reviewData.user_name}</h6>
                <div class="ratings">
                    <ul class="rating">
                        ${Array.from({length: 5}, (_, i) => 
                            `<li><i class="fa ${reviewData.rate >= (i + 1) ? 'fa-star' : 'fa-star-o'}"></i></li>`
                        ).join('')}
                    </ul>
                    <div class="rate-count">(${reviewData.rate})</div>
                </div>
                <p>${reviewData.review}</p>
                <small class="text-muted" style="font-size: 12px;">${new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</small>
            </div>
        `;

        const avgSection = reviewsContainer.querySelector('.avg-ratting');
        if (avgSection) {
            const existingReviews = avgSection.nextElementSibling;
            if (existingReviews && existingReviews.classList.contains('single-rating')) {
                reviewsContainer.insertBefore(singleRating, existingReviews);
            } else {
                avgSection.insertAdjacentElement('afterend', singleRating);
            }
        } else {
            reviewsContainer.appendChild(singleRating);
        }

        // Trigger animation
        singleRating.style.opacity = '0';
        singleRating.style.transform = 'translateY(20px)';
        setTimeout(() => {
            singleRating.style.transition = 'all 0.5s ease';
            singleRating.style.opacity = '1';
            singleRating.style.transform = 'translateY(0)';
        }, 100);
    }

    function updateRatings(avgRating, totalReviews) {
        const ceilAvg = Math.ceil(avgRating);

        if (productRatingStars) {
            productRatingStars.innerHTML = Array.from({length: 5}, (_, i) => 
                `<li><i class="fa ${ceilAvg >= (i + 1) ? 'fa-star' : 'fa-star-o'}"></i></li>`
            ).join('');
        }

        if (totalReviewCount) {
            totalReviewCount.innerHTML = `(${totalReviews}) Review${totalReviews !== 1 ? 's' : ''}`;
        }

        if (avgRatingSection) {
            const h4 = avgRatingSection.querySelector('h4');
            const span = avgRatingSection.querySelector('span');
            if (h4) h4.innerHTML = `${ceilAvg} <span>(Overall)</span>`;
            if (span) span.innerHTML = `Based on ${totalReviews} Comments`;
        }
    }

    /* ===============================
        TAB SCROLL FIX
    ============================== */
    const reviewTabLink = document.getElementById('reviews-tab');
    const descriptionTabLink = document.getElementById('description-tab');

    function handleTabClick(e, targetId) {
        e.preventDefault();
        e.stopPropagation();

        const link = e.currentTarget;
        const targetPane = document.getElementById(targetId);

        document.querySelectorAll('.nav-tabs .nav-link').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('show', 'active'));

        link.classList.add('active');
        targetPane.classList.add('show', 'active');

        if (targetPane) {
            const offsetTop = targetPane.offsetTop - 100;
            window.scrollTo({
                top: offsetTop,
                behavior: 'smooth'
            });
        }
    }

    if (reviewTabLink) {
        reviewTabLink.addEventListener('click', function(e) {
            handleTabClick(e, 'reviews');
        });
    }

    if (descriptionTabLink) {
        descriptionTabLink.addEventListener('click', function(e) {
            handleTabClick(e, 'description');
        });
    }

    const totalReviewLink = document.getElementById('totalReviewCount');
    if (totalReviewLink) {
        totalReviewLink.addEventListener('click', function(e) {
            e.preventDefault();
            handleTabClick({ currentTarget: reviewTabLink, preventDefault: () => {}, stopPropagation: () => {} }, 'reviews');
        });
    }

    /* ===============================
        UPDATE WISHLIST HREF WITH VARIANT ID
    ============================== */
    function updateWishlistHref() {
        if (!wishlistBtn || !currentVariant) return;

        let currentHref = wishlistBtn.getAttribute('href');
        const separator = currentHref.includes('?') ? '&' : '?';
        const newHref = currentHref.includes('variant_id=') 
            ? currentHref.replace(/variant_id=\d+/, `variant_id=${currentVariant.id}`) 
            : `${currentHref}${separator}variant_id=${currentVariant.id}`;

        wishlistBtn.href = newHref;
    }

    /* ===============================
        QUANTITY CONTROL FUNCTIONS
    ============================== */
    function updateMinusState() {
        if (!minusBtn || !qtyInput) return;
        const min = parseInt(qtyInput.getAttribute('data-min')) || 1;
        const currentValue = parseInt(qtyInput.value) || 1;
        minusBtn.disabled = currentValue <= min;
    }

    function updateQuantityMax() {
        if (!qtyInput || !currentVariant) return;
        const max = parseInt(currentVariant.stock) || 0;
        qtyInput.setAttribute('data-max', max);
        const val = parseInt(qtyInput.value) || 1;
        if (val > max && max > 0) {
            qtyInput.value = max;
        }
        updateMinusState();
    }

    /* ===============================
        VARIANT SELECTION HANDLERS
    ============================== */
    function handleVariantSelection(e) {
        if (isUpdating) return;
        const target = e.currentTarget;
        const variantType = target.dataset.variantType;
        const variantValue = target.dataset.variantValue;

        lastChangedType = variantType.toLowerCase();
        selectedVariantOptions[variantType] = variantValue;

        document.querySelectorAll(`[data-variant-type="${variantType}"].variant-option-btn`).forEach(b => {
            b.classList.remove('active');
        });
        target.classList.add('active');

        updateVariantWithLoading();
    }

    function handleColorSwatchSelection(e) {
        if (isUpdating) return;

        if (e && e.preventDefault) e.preventDefault();
        if (e && e.stopPropagation) e.stopPropagation();

        let input = null;
        
        if (e.target?.tagName === 'INPUT') {
            input = e.target;
        } else if (e.currentTarget?.tagName === 'INPUT') {
            input = e.currentTarget;
        } else if (e.currentTarget?.querySelector) {
            input = e.currentTarget.querySelector('input[type="radio"]');
        } else if (e.target?.closest) {
            const swatch = e.target.closest('.color-swatch');
            if (swatch) input = swatch.querySelector('input[type="radio"]');
        }
        
        if (!input) return;

        input.disabled = false;
        const variantType = input.dataset.variantType || input.name;
        const variantValue = input.value;

        input.checked = true;
        lastChangedType = variantType.toLowerCase();
        selectedVariantOptions[variantType] = variantValue;

        document.querySelectorAll(`input[name="${variantType}"]`).forEach(inp => {
            const swatch = inp.closest('.color-swatch');
            if (swatch) {
                const checkmark = swatch.querySelector('.color-swatch-checkmark');
                const label = swatch.querySelector('.color-swatch-label');
                if (checkmark) checkmark.classList.add('d-none');
                if (label) label.classList.remove('active');
            }
        });

        const selectedSwatch = input.closest('.color-swatch');
        if (selectedSwatch) {
            const checkmark = selectedSwatch.querySelector('.color-swatch-checkmark');
            const label = selectedSwatch.querySelector('.color-swatch-label');
            if (checkmark) checkmark.classList.remove('d-none');
            if (label) label.classList.add('active');
        }

        updateVariantWithLoading();
    }

    /* ===============================
        UPDATE AVAILABLE OPTIONS
    ============================== */
    function updateAvailableOptions() {
        const normSelected = normalizeOptions(selectedVariantOptions);
        
        const allVariantTypes = [...new Set(variants.flatMap(v => {
            const vals = getVariantValuesNormalized(v);
            return Object.keys(vals);
        }))];

        allVariantTypes.forEach(variantTypeLower => {
            const typeButtons = Array.from(document.querySelectorAll('.variant-option-btn'))
                .filter(btn => (btn.dataset.variantType || '').toLowerCase() === variantTypeLower);
            const typeInputs = Array.from(document.querySelectorAll('input[type="radio"]'))
                .filter(inp => ((inp.dataset.variantType || inp.name || '').toLowerCase() === variantTypeLower));

            [...typeButtons, ...typeInputs].forEach(element => {
                const variantValue = element.tagName === 'INPUT' ? 
                    element.value : element.dataset.variantValue;
                const swatch = element.tagName === 'INPUT' ? 
                    element.closest('.color-swatch') : null;

                let exists = false;

                if (variantTypeLower === 'color') {
                    exists = variants.some(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const normVals = getVariantValuesNormalized(v);
                        return normVals.color === normalizeVal(variantValue);
                    });
                } else if (variantTypeLower === 'storage') {
                    exists = variants.some(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const normVals = getVariantValuesNormalized(v);
                        
                        if (normVals.storage !== normalizeVal(variantValue)) return false;
                        if (normSelected.color && normVals.color !== normSelected.color) return false;
                        
                        if (lastChangedType === 'ram' && normSelected.ram) {
                            if (normVals.ram !== normSelected.ram) return false;
                        }
                        
                        return true;
                    });
                } else if (variantTypeLower === 'ram') {
                    exists = variants.some(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const normVals = getVariantValuesNormalized(v);
                        
                        if (normVals.ram !== normalizeVal(variantValue)) return false;
                        if (normSelected.color && normVals.color !== normSelected.color) return false;
                        
                        if (lastChangedType === 'storage' && normSelected.storage) {
                            if (normVals.storage !== normSelected.storage) return false;
                        }
                        
                        return true;
                    });
                } else {
                    exists = variants.some(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const normVals = getVariantValuesNormalized(v);
                        
                        if (normVals[variantTypeLower] !== normalizeVal(variantValue)) return false;
                        if (normSelected.color && normVals.color !== normSelected.color) return false;
                        
                        return true;
                    });
                }

                if (element.tagName === 'INPUT') {
                    element.disabled = !exists;
                    if (swatch) {
                        const label = swatch.querySelector('.color-swatch-label');
                        if (exists) {
                            swatch.classList.remove('disabled');
                            if (label) label.classList.remove('disabled');
                        } else {
                            swatch.classList.add('disabled');
                            if (label) label.classList.add('disabled');
                        }
                    }
                } else {
                    if (exists) {
                        element.classList.remove('disabled');
                        element.removeAttribute('disabled');
                    } else {
                        element.classList.add('disabled');
                        element.setAttribute('disabled', 'disabled');
                    }
                }
            });
        });
    }

    /* ===============================
        AUTO-SELECT MATCHING VARIANT
    ============================== */
    function autoSelectMatchingVariant() {
        const normSelected = normalizeOptions(selectedVariantOptions);
        
        if (!normSelected.color) return null;

        // Case 1: Storage changed, auto-select RAM
        if (lastChangedType === 'storage' && normSelected.storage) {
            const matchingVariants = variants.filter(v => {
                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                const normVals = getVariantValuesNormalized(v);
                return normVals.color === normSelected.color && 
                       normVals.storage === normSelected.storage;
            });

            if (matchingVariants.length > 0) {
                matchingVariants.sort((a, b) => {
                    const priceA = parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100);
                    const priceB = parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100);
                    return priceA - priceB;
                });

                const selectedVariant = matchingVariants[0];
                const vals = typeof selectedVariant.variant_values === 'string' 
                    ? JSON.parse(selectedVariant.variant_values) 
                    : selectedVariant.variant_values;

                Object.entries(vals).forEach(([type, value]) => {
                    const typeLower = type.toLowerCase();
                    if (typeLower === 'ram') {
                        selectedVariantOptions[type] = value;

                        document.querySelectorAll('.variant-option-btn').forEach(btn => {
                            if ((btn.dataset.variantType || '').toLowerCase() === 'ram') {
                                btn.classList.remove('active');
                            }
                        });

                        const ramLower = normalizeVal(value);
                        document.querySelectorAll('.variant-option-btn').forEach(btn => {
                            if ((btn.dataset.variantType || '').toLowerCase() === 'ram') {
                                const btnVal = normalizeVal(btn.dataset.variantValue);
                                if (btnVal === ramLower) {
                                    btn.classList.add('active');
                                    btn.classList.remove('disabled');
                                    btn.removeAttribute('disabled');
                                }
                            }
                        });
                    }
                });

                return selectedVariant;
            }
        }

        // Case 2: RAM changed, auto-select Storage
        if (lastChangedType === 'ram' && normSelected.ram) {
            const matchingVariants = variants.filter(v => {
                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                const normVals = getVariantValuesNormalized(v);
                return normVals.color === normSelected.color && 
                       normVals.ram === normSelected.ram;
            });

            if (matchingVariants.length > 0) {
                matchingVariants.sort((a, b) => {
                    const priceA = parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100);
                    const priceB = parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100);
                    return priceA - priceB;
                });

                const selectedVariant = matchingVariants[0];
                const vals = typeof selectedVariant.variant_values === 'string' 
                    ? JSON.parse(selectedVariant.variant_values) 
                    : selectedVariant.variant_values;

                Object.entries(vals).forEach(([type, value]) => {
                    const typeLower = type.toLowerCase();
                    if (typeLower === 'storage') {
                        selectedVariantOptions[type] = value;

                        document.querySelectorAll('.variant-option-btn').forEach(btn => {
                            if ((btn.dataset.variantType || '').toLowerCase() === 'storage') {
                                btn.classList.remove('active');
                            }
                        });

                        const storageLower = normalizeVal(value);
                        document.querySelectorAll('.variant-option-btn').forEach(btn => {
                            if ((btn.dataset.variantType || '').toLowerCase() === 'storage') {
                                const btnVal = normalizeVal(btn.dataset.variantValue);
                                if (btnVal === storageLower) {
                                    btn.classList.add('active');
                                    btn.classList.remove('disabled');
                                    btn.removeAttribute('disabled');
                                }
                            }
                        });
                    }
                });

                return selectedVariant;
            }
        }

        // Case 3: Only color selected, auto-select cheapest
        if (normSelected.color && !normSelected.storage && !normSelected.ram) {
            const matchingVariants = variants.filter(v => {
                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                const normVals = getVariantValuesNormalized(v);
                return normVals.color === normSelected.color;
            });

            if (matchingVariants.length > 0) {
                matchingVariants.sort((a, b) => {
                    const priceA = parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100);
                    const priceB = parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100);
                    return priceA - priceB;
                });

                const selectedVariant = matchingVariants[0];
                const vals = typeof selectedVariant.variant_values === 'string' 
                    ? JSON.parse(selectedVariant.variant_values) 
                    : selectedVariant.variant_values;

                Object.entries(vals).forEach(([type, value]) => {
                    const typeLower = type.toLowerCase();
                    if (typeLower !== 'color') {
                        selectedVariantOptions[type] = value;
                        
                        const valueLower = normalizeVal(value);
                        document.querySelectorAll('.variant-option-btn').forEach(btn => {
                            if ((btn.dataset.variantType || '').toLowerCase() === typeLower) {
                                const btnVal = normalizeVal(btn.dataset.variantValue);
                                if (btnVal === valueLower) {
                                    btn.classList.add('active');
                                    btn.classList.remove('disabled');
                                    btn.removeAttribute('disabled');
                                } else {
                                    btn.classList.remove('active');
                                }
                            }
                        });
                    }
                });

                return selectedVariant;
            }
        }

        return null;
    }

    /* ===============================
        MAIN UPDATE FUNCTION
    ============================== */
    async function updateVariantWithLoading() {
        isUpdating = true;
        showLoadingStates();
        
        updateAvailableOptions();
        const autoSelected = autoSelectMatchingVariant();

        let matchingVariant = variants.find(v => {
            if (v.status !== 'active') return false;
            const normVals = getVariantValuesNormalized(v);
            const selectedKeys = Object.keys(normalizeOptions(selectedVariantOptions));
            return selectedKeys.every(key => 
                normVals[key] === normalizeOptions(selectedVariantOptions)[key]
            );
        });

        if (matchingVariant) {
            currentVariant = matchingVariant;
            applyVariantToUI(matchingVariant);
            updateProductDisplay(matchingVariant);
        } else if (autoSelected) {
            currentVariant = autoSelected;
            applyVariantToUI(autoSelected);
            updateProductDisplay(autoSelected);
        } else {
            handleNoVariantFound();
        }

        // Update wishlist href after variant is set
        updateWishlistHref();

        hideLoadingStates();
        isUpdating = false;
    }

    /* ===============================
        UPDATE PRODUCT DISPLAY
    ============================== */
    function updateProductDisplay(variant) {
        if (priceContainer) priceContainer.classList.add('loading-shimmer');

        setTimeout(() => {
            const price = parseFloat(variant.price);
            const discount = parseFloat(variant.discount || 0);
            const discountedPrice = price * (1 - discount / 100);

            if (displayPrice) displayPrice.textContent = '$' + discountedPrice.toFixed(2);

            if (originalPriceEl) {
                if (discount > 0) {
                    originalPriceEl.innerHTML = '<s class="text-muted">$' + price.toFixed(2) + '</s>';
                    originalPriceEl.style.display = 'inline';
                } else {
                    originalPriceEl.style.display = 'none';
                }
            }

            if (discountBadge) {
                if (discount > 0) {
                    discountBadge.textContent = discount + '% off';
                    discountBadge.style.display = 'inline';
                } else {
                    discountBadge.style.display = 'none';
                }
            }

            if (priceContainer) priceContainer.classList.remove('loading-shimmer');
        }, 150);

        if (displaySku) displaySku.textContent = variant.sku || 'N/A';

        const stock = parseInt(variant.stock || 0);

        if (displayStock) {
            displayStock.innerHTML = stock > 0 
                ? `<span class="badge badge-success">${stock}</span>`
                : '<span class="badge badge-danger">Out of Stock</span>';
        }

        if (stockAlert) {
            stockAlert.classList.add('d-none');
        }

        if (quantitySection) {
            quantitySection.style.display = 'block';
            quantitySection.classList.remove('hidden');
        }
        
        if (addToCartBtn) {
            addToCartBtn.style.display = 'inline-block';
            addToCartBtn.disabled = stock <= 0;
            addToCartBtn.textContent = stock > 0 ? 'Add to cart' : 'Out of Stock';
        }
        
        if (wishlistBtn) {
            wishlistBtn.style.display = 'inline-block';
        }

        if (qtyInput && stock > 0) {
            qtyInput.setAttribute('data-max', stock);
            updateQuantityMax();
        }

        if (variantIdInput) {
            variantIdInput.value = variant.id || '';
        }

        updateVariantImages(variant);
    }

    /* ===============================
        UPDATE VARIANT IMAGES
    ============================== */
    function updateVariantImages(variant) {
        if (!variant.images || variant.images.length === 0 || !mainImage) return;

        imageLoadingOverlay?.classList.remove('d-none');
        mainImage.classList.add('loading');

        const firstImage = variant.images.find(img => img.is_primary) || variant.images[0];
        mainImage.src = firstImage.image_path;

        mainImage.onload = () => {
            imageLoadingOverlay?.classList.add('d-none');
            mainImage.classList.remove('loading');
        };

        if (!thumbnailContainer) return;

        thumbnailContainer.innerHTML = '';
        variant.images.forEach((img, index) => {
            const thumbDiv = document.createElement('div');
            thumbDiv.className = 'thumbnail-item mx-1';
            thumbDiv.style.cssText = 'flex: 0 0 auto; opacity: 0; transition: opacity 0.3s ease;';

            const thumbImg = document.createElement('img');
            thumbImg.src = img.image_path;
            thumbImg.alt = `Thumbnail ${index + 1}`;
            thumbImg.className = `img-fluid thumbnail-image ${index === 0 ? 'active' : ''}`;
            thumbImg.style.cssText = `width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid ${index === 0 ? '#2874f0' : '#eee'}; transition: all 0.2s ease;`;
            thumbImg.dataset.full = img.image_path;

            thumbImg.addEventListener('click', function() {
                document.querySelectorAll('.thumbnail-image').forEach(t => {
                    t.classList.remove('active');
                    t.style.border = '2px solid #eee';
                });
                this.classList.add('active');
                this.style.border = '2px solid #2874f0';

                const fullSrc = this.dataset.full || this.src;
                if (mainImage && imageLoadingOverlay) {
                    imageLoadingOverlay.classList.remove('d-none');
                    mainImage.classList.add('loading');
                    mainImage.src = fullSrc;
                    mainImage.onload = () => {
                        imageLoadingOverlay.classList.add('d-none');
                        mainImage.classList.remove('loading');
                    };
                }
            });

            thumbDiv.appendChild(thumbImg);
            thumbnailContainer.appendChild(thumbDiv);
            setTimeout(() => thumbDiv.style.opacity = '1', index * 50);
        });
    }

    /* ===============================
        APPLY VARIANT TO UI
    ============================== */
    function applyVariantToUI(variant) {
        if (!variant) return;
        const vals = typeof variant.variant_values === 'string' 
            ? JSON.parse(variant.variant_values) 
            : variant.variant_values;

        Object.entries(vals).forEach(([type, value]) => {
            const typeLower = type.toLowerCase();
            const valueLower = normalizeVal(value);

            selectedVariantOptions[type] = value;

            document.querySelectorAll('.variant-option-btn').forEach(btn => {
                if ((btn.dataset.variantType || '').toLowerCase() === typeLower) {
                    btn.classList.remove('active');
                }
            });

            document.querySelectorAll('input[type="radio"]').forEach(inp => {
                const inpType = ((inp.dataset.variantType || inp.name) || '').toLowerCase();
                if (inpType === typeLower) {
                    inp.checked = false;
                    const sw = inp.closest('.color-swatch');
                    if (sw) {
                        sw.querySelector('.color-swatch-checkmark')?.classList.add('d-none');
                        sw.querySelector('.color-swatch-label')?.classList.remove('active');
                    }
                }
            });

            document.querySelectorAll('.variant-option-btn').forEach(btn => {
                if ((btn.dataset.variantType || '').toLowerCase() === typeLower && 
                    normalizeVal(btn.dataset.variantValue) === valueLower) {
                    btn.classList.add('active');
                }
            });

            document.querySelectorAll('input[type="radio"]').forEach(inp => {
                const dt = ((inp.dataset.variantType || inp.name) || '').toLowerCase();
                if (dt === typeLower && normalizeVal(inp.value) === valueLower) {
                    inp.checked = true;
                    const sw = inp.closest('.color-swatch');
                    if (sw) {
                        sw.querySelector('.color-swatch-checkmark')?.classList.remove('d-none');
                        sw.querySelector('.color-swatch-label')?.classList.add('active');
                    }
                }
            });
        });
    }

    /* ===============================
        PRESELECT CHEAPEST VARIANT
    ============================== */
    function preselectCheapestVariant() {
        const inStock = variants.filter(v => v.status === 'active' && parseInt(v.stock) > 0);
        const toSelect = inStock.length > 0 ? inStock : variants.filter(v => v.status === 'active');
        
        if (toSelect.length === 0) return;

        const sorted = [...toSelect].sort((a, b) => {
            const priceA = parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100);
            const priceB = parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100);
            return priceA - priceB;
        });

        const variant = sorted[0];
        const vals = typeof variant.variant_values === 'string' 
            ? JSON.parse(variant.variant_values) 
            : variant.variant_values;

        Object.entries(vals).forEach(([type, value]) => {
            selectedVariantOptions[type] = value;
            
            const typeLower = type.toLowerCase();
            const valueLower = normalizeVal(value);

            document.querySelectorAll('.variant-option-btn').forEach(btn => {
                if ((btn.dataset.variantType || '').toLowerCase() === typeLower && 
                    normalizeVal(btn.dataset.variantValue) === valueLower) {
                    btn.classList.add('active');
                }
            });

            document.querySelectorAll('input[type="radio"]').forEach(inp => {
                const dt = ((inp.dataset.variantType || inp.name) || '').toLowerCase();
                if (dt === typeLower && normalizeVal(inp.value) === valueLower) {
                    inp.checked = true;
                    const sw = inp.closest('.color-swatch');
                    if (sw) {
                        sw.querySelector('.color-swatch-checkmark')?.classList.remove('d-none');
                        sw.querySelector('.color-swatch-label')?.classList.add('active');
                    }
                }
            });
        });

        currentVariant = variant;
        if (variantIdInput) variantIdInput.value = variant.id || '';
        updateVariantWithLoading();
    }

    /* ===============================
        HANDLE NO VARIANT FOUND
    ============================== */
    function handleNoVariantFound() {
        if (stockAlert) {
            stockAlert.classList.remove('d-none');
            const msg = document.getElementById('stockAlertMessage');
            if (msg) msg.textContent = 'No matching variant available.';
        }
        
        if (quantitySection) {
            quantitySection.style.display = 'none';
        }
        if (addToCartBtn) {
            addToCartBtn.style.display = 'none';
        }
        
        if (wishlistBtn) {
            wishlistBtn.style.display = 'none';
        }
        
        if (variantIdInput) {
            variantIdInput.value = '';
        }

        // Reset wishlist href to base (no variant_id)
        if (wishlistBtn) {
            let currentHref = wishlistBtn.getAttribute('href');
            const baseHref = currentHref.split('?')[0].split('&')[0]; // Remove query params
            wishlistBtn.href = baseHref;
        }
    }

    /* ===============================
        LOADING STATES
    ============================== */
    function showLoadingStates() {
        priceContainer?.classList.add('loading-shimmer');
    }

    function hideLoadingStates() {
        priceContainer?.classList.remove('loading-shimmer');
    }

    /* ===============================
        FORM SUBMISSION HANDLER FOR ADD TO CART
    ============================== */
    if (addToCartForm) {
        addToCartForm.addEventListener('submit', function(e) {
            const variantId = variantIdInput ? variantIdInput.value.trim() : '';
            const quantity = parseInt(qtyInput.value) || 1;
            const stock = currentVariant ? parseInt(currentVariant.stock) || 0 : parseInt(document.querySelector('#displayStock .badge')?.textContent) || 0;

            // Client-side validation for stock
            if (stock < quantity) {
                e.preventDefault();
                if (stockAlert) {
                    stockAlert.classList.remove('d-none');
                    document.getElementById('stockAlertMessage').textContent = `Insufficient stock. Only ${stock} available.`;
                }
                return false;
            }

            // Ensure variant_id is empty for non-variant products
            if (!hasVariants && variantId) {
                variantIdInput.value = '';
            }

            // Update wishlist href before submit (in case user clicks wishlist after)
            updateWishlistHref();
        });
    }

    /* ===============================
        INITIALIZE VARIANT SYSTEM
    ============================== */
    function initializeVariantSystem() {
        document.querySelectorAll('.variant-option-btn').forEach(btn => {
            btn.addEventListener('click', handleVariantSelection);
        });

        document.querySelectorAll('#variantContainer input[type="radio"]').forEach(inp => {
            inp.addEventListener('change', function(e) {
                if (isUpdating) return;
                handleColorSwatchSelection({ 
                    target: this, 
                    currentTarget: this.closest('.color-swatch'), 
                    preventDefault: () => {}, 
                    stopPropagation: () => {} 
                });
            });
        });

        document.querySelectorAll('.color-swatch').forEach(s => {
            const inp = s.querySelector('input[type="radio"]');
            if (!inp) return;

            s.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                handleColorSwatchSelection({ 
                    target: inp, 
                    currentTarget: this, 
                    preventDefault: () => {}, 
                    stopPropagation: () => {} 
                });
            });

            const label = s.querySelector('.color-swatch-label');
            if (label) {
                label.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handleColorSwatchSelection({ 
                        target: inp, 
                        currentTarget: s, 
                        preventDefault: () => {}, 
                        stopPropagation: () => {} 
                    });
                });
            }
        });

        updateAvailableOptions();
        preselectCheapestVariant();
    }

    /* ===============================
        QUANTITY CONTROLS
    ============================== */
    plusBtn?.addEventListener('click', () => {
        const max = parseInt(qtyInput.getAttribute('data-max')) || 1000;
        const val = parseInt(qtyInput.value) || 1;
        if (val < max) {
            qtyInput.value = val + 1;
            updateMinusState();
        }
    });

    minusBtn?.addEventListener('click', () => {
        const min = parseInt(qtyInput.getAttribute('data-min')) || 1;
        const val = parseInt(qtyInput.value) || 1;
        if (val > min) {
            qtyInput.value = val - 1;
            updateMinusState();
        }
    });

    qtyInput?.addEventListener('change', function() {
        const min = parseInt(this.getAttribute('data-min')) || 1;
        const max = parseInt(this.getAttribute('data-max')) || 1000;
        let val = parseInt(this.value) || 1;
        val = Math.max(min, Math.min(max, val));
        this.value = val;
        updateMinusState();
    });

    updateMinusState();

    /* ===============================
        INITIAL IMAGE GALLERY (fallback if no variant images)
    ============================== */
    document.querySelectorAll('.thumbnail-image').forEach(thumb => {
        thumb.addEventListener('click', function() {
            document.querySelectorAll('.thumbnail-image').forEach(t => {
                t.classList.remove('active');
                t.style.border = '2px solid #eee';
            });
            this.classList.add('active');
            this.style.border = '2px solid #2874f0';

            const fullSrc = this.dataset.full || this.src;
            if (mainImage && imageLoadingOverlay) {
                imageLoadingOverlay.classList.remove('d-none');
                mainImage.classList.add('loading');
                mainImage.src = fullSrc;
                mainImage.onload = () => {
                    imageLoadingOverlay.classList.add('d-none');
                    mainImage.classList.remove('loading');
                };
            }
        });
    });

    /* ===============================
        HANDLE NON-VARIANT PRODUCTS
    ============================== */
    if (!hasVariants) {
        // For non-variant products, ensure variant_id is empty and use base stock/price
        if (variantIdInput) variantIdInput.value = '';
        const baseStock = {{ $product_detail->base_stock ?? 0 }};
        if (addToCartBtn) {
            addToCartBtn.disabled = baseStock <= 0;
            addToCartBtn.textContent = baseStock > 0 ? 'Add to cart' : 'Out of Stock';
        }
        if (qtyInput) {
            qtyInput.setAttribute('data-max', baseStock);
        }

        // Ensure wishlist href has no variant_id for non-variants
        if (wishlistBtn) {
            let currentHref = wishlistBtn.getAttribute('href');
            const baseHref = currentHref.split('?')[0].split('&')[0]; // Remove query params
            wishlistBtn.href = baseHref;
        }
    }

    /* ===============================
        INITIALIZE
    ============================== */
    if (hasVariants && variants.length > 0) {
        initializeVariantSystem();
    }
});
</script>
@endpush