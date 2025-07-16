@extends('frontend.layouts.master')
@section('main-content')

<!-- Slider -->
@if($banners?->count())

@php
$firstBanner = $banners->first();
$firstPhoto = $firstBanner?->photo ?? 'images/placeholder-banner.jpg';
$firstWebp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $firstPhoto);
@endphp

<!-- Preload First Banner Images for LCP -->
<link rel="preload" as="image" href="{{ asset($firstWebp) }}" type="image/webp">
<link rel="preload" as="image" href="{{ asset($firstPhoto) }}" type="image/{{ pathinfo($firstPhoto, PATHINFO_EXTENSION) }}">

<section id="gslider" class="carousel slide" data-ride="carousel" data-interval="3000">
    <ol class="carousel-indicators">
        @foreach($banners as $key => $banner)
        <li data-target="#gslider" data-slide-to="{{ $key }}" class="{{ $key === 0 ? 'active' : '' }}" aria-label="Slide {{ $key + 1 }}"></li>
        @endforeach
    </ol>

    <div class="carousel-inner">
        @foreach($banners as $key => $banner)
        @php
        $photo = $banner->photo ?? 'images/placeholder-banner.jpg';
        $webp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $photo);
        $isFirst = $key === 0;
        @endphp

        <div class="carousel-item {{ $isFirst ? 'active' : '' }}" aria-label="Slide {{ $key + 1 }}">
            <picture>
                <source srcset="{{ asset($webp) }}" type="image/webp">
                <img src="{{ asset($photo) }}"
                    class="d-block w-100"
                    alt="{{ $banner->title }}"
                    width="1200" height="550"
                    loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                    fetchpriority="{{ $isFirst ? 'high' : 'low' }}">
            </picture>

            <div class="carousel-caption d-none d-md-block text-left">
                <h1>{{ $banner->title }}</h1>
                <p>{!! $banner->description !!}</p>
                <a class="btn btn-lg btn-primary" href="{{ route('product-grids') }}">
                    Shop Now <i class="fa fa-arrow-right"></i>
                </a>
            </div>
        </div>
        @endforeach
    </div>

    <a class="carousel-control-prev" href="#gslider" role="button" data-slide="prev" aria-label="Previous slide">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
    </a>
    <a class="carousel-control-next" href="#gslider" role="button" data-slide="next" aria-label="Next slide">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
    </a>
</section>

@endif

<!-- Category Banners -->
@if($categoryBanners?->count())
<section class="small-banner section">
    <div class="container-fluid">
        <div class="row">
            @foreach($categoryBanners->take(3) as $cat)
            <div class="col-lg-4 col-md-6 col-12">
                <div class="single-banner">
                    <img src="{{ asset($cat->photo ?? 'images/placeholder-category.jpg') }}" alt="{{ $cat->title }}" loading="lazy">
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

<!-- All Products Section -->
@if($product_lists?->count())
<section class="product-area section" id="all-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>All Products</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="allProductsGrid" role="tabpanel" aria-labelledby="tab-all">
                    <div class="product-listing-wrapper">
                        @foreach($product_lists->take(12) as $product)
                        <div class="product-card-container">
                            @include('frontend.partials.product-card', ['product' => $product])
                            @include('frontend.partials.product-modal', ['product' => $product])
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Kids Section -->
@if($product_lists->filter(fn($item) => $item->cat_info?->title === "Kid's")->count())
<section class="product-area section" id="kids-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Kids</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="kidsProductsGrid" role="tabpanel" aria-labelledby="tab-kids">
                    <div class="product-listing-wrapper">
                        @foreach($product_lists->filter(fn($item) => $item->cat_info?->title === "Kid's")->take(12) as $product)
                        <div class="product-card-container category-kids">
                            @include('frontend.partials.product-card', ['product' => $product])
                            @include('frontend.partials.product-modal', ['product' => $product])
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Women Section -->
@if($product_lists->filter(fn($item) => $item->cat_info?->title === "Women's Fashion")->count())
<section class="product-area section" id="women-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Women</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="womenProductsGrid" role="tabpanel" aria-labelledby="tab-women">
                    <div class="product-listing-wrapper">
                        @foreach($product_lists->filter(fn($item) => $item->cat_info?->title === "Women's Fashion")->take(12) as $product)
                        <div class="product-card-container category-women">
                            @include('frontend.partials.product-card', ['product' => $product])
                            @include('frontend.partials.product-modal', ['product' => $product])
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Men Section -->
@if($product_lists->filter(fn($item) => $item->cat_info?->title === "Men's Fashion")->count())
<section class="product-area section" id="men-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Men</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="menProductsGrid" role="tabpanel" aria-labelledby="tab-men">
                    <div class="product-listing-wrapper">
                        @foreach($product_lists->filter(fn($item) => $item->cat_info?->title === "Men's Fashion")->take(12) as $product)
                        <div class="product-card-container category-men">
                            @include('frontend.partials.product-card', ['product' => $product])
                            @include('frontend.partials.product-modal', ['product' => $product])
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Shop Services -->
<section class="shop-services section">
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
                    <p>Within 30 days</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-lock"></i>
                    <h4>Secure Payment</h4>
                    <p>100% secure</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <div class="single-service">
                    <i class="ti-tag"></i>
                    <h4>Best Price</h4>
                    <p>Guaranteed</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Product Modals -->
@if($product_lists?->count())
@foreach($product_lists->take(5) as $product)
@include('frontend.partials.product-modal', ['product' => $product])
@endforeach
@endif
@endsection

@push('styles')
<style>
    /* General Section Styling */
    .section {
        padding: 60px 0;
    }

    .section-title h2 {
        font-size: 32px;
        font-weight: 700;
        color: #333;
        position: relative;
        padding-bottom: 10px;
        text-align: center;
    }

    .section-title h2::after {
        content: '';
        display: block;
        width: 50px;
        height: 4px;
        background-color: #F7941D;
        margin: 10px auto 0;
        border-radius: 2px;
    }

    /* ==========================
   Slider Wrapper
========================== */
    #gslider {
        position: relative;
        overflow: hidden;
    }

    /* Slider Inner Container */
    #gslider .carousel-inner {
        height: 550px;
        min-height: 550px;
        position: relative;
    }

    /* Carousel Items */
    .carousel-item {
        height: 550px;
        position: relative;
        background-color: #f9f9f9;
        /* fallback to prevent black flash */
        transition: transform 0.6s ease-in-out;
        /* Bootstrap slide transition */
    }

    /* Main Image Styling */
    .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        opacity: 0.85;
        z-index: 1;
    }

    /* ==========================
   Caption Styles
========================== */
    #gslider .carousel-caption {
        position: absolute;
        bottom: 50%;
        transform: translateY(50%);
        text-align: left;
        z-index: 2;
    }

    #gslider .carousel-caption h1 {
        font-size: 48px;
        font-weight: bold;
        color: #F7941D;
        margin-bottom: 10px;
    }

    #gslider .carousel-caption p {
        font-size: 18px;
        color: white;
        margin: 20px 0;
    }

    /* ==========================
   Carousel Indicators
========================== */
    #gslider .carousel-indicators {
        bottom: 20px;
        z-index: 3;
    }

    /* ==========================
   Controls (Prev / Next Arrows)
========================== */
    .carousel-control-prev,
    .carousel-control-next {
        z-index: 4;
        opacity: 1 !important;
        width: 5%;
    }

    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        background-color: rgba(0, 0, 0, 0.4);
        border-radius: 50%;
        padding: 10px;
        background-size: 100% 100%;
    }


    /* Product Cards */
    .product-card-container {
        flex: 0 0 auto;
        width: 250px;
        margin: 10px;
    }

    .single-product {
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
        background: #fff;
    }

    .single-product:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .product-img img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .single-product:hover .product-img img {
        transform: scale(1.05);
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
        text-decoration: line-through;
    }

    /* Category Banners */
    .category-banners .single-banner {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
    }

    .category-banners img {
        width: 100%;
        height: 250px;
        object-fit: cover;
        transition: transform 0.3s;
    }

    .category-banners .single-banner:hover img {
        transform: scale(1.05);
    }

    .category-banners .content {
        position: absolute;
        bottom: 20px;
        left: 20px;
        color: white;
        text-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
    }

    .category-banners .content h3 {
        font-size: 24px;
        margin-bottom: 10px;
    }

    .category-banners .content a {
        color: #F7941D;
        font-weight: bold;
        text-decoration: none;
    }

    /* Shop Services */
    .shop-services .single-service {
        text-align: center;
        padding: 20px;
        border-radius: 8px;
        transition: background 0.3s;
    }

    .shop-services .single-service:hover {
        background: #f8f9fa;
    }

    .shop-services .single-service i {
        font-size: 36px;
        color: #F7941D;
        margin-bottom: 10px;
    }

    .shop-services .single-service h4 {
        font-size: 20px;
        margin-bottom: 5px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #gslider .carousel-caption h1 {
            font-size: 28px;
        }

        #gslider .carousel-caption p {
            font-size: 14px;
        }

        .section-title h2 {
            font-size: 24px;
        }

        .product-card-container {
            width: 100%;
            max-width: 300px;
        }

        .category-banners img {
            height: 200px;
        }
    }

    @media (max-width: 576px) {
        #gslider .carousel-caption {
            bottom: 30%;
        }

        #gslider .carousel-caption h1 {
            font-size: 20px;
        }

        #gslider .carousel-caption p {
            font-size: 12px;
        }

        .category-banners .content h3 {
            font-size: 18px;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    $(document).ready(function() {
        // Smooth scroll to sections
        $('a[href*="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top
                }, 1000);
            }
        });

        // Autocomplete (reused from original)
        let searchTimeout;
        const $searchInput = $('#search-input');
        const $dropdown = $('#autocomplete-dropdown');
        const $list = $('#autocomplete-list');

        $searchInput.on('input', function() {
            debounceSearch($(this).val().trim());
        });

        function debounceSearch(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performAutocomplete(query), 300);
        }

        function performAutocomplete(query) {
            if (query.length < 2) return hideDropdown();
            $list.html('<li class="loading">Searching...</li>');
            showDropdown();

            $.ajax({
                url: '{{ route("autocomplete") }}',
                method: 'GET',
                data: {
                    q: query
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.suggestions?.length) {
                        $list.empty();
                        response.suggestions.forEach(item => {
                            const discountPrice = item.price - (item.price * (item.discount || 0) / 100);
                            const priceHTML = item.discount > 0 ?
                                `<span class="price">$${discountPrice.toFixed(2)} <del>$${parseFloat(item.price).toFixed(2)}</del></span>` :
                                `<span class="price">$${parseFloat(item.price).toFixed(2)}</span>`;
                            $list.append(`
                                <li class="autocomplete-item" data-slug="${item.slug}">
                                    <div class="item-content">
                                        <span class="title">${item.title}</span>
                                        ${priceHTML}
                                    </div>
                                </li>
                            `);
                        });
                    } else {
                        $list.html('<li class="no-results">No products found</li>');
                    }
                    showDropdown();
                },
                error: function(xhr, status, error) {
                    console.error('Autocomplete error:', error);
                    hideDropdown();
                }
            });
        }

        function showDropdown() {
            $dropdown.removeAttr('hidden');
        }

        function hideDropdown() {
            $dropdown.attr('hidden', true);
        }

        $(document).on('click', '.autocomplete-item', function() {
            window.location.href = `{{ url('/product-detail') }}/${$(this).data('slug')}`;
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-container').length) hideDropdown();
        });
    });
</script>
@endpush