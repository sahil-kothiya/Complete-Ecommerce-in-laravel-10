@extends('frontend.layouts.master')

@section('title', 'E-SHOP || PRODUCT PAGE')

@section('main-content')
<!-- Breadcrumbs Section -->
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
                                <option value="latest">Latest</option>
                                <option value="price_low_high">Price: Low → High</option>
                                <option value="price_high_low">Price: High → Low</option>
                            </select>
                            <select name="show" id="show" tabindex="4">
                                <option value="12">Show 12</option>
                                <option value="20">Show 20</option>
                            </select>
                        </div>
                    </div>

                    <!-- Initial Loading State - Products Load via AJAX -->
                    <div id="product-grids">
                        <div class="text-center py-5">
                            <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                            <p class="mt-3">Loading products...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ADDED: Hidden inputs to preserve filter state -->
    @if(!empty($applied_filters['price_range']))
    <input type="hidden" id="price_range" name="price_range" value="{{ $applied_filters['price_range'] }}">
    @endif
</form>

<!-- Product Modals (Dynamically Loaded via AJAX HTML) -->
{{-- Note: Modals are now included in the AJAX-fetched product-grid-html, so no static include here --}}

@endsection

@push('styles')
<style>
    /* Pagination Styling */
    .pagination {
        display: inline-flex;
    }

    /* Checkbox Styling */
    .single-widget.rating input[type="checkbox"],
    .single-widget.discount input[type="checkbox"],
    .single-widget.mainCategory input[type="checkbox"],
    .single-widget.rating-filter input[type="checkbox"],
    .single-widget.category input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        vertical-align: middle;
        accent-color: #f7941d;
    }

    /* Label Styling */
    .single-widget.rating label,
    .single-widget.discount label,
    .single-widget.active-filters label,
    .single-widget.category label {
        font-size: 14px;
        line-height: 1.8;
        color: #212121;
    }

    /* Shop Top Bar */
    .shop-top {
        padding: 15px 0;
        background: #f9f9f9;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Filter Status Indicator */
    .filter-status {
        display: flex;
        align-items: center;
    }

    .filter-status .badge {
        background-color: #17a2b8;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
    }

    /* Select Dropdown Styling */
    .shop-shorter select {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-right: 10px;
        background: #fff;
        font-size: 14px;
    }

    /* Product Card Styling */
    .single-post {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 15px;
        transition: box-shadow 0.3s ease;
        text-align: center;
    }

    .single-post:hover {
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    }

    /* Product Grid Container - Fixed for even spacing and responsiveness */
    .product-grid-container {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-bottom: 20px;
        justify-content: flex-start;
    }

    .product-card-container {
        flex: 1 1 calc(50% - 0.75rem); /* 2 columns on small screens */
        max-width: calc(50% - 0.75rem);
        margin: 0;
        padding: 0 !important; /* Override px-3 if causing issues */
    }

    @media (min-width: 768px) {
        .product-card-container {
            flex: 1 1 calc(33.333% - 1rem); /* 3 columns on medium */
            max-width: calc(33.333% - 1rem);
        }
    }

    @media (min-width: 992px) {
        .product-card-container {
            flex: 1 1 calc(25% - 1.125rem); /* 4 columns on large */
            max-width: calc(25% - 1.125rem);
        }
    }

    /* Card Body - Ensure full height and even distribution */
    .card {
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .card-body {
        flex-grow: 1;
        padding: 1rem !important; /* Consistent padding */
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    /* Product Image - Fixed sizing and fitting */
    .image img,
    .slider-image {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover !important; /* Prevent distortion */
        border-radius: 8px 8px 0 0;
        display: block;
    }

    /* Slider Fixes */
    .slider-wrapper {
        width: 100%;
        height: 100%;
        overflow: hidden;
        position: relative;
        border-radius: 8px 8px 0 0;
    }

    .slider-track {
        width: 100%;
        height: 100%;
        display: flex;
        transition: transform 0.5s ease-in-out;
    }

    /* Badge Positioning - Absolute within image container */
    .badge-status {
        position: absolute !important;
        top: 0.5rem;
        right: 0.5rem;
        z-index: 10;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    /* Price Styling - Consistent */
    .price {
        font-size: 1.25rem;
        font-weight: bold;
        color: #f7941d;
        margin-bottom: 0.5rem;
    }

    .price del {
        color: #999;
        font-size: 0.875rem;
        margin-left: 0.25rem;
    }

    /* Add to Cart Button - Full width and consistent */
    .add-to-cart,
    .btn-dark {
        width: 100%;
        margin-top: auto; /* Push to bottom */
        padding: 0.75rem;
        background: #333 !important;
        border: none !important;
        border-radius: 4px;
        color: white !important;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .add-to-cart:hover,
    .btn-dark:hover:not(.disabled) {
        background: #f7941d !important;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(247, 148, 29, 0.3);
    }

    /* Product Actions - Consistent spacing */
    .product-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 0.5rem;
        font-size: 0.875rem;
    }

    .product-actions a {
        color: #6c757d;
        text-decoration: none;
        transition: color 0.3s ease;
    }

    .product-actions a:hover {
        color: #f7941d;
    }

    /* Responsive Design Fixes */
    @media (max-width: 768px) {
        .shop-top {
            padding: 10px;
            flex-direction: column;
            gap: 10px;
        }

        .shop-shorter select {
            width: 100%;
            margin-bottom: 10px;
        }

        .product-card-container {
            flex: 1 1 100%; /* 1 column on mobile */
            max-width: 100%;
        }

        .single-post {
            padding: 10px;
        }

        .product-title {
            font-size: 14px;
        }

        .price {
            font-size: 14px;
        }
    }

    /* Loading State Enhancement */
    .loading-spinner {
        color: #f7941d;
    }

    /* Aspect Ratio Enforcement */
    [style*="aspect-ratio"] {
        position: relative !important;
        overflow: hidden !important;
    }

    [style*="aspect-ratio"]::before {
        content: "";
        display: block;
        padding-bottom: 100%; /* 1:1 ratio */
        float: left;
        width: 0;
    }

    [style*="aspect-ratio"] > * {
        position: absolute !important;
        top: 0;
        left: 0;
        width: 100% !important;
        height: 100% !important;
    }

    /* Fix for badges on small screens */
    @media (max-width: 576px) {
        .badge-status {
            font-size: 0.65rem;
            padding: 0.2rem 0.4rem;
            top: 0.25rem;
            right: 0.25rem;
        }
    }
</style>
@endpush