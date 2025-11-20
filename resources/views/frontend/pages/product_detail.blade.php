@extends('frontend.layouts.master')

@section('meta')
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name='copyright' content=''>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="keywords" content="online shop, purchase, cart, ecommerce site, best online shopping">
    <meta name="description" content="{{ $product_detail->summary }}">
    <meta property="og:url" content="{{ route('product-detail', $product_detail->slug) }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $product_detail->title }}">
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
    <meta property="og:description" content="{{ $product_detail->description }}">
@endsection

@section('title', 'E-SHOP || PRODUCT DETAIL')

@section('main-content')

    <!-- Breadcrumbs -->
    <div class="breadcrumbs">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="bread-inner">
                        <ul class="bread-list">
                            <li><a href="{{ route('home') }}" tabindex="13">Home<i class="ti-arrow-right"></i></a></li>
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
                                    $mainImagePath =
                                        $primary?->image_path ?? ($allImages->first()?->image_path ?? null);
                                    $mainImageUrl = $mainImagePath
                                        ? product_image_url($mainImagePath)
                                        : asset('images/no-image.png');
                                @endphp

                                <div class="main-image-container position-relative mb-3"
                                    style="display:flex;align-items:center;justify-content:center;">
                                    <div id="imageLoadingOverlay" class="loading-overlay d-none position-absolute"
                                        style="top:0;left:0;right:0;bottom:0;background:rgba(255,255,255,0.8);display:flex;align-items:center;justify-content:center;z-index:10;">
                                        <div class="spinner-border text-primary" style="width:2rem;height:2rem;"></div>
                                    </div>
                                    <img src="{{ $mainImageUrl }}" alt="{{ $product_detail->title }}"
                                        class="main-image img-fluid" id="mainImage" tabindex="15"
                                        style="max-height:500px;object-fit:contain;border-radius:10px;width:100%;min-width:300px;border:2px solid #eee;transition:opacity .3s ease;"
                                        data-original-src="{{ $mainImageUrl }}">
                                </div>

                                <div class="thumbnail-carousel mt-2">
                                    <div class="thumbnails d-flex flex-row flex-nowrap" id="thumbnailContainer">
                                        @if ($allImages->isEmpty())
                                            <div class="thumbnail-item mx-1" style="flex:0 0 auto;">
                                                <img src="{{ asset('images/no-image.png') }}" alt="No Image"
                                                    class="img-fluid thumbnail-image active"
                                                    style="width:80px;height:80px;object-fit:cover;border-radius:5px;border:2px solid #2874f0;">
                                            </div>
                                        @else
                                            @foreach ($allImages as $index => $image)
                                                <div class="thumbnail-item mx-1" style="flex:0 0 auto;">
                                                    <img src="{{ $image->url }}" alt="Thumbnail {{ $index + 1 }}"
                                                        class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
                                                        style="width:80px;height:80px;object-fit:cover;border-radius:5px;cursor:pointer;border:2px solid {{ $index == 0 ? '#2874f0' : '#eee' }};"
                                                        data-index="{{ $index }}" data-full="{{ $image->url }}">
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-12">
                            <div class="product-des">
                                <!-- Product Title -->
                                <h1 class="product-title" tabindex="17">{{ $product_detail->title }}</h1>

                                <!-- Rating & Reviews -->
                                <div class="rating-section" id="productRatingSummary">
                                    <div class="rating-left">
                                        <div class="rating-badge">
                                            @php
                                                $rate = ceil($product_detail->getReview->avg('rate'));
                                            @endphp
                                            <span class="rating-value">{{ $rate }}</span>
                                            <i class="fa fa-star"></i>
                                        </div>
                                        <div class="rating-details">
                                            <span
                                                class="rating-count">{{ number_format($product_detail['getReview']->count()) }}
                                                Ratings</span>
                                            <span class="review-separator">&</span>
                                            <a href="#reviews" class="review-link"
                                                tabindex="18">{{ number_format($product_detail['getReview']->count()) }}
                                                Reviews</a>
                                        </div>
                                    </div>
                                    <!-- Wishlist Button (Rounded) -->
                                    <a href="{{ route('add-to-wishlist', $product_detail->slug) }}"
                                        class="btn-wishlist-rounded" id="wishlistBtn" title="Add to Wishlist">
                                        <i class="fa fa-heart-o"></i>
                                    </a>
                                </div>

                                <!-- Special Price Label -->
                                <div class="special-price-label">
                                    <span class="label-text">Special Price</span>
                                </div>

                                <!-- Price Section -->
                                <div class="price-container" id="priceContainer">
                                    <div class="price-row">
                                        <span class="price-current" id="displayPrice">
                                            ${{ number_format($product_detail->discounted_price, 0) }}
                                        </span>
                                        @if ($product_detail->discount_percentage > 0)
                                            <span class="price-original"
                                                id="originalPrice">₹{{ number_format($product_detail->original_price, 0) }}</span>
                                            <span class="price-discount"
                                                id="discountBadge">{{ $product_detail->discount_percentage }}% off</span>
                                        @endif
                                    </div>
                                    {{-- <div class="extra-discount-label">
									<span>+ exchange offers</span>
								</div> --}}
                                </div>

                                {{-- Variant Selection Section --}}
                                @if ($product_detail->has_variants && $product_detail->variants->count() > 0)
                                    <div class="variant-selection-container" id="variantContainer">
                                        @php $tabindex = 21; @endphp
                                        @foreach ($variantTypes as $type)
                                            <div class="variant-group">
                                                <h6 class="variant-label">{{ $type->display_name }}</h6>
                                                <div class="variant-options">
                                                    @foreach ($type->options as $option)
                                                        @php
                                                            $isColor = strtolower($type->name) === 'color';
                                                            $isRAM = strtolower($type->name) === 'ram';
                                                            $isStorage = strtolower($type->name) === 'storage';
                                                        @endphp

                                                        @if ($isColor)
                                                            {{-- Color Variant with Image Thumbnail Style --}}
                                                            <div class="color-variant-item"
                                                                tabindex="{{ $tabindex++ }}">
                                                                <input type="radio"
                                                                    id="color-{{ $type->id }}-{{ $option->id }}"
                                                                    name="{{ $type->name }}"
                                                                    value="{{ $option->value }}"
                                                                    class="color-variant-radio"
                                                                    data-variant-type="{{ $type->name }}"
                                                                    data-variant-value="{{ $option->value }}">
                                                                <label
                                                                    for="color-{{ $type->id }}-{{ $option->id }}"
                                                                    class="color-variant-label">
                                                                    <div class="color-image-box">
                                                                        @php
                                                                            // Get first variant with this color for thumbnail
                                                                            $colorVariant = $product_detail->variants->first(
                                                                                function ($v) use ($option) {
                                                                                    $values = is_string(
                                                                                        $v->variant_values,
                                                                                    )
                                                                                        ? json_decode(
                                                                                            $v->variant_values,
                                                                                            true,
                                                                                        )
                                                                                        : $v->variant_values;
                                                                                    return isset($values['color']) &&
                                                                                        strtolower($values['color']) ===
                                                                                            strtolower($option->value);
                                                                                },
                                                                            );
                                                                            $thumbImage =
                                                                                $colorVariant &&
                                                                                $colorVariant->images->isNotEmpty()
                                                                                    ? $colorVariant->images->first()
                                                                                        ->image_path
                                                                                    : null;
                                                                            $thumbImage = $thumbImage
                                                                                ? variant_image_url($thumbImage)
                                                                                : null;
                                                                        @endphp
                                                                        @if ($thumbImage)
                                                                            <img src="{{ $thumbImage }}"
                                                                                alt="{{ $option->value }}">
                                                                        @else
                                                                            <div class="color-box"
                                                                                style="background: {{ $option->hex_color ?? '#ccc' }}">
                                                                            </div>
                                                                        @endif
                                                                    </div>
                                                                    <span
                                                                        class="color-name">{{ $option->display_value ?? $option->value }}</span>
                                                                </label>
                                                            </div>
                                                        @elseif($isRAM || $isStorage)
                                                            {{-- RAM/Storage Buttons --}}
                                                            <button type="button" class="variant-btn-flipkart"
                                                                data-variant-type="{{ $type->name }}"
                                                                data-variant-value="{{ $option->value }}"
                                                                tabindex="{{ $tabindex++ }}">
                                                                {{ $option->display_value ?? $option->value }}
                                                            </button>
                                                        @else
                                                            {{-- Other variants --}}
                                                            <button type="button" class="variant-btn-flipkart"
                                                                data-variant-type="{{ $type->name }}"
                                                                data-variant-value="{{ $option->value }}"
                                                                tabindex="{{ $tabindex++ }}">
                                                                {{ $option->display_value ?? $option->value }}
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Product Buy Section -->
                                <div class="product-buy-section">
                                    <form action="{{ route('single-add-to-cart') }}" method="POST" id="addToCartForm">
                                        @csrf
                                        <input type="hidden" name="slug" value="{{ $product_detail->slug }}">
                                        <input type="hidden" name="variant_id" id="selectedVariantId" value="">
                                        <input type="hidden" name="quantity" id="quantityValue" value="1"
                                            data-min="1" data-max="1000">

                                        <!-- Stock Alert -->
                                        <div id="stockAlert" class="alert alert-danger d-none" role="alert">
                                            <i class="fa fa-exclamation-circle"></i>
                                            <span id="stockAlertMessage">This variant is currently out of stock</span>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="flipkart-action-buttons">
                                            <button type="submit" class="btn-flipkart-cart" id="addToCartBtn">
                                                <i class="fa fa-shopping-cart"></i> ADD TO CART
                                            </button>
                                            <button type="button" class="btn-flipkart-buy" id="buyNowBtn">
                                                <i class="fa fa-bolt"></i> BUY NOW
                                            </button>
                                            <!-- Notify Me (shown when variant is unavailable) -->
                                            <button type="button" class="btn-flipkart-notify" id="notifyMeBtn"
                                                style="display:none;">
                                                <i class="fa fa-bell"></i> NOTIFY ME
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Product Highlights -->
                                <div class="product-highlights">
                                    <h6 class="section-label">Highlights</h6>
                                    <ul class="highlights-list">
                                        <li>{{ $product_detail->summary }}</li>
                                        @if ($product_detail->cat_info)
                                            <li>Category: {{ $product_detail->cat_info['title'] }}@if ($product_detail->sub_cat_info)
                                                    > {{ $product_detail->sub_cat_info['title'] }}
                                                @endif
                                            </li>
                                        @endif
                                        <li>SKU: <span id="displaySku">{{ $product_detail->current_sku }}</span></li>
                                        <li>Stock: <span id="displayStock">
                                                @if ($product_detail->current_stock > 0)
                                                    <span class="text-success">{{ $product_detail->current_stock }} units
                                                        available</span>
                                                @else
                                                    <span class="text-danger">Out of Stock</span>
                                                @endif
                                            </span></li>
                                    </ul>
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
                                        <li class="nav-item"><a class="nav-link active" id="description-tab"
                                                data-toggle="tab" href="#description" role="tab"
                                                tabindex="44">Description</a></li>
                                        <li class="nav-item"><a class="nav-link" id="reviews-tab" data-toggle="tab"
                                                href="#reviews" role="tab" tabindex="45">Reviews</a></li>
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
                                                            <p tabindex="48">Your email address will not be published.
                                                                Required fields are marked <span
                                                                    class="text-danger">*</span></p>
                                                        </div>
                                                        <h4 tabindex="49">Your Rating <span class="text-danger">*</span>
                                                        </h4>
                                                        <div class="review-inner">
                                                            @auth
                                                                <form class="form review-form" method="post"
                                                                    action="{{ route('review.store', ['slug' => $product_detail->slug]) }}"
                                                                    id="reviewForm">
                                                                    @csrf
                                                                    <input type="hidden" name="slug"
                                                                        value="{{ $product_detail->slug }}">
                                                                    <div class="row">
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="rating_box">
                                                                                <div class="star-rating">
                                                                                    <div class="star-rating__wrap">
                                                                                        <!-- Stars in reverse order (5 to 1) for left-to-right fill -->
                                                                                        <input class="star-rating__input"
                                                                                            id="star-rating-5" type="radio"
                                                                                            name="rate" value="5"
                                                                                            tabindex="50">
                                                                                        <label
                                                                                            class="star-rating__ico fa fa-star"
                                                                                            for="star-rating-5"
                                                                                            title="5 out of 5 stars"></label>

                                                                                        <input class="star-rating__input"
                                                                                            id="star-rating-4" type="radio"
                                                                                            name="rate" value="4"
                                                                                            tabindex="51">
                                                                                        <label
                                                                                            class="star-rating__ico fa fa-star"
                                                                                            for="star-rating-4"
                                                                                            title="4 out of 5 stars"></label>

                                                                                        <input class="star-rating__input"
                                                                                            id="star-rating-3" type="radio"
                                                                                            name="rate" value="3"
                                                                                            tabindex="52">
                                                                                        <label
                                                                                            class="star-rating__ico fa fa-star"
                                                                                            for="star-rating-3"
                                                                                            title="3 out of 5 stars"></label>

                                                                                        <input class="star-rating__input"
                                                                                            id="star-rating-2" type="radio"
                                                                                            name="rate" value="2"
                                                                                            tabindex="53">
                                                                                        <label
                                                                                            class="star-rating__ico fa fa-star"
                                                                                            for="star-rating-2"
                                                                                            title="2 out of 5 stars"></label>

                                                                                        <input class="star-rating__input"
                                                                                            id="star-rating-1" type="radio"
                                                                                            name="rate" value="1"
                                                                                            tabindex="54">
                                                                                        <label
                                                                                            class="star-rating__ico fa fa-star"
                                                                                            for="star-rating-1"
                                                                                            title="1 out of 5 stars"></label>
                                                                                    </div>
                                                                                </div>
                                                                                @error('rate')
                                                                                    <span
                                                                                        class="text-danger d-block mt-2">{{ $message }}</span>
                                                                                @enderror
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="form-group">
                                                                                <label for="review-text" tabindex="55">Write
                                                                                    a review <span
                                                                                        class="text-danger">*</span></label>
                                                                                <textarea name="review" id="review-text" rows="6" placeholder="Share your experience with this product..."
                                                                                    tabindex="56" required></textarea>
                                                                                @error('review')
                                                                                    <span
                                                                                        class="text-danger">{{ $message }}</span>
                                                                                @enderror
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="form-group button5">
                                                                                <button type="submit" class="btn btn-primary"
                                                                                    id="submitReviewBtn" tabindex="57">Submit
                                                                                    Review</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            @else
                                                                <p class="text-center p-5" tabindex="58">
                                                                    You need to <a href="{{ route('login.form') }}"
                                                                        style="color:rgb(54, 54, 204)"
                                                                        tabindex="59">Login</a> OR <a style="color:blue"
                                                                        href="{{ route('register.form') }}"
                                                                        tabindex="60">Register</a>
                                                                </p>
                                                            @endauth
                                                        </div>
                                                    </div>

                                                    <div class="ratting-main" id="reviewsContainer">
                                                        <div class="avg-ratting" id="avgRatingSection">
                                                            <h4 tabindex="61">
                                                                {{ ceil($product_detail->getReview->avg('rate')) }}
                                                                <span>(Overall)</span>
                                                            </h4>
                                                            <span tabindex="62">Based on
                                                                {{ $product_detail->getReview->count() }} Comments</span>
                                                        </div>
                                                        @forelse($product_detail['getReview'] as $index => $data)
                                                            <div class="single-rating">
                                                                <div class="rating-author">
                                                                    @if ($data->user_info['photo'])
                                                                        <img src="{{ $data->user_info['photo'] ?? asset('backend/img/avatar.webp') }}"
                                                                            alt="{{ $data->user_info['name'] }}"
                                                                            loading="lazy">
                                                                    @else
                                                                        <img src="{{ asset('backend/img/avatar.webp') }}"
                                                                            alt="Default Avatar" loading="lazy">
                                                                    @endif
                                                                </div>
                                                                <div class="rating-des">
                                                                    <h6 tabindex="{{ 64 + $index * 4 }}">
                                                                        {{ $data->user_info['name'] }}</h6>
                                                                    <div class="ratings">
                                                                        <ul class="rating">
                                                                            @for ($i = 1; $i <= 5; $i++)
                                                                                @if ($data->rate >= $i)
                                                                                    <li><i class="fa fa-star"></i></li>
                                                                                @else
                                                                                    <li><i class="fa fa-star-o"></i></li>
                                                                                @endif
                                                                            @endfor
                                                                        </ul>
                                                                        <div class="rate-count"
                                                                            tabindex="{{ 65 + $index * 4 }}">
                                                                            ({{ $data->rate }})
                                                                        </div>
                                                                    </div>
                                                                    <p tabindex="{{ 66 + $index * 4 }}">
                                                                        {{ $data->review }}</p>
                                                                    <small class="text-muted"
                                                                        style="font-size: 12px;">{{ $data->created_at->format('M d, Y') }}</small>
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

    <x-similar-products-carousel :products="$related_products" title="Similar Products" carousel-id="relatedCarousel"
        no-products-message="No related products found." starting-tab-index="47" default-background-color="#ff6b35"
        default-text-color="white" default-auto-scroll-speed="200" default-scroll-amount="3" default-shimmer="true" />

    @if (isset($recent_products) && $recent_products->count() > 0)
        <x-recently-viewed-carousel :products="$recent_products" title="Recently Viewed Products" carousel-id="customRecentCarousel"
            no-products-message="No recent views yet!" starting-tab-index="100" default-background-color="#28a745"
            default-text-color="white" default-auto-scroll-speed="100" default-scroll-amount="10"
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
            const serverOrderJson = '{{ addslashes(json_encode($variantTypes->pluck('name')->toArray() ?? [])) }}';
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
        /* ========== FLIPKART-STYLE PRODUCT DETAIL PAGE ========== */

        /* Product Title */
        .product-title {
            font-size: 18px;
            font-weight: 400;
            color: #212121;
            margin-bottom: 10px;
            line-height: 1.4;
        }

        /* Rating Section - Flipkart Style */
        .rating-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            margin-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }

        .rating-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .rating-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #388e3c;
            color: white;
            padding: 4px 10px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 500;
        }

        .rating-badge .rating-value {
            font-weight: 600;
        }

        .rating-badge .fa-star {
            font-size: 10px;
        }

        .rating-details {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #878787;
        }

        .rating-count {
            font-weight: 500;
        }

        .review-separator {
            color: #878787;
        }

        .review-link {
            color: #878787;
            text-decoration: none;
            font-weight: 500;
        }

        .review-link:hover {
            color: #2874f0;
        }

        /* Special Price Label */
        .special-price-label {
            margin-bottom: 8px;
        }

        .special-price-label .label-text {
            color: #388e3c;
            font-size: 13px;
            font-weight: 500;
        }

        /* Price Section - Flipkart Style */
        .price-container {
            margin-bottom: 16px;
        }

        .price-row {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }

        .price-current {
            font-size: 28px;
            font-weight: 500;
            color: #212121;
        }

        .price-original {
            font-size: 16px;
            color: #878787;
            text-decoration: line-through;
        }

        .price-discount {
            font-size: 14px;
            color: #388e3c;
            font-weight: 500;
        }

        .extra-discount-label {
            font-size: 12px;
            color: #388e3c;
        }

        /* Offers Section */
        .offers-section {
            background: #fff;
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 20px;
        }

        .offers-title {
            font-size: 14px;
            font-weight: 500;
            color: #212121;
            margin-bottom: 12px;
        }

        .offer-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .offer-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13px;
            color: #212121;
        }

        .offer-icon {
            color: #388e3c;
            font-size: 14px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .offer-text {
            flex: 1;
            line-height: 1.5;
        }

        .offer-text strong {
            font-weight: 600;
        }

        .terms-link {
            color: #2874f0;
            text-decoration: none;
            font-weight: 500;
            white-space: nowrap;
            font-size: 12px;
        }

        .terms-link:hover {
            text-decoration: underline;
        }

        /* Delivery Section */
        .delivery-section {
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 20px;
        }

        .section-label {
            font-size: 14px;
            font-weight: 500;
            color: #878787;
            margin-bottom: 12px;
            text-transform: capitalize;
        }

        .delivery-input-group {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .delivery-pincode-input {
            flex: 1;
            max-width: 200px;
            padding: 8px 12px;
            border: 1px solid #c2c2c2;
            border-radius: 2px;
            font-size: 14px;
            outline: none;
        }

        .delivery-pincode-input:focus {
            border-color: #2874f0;
        }

        .check-btn {
            padding: 8px 20px;
            background: #fff;
            border: 1px solid #2874f0;
            color: #2874f0;
            border-radius: 2px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .check-btn:hover {
            background: #2874f0;
            color: #fff;
        }

        .delivery-info {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #212121;
        }

        .delivery-info .fa-truck {
            color: #388e3c;
            font-size: 16px;
        }

        /* Variant Selection - Flipkart Style */
        .variant-selection-container {
            padding: 16px 0;
            border-bottom: 1px solid #f0f0f0;
            margin-bottom: 20px;
        }

        .variant-group {
            margin-bottom: 16px;
        }

        .variant-label {
            font-size: 14px;
            font-weight: 500;
            color: #878787;
            margin-bottom: 10px;
            text-transform: capitalize;
        }

        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: flex-start;
        }

        /* Color Variant with Image - Flipkart Style */
        .color-variant-item {
            position: relative;
        }

        .color-variant-radio {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .color-variant-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .color-image-box {
            width: 56px;
            height: 56px;
            border: 1.5px solid #c2c2c2;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            transition: all 0.2s;
        }

        .color-image-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .color-image-box .color-box {
            width: 100%;
            height: 100%;
        }

        .color-name {
            font-size: 12px;
            color: #212121;
            text-align: center;
            max-width: 70px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .color-variant-radio:checked+.color-variant-label .color-image-box {
            border-color: #2874f0;
            border-width: 2px;
            box-shadow: 0 0 0 1px #2874f0;
        }

        .color-variant-radio:checked+.color-variant-label .color-name {
            color: #2874f0;
            font-weight: 600;
        }

        .color-variant-label:hover .color-image-box {
            border-color: #2874f0;
        }

        /* Disabled Color Variant */
        .color-variant-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .color-variant-item.disabled .color-variant-label {
            cursor: not-allowed;
        }

        /* RAM/Storage Buttons - Flipkart Style */
        .variant-btn-flipkart {
            min-width: 80px;
            padding: 10px 18px;
            background: #fff;
            border: 1.5px solid #c2c2c2;
            border-radius: 50px;
            color: #212121;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            outline: none;
        }

        .variant-btn-flipkart:hover:not(.disabled) {
            border-color: #2874f0;
            color: #2874f0;
        }

        .variant-btn-flipkart.active {
            border-color: #2874f0;
            color: #2874f0;
            background: #e8f0fe;
            font-weight: 600;
        }

        .variant-btn-flipkart.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            text-decoration: line-through;
        }

        /* Action Buttons - Flipkart Style */
        .product-buy-section {
            padding: 20px 0;
        }

        .flipkart-action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 20px;
        }

        .btn-flipkart-cart,
        .btn-flipkart-buy {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 2px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-flipkart-cart {
            background: #ff9f00;
            color: #fff;
            box-shadow: 0 2px 4px rgba(255, 159, 0, 0.3);
        }

        .btn-flipkart-cart:hover {
            background: #e68a00;
            box-shadow: 0 4px 8px rgba(255, 159, 0, 0.4);
            transform: translateY(-2px);
        }

        .btn-flipkart-buy {
            background: #fb641b;
            color: #fff;
            box-shadow: 0 2px 4px rgba(251, 100, 27, 0.3);
        }

        .btn-flipkart-buy:hover {
            background: #e25513;
            box-shadow: 0 4px 8px rgba(251, 100, 27, 0.4);
            transform: translateY(-2px);
        }

        /* Notify Me Button - Flipkart Style */
        .btn-flipkart-notify {
            flex: 1;
            padding: 16px 24px;
            border: none;
            border-radius: 2px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #2874f0;
            color: #fff;
            box-shadow: 0 2px 4px rgba(40, 116, 240, 0.3);
        }

        .btn-flipkart-notify:hover {
            background: #0f5ed7;
            box-shadow: 0 4px 8px rgba(40, 116, 240, 0.4);
            transform: translateY(-2px);
        }

        .btn-flipkart-cart i,
        .btn-flipkart-buy i {
            font-size: 18px;
        }

        /* Wishlist Button - Rounded Style */
        .btn-wishlist-rounded {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #fff;
            border: 1px solid #c2c2c2;
            border-radius: 50%;
            color: #878787;
            font-size: 18px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            flex-shrink: 0;
        }

        .btn-wishlist-rounded:hover {
            background: #fff;
            border-color: #ff3e6c;
            color: #ff3e6c;
            text-decoration: none;
            transform: scale(1.1);
        }

        .btn-wishlist-rounded i {
            transition: all 0.2s;
        }

        /* Product Highlights */
        .product-highlights {
            padding: 16px 0;
            border-top: 1px solid #f0f0f0;
            margin-top: 20px;
        }

        .highlights-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .highlights-list li {
            padding: 8px 0;
            font-size: 14px;
            color: #212121;
            line-height: 1.6;
            position: relative;
            padding-left: 20px;
        }

        .highlights-list li::before {
            content: "•";
            position: absolute;
            left: 0;
            color: #878787;
            font-weight: bold;
        }

        /* Stock Alert */
        #stockAlert {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 12px 16px;
            color: #856404;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        #stockAlert.d-none {
            display: none !important;
        }

        /* Product Gallery */
        .main-image-container {
            position: relative;
            background: #fafafa;
            padding: 20px;
            border-radius: 4px;
            min-height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .main-image {
            max-height: 500px;
            max-width: 100%;
            object-fit: contain;
            transition: opacity 0.3s;
        }

        .thumbnail-carousel {
            overflow-x: auto;
            scrollbar-width: thin;
        }

        .thumbnail-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            border: 1.5px solid #c2c2c2;
            cursor: pointer;
            transition: all 0.2s;
        }

        .thumbnail-image:hover {
            border-color: #2874f0;
        }

        .thumbnail-image.active {
            border-color: #2874f0;
            border-width: 2px;
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
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 5px;
        }

        .star-rating__input {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
            pointer-events: none;
        }

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

        .star-rating__ico:hover,
        .star-rating__ico:hover~.star-rating__ico {
            color: #ffd700;
            text-shadow: 0 2px 4px rgba(255, 215, 0, 0.4);
        }

        .star-rating__input:checked~.star-rating__ico {
            color: #ffd700;
            text-shadow: 0 2px 4px rgba(255, 215, 0, 0.4);
        }

        /* Review Section */
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

        /* Responsive Styles */
        @media (max-width: 991px) {
            .product-title {
                font-size: 16px;
            }

            .price-current {
                font-size: 24px;
            }

            .flipkart-action-buttons {
                flex-direction: column;
            }

            .btn-flipkart-cart,
            .btn-flipkart-buy {
                width: 100%;
            }
        }

        @media (max-width: 767px) {
            .product-title {
                font-size: 15px;
            }

            .price-current {
                font-size: 22px;
            }

            .price-original {
                font-size: 14px;
            }

            .offers-section {
                padding: 12px 0;
            }

            .offer-item {
                font-size: 12px;
            }

            .variant-btn-flipkart {
                min-width: 70px;
                padding: 8px 14px;
                font-size: 13px;
            }

            .color-image-box {
                width: 48px;
                height: 48px;
            }

            .btn-flipkart-cart,
            .btn-flipkart-buy {
                padding: 14px 20px;
                font-size: 14px;
            }

            .main-image-container {
                min-height: 300px;
            }

            .thumbnail-image {
                width: 50px;
                height: 50px;
            }
        }

        /* Hide old quantity selector for Flipkart design */
        .quantity-wrapper {
            display: none !important;
        }

        /* Remove old unused styles */
        .product-actions-row,
        .action-buttons,
        .btn-add-cart {
            display: none !important;
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

            let selectedVariantOptions = {};
            let currentVariant = null;
            let isUpdating = false;
            let lastChangedType = null;

            // Normalize functions
            function normalizeVal(v) {
                if (v === null || v === undefined) return '';
                return String(v).trim().toLowerCase();
            }

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
            const qtyDisplay = document.getElementById('quantity');
            const qtyHidden = document.getElementById('quantityValue');
            const minusBtn = document.querySelector('.qty-minus');
            const plusBtn = document.querySelector('.qty-plus');
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
            const buyNowBtn = document.querySelector('.btn-flipkart-buy');
            const notifyMeBtn = document.getElementById('notifyMeBtn');
            const quantitySection = document.getElementById('quantitySection');
            const variantIdInput = document.getElementById('selectedVariantId');
            const addToCartForm = document.getElementById('addToCartForm');
            const wishlistBtn = document.getElementById('wishlistBtn');

            /* ===============================
                RESET BUTTON STATES ON PAGE LOAD
            ============================== */
            // Ensure buttons are enabled on page load
            if (addToCartBtn) {
                addToCartBtn.disabled = false;
            }

            if (buyNowBtn) {
                buyNowBtn.disabled = false;
            }

            /* ===============================
                QUANTITY CONTROL FUNCTIONS
            ============================== */
            function updateQuantityDisplay() {
                if (!qtyDisplay || !qtyHidden) return;
                qtyDisplay.value = qtyHidden.value;
                updateMinusState();
            }

            function updateQuantityMax() {
                if (!qtyHidden) return;

                let max = 1000; // Default max

                if (hasVariants && currentVariant) {
                    max = parseInt(currentVariant.stock) || 0;
                } else if (!hasVariants) {
                    // For non-variant products, get stock from display
                    const stockBadge = displayStock?.querySelector('.badge');
                    const stockText = stockBadge?.textContent?.trim() || '0';
                    max = parseInt(stockText) || 0;
                }

                qtyHidden.setAttribute('data-max', max);

                const currentQty = parseInt(qtyHidden.value) || 1;
                if (currentQty > max && max > 0) {
                    qtyHidden.value = max;
                    updateQuantityDisplay();
                }
            }

            function updateMinusState() {
                if (!minusBtn || !qtyHidden) return;
                const min = parseInt(qtyHidden.getAttribute('data-min')) || 1;
                const currentValue = parseInt(qtyHidden.value) || 1;
                minusBtn.disabled = currentValue <= min;
            }

            // Plus button handler
            plusBtn?.addEventListener('click', () => {
                const max = parseInt(qtyHidden.getAttribute('data-max')) || 1000;
                const val = parseInt(qtyHidden.value) || 1;
                if (val < max) {
                    qtyHidden.value = val + 1;
                    updateQuantityDisplay();
                }
            });

            // Minus button handler
            minusBtn?.addEventListener('click', () => {
                const min = parseInt(qtyHidden.getAttribute('data-min')) || 1;
                const val = parseInt(qtyHidden.value) || 1;
                if (val > min) {
                    qtyHidden.value = val - 1;
                    updateQuantityDisplay();
                }
            });

            // Display input click handler
            qtyDisplay?.addEventListener('click', function() {
                this.select();
            });

            /* ===============================
                VARIANT SELECTION HANDLERS
            ============================== */
            function handleVariantSelection(e) {
                if (isUpdating) return;
                const target = e.currentTarget;
                const variantType = target.dataset.variantType;
                const variantValue = target.dataset.variantValue;

                // Set the selection first
                lastChangedType = variantType.toLowerCase();
                selectedVariantOptions[variantType] = variantValue;

                // Update UI for Flipkart-style buttons
                document.querySelectorAll(`[data-variant-type="${variantType}"].variant-btn-flipkart`).forEach(
                    b => {
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
                    const swatch = e.target.closest('.color-variant-item');
                    if (swatch) input = swatch.querySelector('input[type="radio"]');
                }

                if (!input) return;

                input.disabled = false;
                const variantType = input.dataset.variantType || input.name;
                const variantValue = input.value;

                input.checked = true;
                lastChangedType = variantType.toLowerCase();
                selectedVariantOptions[variantType] = variantValue;

                // Clear selections for other variant types (Storage, RAM) when color changes
                // This allows auto-selection of the cheapest variant with the new color
                const allVariantTypes = [...new Set(variants.flatMap(v => {
                    const vals = getVariantValuesNormalized(v);
                    return Object.keys(vals);
                }))];

                allVariantTypes.forEach(type => {
                    if (type !== 'color') {
                        delete selectedVariantOptions[type];
                    }
                });

                // Update UI for color variants - clear all first
                document.querySelectorAll(`input[name="${variantType}"]`).forEach(inp => {
                    const colorItem = inp.closest('.color-variant-item');
                    if (colorItem) {
                        const label = colorItem.querySelector('.color-variant-label');
                        if (label) {
                            const imageBox = label.querySelector('.color-image-box');
                            if (imageBox) {
                                imageBox.style.borderColor = '#c2c2c2';
                                imageBox.style.borderWidth = '1.5px';
                            }
                            const colorName = label.querySelector('.color-name');
                            if (colorName) {
                                colorName.style.color = '#212121';
                                colorName.style.fontWeight = '400';
                            }
                        }
                    }

                    const label = inp.nextElementSibling;
                    if (label) label.classList.remove('active');
                });

                // Set selected color as active
                const colorItem = input.closest('.color-variant-item');
                if (colorItem) {
                    const label = colorItem.querySelector('.color-variant-label');
                    if (label) {
                        const imageBox = label.querySelector('.color-image-box');
                        if (imageBox) {
                            imageBox.style.borderColor = '#2874f0';
                            imageBox.style.borderWidth = '2px';
                        }
                        const colorName = label.querySelector('.color-name');
                        if (colorName) {
                            colorName.style.color = '#2874f0';
                            colorName.style.fontWeight = '600';
                        }
                    }
                }

                const selectedLabel = input.nextElementSibling;
                if (selectedLabel) selectedLabel.classList.add('active');

                updateVariantWithLoading();
            }

            /* ===============================
                UPDATE AVAILABLE OPTIONS
                NOTE: ALL buttons are now enabled. Availability checking happens on click.
            ============================== */
            function updateAvailableOptions() {
                const allVariantTypes = [...new Set(variants.flatMap(v => {
                    const vals = getVariantValuesNormalized(v);
                    return Object.keys(vals);
                }))];

                // Enable ALL buttons - no disabling based on availability
                allVariantTypes.forEach(variantTypeLower => {
                    const typeButtons = Array.from(document.querySelectorAll(
                            '.variant-btn-flipkart, .variant-option-btn'))
                        .filter(btn => (btn.dataset.variantType || '').toLowerCase() === variantTypeLower);
                    const typeInputs = Array.from(document.querySelectorAll(
                            '#variantContainer input[type="radio"]'))
                        .filter(inp => ((inp.dataset.variantType || inp.name || '').toLowerCase() ===
                            variantTypeLower));

                    [...typeButtons, ...typeInputs].forEach(element => {
                        // ALWAYS ENABLE ALL BUTTONS
                        if (element.tagName === 'INPUT') {
                            element.disabled = false;
                            const colorItem = element.closest('.color-variant-item');
                            if (colorItem) {
                                colorItem.classList.remove('disabled');
                            }
                        } else {
                            element.classList.remove('disabled');
                            element.removeAttribute('disabled');
                        }
                    });
                });
            }

            /* ===============================
                PRUNE INVALID SELECTIONS (NEW)
               If the current combination the user has selected does NOT map to any
               in-stock active variant, progressively drop the most recently changed
               (or other non-color) attributes until we reach a valid partial state.
               This prevents an impossible combination (e.g. Color=Green, Storage=128GB,
               RAM=4GB when that trio doesn't exist) from staying visually "selected".
            ============================== */
            function pruneInvalidSelections() {
                return false;
            }

            /* ===============================
                AUTO-SELECT MATCHING VARIANT
            ============================== */
            function autoSelectMatchingVariant() {
                const normSelected = normalizeOptions(selectedVariantOptions);
                if (!normSelected.color) {
                    return null;
                }

                const selectedKeys = Object.keys(normSelected).filter(Boolean);
                const onlyColorSelected = selectedKeys.length === 1 && selectedKeys[0] === 'color';
                const triggeredByColor = lastChangedType === 'color';

                if (!onlyColorSelected && !triggeredByColor) {
                    return null;
                }
                const colorMatches = variants.filter(v => {
                    if (v.status !== 'active') return false;
                    const normVals = getVariantValuesNormalized(v);
                    return normVals.color === normSelected.color;
                });

                if (colorMatches.length === 0) {
                    return null;
                }

                const inStock = colorMatches.filter(v => parseInt(v.stock || 0) > 0);
                const ordered = (inStock.length > 0 ? inStock : colorMatches).sort((a, b) => {
                    const priceA = parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100);
                    const priceB = parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100);
                    return priceA - priceB;
                });

                const selectedVariant = ordered[0];
                const vals = typeof selectedVariant.variant_values === 'string' ?
                    JSON.parse(selectedVariant.variant_values) :
                    selectedVariant.variant_values;

                Object.entries(vals).forEach(([type, value]) => {
                    const typeLower = type.toLowerCase();
                    const valueLower = normalizeVal(value);

                    selectedVariantOptions[type] = value;

                    document.querySelectorAll('.variant-btn-flipkart').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower) {
                            btn.classList.remove('active');
                        }
                    });

                    document.querySelectorAll('.variant-option-btn').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower) {
                            btn.classList.remove('active');
                        }
                    });

                    document.querySelectorAll('.variant-btn-flipkart').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower &&
                            normalizeVal(btn.dataset.variantValue) === valueLower) {
                            btn.classList.add('active');
                            btn.classList.remove('disabled');
                            btn.removeAttribute('disabled');
                        }
                    });

                    document.querySelectorAll('.variant-option-btn').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower &&
                            normalizeVal(btn.dataset.variantValue) === valueLower) {
                            btn.classList.add('active');
                            btn.classList.remove('disabled');
                            btn.removeAttribute('disabled');
                        }
                    });

                    document.querySelectorAll('input[type="radio"]').forEach(inp => {
                        const dt = ((inp.dataset.variantType || inp.name) || '').toLowerCase();
                        if (dt === typeLower) {
                            inp.checked = normalizeVal(inp.value) === valueLower;

                            const colorItem = inp.closest('.color-variant-item');
                            if (colorItem) {
                                const label = colorItem.querySelector('.color-variant-label');
                                if (label) {
                                    const imageBox = label.querySelector('.color-image-box');
                                    const colorName = label.querySelector('.color-name');
                                    if (imageBox) {
                                        imageBox.style.borderColor = inp.checked ? '#2874f0' :
                                            '#c2c2c2';
                                        imageBox.style.borderWidth = inp.checked ? '2px' : '1.5px';
                                    }
                                    if (colorName) {
                                        colorName.style.color = inp.checked ? '#2874f0' : '#212121';
                                        colorName.style.fontWeight = inp.checked ? '600' : '400';
                                    }
                                }
                            }

                            const sw = inp.closest('.color-swatch');
                            if (sw) {
                                sw.querySelector('.color-swatch-checkmark')?.classList.toggle(
                                    'd-none', !inp.checked);
                                sw.querySelector('.color-swatch-label')?.classList.toggle('active',
                                    inp.checked);
                            }
                        }
                    });
                });

                return selectedVariant;
            }

            /* ===============================
                MAIN UPDATE FUNCTION
            ============================== */
            async function updateVariantWithLoading() {
                isUpdating = true;
                showLoadingStates();

                updateAvailableOptions();
                // New: ensure we are not holding onto an impossible combination
                const pruned = pruneInvalidSelections();

                const autoSelected = autoSelectMatchingVariant();

                let matchingVariant = variants.find(v => {
                    if (v.status !== 'active') return false;
                    const normVals = getVariantValuesNormalized(v);
                    const selectedKeys = Object.keys(normalizeOptions(selectedVariantOptions));
                    return selectedKeys.every(key =>
                        normVals[key] === normalizeOptions(selectedVariantOptions)[key]
                    );
                });

                if (!matchingVariant && pruned) {
                    // After pruning we may now have only color selected; attempt auto-selection again
                    const reAuto = autoSelectMatchingVariant();
                    if (reAuto) {
                        matchingVariant = reAuto;
                    }
                }

                if (matchingVariant) {
                    currentVariant = matchingVariant;

                    // CRITICAL: Sync selectedVariantOptions with the actual variant values
                    // This ensures UI shows what's actually selected after pruning
                    const vals = typeof matchingVariant.variant_values === 'string' ?
                        JSON.parse(matchingVariant.variant_values) : matchingVariant.variant_values;
                    Object.entries(vals).forEach(([type, value]) => {
                        selectedVariantOptions[type] = value;
                    });

                    applyVariantToUI(matchingVariant);
                    updateProductDisplay(matchingVariant);
                } else if (autoSelected) {
                    currentVariant = autoSelected;
                    applyVariantToUI(autoSelected);
                    updateProductDisplay(autoSelected);
                } else {
                    currentVariant = null;
                    handleNoVariantFound();
                }

                updateWishlistHref();
                hideLoadingStates();
                isUpdating = false;
            }

            /* ===============================
                UPDATE WISHLIST HREF
            ============================== */
            function updateWishlistHref() {
                if (!wishlistBtn) return;

                let currentHref = wishlistBtn.getAttribute('href');

                if (hasVariants && currentVariant) {
                    const separator = currentHref.includes('?') ? '&' : '?';
                    const newHref = currentHref.includes('variant_id=') ?
                        currentHref.replace(/variant_id=\d+/, `variant_id=${currentVariant.id}`) :
                        `${currentHref}${separator}variant_id=${currentVariant.id}`;
                    wishlistBtn.href = newHref;
                } else {
                    const baseHref = currentHref.split('?')[0];
                    wishlistBtn.href = baseHref;
                }
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
                            originalPriceEl.innerHTML = '<s class="text-muted">$' + price.toFixed(2) +
                                '</s>';
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
                    displayStock.innerHTML = stock > 0 ?
                        `<span class="badge badge-success">${stock}</span>` :
                        '<span class="badge badge-danger">Out of Stock</span>';
                }

                if (stockAlert) {
                    stockAlert.classList.add('d-none');
                }

                if (quantitySection) {
                    quantitySection.style.display = 'block';
                    quantitySection.classList.remove('hidden');
                }

                if (addToCartBtn) {
                    addToCartBtn.style.display = 'inline-flex';
                    addToCartBtn.disabled = stock <= 0;
                    addToCartBtn.innerHTML = stock > 0 ?
                        '<i class="fa fa-shopping-cart"></i> ADD TO CART' :
                        'OUT OF STOCK';
                }

                if (wishlistBtn) {
                    wishlistBtn.style.display = 'inline-block';
                }

                updateQuantityMax();

                if (variantIdInput) {
                    variantIdInput.value = variant.id || '';
                }

                updateVariantImages(variant);
                updateCtasState(variant);
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
                    thumbImg.style.cssText =
                        `width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid ${index === 0 ? '#2874f0' : '#eee'}; transition: all 0.2s ease;`;
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
                const vals = typeof variant.variant_values === 'string' ?
                    JSON.parse(variant.variant_values) :
                    variant.variant_values;

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
                                sw.querySelector('.color-swatch-checkmark')?.classList.add(
                                    'd-none');
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
                                sw.querySelector('.color-swatch-checkmark')?.classList.remove(
                                    'd-none');
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
                const vals = typeof variant.variant_values === 'string' ?
                    JSON.parse(variant.variant_values) :
                    variant.variant_values;

                // Clear all selections first
                document.querySelectorAll('.variant-btn-flipkart').forEach(btn => {
                    btn.classList.remove('active');
                });

                document.querySelectorAll('input[type="radio"]').forEach(inp => {
                    inp.checked = false;
                });

                // Apply the cheapest variant selections
                Object.entries(vals).forEach(([type, value]) => {
                    selectedVariantOptions[type] = value;

                    const typeLower = type.toLowerCase();
                    const valueLower = normalizeVal(value);

                    // Handle Flipkart-style buttons
                    document.querySelectorAll('.variant-btn-flipkart').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower &&
                            normalizeVal(btn.dataset.variantValue) === valueLower) {
                            btn.classList.add('active');
                        }
                    });

                    // Handle old-style buttons
                    document.querySelectorAll('.variant-option-btn').forEach(btn => {
                        if ((btn.dataset.variantType || '').toLowerCase() === typeLower &&
                            normalizeVal(btn.dataset.variantValue) === valueLower) {
                            btn.classList.add('active');
                        }
                    });

                    // Handle radio inputs (color variants)
                    document.querySelectorAll('input[type="radio"]').forEach(inp => {
                        const dt = ((inp.dataset.variantType || inp.name) || '').toLowerCase();
                        if (dt === typeLower && normalizeVal(inp.value) === valueLower) {
                            inp.checked = true;

                            // Update visual state for color variant items
                            const colorItem = inp.closest('.color-variant-item');
                            if (colorItem) {
                                const label = colorItem.querySelector('.color-variant-label');
                                if (label) {
                                    const imageBox = label.querySelector('.color-image-box');
                                    if (imageBox) {
                                        imageBox.style.borderColor = '#2874f0';
                                        imageBox.style.borderWidth = '2px';
                                    }
                                    const colorName = label.querySelector('.color-name');
                                    if (colorName) {
                                        colorName.style.color = '#2874f0';
                                        colorName.style.fontWeight = '600';
                                    }
                                }
                            }

                            // Old color swatch support
                            const sw = inp.closest('.color-swatch');
                            if (sw) {
                                sw.querySelector('.color-swatch-checkmark')?.classList.remove(
                                    'd-none');
                                sw.querySelector('.color-swatch-label')?.classList.add('active');
                            }
                        }
                    });
                });

                currentVariant = variant;
                if (variantIdInput) variantIdInput.value = variant.id || '';

                // Update the display immediately without calling updateVariantWithLoading to avoid recursion
                updateProductDisplay(variant);
                updateAvailableOptions();
                updateWishlistHref();
            }

            /* ===============================
                HANDLE NO VARIANT FOUND
            ============================== */
            function handleNoVariantFound() {
                // Show user-friendly alert
                const currentSelections = Object.entries(selectedVariantOptions)
                    .map(([k, v]) => v)
                    .join(' + ');

                if (currentSelections) {
                    showAlert(
                        `⚠️ The selected variant (${currentSelections}) is not available right now. Please choose a different combination.`,
                        'warning'
                    );
                }

                if (stockAlert) {
                    stockAlert.classList.remove('d-none');
                    const msg = document.getElementById('stockAlertMessage');
                    if (msg) msg.textContent = 'Variant not available. Please select different options.';
                }

                if (displayStock) {
                    displayStock.innerHTML = '<span class="badge badge-danger">Variant not available</span>';
                }

                if (quantitySection) {
                    quantitySection.style.display = 'none';
                }
                if (addToCartBtn) {
                    addToCartBtn.style.display = 'none';
                }

                if (buyNowBtn) {
                    buyNowBtn.style.display = 'none';
                }

                if (notifyMeBtn) {
                    notifyMeBtn.style.display = 'inline-flex';
                }

                if (wishlistBtn) {
                    wishlistBtn.style.display = 'none';
                }

                if (variantIdInput) {
                    variantIdInput.value = '';
                }

                updateCtasState(null);
                updateWishlistHref();
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
                CTA TOGGLE (BUY NOW <-> NOTIFY)
            ============================== */
            function updateCtasState(variantOrNull) {
                const isAvailable = (() => {
                    if (!variantOrNull) {
                        return false;
                    }
                    const stock = parseInt(variantOrNull.stock || 0);
                    const isActive = variantOrNull.status === 'active';
                    const hasStock = stock > 0;
                    const available = isActive && hasStock;

                    return available;
                })();

                if (addToCartBtn) {
                    if (isAvailable) {
                        addToCartBtn.style.display = 'inline-flex';
                        addToCartBtn.disabled = false;
                        addToCartBtn.innerHTML = '<i class="fa fa-shopping-cart"></i> ADD TO CART';
                    } else {
                        addToCartBtn.style.display = 'none';
                    }
                }

                if (buyNowBtn && notifyMeBtn) {
                    if (isAvailable) {
                        buyNowBtn.style.display = 'inline-flex';
                        buyNowBtn.disabled = false;
                        notifyMeBtn.style.display = 'none';
                    } else {
                        buyNowBtn.style.display = 'none';
                        notifyMeBtn.style.display = 'inline-flex';
                    }
                }
            }

            /* ===============================
                FORM SUBMISSION - FIXED
            ============================== */
            if (addToCartForm) {
                addToCartForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    // Validate variant selection for products with variants (only basic validation)
                    if (hasVariants && !currentVariant) {
                        showAlert('Please select a valid product variant.', 'error');
                        if (stockAlert) {
                            stockAlert.classList.remove('d-none');
                            document.getElementById('stockAlertMessage').textContent =
                                'Please select a valid product variant.';
                        }
                        return false;
                    }

                    // Set variant_id properly
                    if (hasVariants && currentVariant) {
                        variantIdInput.value = currentVariant.id;
                    } else if (!hasVariants) {
                        variantIdInput.value = '';
                    }

                    // Create FormData
                    const formData = new FormData(addToCartForm);

                    // Ensure quantity is from hidden input
                    const quantity = parseInt(qtyHidden?.value || 1);
                    formData.set('quantity', quantity);

                    // Submit via fetch
                    fetch(addToCartForm.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin'
                        })
                        .then(response => {
                            // Handle 401 Unauthorized - redirect to login immediately
                            if (response.status === 401) {
                                // Redirect to login page
                                const loginUrl = '{{ route('login.form') }}';
                                const returnUrl = encodeURIComponent(window.location.href);
                                window.location.href = loginUrl + '?redirect=' + returnUrl;
                                return Promise.reject('redirecting');
                            }

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Show the actual message from server (handles "updated" vs "added")
                                showAlert(data.message || 'Product added to cart!', 'success');

                                // Update cart count
                                updateCartCount();

                                // Reset quantity to 1 after successful add
                                qtyHidden.value = 1;
                                updateQuantityDisplay();

                                // Optional: Show mini cart preview or scroll to top
                                // window.scrollTo({ top: 0, behavior: 'smooth' });
                            } else {
                                showAlert(data.message || 'Failed to add to cart', 'error');
                            }
                        })
                        .catch(error => {
                            // Don't show error if we're redirecting to login
                            if (error !== 'redirecting') {
                                showAlert(error.message || 'An error occurred. Please try again.',
                                    'error');
                            }
                        })
                        .finally(() => {
                            // Update button state based on current stock
                            let currentStock = 0;

                            if (hasVariants && currentVariant) {
                                currentStock = parseInt(currentVariant.stock) || 0;
                            } else if (!hasVariants) {
                                // Get stock from the display element
                                const stockBadge = displayStock?.querySelector(
                                    '.badge, .text-success, .text-danger');
                                const stockText = stockBadge?.textContent?.trim() || '0';
                                const stockMatch = stockText.match(/\d+/);
                                currentStock = stockMatch ? parseInt(stockMatch[0]) : 0;
                            }

                            addToCartBtn.disabled = currentStock <= 0;
                        });
                });
            }

            /* ===============================
                BUY NOW BUTTON HANDLER
            ============================== */
            if (buyNowBtn) {
                buyNowBtn.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Validate variant selection for products with variants (only basic validation)
                    if (hasVariants && !currentVariant) {
                        showAlert('Please select a valid product variant.', 'error');
                        if (stockAlert) {
                            stockAlert.classList.remove('d-none');
                            document.getElementById('stockAlertMessage').textContent =
                                'Please select a valid product variant.';
                        }
                        return false;
                    }

                    // Set variant_id properly
                    if (hasVariants && currentVariant) {
                        variantIdInput.value = currentVariant.id;
                    } else if (!hasVariants) {
                        variantIdInput.value = '';
                    }

                    // Create FormData from the form
                    const quantity = parseInt(qtyHidden?.value || 1);
                    const formData = new FormData(addToCartForm);
                    formData.set('quantity', quantity);

                    // Submit via fetch to add to cart first
                    fetch(addToCartForm.action, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin'
                        })
                        .then(response => {
                            // Handle 401 Unauthorized - redirect to login immediately
                            if (response.status === 401) {
                                // Redirect to login page
                                const loginUrl = '{{ route('login.form') }}';
                                const returnUrl = encodeURIComponent(window.location.href);
                                window.location.href = loginUrl + '?redirect=' + returnUrl;
                                return Promise.reject('redirecting');
                            }

                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                // Redirect to checkout page
                                window.location.href = '{{ route('checkout') }}';
                            } else {
                                showAlert(data.message || 'Failed to process. Please try again.',
                                    'error');
                            }
                        })
                        .catch(error => {
                            // Don't show error if we're redirecting to login
                            if (error !== 'redirecting') {
                                showAlert(error.message || 'An error occurred. Please try again.',
                                    'error');
                            }
                        });
                });
            }

            /* ===============================
                HELPER FUNCTIONS
            ============================== */
            function showAlert(message, type) {
                document.querySelectorAll('.cart-alert').forEach(el => el.remove());

                const alertDiv = document.createElement('div');
                const alertClass = type === 'success' ? 'success' : (type === 'warning' ? 'warning' : 'danger');
                const iconType = type === 'success' ? 'check-circle' : (type === 'warning' ?
                    'exclamation-triangle' : 'exclamation-circle');

                alertDiv.className = `alert alert-${alertClass} cart-alert`;
                alertDiv.style.cssText =
                    'position: fixed; top: 80px; right: 20px; z-index: 9999; max-width: 400px; animation: slideInRight 0.3s ease;';
                alertDiv.innerHTML = `
            <i class="fa fa-${iconType}"></i>
            <span>${message}</span>
            <button type="button" class="close" onclick="this.parentElement.remove()" style="margin-left: 10px;">
                <span>&times;</span>
            </button>
        `;

                document.body.appendChild(alertDiv);

                setTimeout(() => {
                    alertDiv.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => alertDiv.remove(), 300);
                }, 4000);
            }

            function updateCartCount() {
                fetch('/cart/count', {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    })
                    .then(response => response.json())
                    .then(data => {
                        const cartCountEl = document.querySelector('.cart-count, .shopping-item .badge');
                        if (cartCountEl) {
                            cartCountEl.textContent = data.count;

                            // Add animation
                            cartCountEl.style.animation = 'bounce 0.5s ease';
                            setTimeout(() => {
                                cartCountEl.style.animation = '';
                            }, 500);
                        }
                    })
                    .catch(() => {
                        /* Cart count update failed silently to avoid console noise. */
                    });
            }

            /* ===============================
                INITIALIZE VARIANT SYSTEM
            ============================== */
            function initializeVariantSystem() {
                // Handle Flipkart-style variant buttons
                document.querySelectorAll('.variant-btn-flipkart').forEach(btn => {
                    btn.addEventListener('click', handleVariantSelection);
                });

                // Handle old-style variant buttons (fallback)
                document.querySelectorAll('.variant-option-btn').forEach(btn => {
                    btn.addEventListener('click', handleVariantSelection);
                });

                // Handle color variant items
                document.querySelectorAll('#variantContainer input[type="radio"]').forEach(inp => {
                    inp.addEventListener('change', function(e) {
                        if (isUpdating) return;
                        handleColorSwatchSelection({
                            target: this,
                            currentTarget: this.closest('.color-variant-item'),
                            preventDefault: () => {},
                            stopPropagation: () => {}
                        });
                    });
                });

                document.querySelectorAll('.color-variant-item').forEach(item => {
                    const inp = item.querySelector('input[type="radio"]');
                    if (!inp) return;

                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        handleColorSwatchSelection({
                            target: inp,
                            currentTarget: this,
                            preventDefault: () => {},
                            stopPropagation: () => {}
                        });
                    });

                    const label = item.querySelector('.color-variant-label');
                    if (label) {
                        label.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            handleColorSwatchSelection({
                                target: inp,
                                currentTarget: item,
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
                INITIAL IMAGE GALLERY
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
                if (variantIdInput) variantIdInput.value = '';

                updateQuantityMax();

                // Get stock from display element - checks multiple selectors
                const stockBadge = displayStock?.querySelector('.badge, .text-success, .text-danger');
                const stockText = stockBadge?.textContent?.trim() || '0';
                const stockMatch = stockText.match(/\d+/);
                const baseStock = stockMatch ? parseInt(stockMatch[0]) : 0;

                if (addToCartBtn) {
                    addToCartBtn.disabled = baseStock <= 0;
                    addToCartBtn.innerHTML = baseStock > 0 ?
                        '<i class="fa fa-shopping-cart"></i> ADD TO CART' :
                        'OUT OF STOCK';
                }

                // Toggle Buy/Notify for non-variant products
                if (buyNowBtn && notifyMeBtn) {
                    if (baseStock > 0) {
                        buyNowBtn.style.display = 'inline-flex';
                        buyNowBtn.disabled = false;
                        notifyMeBtn.style.display = 'none';
                    } else {
                        buyNowBtn.style.display = 'none';
                        notifyMeBtn.style.display = 'inline-flex';
                    }
                }

                updateWishlistHref();
            }

            /* ===============================
                INITIALIZE
            ============================== */
            if (hasVariants && variants.length > 0) {
                initializeVariantSystem();
            } else {
                // Initialize quantity controls for non-variant products
                updateQuantityDisplay();
                updateMinusState();
            }

            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        @keyframes bounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }
        .cart-alert {
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .cart-alert i {
            font-size: 20px;
        }
        .cart-alert .close {
            background: transparent;
            border: none;
            font-size: 24px;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
        }
    `;
            document.head.appendChild(style);
            // Notify Me click placeholder
            if (notifyMeBtn) {
                notifyMeBtn.addEventListener('click', function() {
                    showAlert('We\'ll notify you when this product is back in stock.', 'success');
                });
            }
        });
    </script>
@endpush
