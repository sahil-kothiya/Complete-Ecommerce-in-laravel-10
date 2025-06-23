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
                            <li><a href="{{route('product-lists')}}"><i class="fa fa-th-list"></i></a></li>
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
                            {{ $products->appends(request()->query())->links() }}
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