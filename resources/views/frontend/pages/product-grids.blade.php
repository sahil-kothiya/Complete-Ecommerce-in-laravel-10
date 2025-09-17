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
                            <select name="sortBy" id="sortBy" tabindex="3">
                                <!-- Populated by JS -->
                            </select>
                            <select name="show" id="show" tabindex="4">
                                <!-- Populated by JS -->
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
<style>
    /* Pagination */
    .pagination { display: inline-flex; }
    .pagination .page-link { color: #f7941d; }
    .pagination .page-item.active .page-link { background-color: #f7941d; border-color: #f7941d; }
    .pagination .page-item.disabled .page-link { color: #6c757d; }

    /* Checkbox Styling */
    .filter-checkbox {
        width: 18px; height: 18px; margin-right: 10px; vertical-align: middle; accent-color: #f7941d;
    }

    /* Label Styling */
    .single-widget label {
        font-size: 14px; line-height: 1.8; color: #212121; cursor: pointer;
    }

    /* Shop Top Bar */
    .shop-top {
        padding: 15px 0; background: #f9f9f9; border-radius: 5px; margin-bottom: 20px;
        display: flex; justify-content: space-between; align-items: center;
    }

    /* Select Dropdown */
    .shop-shorter select {
        padding: 8px 15px; border: 1px solid #ddd; border-radius: 5px; margin-right: 10px;
        background: #fff; font-size: 14px;
    }

    /* Product Card */
    /* .product-card-container {
        flex: 1 1 calc(50% - 0.75rem); max-width: calc(50% - 0.75rem);
    }
    @media (min-width: 768px) {
        .product-card-container {
            flex: 1 1 calc(33.333% - 1rem); max-width: calc(33.333% - 1rem);
        }
    }
    @media (min-width: 992px) {
        .product-card-container {
            flex: 1 1 calc(25% - 1.125rem); max-width: calc(25% - 1.125rem);
        }
    } */

    .product-card {
        height: 100%; display: flex; flex-direction: column;
        transition: box-shadow 0.3s ease;
    }
    .product-card:hover { box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1); }

    .product-image-container { position: relative; }
    .slider-wrapper { width: 100%; height: 100%; overflow: hidden; border-radius: 8px 8px 0 0; }
    .slider-image {
        width: 100% !important; height: 100% !important; object-fit: cover !important;
        border-radius: 8px 8px 0 0;
    }

    .badge-status {
        position: absolute; top: 0.5rem; right: 0.5rem; z-index: 10;
        font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 4px;
    }

    .card-body {
        flex-grow: 1; padding: 1rem !important; display: flex; flex-direction: column;
        justify-content: space-between;
    }

    .price-container .current-price { font-size: 1.25rem; font-weight: bold; color: #f7941d; }
    .price-container .original-price { font-size: 0.875rem; color: #999; margin-left: 0.25rem; }

    .add-to-cart-btn {
        width: 100%; padding: 0.75rem; background: #333 !important; border-radius: 4px;
        color: white !important; font-weight: 600; transition: all 0.3s ease;
    }
    .add-to-cart-btn:hover:not(.disabled) {
        background: #f7941d !important; transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(247, 148, 29, 0.3);
    }
    .add-to-cart-btn.disabled { background: #6c757d !important; cursor: not-allowed; }

    .secondary-actions a { color: #6c757d; text-decoration: none; }
    .secondary-actions a:hover { color: #f7941d; }

    @media (max-width: 768px) {
        .shop-top { flex-direction: column; gap: 10px; padding: 10px; }
        .shop-shorter select { width: 100%; margin-bottom: 10px; }
        .product-card-container { flex: 1 1 100%; max-width: 100%; }
        .product-title { font-size: 14px; }
        .price-container .current-price { font-size: 14px; }
    }

    [style*="aspect-ratio"] {
        position: relative !important; overflow: hidden !important;
    }
    [style*="aspect-ratio"]::before {
        content: ""; display: block; padding-bottom: 100%; float: left; width: 0;
    }
    [style*="aspect-ratio"] > * {
        position: absolute !important; top: 0; left: 0; width: 100% !important; height: 100% !important;
    }
</style>
@endpush