@extends('frontend.layouts.master')

@section('meta')
    <!-- Meta Tags for SEO and Social Sharing -->
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="keywords" content="online shop, purchase, cart, ecommerce site, best online shopping">
    <meta name="description" content="{{ $product_detail->summary }}">
    <meta property="og:url" content="{{ route('product-detail', $product_detail->slug) }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $product_detail->title }}">
    <meta property="og:image" content="{{ $product_detail->photo }}">
    <meta property="og:description" content="{{ $product_detail->description }}">
@endsection

@section('title', 'E-SHOP || PRODUCT DETAIL')

@section('main-content')
    <!-- Breadcrumbs Section -->
    <div class="breadcrumbs">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="bread-inner">
                        <ul class="bread-list">
                            <li><a href="{{ route('home') }}" tabindex="13">Home<i class="ti-arrow-right"></i></a></li>
                            <li class="active"><a href="" tabindex="14">Shop Details</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- End Breadcrumbs -->

    <!-- Product Detail Section -->
    <section class="shop single section">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="row">
                        <!-- Product Gallery Column -->
                        <div class="col-lg-6 col-12">
                            <div class="product-gallery">
                                <!-- Main Image Viewer -->
                                @php
                                    $mainImagePath = $product_detail->images->isNotEmpty() 
                                        ? 'storage/' . ltrim($product_detail->images[0]->image_path, '/')
                                        : asset('images/no-image.png');
                                @endphp
                                <div class="main-image-container position-relative mb-3" style="display: flex; align-items: center; justify-content: center;">
                                    <div id="imageLoadingOverlay" class="loading-overlay d-none position-absolute" style="top: 0; left: 0; right: 0; bottom: 0; background: rgba(255,255,255,0.8); display: flex; align-items: center; justify-content: center; z-index: 10;">
                                        <div class="spinner-border text-primary" role="status" style="width: 2rem; height: 2rem;"></div>
                                    </div>
                                    <img src="{{ asset($mainImagePath) }}"
                                         alt="{{ $product_detail->title }}"
                                         class="main-image img-fluid"
                                         id="mainImage"
                                         tabindex="15"
                                         style="max-height: 500px; object-fit: contain; border-radius: 10px; width: 100%; min-width: 300px; border: 2px solid #eee;"
                                         data-original-src="{{ asset($mainImagePath) }}">
                                </div>

                                <!-- Thumbnail Carousel -->
                                <div class="thumbnail-carousel mt-2">
                                    <div class="thumbnails d-flex flex-row flex-nowrap" id="thumbnailContainer">
                                        @forelse($product_detail->images as $index => $image)
                                            @php
                                                $imgPath = $image->image_path ? 'storage/' . ltrim($image->image_path, '/') : asset('images/no-image.png');
                                            @endphp
                                            <div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
                                                <img src="{{ asset($imgPath) }}"
                                                     alt="Thumbnail {{ $index + 1 }}"
                                                     tabindex="{{ 16 + $index }}"
                                                     class="img-fluid thumbnail-image {{ $index == 0 ? 'active' : '' }}"
                                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid {{ $index == 0 ? '#2874f0' : '#eee' }};"
                                                     data-index="{{ $index }}">
                                            </div>
                                        @empty
                                            <div class="thumbnail-item mx-1" style="flex: 0 0 auto;">
                                                <img src="{{ asset('images/no-image.png') }}"
                                                     alt="No Image"
                                                     tabindex="16"
                                                     class="img-fluid thumbnail-image active"
                                                     style="width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid #2874f0;"
                                                     data-index="0">
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Product Description Column -->
                        <div class="col-lg-6 col-12">
                            <div class="product-des">
                                <!-- Product Title and Rating -->
                                <div class="short">
                                    <h4 tabindex="17">{{ $product_detail->title }}</h4>
                                    <div class="rating-main">
                                        <ul class="rating">
                                            @php $rate = ceil($product_detail->getReview->avg('rate')); @endphp
                                            @for($i = 1; $i <= 5; $i++)
                                                <li><i class="fa {{ $rate >= $i ? 'fa-star' : 'fa-star-o' }}"></i></li>
                                            @endfor
                                        </ul>
                                        <a href="#reviews" class="total-review" tabindex="18">({{ $product_detail->getReview->count() }}) Review</a>
                                    </div>

                                    <!-- Price Display -->
                                    <div class="price-container" id="priceContainer">
                                        <p class="price" tabindex="19">
                                            <span class="text-danger font-weight-bold" id="displayPrice">
                                                ${{ number_format($product_detail->discounted_price, 2) }}
                                            </span>
                                            @if($product_detail->discount_percentage > 0)
                                                <br>
                                                <small><s class="text-muted" id="originalPrice">${{ number_format($product_detail->original_price, 2) }}</s></small>
                                                <small class="text-success ml-2" id="discountBadge">{{ $product_detail->discount_percentage }}% off</small>
                                            @endif
                                        </p>
                                    </div>

                                    <p class="description" tabindex="20">{!! $product_detail->summary !!}</p>
                                </div>

                                <!-- Variant Selection -->
                                @if($product_detail->has_variants && $product_detail->variants->count() > 0)
                                    <div class="variant-selection-container mt-4" id="variantContainer">
                                        @php $tabindex = 21; @endphp
                                        @foreach($variantTypes as $type)
                                            <div class="variant-group mb-3">
                                                <h6 class="mb-2">{{ $type->display_name }}</h6>
                                                <div class="variant-options d-flex flex-wrap gap-2">
                                                    @foreach($type->options as $option)
                                                        @php
                                                            $isColor = strtolower($type->name) === 'color';
                                                            $colorCode = $option->hex_color ?? ($fallbackMap[strtolower($option->value)] ?? '');
                                                        @endphp
                                                        @if($isColor && $colorCode)
                                                            <!-- Color Swatch UI -->
                                                            <div class="color-swatch position-relative" tabindex="{{ $tabindex++ }}">
                                                                <input type="radio"
                                                                       id="color-{{ $type->id }}-{{ $option->id }}"
                                                                       name="{{ $type->name }}"
                                                                       value="{{ $option->value }}"
                                                                       class="sr-only"
                                                                       data-variant-type="{{ $type->name }}"
                                                                       data-variant-value="{{ $option->value }}">
                                                                <label for="color-{{ $type->id }}-{{ $option->id }}"
                                                                       class="color-swatch-label"
                                                                       style="background-color: {{ $colorCode }}; border: 2px solid #fff;">
                                                                    <span class="sr-only">{{ $option->display_value ?? $option->value }}</span>
                                                                </label>
                                                                <div class="color-swatch-checkmark d-none position-absolute">
                                                                    <i class="fa fa-check"></i>
                                                                </div>
                                                            </div>
                                                        @else
                                                            <!-- Text Button UI -->
                                                            <button type="button"
                                                                    class="variant-option-btn btn btn-outline-secondary"
                                                                    data-variant-type="{{ $type->name }}"
                                                                    data-variant-value="{{ $option->value }}"
                                                                    style="min-width: 80px; padding: 8px 16px; border-radius: 4px; font-size: 14px;"
                                                                    tabindex="{{ $tabindex++ }}">
                                                                {{ $option->display_value ?? $option->value }}
                                                            </button>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <!-- Stock Alert -->
                                <div id="stockAlert" class="alert alert-danger d-none mt-3" role="alert">
                                    <i class="fa fa-exclamation-circle"></i>
                                    <span id="stockAlertMessage">This variant is currently out of stock</span>
                                </div>

                                <!-- Add to Cart Form -->
                                <div class="product-buy">
                                    <form action="{{ route('single-add-to-cart') }}" method="POST" id="addToCartForm">
                                        @csrf
                                        <input type="hidden" name="slug" value="{{ $product_detail->slug }}">
                                        <input type="hidden" name="variant_id" id="selectedVariantId" value="">

                                        <!-- Quantity Selector -->
                                        <div class="quantity" id="quantitySection">
                                            <h6 class="text-center" tabindex="32">Quantity:</h6>
                                            <div class="input-group">
                                                <div class="button minus">
                                                    <button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]" tabindex="33">
                                                        <i class="ti-minus"></i>
                                                    </button>
                                                </div>
                                                <input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1" id="quantity" tabindex="34">
                                                <div class="button plus">
                                                    <button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]" tabindex="35">
                                                        <i class="ti-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Action Buttons -->
                                        <div class="add-to-cart mt-4">
                                            <button type="submit" class="btn" id="addToCartBtn" tabindex="36">Add to cart</button>
                                            <a href="{{ route('add-to-wishlist', $product_detail->slug) }}" class="btn min" tabindex="37"><i class="ti-heart"></i></a>
                                        </div>
                                    </form>

                                    <!-- Product Meta -->
                                    <p class="cat" tabindex="38">Category: <a href="{{ route('product-cat', $product_detail->cat_info['slug']) }}" tabindex="39">{{ $product_detail->cat_info['title'] }}</a></p>
                                    @if($product_detail->sub_cat_info)
                                        <p class="cat mt-1" tabindex="40">Sub Category: <a href="{{ route('product-cat', [$product_detail->cat_info['slug'], $product_detail->sub_cat_info['slug']]) }}" tabindex="41">{{ $product_detail->sub_cat_info['title'] }}</a></p>
                                    @endif
                                    <p class="availability" tabindex="42">SKU: <span id="displaySku">{{ $product_detail->current_sku }}</span></p>
                                    <p class="availability" tabindex="43">Stock: <span id="displayStock">
                                        @if($product_detail->current_stock > 0)
                                            <span class="badge badge-success">{{ $product_detail->current_stock }}</span>
                                        @else
                                            <span class="badge badge-danger">Out of Stock</span>
                                        @endif
                                    </span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Tabs (Description and Reviews) -->
                    <div class="row">
                        <div class="col-12">
                            <div class="product-info">
                                <div class="nav-main">
                                    <ul class="nav nav-tabs" id="myTab" role="tablist">
                                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#description" role="tab" tabindex="44">Description</a></li>
                                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#reviews" role="tab" tabindex="45">Reviews</a></li>
                                    </ul>
                                </div>
                                <div class="tab-content" id="myTabContent">
                                    <!-- Description Tab -->
                                    <div class="tab-pane fade show active" id="description" role="tabpanel">
                                        <div class="tab-single">
                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="single-des">
                                                        <p tabindex="46">{!! $product_detail->description !!}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Reviews Tab -->
                                    <div class="tab-pane fade" id="reviews" role="tabpanel">
                                        <div class="tab-single review-panel">
                                            <div class="row">
                                                <div class="col-12">
                                                    <!-- Add Review Form -->
                                                    <div class="comment-review">
                                                        <div class="add-review">
                                                            <h5 tabindex="47">Add A Review</h5>
                                                            <p tabindex="48">Your email address will not be published. Required fields are marked</p>
                                                        </div>
                                                        <h4 tabindex="49">Your Rating <span class="text-danger">*</span></h4>
                                                        <div class="review-inner">
                                                            @auth
                                                                <form class="form" method="post" action="{{ route('review.store', $product_detail->slug) }}">
                                                                    @csrf
                                                                    <input type="hidden" name="slug" value="{{ $product_detail->slug }}">
                                                                    <div class="row">
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="rating_box">
                                                                                <div class="star-rating">
                                                                                    <div class="star-rating__wrap">
                                                                                        @for($i = 5; $i >= 1; $i--)
                                                                                            <input class="star-rating__input" id="star-rating-{{ $i }}" type="radio" name="rate" value="{{ $i }}" tabindex="{{ 50 + (5 - $i) }}">
                                                                                            <label class="star-rating__ico fa fa-star-o" for="star-rating-{{ $i }}" title="{{ $i }} out of 5 stars"></label>
                                                                                        @endfor
                                                                                        @error('rate')
                                                                                            <span class="text-danger">{{ $message }}</span>
                                                                                        @enderror
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="form-group">
                                                                                <label for="review-text" tabindex="55">Write a review</label>
                                                                                <textarea name="review" id="review-text" rows="6" placeholder="" tabindex="56"></textarea>
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-lg-12 col-12">
                                                                            <div class="form-group button5">
                                                                                <button type="submit" class="btn" tabindex="57">Submit</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </form>
                                                            @else
                                                                <p class="text-center p-5" tabindex="58">
                                                                    You need to <a href="{{ route('login.form') }}" style="color:rgb(54, 54, 204)" tabindex="59">Login</a> OR <a style="color:blue" href="{{ route('register.form') }}" tabindex="60">Register</a>
                                                                </p>
                                                            @endauth
                                                        </div>
                                                    </div>

                                                    <!-- Display Reviews -->
                                                    <div class="ratting-main">
                                                        <div class="avg-ratting">
                                                            <h4 tabindex="61">{{ ceil($product_detail->getReview->avg('rate')) }} <span>(Overall)</span></h4>
                                                            <span tabindex="62">Based on {{ $product_detail->getReview->count() }} Comments</span>
                                                        </div>
                                                        @foreach($product_detail->getReview as $index => $data)
                                                            <div class="single-rating">
                                                                <div class="rating-author">
                                                                    <img src="{{ $data->user_info['photo'] ?? asset('backend/img/avatar.png') }}"
                                                                         alt="{{ $data->user_info['name'] }}"
                                                                         tabindex="{{ 63 + $index * 4 }}">
                                                                </div>
                                                                <div class="rating-des">
                                                                    <h6 tabindex="{{ 64 + $index * 4 }}">{{ $data->user_info['name'] }}</h6>
                                                                    <div class="ratings">
                                                                        <ul class="rating">
                                                                            @for($i = 1; $i <= 5; $i++)
                                                                                <li><i class="fa {{ $data->rate >= $i ? 'fa-star' : 'fa-star-o' }}"></i></li>
                                                                            @endfor
                                                                        </ul>
                                                                        <div class="rate-count" tabindex="{{ 65 + $index * 4 }}">(<span>{{ $data->rate }}</span>)</div>
                                                                    </div>
                                                                    <p tabindex="{{ 66 + $index * 4 }}">{{ $data->review }}</p>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pass Variant Data to JavaScript -->
    <script>
        window.productVariants = @json($processedVariants);
        window.productSlug = "{{ $product_detail->slug }}";
        window.hasVariants = {{ $product_detail->has_variants ? 'true' : 'false' }};
        window.variantTypeOrder = @json($variantTypes->pluck('name')->toArray());
    </script>
@endsection

@push('styles')
    <style>
        /* === Product Detail Page Styles === */

        /* Variant Selection Container */
        .variant-selection-container {
            background: #fff;
            padding: 15px 0;
            border-top: 1px solid #f0f0f0;
            border-bottom: 1px solid #f0f0f0;
        }

        .variant-group h6 {
            font-size: 14px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 12px;
            text-transform: uppercase;
        }

        .variant-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* Text Variant Button */
        .variant-option-btn {
            min-width: 80px;
            padding: 10px 20px;
            border: 1px solid #c2c2c2;
            border-radius: 2px;
            background: #fff;
            color: #212121;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .variant-option-btn:hover:not(.disabled):not(.active) {
            border-color: #2874f0;
            box-shadow: 0 2px 4px rgba(40, 116, 240, 0.1);
            transform: translateY(-1px);
        }

        .variant-option-btn.active {
            border: 2px solid #2874f0;
            background: #e8f0fe;
            color: #2874f0;
            font-weight: 600;
        }

        .variant-option-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: #fafafa;
            color: #878787;
        }

        /* Color Swatch Styles */
        .color-swatch {
            position: relative;
            width: 32px;
            height: 32px;
        }

        .color-swatch-label {
            display: block;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            cursor: pointer;
            border: 2px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
        }

        .color-swatch input:checked + .color-swatch-label {
            border-color: #2874f0;
            box-shadow: 0 0 0 2px #2874f0;
            transform: scale(1.1);
        }

        .color-swatch-checkmark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #fff;
            font-size: 10px;
            font-weight: bold;
        }

        /* Stock Alert */
        .stock-alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 12px 16px;
            color: #856404;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Price Display */
        .price-container .price span {
            font-size: 28px;
            font-weight: 500;
        }

        .price-container .price small {
            font-size: 16px;
            margin-left: 8px;
        }

        /* Add to Cart Button */
        .add-to-cart .btn {
            background: #ff9f00;
            border: none;
            color: #fff;
            padding: 12px 40px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 2px;
            transition: all 0.2s ease;
        }

        .add-to-cart .btn:hover:not(:disabled) {
            background: #e68a00;
            box-shadow: 0 4px 8px rgba(255, 159, 0, 0.3);
            transform: translateY(-1px);
        }

        /* Quantity Selector */
        .quantity .input-group {
            display: inline-flex;
            align-items: center;
            border: 1px solid #c2c2c2;
            border-radius: 2px;
        }

        .quantity .btn-number {
            background: #fff;
            border: none;
            color: #2874f0;
            padding: 8px 12px;
            font-size: 18px;
        }

        /* Responsive Adjustments */
        @media (max-width: 991px) {
            .variant-option-btn {
                min-width: 70px;
                padding: 8px 16px;
                font-size: 13px;
            }
        }

        @media (max-width: 767px) {
            .add-to-cart .btn {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        /**
         * Product Detail Page JavaScript
         * Handles variant selection, quantity controls, and image gallery functionality
         */
        (function () {
            'use strict';

            // === Global Variables ===
            const state = {
                variants: window.productVariants || [],
                hasVariants: window.hasVariants || false,
                productSlug: window.productSlug || '',
                variantTypeOrder: window.variantTypeOrder || [],
                selectedOptions: {},
                currentVariant: null,
                isUpdating: false,
                lastChangedType: null
            };

            // === DOM Elements ===
            const elements = {
                qtyInput: document.getElementById('quantity'),
                minusBtn: document.querySelector('.button.minus .btn-number'),
                plusBtn: document.querySelector('.button.plus .btn-number'),
                mainImage: document.getElementById('mainImage'),
                thumbnailContainer: document.getElementById('thumbnailContainer'),
                imageLoadingOverlay: document.getElementById('imageLoadingOverlay'),
                priceContainer: document.getElementById('priceContainer'),
                displayPrice: document.getElementById('displayPrice'),
                originalPrice: document.getElementById('originalPrice'),
                discountBadge: document.getElementById('discountBadge'),
                displaySku: document.getElementById('displaySku'),
                displayStock: document.getElementById('displayStock'),
                stockAlert: document.getElementById('stockAlert'),
                addToCartBtn: document.getElementById('addToCartBtn'),
                quantitySection: document.getElementById('quantitySection'),
                variantIdInput: document.getElementById('selectedVariantId')
            };

            // === Utility Functions ===
            /**
             * Normalizes a value to a comparable string
             * @param {*} value - The value to normalize
             * @returns {string} Normalized string
             */
            function normalizeValue(value) {
                return value === null || value === undefined ? '' : String(value).trim().toLowerCase();
            }

            /**
             * Normalizes an options object
             * @param {Object} obj - Options object
             * @returns {Object} Normalized options
             */
            function normalizeOptions(obj) {
                return Object.entries(obj || {}).reduce((acc, [key, value]) => {
                    acc[key.toLowerCase()] = normalizeValue(value);
                    return acc;
                }, {});
            }

            /**
             * Normalizes variant values
             * @param {Object} variant - Variant object
             * @returns {Object} Normalized variant values
             */
            function getNormalizedVariantValues(variant) {
                const raw = typeof variant.variant_values === 'string'
                    ? JSON.parse(variant.variant_values)
                    : variant.variant_values || {};
                return Object.entries(raw).reduce((acc, [key, value]) => {
                    acc[key.toLowerCase()] = normalizeValue(value);
                    return acc;
                }, {});
            }

            // === Quantity Control Functions ===
            /**
             * Updates the minus button state based on quantity
             */
            function updateMinusButtonState() {
                if (!elements.minusBtn || !elements.qtyInput) return;
                const min = parseInt(elements.qtyInput.getAttribute('data-min')) || 1;
                const currentValue = parseInt(elements.qtyInput.value) || 1;
                elements.minusBtn.disabled = currentValue <= min;
            }

            /**
             * Updates the maximum quantity based on variant stock
             */
            function updateQuantityMax() {
                if (!elements.qtyInput || !state.currentVariant) return;
                const max = parseInt(state.currentVariant.stock) || 0;
                elements.qtyInput.setAttribute('data-max', max);
                const value = parseInt(elements.qtyInput.value) || 1;
                if (value > max && max > 0) {
                    elements.qtyInput.value = max;
                }
                updateMinusButtonState();
            }

            // === Variant Selection Handlers ===
            /**
             * Handles variant button click
             * @param {Event} event - Click event
             */
            function handleVariantButtonClick(event) {
                if (state.isUpdating) return;
                const { variantType, variantValue } = event.currentTarget.dataset;
                state.lastChangedType = variantType.toLowerCase();
                state.selectedOptions[variantType] = variantValue;

                document.querySelectorAll(`[data-variant-type="${variantType}"].variant-option-btn`)
                    .forEach(btn => btn.classList.remove('active'));
                event.currentTarget.classList.add('active');

                updateVariantWithLoading();
            }

            /**
             * Handles color swatch selection
             * @param {Event} event - Click or change event
             * @param {HTMLElement} [swatch] - Optional color swatch element
             */
            function handleColorSwatchSelection(event, swatch = null) {
                if (state.isUpdating) return;

                // Safely handle event methods
                event?.preventDefault?.();
                event?.stopPropagation?.();

                // Determine the input element
                let input = null;
                if (event?.target?.tagName === 'INPUT') {
                    input = event.target;
                } else if (event?.currentTarget?.tagName === 'INPUT') {
                    input = event.currentTarget;
                } else if (swatch) {
                    input = swatch.querySelector('input[type="radio"]');
                } else if (event?.target?.closest) {
                    input = event.target.closest('.color-swatch')?.querySelector('input[type="radio"]');
                }

                if (!input) return;

                input.disabled = false;
                const variantType = input.dataset.variantType || input.name;
                const variantValue = input.value;

                input.checked = true;
                state.lastChangedType = variantType.toLowerCase();
                state.selectedOptions[variantType] = variantValue;

                // Update UI for swatches
                document.querySelectorAll(`input[name="${variantType}"]`).forEach(inp => {
                    const sw = inp.closest('.color-swatch');
                    if (sw) {
                        sw.querySelector('.color-swatch-checkmark')?.classList.add('d-none');
                        sw.querySelector('.color-swatch-label')?.classList.remove('active');
                    }
                });

                const selectedSwatch = input.closest('.color-swatch');
                if (selectedSwatch) {
                    selectedSwatch.querySelector('.color-swatch-checkmark')?.classList.remove('d-none');
                    selectedSwatch.querySelector('.color-swatch-label')?.classList.add('active');
                }

                updateVariantWithLoading();
            }

            // === Variant Availability ===
            /**
             * Updates the availability of variant options
             */
            function updateAvailableOptions() {
                const normalizedSelected = normalizeOptions(state.selectedOptions);
                const allVariantTypes = [...new Set(state.variants.flatMap(v =>
                    Object.keys(getNormalizedVariantValues(v))))];

                allVariantTypes.forEach(type => {
                    const buttons = Array.from(document.querySelectorAll('.variant-option-btn'))
                        .filter(btn => btn.dataset.variantType.toLowerCase() === type);
                    const inputs = Array.from(document.querySelectorAll('input[type="radio"]'))
                        .filter(inp => (inp.dataset.variantType || inp.name).toLowerCase() === type);

                    [...buttons, ...inputs].forEach(element => {
                        const value = element.tagName === 'INPUT' ? element.value : element.dataset.variantValue;
                        const swatch = element.tagName === 'INPUT' ? element.closest('.color-swatch') : null;
                        let isAvailable = false;

                        if (type === 'color') {
                            isAvailable = state.variants.some(v => {
                                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                                return getNormalizedVariantValues(v).color === normalizeValue(value);
                            });
                        } else if (type === 'storage') {
                            isAvailable = state.variants.some(v => {
                                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                                const vals = getNormalizedVariantValues(v);
                                if (vals.storage !== normalizeValue(value)) return false;
                                if (normalizedSelected.color && vals.color !== normalizedSelected.color) return false;
                                if (state.lastChangedType === 'ram' && normalizedSelected.ram) {
                                    return vals.ram === normalizedSelected.ram;
                                }
                                return true;
                            });
                        } else if (type === 'ram') {
                            isAvailable = state.variants.some(v => {
                                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                                const vals = getNormalizedVariantValues(v);
                                if (vals.ram !== normalizeValue(value)) return false;
                                if (normalizedSelected.color && vals.color !== normalizedSelected.color) return false;
                                if (state.lastChangedType === 'storage' && normalizedSelected.storage) {
                                    return vals.storage === normalizedSelected.storage;
                                }
                                return true;
                            });
                        } else {
                            isAvailable = state.variants.some(v => {
                                if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                                const vals = getNormalizedVariantValues(v);
                                if (vals[type] !== normalizeValue(value)) return false;
                                if (normalizedSelected.color && vals.color !== normalizedSelected.color) return false;
                                return true;
                            });
                        }

                        if (element.tagName === 'INPUT') {
                            element.disabled = !isAvailable;
                            if (swatch) {
                                swatch.classList.toggle('disabled', !isAvailable);
                                swatch.querySelector('.color-swatch-label')?.classList.toggle('disabled', !isAvailable);
                            }
                        } else {
                            element.classList.toggle('disabled', !isAvailable);
                            element.toggleAttribute('disabled', !isAvailable);
                        }
                    });
                });
            }

            // === Auto-Selection Logic ===
            /**
             * Auto-selects a matching variant based on current selection
             * @returns {Object|null} Selected variant or null
             */
            function autoSelectMatchingVariant() {
                const normalizedSelected = normalizeOptions(state.selectedOptions);
                if (!normalizedSelected.color) return null;

                // Auto-select RAM when Storage changes
                if (state.lastChangedType === 'storage' && normalizedSelected.storage) {
                    const matches = state.variants.filter(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const vals = getNormalizedVariantValues(v);
                        return vals.color === normalizedSelected.color && vals.storage === normalizedSelected.storage;
                    });

                    if (matches.length > 0) {
                        const selectedVariant = matches.sort((a, b) =>
                            parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100) -
                            parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100)
                        )[0];

                        const vals = getNormalizedVariantValues(selectedVariant);
                        Object.entries(vals).forEach(([type, value]) => {
                            if (type === 'ram') {
                                state.selectedOptions[type] = value;
                                document.querySelectorAll('.variant-option-btn').forEach(btn => {
                                    if (btn.dataset.variantType.toLowerCase() === 'ram') {
                                        btn.classList.toggle('active', normalizeValue(btn.dataset.variantValue) === value);
                                        btn.classList.remove('disabled');
                                        btn.removeAttribute('disabled');
                                    }
                                });
                            }
                        });
                        return selectedVariant;
                    }
                }

                // Auto-select Storage when RAM changes
                if (state.lastChangedType === 'ram' && normalizedSelected.ram) {
                    const matches = state.variants.filter(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        const vals = getNormalizedVariantValues(v);
                        return vals.color === normalizedSelected.color && vals.ram === normalizedSelected.ram;
                    });

                    if (matches.length > 0) {
                        const selectedVariant = matches.sort((a, b) =>
                            parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100) -
                            parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100)
                        )[0];

                        const vals = getNormalizedVariantValues(selectedVariant);
                        Object.entries(vals).forEach(([type, value]) => {
                            if (type === 'storage') {
                                state.selectedOptions[type] = value;
                                document.querySelectorAll('.variant-option-btn').forEach(btn => {
                                    if (btn.dataset.variantType.toLowerCase() === 'storage') {
                                        btn.classList.toggle('active', normalizeValue(btn.dataset.variantValue) === value);
                                        btn.classList.remove('disabled');
                                        btn.removeAttribute('disabled');
                                    }
                                });
                            }
                        });
                        return selectedVariant;
                    }
                }

                // Auto-select cheapest variant when only color is selected
                if (normalizedSelected.color && !normalizedSelected.storage && !normalizedSelected.ram) {
                    const matches = state.variants.filter(v => {
                        if (v.status !== 'active' || parseInt(v.stock) <= 0) return false;
                        return getNormalizedVariantValues(v).color === normalizedSelected.color;
                    });

                    if (matches.length > 0) {
                        const selectedVariant = matches.sort((a, b) =>
                            parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100) -
                            parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100)
                        )[0];

                        const vals = getNormalizedVariantValues(selectedVariant);
                        Object.entries(vals).forEach(([type, value]) => {
                            if (type !== 'color') {
                                state.selectedOptions[type] = value;
                                document.querySelectorAll('.variant-option-btn').forEach(btn => {
                                    if (btn.dataset.variantType.toLowerCase() === type) {
                                        btn.classList.toggle('active', normalizeValue(btn.dataset.variantValue) === value);
                                        btn.classList.remove('disabled');
                                        btn.removeAttribute('disabled');
                                    }
                                });
                            }
                        });
                        return selectedVariant;
                    }
                }

                return null;
            }

            // === UI Update Functions ===
            /**
             * Updates the UI with variant details
             * @param {Object} variant - Selected variant
             */
            function updateProductDisplay(variant) {
                elements.priceContainer?.classList.add('loading-shimmer');

                setTimeout(() => {
                    const price = parseFloat(variant.price);
                    const discount = parseFloat(variant.discount || 0);
                    const discountedPrice = price * (1 - discount / 100);

                    if (elements.displayPrice) {
                        elements.displayPrice.textContent = `$${discountedPrice.toFixed(2)}`;
                    }

                    if (elements.originalPrice) {
                        elements.originalPrice.innerHTML = discount > 0
                            ? `<s class="text-muted">$${price.toFixed(2)}</s>`
                            : '';
                        elements.originalPrice.style.display = discount > 0 ? 'inline' : 'none';
                    }

                    if (elements.discountBadge) {
                        elements.discountBadge.textContent = discount > 0 ? `${discount}% off` : '';
                        elements.discountBadge.style.display = discount > 0 ? 'inline' : 'none';
                    }

                    elements.priceContainer?.classList.remove('loading-shimmer');
                }, 150);

                if (elements.displaySku) elements.displaySku.textContent = variant.sku || 'N/A';
                if (elements.displayStock) {
                    const stock = parseInt(variant.stock || 0);
                    elements.displayStock.innerHTML = stock > 0
                        ? `<span class="badge badge-success">${stock}</span>`
                        : '<span class="badge badge-danger">Out of Stock</span>';
                }

                elements.stockAlert?.classList.add('d-none');
                elements.quantitySection?.classList.remove('hidden');
                if (elements.addToCartBtn) {
                    const stock = parseInt(variant.stock || 0);
                    elements.addToCartBtn.disabled = stock <= 0;
                    elements.addToCartBtn.textContent = stock > 0 ? 'Add to cart' : 'Out of Stock';
                }

                if (elements.qtyInput && parseInt(variant.stock) > 0) {
                    updateQuantityMax();
                }

                if (elements.variantIdInput) elements.variantIdInput.value = variant.id;
                updateVariantImages(variant);
            }

            /**
             * Updates variant images in the gallery
             * @param {Object} variant - Selected variant
             */
            function updateVariantImages(variant) {
                if (!variant.images || !elements.mainImage) return;

                elements.imageLoadingOverlay?.classList.remove('d-none');
                elements.mainImage.classList.add('loading');

                const firstImage = variant.images.find(img => img.is_primary) || variant.images[0];
                elements.mainImage.src = firstImage.image_path;
                elements.mainImage.onload = () => {
                    elements.imageLoadingOverlay?.classList.add('d-none');
                    elements.mainImage.classList.remove('loading');
                };

                if (elements.thumbnailContainer) {
                    elements.thumbnailContainer.innerHTML = '';
                    variant.images.forEach((img, index) => {
                        const thumbDiv = document.createElement('div');
                        thumbDiv.className = 'thumbnail-item mx-1';
                        thumbDiv.style.cssText = 'flex: 0 0 auto; opacity: 0; transition: opacity 0.3s ease;';

                        const thumbImg = document.createElement('img');
                        thumbImg.src = img.image_path;
                        thumbImg.alt = `Thumbnail ${index + 1}`;
                        thumbImg.className = `img-fluid thumbnail-image ${index === 0 ? 'active' : ''}`;
                        thumbImg.style.cssText = `width: 80px; height: 80px; object-fit: cover; border-radius: 5px; cursor: pointer; border: 2px solid ${index === 0 ? '#2874f0' : '#eee'};`;

                        thumbImg.addEventListener('click', () => {
                            document.querySelectorAll('.thumbnail-image').forEach(t => {
                                t.classList.remove('active');
                                t.style.border = '2px solid #eee';
                            });
                            thumbImg.classList.add('active');
                            thumbImg.style.border = '2px solid #2874f0';
                            elements.mainImage.src = thumbImg.src;
                        });

                        thumbDiv.appendChild(thumbImg);
                        elements.thumbnailContainer.appendChild(thumbDiv);
                        setTimeout(() => thumbDiv.style.opacity = '1', index * 50);
                    });
                }
            }

            /**
             * Applies variant options to the UI
             * @param {Object} variant - Selected variant
             */
            function applyVariantToUI(variant) {
                if (!variant) return;
                const vals = getNormalizedVariantValues(variant);
                Object.entries(vals).forEach(([type, value]) => {
                    state.selectedOptions[type] = value;

                    document.querySelectorAll('.variant-option-btn').forEach(btn => {
                        if (btn.dataset.variantType.toLowerCase() === type) {
                            btn.classList.toggle('active', normalizeValue(btn.dataset.variantValue) === value);
                        }
                    });

                    document.querySelectorAll('input[type="radio"]').forEach(inp => {
                        if ((inp.dataset.variantType || inp.name).toLowerCase() === type) {
                            inp.checked = normalizeValue(inp.value) === value;
                            const swatch = inp.closest('.color-swatch');
                            if (swatch) {
                                swatch.querySelector('.color-swatch-checkmark')?.classList.toggle('d-none', !inp.checked);
                                swatch.querySelector('.color-swatch-label')?.classList.toggle('active', inp.checked);
                            }
                        }
                    });
                });
            }

            /**
             * Preselects the cheapest available variant
             */
            function preselectCheapestVariant() {
                const availableVariants = state.variants.filter(v => v.status === 'active' && parseInt(v.stock) > 0);
                const variantsToSelect = availableVariants.length > 0 ? availableVariants : state.variants.filter(v => v.status === 'active');

                if (variantsToSelect.length === 0) return;

                const selectedVariant = variantsToSelect.sort((a, b) =>
                    parseFloat(a.price) * (1 - (parseFloat(a.discount) || 0) / 100) -
                    parseFloat(b.price) * (1 - (parseFloat(b.discount) || 0) / 100)
                )[0];

                const vals = getNormalizedVariantValues(selectedVariant);
                Object.entries(vals).forEach(([type, value]) => {
                    state.selectedOptions[type] = value;
                    document.querySelectorAll('.variant-option-btn').forEach(btn => {
                        if (btn.dataset.variantType.toLowerCase() === type) {
                            btn.classList.toggle('active', normalizeValue(btn.dataset.variantValue) === value);
                        }
                    });
                    document.querySelectorAll('input[type="radio"]').forEach(inp => {
                        if ((inp.dataset.variantType || inp.name).toLowerCase() === type && normalizeValue(inp.value) === value) {
                            inp.checked = true;
                            const swatch = inp.closest('.color-swatch');
                            if (swatch) {
                                swatch.querySelector('.color-swatch-checkmark')?.classList.remove('d-none');
                                swatch.querySelector('.color-swatch-label')?.classList.add('active');
                            }
                        }
                    });
                });

                state.currentVariant = selectedVariant;
                updateVariantWithLoading();
            }

            /**
             * Handles case when no matching variant is found
             */
            function handleNoVariantFound() {
                if (elements.stockAlert) {
                    elements.stockAlert.classList.remove('d-none');
                    elements.stockAlert.querySelector('#stockAlertMessage').textContent = 'No matching variant available.';
                }
                elements.quantitySection?.classList.add('hidden');
                elements.addToCartBtn?.style.setProperty('display', 'none');
                document.querySelector('.add-to-cart .btn.min')?.style.setProperty('display', 'none');
                if (elements.variantIdInput) elements.variantIdInput.value = '';
            }

            /**
             * Updates variant with loading states
             */
            async function updateVariantWithLoading() {
                state.isUpdating = true;
                elements.priceContainer?.classList.add('loading-shimmer');

                updateAvailableOptions();
                const autoSelected = autoSelectMatchingVariant();
                const normalizedSelected = normalizeOptions(state.selectedOptions);
                const matchingVariant = state.variants.find(v => {
                    if (v.status !== 'active') return false;
                    const vals = getNormalizedVariantValues(v);
                    return Object.keys(normalizedSelected).every(key => vals[key] === normalizedSelected[key]);
                });

                if (matchingVariant) {
                    state.currentVariant = matchingVariant;
                    applyVariantToUI(matchingVariant);
                    updateProductDisplay(matchingVariant);
                } else if (autoSelected) {
                    state.currentVariant = autoSelected;
                    applyVariantToUI(autoSelected);
                    updateProductDisplay(autoSelected);
                } else {
                    handleNoVariantFound();
                }

                elements.priceContainer?.classList.remove('loading-shimmer');
                state.isUpdating = false;
            }

            // === Event Listeners ===
            /**
             * Initializes event listeners for variant selection and quantity controls
             */
            function initializeEventListeners() {
                // Variant Buttons
                document.querySelectorAll('.variant-option-btn').forEach(btn => {
                    btn.addEventListener('click', handleVariantButtonClick);
                });

                // Color Swatches
                document.querySelectorAll('.color-swatch').forEach(swatch => {
                    const input = swatch.querySelector('input[type="radio"]');
                    if (!input) return;

                    // Handle direct radio input changes
                    input.addEventListener('change', () => handleColorSwatchSelection(null, swatch));

                    // Handle swatch or label clicks
                    swatch.addEventListener('click', e => {
                        e.preventDefault();
                        e.stopPropagation();
                        if (!input.disabled) {
                            input.checked = true;
                            handleColorSwatchSelection(e, swatch);
                        }
                    });
                });

                // Quantity Controls
                elements.plusBtn?.addEventListener('click', () => {
                    const max = parseInt(elements.qtyInput.getAttribute('data-max')) || 1000;
                    const value = parseInt(elements.qtyInput.value) || 1;
                    if (value < max) {
                        elements.qtyInput.value = value + 1;
                        updateMinusButtonState();
                    }
                });

                elements.minusBtn?.addEventListener('click', () => {
                    const min = parseInt(elements.qtyInput.getAttribute('data-min')) || 1;
                    const value = parseInt(elements.qtyInput.value) || 1;
                    if (value > min) {
                        elements.qtyInput.value = value - 1;
                        updateMinusButtonState();
                    }
                });

                elements.qtyInput?.addEventListener('change', function () {
                    const min = parseInt(this.getAttribute('data-min')) || 1;
                    const max = parseInt(this.getAttribute('data-max')) || 1000;
                    let value = parseInt(this.value) || 1;
                    value = Math.max(min, Math.min(max, value));
                    this.value = value;
                    updateMinusButtonState();
                });

                // Thumbnail Gallery
                document.querySelectorAll('.thumbnail-image').forEach(thumb => {
                    thumb.addEventListener('click', () => {
                        document.querySelectorAll('.thumbnail-image').forEach(t => {
                            t.classList.remove('active');
                            t.style.border = '2px solid #eee';
                        });
                        thumb.classList.add('active');
                        thumb.style.border = '2px solid #2874f0';
                        if (elements.mainImage && elements.imageLoadingOverlay) {
                            elements.imageLoadingOverlay.classList.remove('d-none');
                            elements.mainImage.classList.add('loading');
                            elements.mainImage.src = thumb.src;
                            elements.mainImage.onload = () => {
                                elements.imageLoadingOverlay.classList.add('d-none');
                                elements.mainImage.classList.remove('loading');
                            };
                        }
                    });
                });
            }

            // === Initialization ===
            /**
             * Initializes the product detail page
             */
            function initialize() {
                updateMinusButtonState();
                if (state.hasVariants && state.variants.length > 0) {
                    initializeEventListeners();
                    preselectCheapestVariant();
                }
            }

            // Start the application
            document.addEventListener('DOMContentLoaded', initialize);
        })();
    </script>
@endpush