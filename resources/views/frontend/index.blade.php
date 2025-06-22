@extends('frontend.layouts.master')
@section('title','E-SHOP || HOME PAGE')
@section('main-content')

<!-- Slider Area -->
@if(isset($banners) && $banners->count() > 0)
<section id="Gslider" class="carousel slide" data-ride="carousel">
    <ol class="carousel-indicators">
        @foreach($banners as $key => $banner)
        <li data-target="#Gslider" data-slide-to="{{ $key }}" class="{{ $key == 0 ? 'active' : '' }}"></li>
        @endforeach
    </ol>

    <div class="carousel-inner" role="group" aria-label="Homepage banner carousel">
        @foreach($banners as $key => $banner)
        <div class="carousel-item {{ $key == 0 ? 'active' : '' }}" role="group" aria-roledescription="slide" aria-label="Slide {{ $key + 1 }} of {{ $banners->count() }}">
            @php
            $imagePath = public_path($banner->photo);
            $imageExists = file_exists($imagePath);
            $dimensions = $imageExists ? getimagesize($imagePath) : [1200, 600];
            @endphp

            <img
                class="first-slide"
                src="{{ $imageExists ? asset($banner->photo) : asset('images/placeholder-banner.jpg') }}"
                fetchpriority="{{ $key == 0 ? 'high' : 'low' }}"
                alt="{{ $banner->title }}"
                loading="{{ $key == 0 ? 'eager' : 'lazy' }}"
                width="{{ $dimensions[0] ?? 1200 }}"
                height="{{ $dimensions[1] ?? 600 }}">

            <div class="carousel-caption d-none d-md-block text-left">
                <h1 class="wow fadeInDown">{{ $banner->title }}</h1>
                <p>{!! html_entity_decode($banner->description) !!}</p>
                <a class="btn btn-lg ws-btn wow fadeInUpBig" href="{{ route('product-grids') }}" role="button">
                    Shop Now <i class="far fa-arrow-alt-circle-right"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    <a class="carousel-control-prev" href="#Gslider" role="button" data-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#Gslider" role="button" data-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="sr-only">Next</span>
    </a>
</section>
@endif
<!--/ End Slider Area -->

<!-- Start Small Banner -->
@if(isset($categoryBanners) && $categoryBanners->count() > 0)
<section class="small-banner section">
    <div class="container-fluid">
        <div class="row">
            @foreach($categoryBanners->take(3) as $cat)
            <div class="col-lg-4 col-md-6 col-12">
                <div class="single-banner">
                    <img
                        src="{{ $cat->photo ? asset($cat->photo) : asset('images/placeholder-category.jpg') }}"
                        alt="{{ $cat->title }}"
                        loading="lazy">
                    <div class="content">
                        <h3>{{ $cat->title }}</h3>
                        <a href="{{ route('product-cat', $cat->slug) }}">Discover Now</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
<!-- End Small Banner -->

<!-- Start Product Area -->
<div class="product-area section">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <div class="section-title text-center">
                    <h2>Trending Items</h2>
                </div>
            </div>
        </div>

        <!-- Category Filter Buttons -->
        @if(isset($categories) && $categories->count() > 0)
        <div class="row mb-4">
            <div class="col-12 text-center">
                <ul class="nav nav-tabs filter-tope-group" id="categoryFilters" role="tablist">
                    <button class="btn how-active1" data-filter="*" role="tab" aria-pressed="true">
                        All Products
                    </button>
                    @foreach($categories as $cat)
                    <button class="btn" data-filter=".category-{{ $cat->id }}" role="tab" aria-pressed="false">
                        {{ $cat->title }}
                    </button>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <!-- Products Grid -->
        <div class="row tab-content isotope-grid" id="productsGrid">
            @if(isset($product_lists) && $product_lists->count() > 0)
            @foreach($product_lists->take(24) as $product)
            @php
            // Get first image safely
            $firstImage = $product->images->first();
            $imageUrl = $firstImage ? asset($firstImage->image_path) : asset('images/no-product-image.jpg');

            // Calculate discounted price
            $originalPrice = $product->price;
            $discountAmount = ($originalPrice * $product->discount) / 100;
            $finalPrice = $originalPrice - $discountAmount;

            // Stock status
            $isInStock = $product->stock > 0;
            @endphp

            <div class="col-sm-6 col-md-4 col-lg-3 p-b-35 isotope-item category-{{ $product->cat_id }}">
                <div class="single-product">
                    <div class="product-img">
                        <a href="{{ route('product-detail', $product->slug) }}">
                            <img
                                class="default-img"
                                src="{{ $imageUrl }}"
                                alt="{{ $product->title }}"
                                loading="lazy">
                            <img
                                class="hover-img"
                                src="{{ $imageUrl }}"
                                alt="{{ $product->title }}"
                                loading="lazy">

                            @if(!$isInStock)
                            <span class="out-of-stock">Sold Out</span>
                            @elseif($product->condition == 'new')
                            <span class="new" aria-label="This product is new">New</span>
                            @elseif($product->condition == 'hot')
                            <span class="hot">Hot</span>
                            @elseif($product->discount > 0)
                            <span class="price-dec">{{ $product->discount }}% Off</span>
                            @endif
                        </a>

                        <div class="button-head">
                            <div class="product-action">
                                <a
                                    data-toggle="modal"
                                    data-target="#productModal{{ $product->id }}"
                                    title="Quick View"
                                    href="#"
                                    aria-label="Quick view {{ $product->title }}">
                                    <i class="ti-eye"></i><span>Quick Shop</span>
                                </a>
                                <a
                                    title="Wishlist"
                                    href="{{ route('add-to-wishlist', $product->slug) }}"
                                    aria-label="Add {{ $product->title }} to wishlist">
                                    <i class="ti-heart"></i><span>Add to Wishlist</span>
                                </a>
                            </div>
                            <div class="product-action-2">
                                @if($isInStock)
                                <a
                                    title="Add to cart"
                                    href="{{ route('add-to-cart', $product->slug) }}"
                                    aria-label="Add {{ $product->title }} to cart">
                                    Add to Cart
                                </a>
                                @else
                                <span class="out-of-stock-btn">Out of Stock</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="product-content">
                        <h3>
                            <a href="{{ route('product-detail', $product->slug) }}">
                                {{ Str::limit($product->title, 50) }}
                            </a>
                        </h3>
                        <div class="product-price">
                            @if($product->discount > 0)
                            <span class="current-price">${{ number_format($finalPrice, 2) }}</span>
                            <del class="original-price">${{ number_format($originalPrice, 2) }}</del>
                            @else
                            <span class="current-price">${{ number_format($originalPrice, 2) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            @else
            <div class="col-12 text-center">
                <p>No trending products available at the moment.</p>
            </div>
            @endif
        </div>
    </div>
</div>
<!-- End Product Area -->

<!-- Start Medium Banner -->
@if(isset($product_lists) && $product_lists->count() > 1)
<section class="midium-banner">
    <div class="container">
        <div class="row">
            @foreach($product_lists->take(2) as $product)
            <div class="col-lg-6 col-md-6 col-12">
                <div class="single-banner">
                    @php
                    $bannerImage = $product->images->first();
                    $bannerImageUrl = $bannerImage ? asset($bannerImage->image_path) : asset('images/no-product-image.jpg');
                    $categoryTitle = $product->cat_info->title ?? 'Product';
                    @endphp

                    <img src="{{ $bannerImageUrl }}" alt="{{ $product->title }}" loading="lazy">
                    <div class="content">
                        <p>{{ $categoryTitle }}</p>
                        <h3>{{ $product->title }} <br>Up to <span>{{ $product->discount }}%</span> Off</h3>
                        <a href="{{ route('product-detail', $product->slug) }}">Shop Now</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
<!-- End Medium Banner -->

<!-- Start Most Popular (Hot Items) -->
@if(isset($product_lists))
@php
$hotProducts = $product_lists->where('condition', 'hot');
@endphp

@if($hotProducts->count() > 0)
<div class="product-area most-popular section">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title">
                    <h2>Hot Items</h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="owl-carousel popular-slider">
                    @foreach($hotProducts as $product)
                    <div class="single-product">
                        <div class="product-img">
                            <a href="{{ route('product-detail', $product->slug) }}">
                                @php
                                $hotImage = $product->images->first();
                                $hotImageUrl = $hotImage ? asset($hotImage->image_path) : asset('images/no-product-image.jpg');
                                $hotFinalPrice = $product->price - (($product->price * $product->discount) / 100);
                                @endphp
                                <img class="default-img" src="{{ $hotImageUrl }}" alt="{{ $product->title }}" loading="lazy">
                                <img class="hover-img" src="{{ $hotImageUrl }}" alt="{{ $product->title }}" loading="lazy">
                            </a>
                            <div class="button-head">
                                <div class="product-action">
                                    <a data-toggle="modal" data-target="#productModal{{ $product->id }}" title="Quick View" href="#">
                                        <i class="ti-eye"></i><span>Quick Shop</span>
                                    </a>
                                    <a title="Wishlist" href="{{ route('add-to-wishlist', $product->slug) }}">
                                        <i class="ti-heart"></i><span>Add to Wishlist</span>
                                    </a>
                                </div>
                                <div class="product-action-2">
                                    <a href="{{ route('add-to-cart', $product->slug) }}">Add to Cart</a>
                                </div>
                            </div>
                        </div>
                        <div class="product-content">
                            <h3>
                                <a href="{{ route('product-detail', $product->slug) }}">
                                    {{ Str::limit($product->title, 40) }}
                                </a>
                            </h3>
                            <div class="product-price">
                                @if($product->discount > 0)
                                <span>${{ number_format($hotFinalPrice, 2) }}</span>
                                <del>${{ number_format($product->price, 2) }}</del>
                                @else
                                <span>${{ number_format($product->price, 2) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endif
<!-- End Most Popular Area -->

<!-- Start Shop Home List (Latest Items) -->
@if(isset($product_lists) && $product_lists->count() > 0)
<section class="shop-home-list section">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 col-md-12 col-12">
                <div class="row">
                    <div class="col-12">
                        <div class="shop-section-title">
                            <h1>Latest Items</h1>
                        </div>
                    </div>
                </div>
                <div class="row">
                    @foreach($product_lists->take(12) as $product)
                    <div class="col-md-4">
                        <div class="single-list">
                            <div class="row">
                                <div class="col-lg-6 col-md-6 col-12">
                                    <div class="list-image overlay">
                                        @php
                                        $listImage = $product->images->first();
                                        $listImageUrl = $listImage ? asset($listImage->image_path) : asset('images/no-product-image.jpg');
                                        $listFinalPrice = $product->price - (($product->price * $product->discount) / 100);
                                        @endphp
                                        <img src="{{ $listImageUrl }}" alt="{{ $product->title }}" loading="lazy">
                                        @if($product->stock > 0)
                                        <a href="{{ route('add-to-cart', $product->slug) }}" class="buy">
                                            <i class="fa fa-shopping-bag"></i>
                                        </a>
                                        @endif
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-12 no-padding">
                                    <div class="content">
                                        <h4 class="title">
                                            <a href="{{ route('product-detail', $product->slug) }}">
                                                {{ Str::limit($product->title, 30) }}
                                            </a>
                                        </h4>
                                        <p class="price with-discount">
                                            @if($product->discount > 0)
                                            ${{ number_format($listFinalPrice, 2) }}
                                            <del>${{ number_format($product->price, 2) }}</del>
                                            @else
                                            ${{ number_format($product->price, 2) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
@endif
<!-- End Shop Home List -->

<!-- Start Shop Services Area -->
<section class="shop-services section home">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-rocket"></i>
                    <h4>Free Shipping</h4>
                    <p>Orders over $100</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-reload"></i>
                    <h4>Free Return</h4>
                    <p>Within 30 days returns</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-lock"></i>
                    <h4>Secure Payment</h4>
                    <p>100% secure payment</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-tag"></i>
                    <h4>Best Price</h4>
                    <p>Guaranteed price</p>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- End Shop Services Area -->

<!-- Product Quick View Modals -->
@if(isset($product_lists) && $product_lists->count() > 0)
@foreach($product_lists->take(24) as $product)
<div class="modal fade" id="productModal{{ $product->id }}" tabindex="-1" role="dialog" aria-labelledby="productModalLabel{{ $product->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="productModalLabel{{ $product->id }}">{{ $product->title }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span class="ti-close" aria-hidden="true"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row no-gutters">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                        <div class="product-gallery">
                            <div class="quickview-slider-active">
                                @forelse($product->images as $image)
                                <div class="single-slider">
                                    <img src="{{ asset($image->image_path) }}" alt="{{ $product->title }}" loading="lazy">
                                </div>
                                @empty
                                <div class="single-slider">
                                    <img src="{{ asset('images/no-product-image.jpg') }}" alt="No image available" loading="lazy">
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                        <div class="quickview-content">
                            <h2>{{ $product->title }}</h2>
                            <div class="quickview-ratting-review">
                                <div class="quickview-ratting-wrap">
                                    <div class="quickview-ratting">
                                        @php
                                        $avgRating = DB::table('product_reviews')->where('product_id', $product->id)->avg('rate') ?? 0;
                                        $reviewCount = DB::table('product_reviews')->where('product_id', $product->id)->count();
                                        @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="fa fa-star {{ $avgRating >= $i ? 'yellow' : '' }}"></i>
                                            @endfor
                                    </div>
                                    <a href="#">({{ $reviewCount }} customer reviews)</a>
                                </div>
                                <div class="quickview-stock">
                                    @if($product->stock > 0)
                                    <span><i class="fa fa-check-circle-o text-success"></i> {{ $product->stock }} in stock</span>
                                    @else
                                    <span><i class="fa fa-times-circle-o text-danger"></i> Out of stock</span>
                                    @endif
                                </div>
                            </div>

                            @php
                            $modalFinalPrice = $product->price - (($product->price * $product->discount) / 100);
                            @endphp

                            <h3>
                                @if($product->discount > 0)
                                <span>${{ number_format($modalFinalPrice, 2) }}</span>
                                <small><del class="text-muted">${{ number_format($product->price, 2) }}</del></small>
                                @else
                                <span>${{ number_format($product->price, 2) }}</span>
                                @endif
                            </h3>

                            @if($product->summary)
                            <div class="quickview-peragraph">
                                <p>{!! html_entity_decode($product->summary) !!}</p>
                            </div>
                            @endif

                            @if($product->size)
                            <div class="size">
                                <div class="row">
                                    <div class="col-lg-6 col-12">
                                        <h5 class="title">Size</h5>
                                        <select class="form-control">
                                            @foreach(explode(',', $product->size) as $size)
                                            <option value="{{ trim($size) }}">{{ trim($size) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if($product->stock > 0)
                            <form action="{{ route('single-add-to-cart') }}" method="POST" class="mt-4">
                                @csrf
                                <div class="quantity">
                                    <div class="input-group">
                                        <div class="button minus">
                                            <button type="button" class="btn btn-primary btn-number" data-type="minus" data-field="quant[{{ $product->id }}]">
                                                <i class="ti-minus"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="slug" value="{{ $product->slug }}">
                                        <input type="text" name="quant[{{ $product->id }}]" class="input-number" data-min="1" data-max="{{ $product->stock }}" value="1">
                                        <div class="button plus">
                                            <button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[{{ $product->id }}]">
                                                <i class="ti-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="add-to-cart">
                                    <button type="submit" class="btn">Add to Cart</button>
                                    <a href="{{ route('add-to-wishlist', $product->slug) }}" class="btn min">
                                        <i class="ti-heart"></i>
                                    </a>
                                </div>
                            </form>
                            @else
                            <div class="add-to-cart">
                                <button class="btn" disabled>Out of Stock</button>
                            </div>
                            @endif

                            <div class="default-social mt-3">
                                <div class="sharethis-inline-share-buttons"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif
<!-- End Modals -->

@endsection

@push('styles')
<script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=5f2e5abf393162001291e431&product=inline-share-buttons' async='async'></script>
<style>
    /* Banner Styling */
    #Gslider .carousel-inner {
        background: #000000;
        height: 550px;
    }

    #Gslider .carousel-inner img {
        width: 100% !important;
        height: 550px;
        object-fit: cover;
        opacity: .8;
    }

    #Gslider .carousel-inner .carousel-caption {
        bottom: 60%;
    }

    #Gslider .carousel-inner .carousel-caption h1 {
        font-size: 50px;
        font-weight: bold;
        line-height: 100%;
        color: #F7941D;
    }

    #Gslider .carousel-inner .carousel-caption p {
        font-size: 18px;
        color: white;
        margin: 28px 0 28px 0;
    }

    #Gslider .carousel-indicators {
        bottom: 70px;
    }

    /* Filter Buttons */
    .filter-tope-group {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-tope-group button {
        background-color: transparent;
        color: #333;
        border: 1px solid #ddd;
        padding: 8px 16px;
        border-radius: 4px;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .filter-tope-group button:hover {
        background-color: #f8f9fa;
        border-color: #F7941D;
    }

    .filter-tope-group button.how-active1 {
        background-color: #F7941D !important;
        color: white !important;
        border-color: #F7941D !important;
    }

    /* Product Cards */
    .single-product {
        margin-bottom: 30px;
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .single-product:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .product-img {
        position: relative;
        overflow: hidden;
    }

    .product-img img {
        width: 100%;
        height: 250px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .single-product:hover .product-img img {
        transform: scale(1.05);
    }

    /* Price Styling */
    .product-price {
        margin-top: 10px;
    }

    .product-price .current-price {
        font-size: 18px;
        font-weight: bold;
        color: #F7941D;
    }

    .product-price .original-price {
        font-size: 14px;
        color: #999;
        margin-left: 8px;
    }

    /* Out of Stock Styling */
    .out-of-stock-btn {
        background-color: #6c757d;
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: not-allowed;
    }

    /* Modal Improvements */
    .modal-lg {
        max-width: 900px;
    }

    .quickview-content {
        padding: 20px;
    }

    .quickview-ratting i.yellow {
        color: #ffc107;
    }

    .quickview-stock .text-success {
        color: #28a745 !important;
    }

    .quickview-stock .text-danger {
        color: #dc3545 !important;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        #Gslider .carousel-inner .carousel-caption h1 {
            font-size: 30px;
        }

        #Gslider .carousel-inner .carousel-caption p {
            font-size: 14px;
        }

        .filter-tope-group {
            justify-content: center;
        }

        .filter-tope-group button {
            font-size: 12px;
            padding: 6px 12px;
        }
    }

    /* Loading States */
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }

    /* Accessibility */
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
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
    $(document).ready(function() {
        var $topeContainer = $('.isotope-grid');
        var $filterButtons = $('.filter-tope-group button');

        // Isotope init
        $topeContainer.isotope({
            itemSelector: '.isotope-item',
            layoutMode: 'fitRows',
            percentPosition: true,
            animationEngine: 'best-available',
            masonry: {
                columnWidth: '.isotope-item'
            }
        });

        // Filter handler
        $filterButtons.on('click', function() {
            var filterValue = $(this).attr('data-filter');

            // Filter items
            $topeContainer.isotope({
                filter: filterValue
            });

            // Update active class
            $filterButtons.removeClass('how-active1');
            $(this).addClass('how-active1');
        });
    });
</script>
<script>
    function cancelFullScreen(el) {
        var requestMethod = el.cancelFullScreen || el.webkitCancelFullScreen || el.mozCancelFullScreen || el.exitFullscreen;
        if (requestMethod) { // cancel full screen.
            requestMethod.call(el);
        } else if (typeof window.ActiveXObject !== "undefined") { // Older IE.
            var wscript = new ActiveXObject("WScript.Shell");
            if (wscript !== null) {
                wscript.SendKeys("{F11}");
            }
        }
    }

    function requestFullScreen(el) {
        // Supports most browsers and their versions.
        var requestMethod = el.requestFullScreen || el.webkitRequestFullScreen || el.mozRequestFullScreen || el.msRequestFullscreen;

        if (requestMethod) { // Native full screen.
            requestMethod.call(el);
        } else if (typeof window.ActiveXObject !== "undefined") { // Older IE.
            var wscript = new ActiveXObject("WScript.Shell");
            if (wscript !== null) {
                wscript.SendKeys("{F11}");
            }
        }
        return false
    }
</script>
@endpush