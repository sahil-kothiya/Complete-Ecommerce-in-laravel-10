@extends('frontend.layouts.master')
@section('main-content')

@if($banners?->count())
@php
$firstBanner = $banners->first();
$firstPhoto = $firstBanner->photo ?? 'images/placeholder-banner.jpg';
$firstWebp = preg_replace('/\.(jpg|jpeg|png)$/i', '.webp', $firstPhoto);
@endphp

<!-- Preload LCP image only once -->
<link rel="preload" as="image" href="{{ asset($firstWebp) }}" fetchpriority="high" type="image/webp">
<link rel="preload" as="image" href="{{ asset($firstPhoto) }}" fetchpriority="high" type="image/{{ pathinfo($firstPhoto, PATHINFO_EXTENSION) }}">

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
        $discount = $banner->discounts->first();

        // Handle different link types
        $ctaUrl = '#';
        $ctaText = 'Shop Now';

        switch($banner->link_type) {
        case 'product':
        // If link contains SKU, find product by SKU
        if($banner->link) {
        $product = \App\Models\Product::where('sku', $banner->link)
        ->orWhere('slug', $banner->link)
        ->first();
        if($product) {
        $ctaUrl = route('product-detail', $product->slug);
        $ctaText = 'View Product';
        }
        }
        break;

        case 'category':
        // If link contains category slug
        if($banner->link) {
        $category = \App\Models\Category::where('slug', $banner->link)->first();
        if($category) {
        $ctaUrl = route('product-cat', $category->slug);
        $ctaText = 'Browse Category';
        }
        }
        break;

        case 'url':
        // Direct URL
        if($banner->link) {
        $ctaUrl = $banner->link;
        $ctaText = 'Learn More';
        }
        break;

        default:
        // Fallback to discount-based routing (your existing logic)
        $category = $discount?->categories?->first();
        $ctaUrl = $category ? route('product-cat', $category->slug) : route('product-grids');
        break;
        }
        @endphp

        <div class="carousel-item {{ $isFirst ? 'active' : '' }}">
            <picture>
                <source srcset="{{ asset($webp) }}" type="image/webp">
                <img src="{{ asset($photo) }}"
                    class="d-block w-100"
                    alt="{{ $banner->title ?? 'Promotional banner' }}"
                    width="1200" height="550"
                    loading="{{ $isFirst ? 'eager' : 'lazy' }}"
                    fetchpriority="{{ $isFirst ? 'high' : 'auto' }}"
                    decoding="{{ $isFirst ? 'sync' : 'async' }}">
            </picture>

            <div class="carousel-caption d-none d-md-block text-left">
                <h1>{{ $banner->title }}</h1>
                <p>{!! $banner->description !!}</p>

                @if($discount)
                <p class="text-warning h5">
                    {{ $discount->title }} -
                    {{ $discount->type === 'percentage' ? $discount->value . '%' : '₹' . number_format($discount->value, 2) }} OFF
                </p>
                @endif

                @if($ctaUrl !== '#')
                <a class="btn btn-lg btn-primary"
                    href="{{ $ctaUrl }}"
                    @if($banner->link_type === 'url' && !str_starts_with($banner->link, url('/')))
                    target="_blank" rel="noopener"
                    @endif>
                    {{ $ctaText }} <i class="fa fa-arrow-right"></i>
                </a>
                @endif
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

<!-- Discount Highlight & Holiday Offer -->
@php
// Get active category-level discounts dynamically (already uncached)
$activeDiscounts = app('App\Services\DiscountService')->getAllActiveCategoryDiscounts();
@endphp

@if (!empty($activeDiscounts))
@foreach ($activeDiscounts as $discount)
<a href="{{ route('product-cat', $discount['category_slug']) }}" style="text-decoration: none;">
    <section class="discount-highlight"
        style="width: 100%; background: linear-gradient(135deg, #F7941D 0%, #e67e22 100%); color: white; padding: 16px 0; text-align: center; margin: 20px 0;">
        <div class="container">
            <p style="margin: 0; font-size: 18px; font-weight: 500;">
                🎉 {{ $discount['title'] }}:
                @if ($discount['type'] === 'percentage')
                Up to <strong>{{ $discount['value'] }}% Off</strong>
                @elseif ($discount['type'] === 'amount')
                Save <strong>${{ number_format($discount['value'], 0) }}</strong>
                @endif
                on <strong>{{ $discount['category_title'] }}</strong>!
            </p>
        </div>
    </section>
</a>
@endforeach
@endif

<!-- Category Banners with defined sizes -->
@if($categoryBanners?->count())
<section class="small-banner section">
    <div class="container-fluid">
        <div class="row">
            @foreach($categoryBanners->take(3) as $cat)
            <div class="col-lg-4 col-md-6 col-12">
                <div class="single-banner">
                    <img src="{{ asset($cat->photo ?? 'images/placeholder-category.jpg') }}"
                        alt="{{ $cat->title }}"
                        width="600" height="250"
                        loading="lazy" decoding="async">
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
                        @foreach($product_lists as $product)
                        <div class="product-card-container">
                            @include('frontend.partials.product-card', ['product' => $product])
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
@if($kidsProducts?->count())
<section class="product-area section" id="kids-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Kids</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="kidsProductsGrid" role="tabpanel" aria-labelledby="tab-kids">
                    <div class="product-listing-wrapper">
                        @foreach($kidsProducts as $product)
                        <div class="product-card-container category-kids">
                            @include('frontend.partials.product-card', ['product' => $product])
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
@if($womenProducts?->count())
<section class="product-area section" id="women-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Women</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="womenProductsGrid" role="tabpanel" aria-labelledby="tab-women">
                    <div class="product-listing-wrapper">
                        @foreach($womenProducts as $product)
                        <div class="product-card-container category-women">
                            @include('frontend.partials.product-card', ['product' => $product])
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
@if($menProducts?->count())
<section class="product-area section" id="men-products">
    <div class="container">
        <div class="section-title text-center">
            <h2>Men</h2>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="d-flex flex-wrap justify-content-center gap-4" id="menProductsGrid" role="tabpanel" aria-labelledby="tab-men">
                    <div class="product-listing-wrapper">
                        @foreach($menProducts as $product)
                        <div class="product-card-container category-men">
                            @include('frontend.partials.product-card', ['product' => $product])
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
    /* ========================
   Autocomplete Styles
======================== */
    .autocomplete-dropdown {
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
    }

    .autocomplete-item:hover,
    .autocomplete-item.active {
        background-color: #f8f9fa;
        cursor: pointer;
    }

    .list-group-item.loading,
    .list-group-item.no-results {
        color: #6c757d;
    }

    .list-group-item.error {
        color: #dc3545;
    }

    /* ========================
   Section Styling
======================== */
    .section {
        padding: 60px 0;
    }

    .section-title h2 {
        font-size: 32px;
        font-weight: 700;
        color: #333;
        text-align: center;
        position: relative;
        padding-bottom: 10px;
    }

    .section-title h2::after {
        content: '';
        display: block;
        width: 50px;
        height: 4px;
        margin: 10px auto 0;
        background-color: #F7941D;
        border-radius: 2px;
    }

    /* ========================
   Slider Section
======================== */
    #gslider {
        position: relative;
        overflow: hidden;
    }

    #gslider .carousel-inner {
        height: 550px;
        min-height: 550px;
        position: relative;
    }

    .carousel-item {
        height: 550px;
        position: relative;
        background-color: #f9f9f9;
    }

    .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
        z-index: 1;
    }

    /* Slider Captions */
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
        color: #fff;
        margin: 20px 0;
    }

    /* Slider Indicators */
    #gslider .carousel-indicators {
        bottom: 20px;
        z-index: 3;
    }

    /* Slider Controls */
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

    /* ========================
   Product Cards
======================== */
    .product-card-container {
        flex: 0 0 auto;
        width: 250px;
        margin: 10px;
    }

    .single-product {
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        transition: transform 0.3s, box-shadow 0.3s;
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

    /* ========================
   Category Banners
======================== */
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
        color: #fff;
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

    /* ========================
   Shop Services
======================== */
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

    /* ========================
   Responsive Styles
======================== */
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
    // document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    //     anchor.addEventListener('click', function(e) {
    //         const target = document.querySelector(this.getAttribute('href'));
    //         if (target) {
    //             e.preventDefault();
    //             target.scrollIntoView({
    //                 behavior: 'smooth'
    //             });
    //         }
    //     });
    // });
</script>
@endpush