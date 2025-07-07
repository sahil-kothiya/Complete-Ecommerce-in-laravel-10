@extends('frontend.layouts.master')

@section('title','E-SHOP || PRODUCT PAGE')

@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="index1.html">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="blog-single.html">Shop Grid</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<form action="{{route('shop.filter')}}" method="POST">
    @csrf
    <section class="product-area shop-sidebar shop section">
        <div class="container">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="shop-sidebar">
                        @include('frontend.partials.shop-sidebar')
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9 col-md-8 col-12">
                    <!-- Shop Controls -->
                    <div class="shop-top">
                        <div class="shop-shorter">
                            <div class="single-shorter">
                                <label>Show :</label>
                                <select class="show" name="show" onchange="this.form.submit();">
                                    <option value="">Default</option>
                                    @foreach([9, 15, 21, 30] as $num)
                                    <option value="{{$num}}" {{request('show') == $num ? 'selected' : ''}}>{{sprintf('%02d', $num)}}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="single-shorter">
                                <label>Sort By :</label>
                                <select class='sortBy' name='sortBy' onchange="this.form.submit();">
                                    <option value="">Default</option>
                                    @foreach(['title' => 'Name', 'price' => 'Price', 'category' => 'Category', 'brand' => 'Brand'] as $key => $label)
                                    <option value="{{$key}}" {{request('sortBy') == $key ? 'selected' : ''}}>{{$label}}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <ul class="view-mode">
                            <li class="active"><a href="javascript:void(0)"><i class="fa fa-th-large"></i></a></li>
                            <!-- <li><a href="{{route('product-lists')}}"><i class="fa fa-th-list"></i></a></li> -->
                        </ul>
                    </div>

                    <!-- Products Grid -->
                    <div class="row">
                        @forelse($products as $product)
                        @include('frontend.partials.product-card', compact('product'))
                        @empty
                        <div class="col-12">
                            <h4 class="text-warning text-center py-5">No products found.</h4>
                        </div>
                        @endforelse
                    </div>

                    <!-- Pagination -->
                    @if($products->hasPages())
                    <div class="row">
                        <div class="col-12 d-flex justify-content-center">
                            {{ $products->appends(request()->query())->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</form>

<!-- Product Modals -->
@include('frontend.partials.product-modals', compact('products'))

@endsection

@push('styles')
<style>
    /* .botm row {
        margin: 3% 1% 0% 3%;
    } */

    .filter_button {
        background: #F7941D;
        padding: 8px 16px;
        margin-top: 10px;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }

    .filter_button:hover {
        background: #e6830a;
    }

    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
        padding: 20px 0;
        list-style: none;
    }

    .pagination li a,
    .pagination li span {
        display: inline-block;
        padding: 8px 14px;
        font-size: 14px;
        color: #333;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.3s ease;
        min-width: 36px;
        text-align: center;
    }

    .pagination li a:hover,
    .pagination li.active span,
    .pagination li.active a {
        background-color: #f7941d;
        color: #fff;
        border-color: #f7941d;
    }

    .pagination .disabled span,
    .pagination .disabled a {
        cursor: not-allowed;
        background-color: #f9f9f9;
        color: #ccc;
        border-color: #eee;
    }

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

    /* Slider */
    #gslider .carousel-inner {
        background: #000;
        height: 550px;
    }

    #gslider img {
        width: 100%;
        height: 550px;
        object-fit: cover;
        opacity: 0.85;
    }

    #gslider .carousel-caption {
        bottom: 50%;
        transform: translateY(50%);
        text-align: left;
    }

    #gslider .carousel-caption h1 {
        font-size: 48px;
        font-weight: bold;
        color: #F7941D;
    }

    #gslider .carousel-caption p {
        font-size: 18px;
        color: white;
        margin: 20px 0;
    }

    #gslider .carousel-indicators {
        bottom: 20px;
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
    $(document).ready(function() {
        // Price range slider
        if ($("#slider-range").length > 0) {
            const maxValue = parseInt($("#slider-range").data('max')) || 500;
            const minValue = parseInt($("#slider-range").data('min')) || 0;
            const currency = $("#slider-range").data('currency') || '$';
            let priceRange = minValue + '-' + maxValue;

            if ($("#price_range").val()) {
                priceRange = $("#price_range").val().trim();
            }

            const price = priceRange.split('-');

            $("#slider-range").slider({
                range: true,
                min: minValue,
                max: maxValue,
                values: price.map(p => parseInt(p)),
                slide: function(event, ui) {
                    $("#amount").val(currency + ui.values[0] + " - " + currency + ui.values[1]);
                    $("#price_range").val(ui.values[0] + "-" + ui.values[1]);
                }
            });

            $("#amount").val(currency + $("#slider-range").slider("values", 0) +
                " - " + currency + $("#slider-range").slider("values", 1));
        }
    });
</script>
@endpush