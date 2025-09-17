@extends('frontend.layouts.master')

@section('title', 'E-SHOP || PRODUCT PAGE')

@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{ route('home') }}" tabindex="1">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0)" tabindex="2">Shop Grid</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Filter Form -->
<form id="productFilterForm" action="{{ url()->current() }}" method="GET">
    <section class="product-area shop-sidebar shop section">
        <div class="container">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="shop-sidebar-container">
                        @include('frontend.partials.shop-sidebar')
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9 col-md-8 col-12">
                    <!-- Shop Controls -->
                    <div class="shop-top">
                        <div class="shop-shorter">
                            <select name="sortBy" id="sortBy" tabindex="3" class="form-control">
                                <option value="">Sort By</option>
                                <option value="latest" {{ request('sortBy') == 'latest' ? 'selected' : '' }}>Latest</option>
                                <option value="price_low_high" {{ request('sortBy') == 'price_low_high' ? 'selected' : '' }}>Price: Low to High</option>
                                <option value="price_high_low" {{ request('sortBy') == 'price_high_low' ? 'selected' : '' }}>Price: High to Low</option>
                                <option value="name_a_z" {{ request('sortBy') == 'name_a_z' ? 'selected' : '' }}>Name: A to Z</option>
                                <option value="name_z_a" {{ request('sortBy') == 'name_z_a' ? 'selected' : '' }}>Name: Z to A</option>
                            </select>
                            <select name="show" id="show" tabindex="4" class="form-control">
                                <option value="">Show</option>
                                <option value="12" {{ request('show', 12) == 12 ? 'selected' : '' }}>12</option>
                                <option value="24" {{ request('show') == 24 ? 'selected' : '' }}>24</option>
                                <option value="36" {{ request('show') == 36 ? 'selected' : '' }}>36</option>
                                <option value="48" {{ request('show') == 48 ? 'selected' : '' }}>48</option>
                            </select>
                        </div>
                    </div>

                    <!-- Initial Loading State -->
                    <div id="product-grids">
                        <div class="text-center py-5">
                            <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                            <p class="mt-3">Loading products...</p>
                        </div>
                    </div>

                    <!-- Performance Info -->
                    <div id="performance-info" class="text-muted small text-center mt-2" style="display: none;"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Hidden inputs for initial state -->
    <input type="hidden" id="category-path" value="{{ $category_path ?? '' }}">
</form>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-grids.css') }}">
@endpush