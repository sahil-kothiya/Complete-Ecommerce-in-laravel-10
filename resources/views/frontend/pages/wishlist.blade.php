@extends('frontend.layouts.master')

@section('title', 'Wishlist Page')

@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{ route('home') }}">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0);">Wishlist</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Breadcrumbs -->

<!-- Shopping Wishlist -->
<div class="shopping-cart section">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <table class="table shopping-summery">
                    <thead>
                        <tr class="main-hading">
                            <th>PRODUCT</th>
                            <th>NAME</th>
                            <th class="text-center">PRICE</th>
                            <th class="text-center">ADD TO CART</th>
                            <th class="text-center"><i class="ti-trash remove-icon"></i></th>
                        </tr>
                    </thead>
                    <tbody id="wishlist-body">
                        @if($wishlistItems->count() > 0)
                            @foreach($wishlistItems as $wishlist)
                                {{--  ←  SAME LOOP AS BEFORE  ←  --}}
                                @php
                                    $originalPrice = $wishlist->variant?->price ?? $wishlist->product->base_price ?? 0;
                                    $discount      = $wishlist->variant?->discount ?? $wishlist->product->base_discount ?? 0;
                                    $variantDetails = $wishlist->variant?->display_name ?? null;

                                    $discountedPrice = $originalPrice * (1 - $discount / 100);
                                    $isDiscounted    = $discountedPrice < $originalPrice;

                                    $images = $wishlist->variant?->images->isNotEmpty()
                                                ? $wishlist->variant->images
                                                : ($wishlist->product->images ?? collect());
                                @endphp

                                <tr data-wishlist-id="{{ $wishlist->id }}">
                                    {{-- IMAGE SLIDER (unchanged) --}}
                                    <td class="image product-slider" data-title="No">
                                        <div class="slider-wrapper" data-images="{{ $images->count() }}">
                                            <div class="slider-container">
                                                @forelse($images as $index => $image)
                                                    <img src="{{ $image->url ?? asset('default.jpg') }}"
                                                         alt="{{ $wishlist->product->title ?? 'Product' }}"
                                                         class="slider-img" data-index="{{ $index }}"
                                                         onerror="this.src='{{ asset('default.jpg') }}'">
                                                @empty
                                                    <img src="{{ asset('default.jpg') }}"
                                                         alt="{{ $wishlist->product->title ?? 'Product' }}"
                                                         class="slider-img" data-index="0">
                                                @endforelse
                                            </div>
                                            @if($images->count() > 1)
                                                <div class="slider-indicators">
                                                    @foreach($images as $index => $image)
                                                        <span class="indicator {{ $index == 0 ? 'active' : '' }}"
                                                              data-index="{{ $index }}"></span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </td>

                                    {{-- NAME & VARIANT --}}
                                    <td class="product-des" data-title="Description">
                                        <p class="product-name">
                                            <a href="{{ route('product-detail', $wishlist->product->slug ?? '') }}"
                                               target="_blank">
                                                {{ $wishlist->product->title ?? 'Product' }}
                                            </a>
                                        </p>
                                        <p class="product-des">{!! $wishlist->product->summary ?? '' !!}</p>
                                        @if($wishlist->variant && $variantDetails && $variantDetails !== 'Variant #' . $wishlist->variant->id)
                                            <small class="text-muted">Variant: {{ $variantDetails }}</small>
                                        @endif
                                    </td>

                                    {{-- PRICE --}}
                                    <td class="price text-center" data-title="Price">
                                        <div class="price-info">
                                            @if($isDiscounted && $originalPrice > 0)
                                                <span class="text-danger font-weight-bold">
                                                    ${{ number_format($discountedPrice, 2) }}
                                                </span><br>
                                                <small><del class="text-muted">${{ number_format($originalPrice, 2) }}</del></small>
                                                <small class="text-success ml-2">{{ $discount }}% off</small>
                                            @else
                                                <span>${{ number_format($discountedPrice, 2) }}</span>
                                            @endif
                                            <span class="price-tooltip"
                                                  data-toggle="tooltip"
                                                  data-placement="top"
                                                  title="Price may vary due to discounts, variants or stock.">
                                                <i class="ti-info-alt"></i>
                                            </span>
                                        </div>
                                    </td>

                                    {{-- ADD TO CART --}}
                                    <td class="text-center" data-title="Add to Cart">
                                        <form action="{{ route('single-add-to-cart') }}"
                                              method="POST"
                                              class="add-to-cart-form d-inline">
                                            @csrf
                                            <input type="hidden" name="slug" value="{{ $wishlist->product->slug }}">
                                            <input type="hidden" name="quant[1]" value="1">
                                            @if($wishlist->variant_id)
                                                <input type="hidden" name="variant_id" value="{{ $wishlist->variant_id }}">
                                            @endif
                                            <button type="submit" class="btn btn-sm text-white">
                                                <i class="ti-shopping-cart"></i> Add
                                            </button>
                                        </form>
                                    </td>

                                    {{-- REMOVE --}}
                                    <td class="action text-center" data-title="Remove">
                                        <a href="javascript:void(0)"
                                           class="remove-wishlist-item text-danger"
                                           data-wishlist-id="{{ $wishlist->id }}"
                                           data-url="{{ route('wishlist-delete', $wishlist->id) }}">
                                            <i class="ti-trash remove-icon"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <p class="lead">Your wishlist is empty.</p>
                                    <a href="{{ route('product-grids') }}" class="btn">Continue Shopping</a>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!--/ End Wishlist -->

<!-- Services -->
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

@include('frontend.layouts.newsletter')
@endsection

@push('styles')
<style>
    .slider-wrapper {
        position: relative;
        width: 80px;
        height: 80px;
        overflow: hidden;
        border-radius: 4px;
        cursor: pointer;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
    }

    .slider-container {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        transition: transform 0.3s ease-in-out;
    }

    .slider-img {
        flex: 0 0 100%;
        width: 80px;
        height: 80px;
        object-fit: cover;
        display: block;
    }

    .slider-indicators {
        position: absolute;
        bottom: 4px;
        left: 50%;
        transform: translateX(-50%);
        display: flex;
        gap: 2px;
        z-index: 10;
    }

    .indicator {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.5);
        cursor: pointer;
        transition: background 0.3s ease;
    }

    .indicator.active {
        background: rgba(255, 255, 255, 0.9);
    }

    .slider-wrapper:hover .indicator {
        background: rgba(255, 255, 255, 0.7);
    }

    .slider-wrapper:hover .indicator.active {
        background: #fff;
    }

    /* Hide indicators if only one image */
    .slider-wrapper[data-images="1"] .slider-indicators,
    .slider-wrapper[data-images="0"] .slider-indicators {
        display: none;
    }

    /* Price Tooltip Styling */
    .price-info {
        position: relative;
    }

    .price-tooltip {
        margin-left: 5px;
        color: #555;
        cursor: pointer;
        font-size: 14px;
    }

    .price-tooltip:hover {
        color: #007bff;
    }

    .tooltip-inner {
        max-width: 250px;
        background-color: #fff;
        color: #000;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .bs-tooltip-top .arrow::before {
        border-top-color: #ccc;
    }

    .remove-wishlist-item {
        transition: color 0.2s;
        font-size: 18px;
    }

    .remove-wishlist-item:hover {
        color: #dc3545 !important;
    }

    .add-to-cart-form button {
        background: #F7941D;
        border: none;
        padding: 8px 15px;
        font-size: 13px;
        transition: all 0.3s;
    }

    .add-to-cart-form button:hover {
        background: #e6830b;
        transform: translateY(-1px);
    }

    .add-to-cart-form button:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }

    .product-name a {
        font-weight: 600;
        color: #333;
    }

    .product-name a:hover {
        color: #F7941D;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    console.log('Wishlist page initialized');

    // Initialize Bootstrap tooltips
    $('[data-toggle="tooltip"]').tooltip();

    // Image Slider (EXACT MATCH WITH CART)
    $('.slider-wrapper').each(function() {
        const wrapper = $(this);
        const container = wrapper.find('.slider-container');
        const images = wrapper.find('.slider-img');
        const indicators = wrapper.find('.indicator');
        const total = images.length;
        
        console.log('Slider initialized with ' + total + ' images');
        
        if (total <= 1) return;

        let current = 0;
        const show = (i) => {
            const offset = i * 100;
            container.css('transform', `translateX(-${offset}%)`);
            indicators.removeClass('active').eq(i).addClass('active');
            current = i;
        };

        // Click on indicators
        indicators.on('click', function(e) {
            e.stopPropagation();
            show($(this).data('index'));
        });

        // Auto-play on hover
        let interval;
        wrapper.on('mouseenter', () => {
            clearInterval(interval);
            interval = setInterval(() => {
                show((current + 1) % total);
            }, 1500);
        }).on('mouseleave', () => {
            clearInterval(interval);
            show(0);
        });

        // Touch swipe support
        let startX = 0;
        wrapper.on('touchstart', e => {
            startX = e.originalEvent.touches[0].clientX;
        });
        wrapper.on('touchend', e => {
            const endX = e.originalEvent.changedTouches[0].clientX;
            const diff = startX - endX;
            if (Math.abs(diff) > 30) {
                if (diff > 0) {
                    show((current + 1) % total);
                } else {
                    show((current - 1 + total) % total);
                }
            }
        });
    });

    // Add to Cart with AJAX
    $('.add-to-cart-form').on('submit', function(e) {
        e.preventDefault();
        
        const form = $(this);
        const btn = form.find('button');
        const originalHtml = btn.html();
        
        btn.prop('disabled', true).html('<i class="ti-reload"></i> Adding...');

        $.ajax({
            url: form.attr('action'),
            method: 'POST',
            data: form.serialize(),
            success: function(response) {
                console.log('Add to cart response:', response);
                
                if (response.success) {
                    // Remove the row
                    form.closest('tr').fadeOut(300, function() {
                        $(this).remove();
                        checkEmptyWishlist();
                    });
                    swal("Success!", response.success || "Added to cart!", "success");
                } else {
                    swal("Error!", response.error || "Failed to add to cart.", "error");
                    btn.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr, status, error});
                console.error('Response:', xhr.responseText);
                swal("Error!", "Network error. Please try again.", "error");
                btn.prop('disabled', false).html(originalHtml);
            }
        });
    });

    // Remove from Wishlist
    $('.remove-wishlist-item').on('click', function(e) {
        e.preventDefault();
        
        const url = $(this).data('url');
        const row = $(this).closest('tr');

        swal({
            title: "Remove from wishlist?",
            text: "This item will be removed from your wishlist.",
            icon: "warning",
            buttons: true,
            dangerMode: true,
        }).then((willDelete) => {
            if (willDelete) {
                $.ajax({
                    url: url,
                    method: 'POST',
                    data: {
                        _token: $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        console.log('Remove response:', response);
                        
                        if (response.success) {
                            row.fadeOut(300, function() {
                                $(this).remove();
                                checkEmptyWishlist();
                            });
                            swal("Removed!", response.success || "Item removed from wishlist.", "success");
                        } else {
                            swal("Error!", response.error || "Failed to remove item.", "error");
                        }
                    },
                    error: function(xhr) {
                        console.error('Remove error:', xhr.responseText);
                        swal("Error!", "Request failed. Please try again.", "error");
                    }
                });
            }
        });
    });

    function checkEmptyWishlist() {
        const remainingItems = $('#wishlist-body tr[data-wishlist-id]').length;
        console.log('Remaining wishlist items:', remainingItems);
        
        if (remainingItems === 0) {
            $('#wishlist-body').html(`
                <tr>
                    <td colspan="5" class="text-center py-5">
                        <p class="lead">Your wishlist is empty.</p>
                        <a href="{{ route('product-grids') }}" class="btn">Continue Shopping</a>
                    </td>
                </tr>
            `);
        }
    }
});
</script>
@endpush