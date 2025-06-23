@extends('frontend.layouts.master')
@section('main-content')
<!-- Slider -->
@if($banners?->count())
<section id="gslider" class="carousel slide" data-bs-ride="carousel" aria-label="Homepage banner carousel">
    <ol class="carousel-indicators">
        @foreach($banners as $key => $banner)
        <li data-bs-target="#gslider" data-bs-slide-to="{{ $key }}" class="{{ $key === 0 ? 'active' : '' }}" aria-label="Slide {{ $key + 1 }}"></li>
        @endforeach
    </ol>
    <div class="carousel-inner">
        @foreach($banners as $key => $banner)
        <div class="carousel-item {{ $key === 0 ? 'active' : '' }}" aria-label="Slide {{ $key + 1 }}">
            <img
                src="{{ asset($banner->photo ?? 'images/placeholder-banner.jpg') }}"
                alt="{{ $banner->title }}"
                loading="{{ $key === 0 ? 'eager' : 'lazy' }}"
                width="1200"
                height="550">
            <div class="carousel-caption d-none d-md-block text-left">
                <h1>{{ $banner->title }}</h1>
                <p>{!! $banner->description !!}</p>
                <a class="btn btn-lg" href="{{ route('product-grids') }}">Shop Now <i class="fa fa-arrow-right"></i></a>
            </div>
        </div>
        @endforeach
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#gslider" data-bs-slide="prev" aria-label="Previous slide">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#gslider" data-bs-slide="next" aria-label="Next slide">
        <span class="carousel-control-next-icon"></span>
    </button>
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
                    <img
                        src="{{ asset($cat->photo ?? 'images/placeholder-category.jpg') }}"
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

<!-- Trending Products -->
@if($product_lists?->count())
<section class="product-area section">
    <div class="container">
        <div class="section-title text-center">
            <h2>Trending Items</h2>
        </div>
        @if($categories?->count())
        <div class="row mb-4">
            <div class="col-12 text-center">
                <ul class="nav filter-tope-group" id="categoryFilters" role="tablist">
                    <button class="btn active" data-filter="*">All Products</button>
                    @foreach($categories as $cat)
                    <button class="btn" data-filter=".category-{{ $cat->id }}">{{ $cat->title }}</button>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
        <div class="row isotope-grid" id="productsGrid">
            @foreach($product_lists->take(24) as $product)
            @include('frontend.partials.product-card', ['product' => $product])
            @endforeach
            @if(!$product_lists->count())
            <div class="col-12 text-center">
                <p>No trending products available.</p>
            </div>
            @endif
        </div>
    </div>
</section>
@endif

<!-- Medium Banners -->
@if($product_lists?->count() > 1)
<section class="medium-banner section">
    <div class="container">
        <div class="row">
            @foreach($product_lists->take(2) as $product)
            <div class="col-lg-6 col-md-6 col-12">
                <div class="single-banner">
                    <img
                        src="{{ asset($product->images->first()?->image_path ?? 'images/no-product-image.jpg') }}"
                        alt="{{ $product->title }}"
                        loading="lazy">
                    <div class="content">
                        <p>{{ $product->cat_info->title ?? 'Product' }}</p>
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

<!-- Hot Items -->
@if($product_lists?->where('condition', 'hot')->count())
<section class="product-area most-popular section">
    <div class="container">
        <div class="section-title text-center">
            <h2>Hot Items</h2>
        </div>
        <div class="owl-carousel popular-slider">
            @foreach($product_lists->where('condition', 'hot') as $product)
            @include('frontend.partials.product-card', ['product' => $product, 'isCarousel' => true])
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Latest Items -->
@if($product_lists?->count())
<section class="shop-home-list section">
    <div class="container">
        <div class="shop-section-title text-center">
            <h2>Latest Items</h2>
        </div>
        <div class="row">
            @foreach($product_lists->take(12) as $product)
            <div class="col-md-4">
                <div class="single-list">
                    <div class="row">
                        <div class="col-lg-6 col-md-6 col-12">
                            <div class="list-image overlay">
                                <img
                                    src="{{ asset($product->images->first()?->image_path ?? 'images/no-product-image.jpg') }}"
                                    alt="{{ $product->title }}"
                                    loading="lazy">
                                @if($product->stock > 0)
                                <a href="{{ route('add-to-cart', $product->slug) }}" class="buy" aria-label="Add to cart">
                                    <i class="fa fa-shopping-bag"></i>
                                </a>
                                @endif
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-6 col-12 no-padding">
                            <div class="content">
                                <h4>
                                    <a href="{{ route('product-detail', $product->slug) }}">{{ Str::limit($product->title, 30) }}</a>
                                </h4>
                                <p class="price with-discount">
                                    @if($product->discount > 0)
                                    ${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}
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
@foreach($product_lists->take(24) as $product)
@include('frontend.partials.product-modal', ['product' => $product])
@endforeach
@endif
@endsection

@push('styles')
<style>
    /* Slider */
    #gslider .carousel-inner {
        background: #000;
        height: 550px;
    }

    #gslider img {
        width: 100%;
        height: 550px;
        object-fit: cover;
        opacity: 0.8;
    }

    #gslider .carousel-caption {
        bottom: 60%;
    }

    #gslider .carousel-caption h1 {
        font-size: 50px;
        font-weight: bold;
        color: #F7941D;
    }

    #gslider .carousel-caption p {
        font-size: 18px;
        color: white;
        margin: 28px 0;
    }

    #gslider .carousel-indicators {
        bottom: 70px;
    }

    /* Filters */
    .filter-tope-group {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 10px;
    }

    .filter-tope-group button {
        background: none;
        border: 1px solid #ddd;
        padding: 8px 16px;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .filter-tope-group button:hover {
        background: #f8f9fa;
        border-color: #F7941D;
    }

    .filter-tope-group button.active {
        background: #F7941D;
        color: white;
        border-color: #F7941D;
    }

    /* Product Cards */
    .single-product {
        border: 1px solid #eee;
        border-radius: 8px;
        overflow: hidden;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .single-product:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .product-img img {
        width: 100%;
        height: 250px;
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
    }

    /* Modals */
    .quickview-content {
        padding: 20px;
    }

    .quickview-ratting i.yellow {
        color: #ffc107;
    }

    .quickview-stock .text-success {
        color: #28a745;
    }

    .quickview-stock .text-danger {
        color: #dc3545;
    }

    /* Responsive */
    @media (max-width: 768px) {
        #gslider .carousel-caption h1 {
            font-size: 30px;
        }

        #gslider .carousel-caption p {
            font-size: 14px;
        }

        .filter-tope-group button {
            font-size: 12px;
            padding: 6px 12px;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js" defer></script>

<script>
    $(document).ready(function() {
        $('.isotope-grid').isotope({
            itemSelector: '.isotope-item',
            layoutMode: 'fitRows',
            percentPosition: true
        });

        $('.filter-tope-group button').on('click', function() {
            $('.filter-tope-group button').removeClass('active');
            $(this).addClass('active');
            $('.isotope-grid').isotope({
                filter: $(this).data('filter')
            });
        });

        // Autocomplete
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

        // Dropdowns
        $("ul.dropdown-menu [data-toggle='dropdown']").on("click", function(event) {
            event.preventDefault();
            event.stopPropagation();
            $(this).siblings().toggleClass("show");
            if (!$(this).next().hasClass('show')) {
                $(this).parents('.dropdown-menu').first().find('.show').removeClass("show");
            }
        });
    });
</script>
@endpush