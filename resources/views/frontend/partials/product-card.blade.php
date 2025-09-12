<!-- Product Card -->
<div class="product-card-container mb-4 isotope-item category-{{ $product->cat_id }} px-3"
    data-product-id="{{ $product->id }}"
    data-product-brand="{{ $product->brand->slug ?? '' }}"
    data-product-price="{{ $product->price }}"
    data-product-discount="{{ $product->discount }}"
    data-product-rating="{{ $product->rating ?? 0 }}">
    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
        <div class="position-relative bg-light" style="aspect-ratio: 1 / 1;">
            <div class="slider-wrapper w-100 h-100" data-slider>
                <div class="slider-track d-flex h-100">
                    @foreach($product->images as $index => $img)
                    @php
                    $pathInfo = pathinfo($img->image_path);
                    $directory = $pathInfo['dirname'];
                    $filename = $pathInfo['filename'];
                    $extension = $pathInfo['extension'];
                    $srcset = [];
                    $sizes = [160, 235, 320, 480];
                    foreach ($sizes as $size) {
                    $responsivePath = "{$directory}/{$filename}_{$size}x{$size}.webp";
                    if (file_exists(public_path($responsivePath))) {
                    $srcset[] = asset($responsivePath) . " {$size}w";
                    }
                    }
                    $srcset[] = asset($img->image_path) . " 370w";
                    $srcsetString = implode(', ', $srcset);
                    @endphp
                    <img
                        src="{{ asset($img->image_path) }}"
                        srcset="{{ $srcsetString }}"
                        sizes="(max-width: 576px) 280px, (max-width: 768px) 235px, (max-width: 992px) 200px, (max-width: 1200px) 180px, 160px"
                        class="slider-image"
                        alt="{{ $product->title }}"
                        loading="lazy"
                        width="235"
                        height="235"
                        decoding="async"
                        fetchpriority="low"
                        onerror="this.src='{{ asset('images/no-image.png') }}';">
                    @endforeach
                </div>
            </div>

            @if($product->discount > 0)
            <span class="badge badge-primary badge-status">{{ $product->discount }}% Off</span>
            @elseif($product->condition === 'new')
            <span class="badge badge-success badge-status">New</span>
            @elseif($product->stock <= 0)
                <span class="badge badge-danger badge-status">Sold Out</span>
                @endif
        </div>

        <div class="card-body d-flex flex-column px-3 py-2">
            <h6 class="text-dark text-truncate mb-1">
                <a href="{{ route('product-detail', $product->slug) }}" class="text-dark">
                    {{ Str::limit($product->title, 50) }}
                </a>
            </h6>

            <!-- Brand Display -->
            @if($product->brand)
            <small class="text-muted mb-1">
                <i class="fa fa-tag"></i> {{ $product->brand->title }}
            </small>
            @endif

            <!-- Rating Display -->
            @if($product->rating > 0)
            <div class="mb-1">
                <small class="text-warning">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="fa fa-star{{ $i <= $product->rating ? '' : '-o' }}"></i>
                        @endfor
                        <span class="text-muted">({{ $product->rating_count ?? 0 }})</span>
                </small>
            </div>
            @endif

            <div class="mb-2">
                @if($product->discount > 0)
                <span class="text-primary font-weight-bold">
                    ${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}
                </span>
                <small class="text-muted ml-2"><del>${{ number_format($product->price, 2) }}</del></small>
                @else
                <span class="font-weight-bold text-primary">${{ number_format($product->price, 2) }}</span>
                @endif
            </div>

            @php
            $inWishlist = Helper::isProductInWishlist($product->slug);
            @endphp

            <div class="mt-auto">
                <a href="{{ route('add-to-cart', $product->slug) }}"
                    class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center {{ $product->stock <= 0 ? 'disabled' : '' }}">
                    <i class="ti-shopping-cart mr-1"></i>
                    {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </a>

                <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                    <a href="{{ route('add-to-wishlist', $product->slug) }}"
                        class="text-decoration-none">
                        <i class="ti-heart mr-1" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i> Wishlist
                    </a>
                    <a href="#"
                        class="text-decoration-none text-muted hover-text-dark"
                        onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');">
                        <i class="ti-eye mr-1"></i> Quick View
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Product Listing Layout */
    .product-listing-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        position: relative;
    }

    /* Filter Transition Effects */
    .product-card-container {
        transition: all 0.3s ease;
        will-change: transform, opacity;
    }

    .product-card-container.filtering {
        pointer-events: none;
    }

    .product-card-container.fade-out {
        opacity: 0;
        transform: scale(0.95);
    }

    .product-card-container.fade-in {
        opacity: 1;
        transform: scale(1);
    }

    /* Enhanced Product Card Hover Effects */
    .product-card {
        transition: all 0.3s ease;
        border: 1px solid transparent !important;
    }

    .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        border-color: #f7941d !important;
    }

    /* Slider Container */
    .slider-wrapper {
        overflow: hidden;
        height: 100%;
        position: relative;
    }

    /* Slider Track */
    .slider-track {
        display: flex;
        width: 100%;
        height: 100%;
        transition: transform 0.5s ease-in-out;
    }

    /* Slider Image */
    .slider-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        flex-shrink: 0;
        image-rendering: auto;
        transform: translateZ(0);
        will-change: transform;
        backface-visibility: hidden;
        transition: transform 0.3s ease;
    }

    .slider-wrapper:hover .slider-image {
        transform: scale(1.05);
    }

    /* Status Badge */
    .badge-status {
        position: absolute;
        top: 8px;
        left: 8px;
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
        z-index: 10;
        font-weight: 600;
        border-radius: 4px;
    }

    .badge-primary {
        background: #f7941d !important;
        color: white;
    }

    .badge-success {
        background: #28a745 !important;
        color: white;
    }

    .badge-danger {
        background: #f7941d !important;
        color: white;
    }

    /* Enhanced Brand and Rating Display */
    .card-body small.text-muted {
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .text-warning i {
        color: #ffc107 !important;
    }

    /* Price Display Enhancement */
    .card-body .mb-2 {
        border-bottom: 1px solid #eee;
        padding-bottom: 0.5rem;
    }

    /* Button Enhancements */
    .btn-dark {
        background: #333 !important;
        border-color: #333 !important;
        transition: all 0.3s ease;
    }

    .btn-dark:hover:not(.disabled) {
        background: #f7941d !important;
        border-color: #f7941d !important;
        transform: translateY(-1px);
    }

    .btn-dark.disabled {
        background: #6c757d !important;
        border-color: #6c757d !important;
        cursor: not-allowed;
    }

    /* Wishlist and Quick View Links */
    .card-body .d-flex a {
        transition: all 0.3s ease;
        font-size: 0.75rem;
    }

    .card-body .d-flex a:hover {
        color: #f7941d !important;
        transform: translateY(-1px);
    }

    /* Aspect Ratio Polyfill */
    [style*="aspect-ratio"] {
        position: relative;
    }

    [style*="aspect-ratio"]::before {
        content: "";
        display: block;
        padding-bottom: calc(100% / (1 / 1));
    }

    [style*="aspect-ratio"]>*:first-child {
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
    }

    /* Loading States for Filtering */
    .products-loading {
        position: relative;
    }

    .products-loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
    }

    /* No Results Message */
    .no-products-message {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }

    .no-products-message i {
        font-size: 3rem;
        color: #6c757d;
        margin-bottom: 1rem;
    }

    .no-products-message h4 {
        color: #495057;
        margin-bottom: 0.5rem;
    }

    .no-products-message p {
        color: #6c757d;
        margin-bottom: 1.5rem;
    }

    .no-products-message .btn {
        background: #f7941d;
        border-color: #f7941d;
        color: white;
    }

    .no-products-message .btn:hover {
        background: #e07c1a;
        border-color: #e07c1a;
    }

    /* Responsive Column */
    @media (min-width: 1200px) {
        .col-lg-5th {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }

    /* Mobile Optimizations */
    @media (max-width: 768px) {
        .product-card:hover {
            transform: none;
        }

        .slider-wrapper:hover .slider-image {
            transform: none;
        }

        .btn-dark:hover:not(.disabled) {
            transform: none;
        }

        .card-body .d-flex a:hover {
            transform: none;
        }
    }

    /* Accessibility Improvements */
    .product-card:focus-within {
        outline: 2px solid #f7941d;
        outline-offset: 2px;
    }

    .btn-dark:focus {
        box-shadow: 0 0 0 3px rgba(247, 148, 29, 0.3);
    }

    /* Print Styles */
    @media print {
        .product-card-container {
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .badge-status,
        .card-body .d-flex {
            display: none !important;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Use IIFE (Immediately Invoked Function Expression) to avoid global scope pollution
    (function() {
        'use strict';

        // Check if already initialized to prevent duplicate execution
        if (window.productCardSystemInitialized) {
            console.log('Product card system already initialized, skipping...');
            return;
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Product Image Slider Functionality
            document.querySelectorAll("[data-slider]").forEach(wrapper => {
                // Skip if already initialized
                if (wrapper.hasAttribute('data-slider-initialized')) {
                    return;
                }
                wrapper.setAttribute('data-slider-initialized', 'true');

                const track = wrapper.querySelector('.slider-track');
                const images = wrapper.querySelectorAll('.slider-image');
                const total = images.length;

                if (total <= 1) return;

                let index = 0;
                let interval;

                const slide = () => {
                    track.style.transform = `translateX(-${index * 100}%)`;
                };

                wrapper.addEventListener("mouseenter", () => {
                    index = 0;
                    interval = setInterval(() => {
                        index = (index + 1) % total;
                        slide();
                    }, 1000);
                });

                wrapper.addEventListener("mouseleave", () => {
                    clearInterval(interval);
                    index = 0;
                    slide();
                });
            });

            // Modal accessibility handling
            document.querySelectorAll('[id^="productModal"]').forEach(modal => {
                if (modal && !modal.hasAttribute('data-modal-initialized')) {
                    modal.setAttribute('data-modal-initialized', 'true');

                    modal.addEventListener('hidden.bs.modal', function() {
                        this.setAttribute('inert', '');
                    });

                    modal.addEventListener('show.bs.modal', function() {
                        this.removeAttribute('inert');
                    });
                }
            });

            // Lazy loading enhancement
            if ('IntersectionObserver' in window && !window.productImageObserverInitialized) {
                window.productImageObserverInitialized = true;
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('.slider-image.lazy').forEach(img => {
                    imageObserver.observe(img);
                });
            }

            // Add to Cart with Loading State
            document.querySelectorAll('.btn-dark:not(.disabled):not([data-cart-initialized])').forEach(button => {
                button.setAttribute('data-cart-initialized', 'true');

                button.addEventListener('click', function(e) {
                    if (this.href.includes('add-to-cart')) {
                        const originalText = this.innerHTML;
                        const originalHref = this.href;

                        // Show loading state
                        this.innerHTML = '<i class="fa fa-spinner fa-spin mr-1"></i> Adding...';
                        this.classList.add('disabled');
                        this.href = 'javascript:void(0)';

                        // Simulate adding to cart (replace with actual AJAX call)
                        setTimeout(() => {
                            this.innerHTML = '<i class="fa fa-check mr-1"></i> Added!';
                            this.classList.add('btn-success');
                            this.classList.remove('btn-dark');

                            setTimeout(() => {
                                this.innerHTML = originalText;
                                this.href = originalHref;
                                this.classList.remove('disabled', 'btn-success');
                                this.classList.add('btn-dark');
                            }, 1500);
                        }, 800);
                    }
                });
            });

            // Quick View Modal Enhancement
            document.querySelectorAll('[onclick*="productModal"]:not([data-quickview-initialized])').forEach(link => {
                link.setAttribute('data-quickview-initialized', 'true');

                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    // Extract modal ID from onclick attribute
                    const onclickAttr = this.getAttribute('onclick');
                    const modalId = onclickAttr.match(/#([^']*)/)?.[1];

                    if (modalId) {
                        const modal = document.getElementById(modalId);
                        if (modal) {
                            // Use Bootstrap modal if available, otherwise fallback
                            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                                const bsModal = new bootstrap.Modal(modal);
                                bsModal.show();
                            } else if (typeof $ !== 'undefined' && $.fn.modal) {
                                $(modal).modal('show');
                            }
                        }
                    }
                });
            });

            // Enhanced Wishlist Functionality
            document.querySelectorAll('[href*="add-to-wishlist"]:not([data-wishlist-initialized])').forEach(link => {
                link.setAttribute('data-wishlist-initialized', 'true');

                link.addEventListener('click', function(e) {
                    e.preventDefault();

                    const heartIcon = this.querySelector('i');
                    const originalColor = heartIcon.style.color;

                    // Animate heart
                    heartIcon.style.transform = 'scale(1.3)';
                    heartIcon.style.color = 'red';

                    setTimeout(() => {
                        heartIcon.style.transform = 'scale(1)';
                    }, 200);

                    // Here you would typically make an AJAX call to add/remove from wishlist
                    console.log('Wishlist action for:', this.href);
                });
            });
        });

        // Filter Integration Functions - Use namespace to avoid conflicts
        if (!window.productCardHelpers) {
            window.productCardHelpers = {
                // Filter products based on active filters
                filterProducts: function(filters) {
                    const productCards = document.querySelectorAll('.product-card-container');
                    let visibleCount = 0;

                    productCards.forEach(card => {
                        let shouldShow = true;

                        // Check brand filter
                        if (filters.brand && Object.keys(filters.brand).length > 0) {
                            const productBrand = card.dataset.productBrand;
                            if (!Object.keys(filters.brand).includes(productBrand)) {
                                shouldShow = false;
                            }
                        }

                        // Check price filter
                        if (filters.price && Object.keys(filters.price).length > 0) {
                            const priceRange = Object.keys(filters.price)[0];
                            const [minPrice, maxPrice] = priceRange.split('-').map(Number);
                            const productPrice = parseFloat(card.dataset.productPrice);
                            const productDiscount = parseFloat(card.dataset.productDiscount) || 0;
                            const finalPrice = productPrice - (productPrice * productDiscount / 100);

                            if (finalPrice < minPrice || finalPrice > maxPrice) {
                                shouldShow = false;
                            }
                        }

                        // Check rating filter
                        if (filters.rating && Object.keys(filters.rating).length > 0) {
                            const productRating = parseFloat(card.dataset.productRating) || 0;
                            const minRatings = Object.keys(filters.rating).map(Number);
                            const passesRating = minRatings.some(minRating => productRating >= minRating);

                            if (!passesRating) {
                                shouldShow = false;
                            }
                        }

                        // Check discount filter
                        if (filters.discount && Object.keys(filters.discount).length > 0) {
                            const productDiscount = parseFloat(card.dataset.productDiscount) || 0;
                            const minDiscounts = Object.keys(filters.discount).map(Number);
                            const passesDiscount = minDiscounts.some(minDiscount => productDiscount >= minDiscount);

                            if (!passesDiscount) {
                                shouldShow = false;
                            }
                        }

                        // Apply visibility
                        if (shouldShow) {
                            card.style.display = 'block';
                            card.classList.remove('fade-out');
                            card.classList.add('fade-in');
                            visibleCount++;
                        } else {
                            card.classList.remove('fade-in');
                            card.classList.add('fade-out');
                            setTimeout(() => {
                                if (card.classList.contains('fade-out')) {
                                    card.style.display = 'none';
                                }
                            }, 300);
                        }
                    });

                    // Show/hide no results message
                    this.toggleNoResultsMessage(visibleCount);
                    return visibleCount;
                },

                // Toggle no Results message
                toggleNoResultsMessage: function(visibleCount) {
                    let noResultsMessage = document.querySelector('.no-products-message');

                    if (visibleCount === 0) {
                        if (!noResultsMessage) {
                            noResultsMessage = this.createNoResultsMessage();
                            const productListing = document.querySelector('.product-listing-wrapper, .products-grid, .row');
                            if (productListing) {
                                productListing.appendChild(noResultsMessage);
                            }
                        }
                        noResultsMessage.style.display = 'block';
                    } else {
                        if (noResultsMessage) {
                            noResultsMessage.style.display = 'none';
                        }
                    }
                },

                // Create no results message element
                createNoResultsMessage: function() {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'no-products-message col-12';
                    messageDiv.innerHTML = `
                        <i class="fa fa-search"></i>
                        <h4>No Products Found</h4>
                        <p>We couldn't find any products matching your current filters.</p>
                        <button type="button" class="btn btn-primary" onclick="window.shopFilterSystem?.clearAllFilters()">
                            <i class="fa fa-refresh mr-1"></i> Clear All Filters
                        </button>
                    `;
                    return messageDiv;
                },

                // Reset all product visibility
                resetProductVisibility: function() {
                    const productCards = document.querySelectorAll('.product-card-container');
                    productCards.forEach(card => {
                        card.style.display = 'block';
                        card.classList.remove('fade-out', 'fade-in');
                    });

                    this.toggleNoResultsMessage(productCards.length);
                },

                // Get product count
                getVisibleProductCount: function() {
                    return document.querySelectorAll('.product-card-container:not([style*="display: none"])').length;
                },

                // Animate product cards on filter change
                animateFilterChange: function() {
                    const productCards = document.querySelectorAll('.product-card-container');
                    productCards.forEach((card, index) => {
                        card.classList.add('filtering');
                        setTimeout(() => {
                            card.classList.remove('filtering');
                        }, 300 + (index * 50)); // Stagger animation
                    });
                }
            };
        }

        // Shop Filter System - Only create if it doesn't exist
        if (!window.ShopFilterSystem && !window.shopFilterSystem) {
            class ShopFilterSystem {
                constructor() {
                    this.activeFilters = {};
                    this.init();
                }

                init() {
                    this.bindEvents();
                    this.loadInitialFilters();
                    this.updateActiveFiltersDisplay();
                }

                bindEvents() {
                    // Filter checkbox change events
                    document.addEventListener('change', (e) => {
                        if (e.target.classList.contains('filter-checkbox')) {
                            this.handleFilterChange(e.target);
                        }
                    });

                    // Price range slider events
                    const priceSlider = document.getElementById('slider-range');
                    if (priceSlider && typeof $ !== 'undefined' && $.fn.slider) {
                        $(priceSlider).on('slidechange', (event, ui) => {
                            this.handlePriceRangeChange(ui.values);
                        });
                    }

                    // Clear all filters
                    document.addEventListener('click', (e) => {
                        if (e.target.classList.contains('clear-all-filters')) {
                            this.clearAllFilters();
                        }

                        // Remove individual active filter
                        if (e.target.classList.contains('active-filter-tag') || e.target.closest('.active-filter-tag')) {
                            const filterTag = e.target.classList.contains('active-filter-tag') ?
                                e.target :
                                e.target.closest('.active-filter-tag');
                            this.removeActiveFilter(filterTag);
                        }
                    });

                    // Debounce filter application
                    this.debouncedApplyFilters = this.debounce(() => {
                        // this.applyFilters();
                    }, 300);
                }

                loadInitialFilters() {
                    // Load filters from URL parameters
                    const urlParams = new URLSearchParams(window.location.search);

                    // Brand filters
                    const brands = urlParams.get('brand');
                    if (brands) {
                        const brandArray = brands.split(',');
                        brandArray.forEach(brand => {
                            const checkbox = document.querySelector(`input[name="brand[]"][value="${brand}"]`);
                            if (checkbox) {
                                this.addToActiveFilters('brand', brand, checkbox.dataset.filterLabel);
                            }
                        });
                    }

                    // Rating filters
                    const ratings = urlParams.get('min_rating');
                    if (ratings) {
                        const ratingArray = ratings.split(',');
                        ratingArray.forEach(rating => {
                            const checkbox = document.querySelector(`input[name="min_rating[]"][value="${rating}"]`);
                            if (checkbox) {
                                this.addToActiveFilters('rating', rating, checkbox.dataset.filterLabel);
                            }
                        });
                    }

                    // Discount filters
                    const discounts = urlParams.get('min_discount');
                    if (discounts) {
                        const discountArray = discounts.split(',');
                        discountArray.forEach(discount => {
                            const checkbox = document.querySelector(`input[name="min_discount[]"][value="${discount}"]`);
                            if (checkbox) {
                                this.addToActiveFilters('discount', discount, checkbox.dataset.filterLabel);
                            }
                        });
                    }

                    // Price range
                    const priceRange = urlParams.get('price_range');
                    if (priceRange) {
                        const [min, max] = priceRange.split('-');
                        this.addToActiveFilters('price', priceRange, `$${min} - $${max}`);
                    }
                }

                handleFilterChange(checkbox) {
                    const filterType = checkbox.dataset.filterType;
                    const filterValue = checkbox.value;
                    const filterLabel = checkbox.dataset.filterLabel;

                    if (checkbox.checked) {
                        this.addToActiveFilters(filterType, filterValue, filterLabel);
                    } else {
                        this.removeFromActiveFilters(filterType, filterValue);
                    }

                    this.updateActiveFiltersDisplay();
                    this.debouncedApplyFilters();
                }

                handlePriceRangeChange(values) {
                    const [min, max] = values;
                    const priceRange = `${min}-${max}`;
                    const priceLabel = `$${min} - $${max}`;

                    // Remove existing price filter
                    this.removeFromActiveFilters('price');

                    // Add new price filter if not default range
                    const sliderElement = document.getElementById('slider-range');
                    const minDefault = parseInt(sliderElement.dataset.min) || 0;
                    const maxDefault = parseInt(sliderElement.dataset.max) || 1000;

                    if (min !== minDefault || max !== maxDefault) {
                        this.addToActiveFilters('price', priceRange, priceLabel);
                    }

                    // Update hidden input
                    document.getElementById('price_range').value = priceRange;

                    this.updateActiveFiltersDisplay();
                    this.debouncedApplyFilters();
                }

                addToActiveFilters(type, value, label) {
                    if (!this.activeFilters[type]) {
                        this.activeFilters[type] = {};
                    }
                    this.activeFilters[type][value] = label;
                }

                removeFromActiveFilters(type, value = null) {
                    if (value === null) {
                        delete this.activeFilters[type];
                    } else {
                        if (this.activeFilters[type]) {
                            delete this.activeFilters[type][value];
                            if (Object.keys(this.activeFilters[type]).length === 0) {
                                delete this.activeFilters[type];
                            }
                        }
                    }
                }

                removeActiveFilter(filterTag) {
                    const filterType = filterTag.dataset.filterType;
                    const filterValue = filterTag.dataset.filterValue;

                    // Uncheck corresponding checkbox
                    const checkbox = document.querySelector(`input[name="${filterType}[]"][value="${filterValue}"], input[name="${filterType}"][value="${filterValue}"]`);
                    if (checkbox) {
                        checkbox.checked = false;
                    }

                    // Handle price range reset
                    if (filterType === 'price') {
                        this.resetPriceRange();
                    }

                    // Remove from active filters
                    this.removeFromActiveFilters(filterType, filterValue);
                    this.updateActiveFiltersDisplay();
                    // Use client-side filtering instead of server-side
                    this.applyFiltersClientSide();
                }

                clearAllFilters() {
                    // Uncheck all filter checkboxes
                    document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                    });

                    // Reset price range
                    this.resetPriceRange();

                    // Clear active filters
                    this.activeFilters = {};
                    this.updateActiveFiltersDisplay();
                    this.applyFilters();
                }

                resetPriceRange() {
                    const sliderElement = document.getElementById('slider-range');
                    const priceRangeInput = document.getElementById('price_range');
                    const amountDisplay = document.getElementById('amount');

                    if (sliderElement && typeof $ !== 'undefined' && $.fn.slider) {
                        const minDefault = parseInt(sliderElement.dataset.min) || 0;
                        const maxDefault = parseInt(sliderElement.dataset.max) || 1000;

                        $(sliderElement).slider('values', [minDefault, maxDefault]);

                        if (priceRangeInput) {
                            priceRangeInput.value = '';
                        }

                        if (amountDisplay) {
                            amountDisplay.value = `${minDefault} - ${maxDefault}`;
                        }
                    }
                }

                updateActiveFiltersDisplay() {
                    const activeFiltersSection = document.getElementById('active-filters-section');
                    const activeFiltersList = document.getElementById('active-filters-list');
                    const clearAllButton = activeFiltersSection.querySelector('.clear-all-filters');

                    if (!activeFiltersSection || !activeFiltersList || !clearAllButton) return;

                    // Always show the section
                    activeFiltersSection.style.display = 'block';

                    // Check if we have any active filters
                    const hasActiveFilters = Object.keys(this.activeFilters).length > 0;

                    if (hasActiveFilters) {
                        clearAllButton.style.display = 'inline-block';
                        activeFiltersList.style.display = 'flex';
                        activeFiltersList.innerHTML = this.generateActiveFiltersHTML();
                    } else {
                        clearAllButton.style.display = 'none';
                        activeFiltersList.style.display = 'none';
                        activeFiltersList.innerHTML = '';
                    }
                }

                generateActiveFiltersHTML() {
                    let html = '';

                    Object.entries(this.activeFilters).forEach(([type, values]) => {
                        Object.entries(values).forEach(([value, label]) => {
                            const cssClass = `${type}-filter`;
                            // Ensure price filter label always shows with $
                            let displayLabel = label;
                            if (type === 'price') {
                                const [min, max] = value.split('-');
                                displayLabel = `$${min} - $${max}`;
                            }
                            html += `
                <button class="active-filter-tag ${cssClass}" 
                        data-filter-type="${type}" 
                        data-filter-value="${value}"
                        title="Remove ${displayLabel} filter">
                    ${displayLabel} <i class="fa fa-times"></i>
                </button>
            `;
                        });
                    });

                    return html;
                }

                applyFilters() {
                    // Show loading state
                    this.showLoadingState();

                    // Build query parameters
                    const params = new URLSearchParams(window.location.search);

                    // Clear existing filter parameters
                    params.delete('brand');
                    params.delete('min_rating');
                    params.delete('min_discount');
                    params.delete('price_range');
                    params.delete('page');

                    // Add active filters to parameters
                    Object.entries(this.activeFilters).forEach(([type, values]) => {
                        const valueArray = Object.keys(values);

                        if (type === 'brand') {
                            params.set('brand', valueArray.join(','));
                        } else if (type === 'rating') {
                            params.set('min_rating', valueArray.join(','));
                        } else if (type === 'discount') {
                            params.set('min_discount', valueArray.join(','));
                        } else if (type === 'price') {
                            params.set('price_range', valueArray[0]);
                        }
                    });

                    // Update URL and reload page
                    const newUrl = `${window.location.pathname}?${params.toString()}`;
                    window.location.href = newUrl;
                }

                showLoadingState() {
                    // Add loading overlay to product listing
                    const productListing = document.querySelector('.product-listing-wrapper, .products-grid');
                    if (productListing && !productListing.querySelector('.filter-loading-overlay')) {
                        const loadingOverlay = document.createElement('div');
                        loadingOverlay.className = 'filter-loading-overlay';
                        loadingOverlay.innerHTML = `
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                                <p>Filtering products...</p>
                            </div>
                        `;
                        productListing.style.position = 'relative';
                        productListing.appendChild(loadingOverlay);
                    }

                    // Disable filter controls
                    document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
                        checkbox.disabled = true;
                    });
                }

                debounce(func, wait) {
                    let timeout;
                    return function executedFunction(...args) {
                        const later = () => {
                            clearTimeout(timeout);
                            func(...args);
                        };
                        clearTimeout(timeout);
                        timeout = setTimeout(later, wait);
                    };
                }

                // Public methods for external access
                getActiveFilters() {
                    return this.activeFilters;
                }

                hasActiveFilters() {
                    return Object.keys(this.activeFilters).length > 0;
                }

                getActiveFilterCount() {
                    let count = 0;
                    Object.values(this.activeFilters).forEach(filterValues => {
                        count += Object.keys(filterValues).length;
                    });
                    return count;
                }
            }

            // Initialize the filter system
            window.addEventListener('load', function() {
                if (!window.shopFilterSystem) {
                    window.shopFilterSystem = new ShopFilterSystem();

                    // Initialize price range slider if jQuery UI is available
                    if (typeof $ !== 'undefined' && $.fn.slider) {
                        const sliderRange = document.getElementById('slider-range');
                        if (sliderRange) {
                            const minPrice = parseInt(sliderRange.dataset.min) || 0;
                            const maxPrice = parseInt(sliderRange.dataset.max) || 5000;
                            const currentRange = document.getElementById('price_range')?.value;

                            let currentMin = minPrice;
                            let currentMax = maxPrice;

                            if (currentRange) {
                                [currentMin, currentMax] = currentRange.split('-').map(Number);
                            }

                            $(sliderRange).slider({
                                range: true,
                                min: minPrice,
                                max: maxPrice,
                                values: [currentMin, currentMax],
                                slide: function(event, ui) {
                                    document.getElementById('amount').value = `$${ui.values[0]} - $${ui.values[1]}`;
                                },
                                change: function(event, ui) {
                                    document.getElementById('amount').value = `$${ui.values[0]} - $${ui.values[1]}`;
                                }
                            });

                            // Set initial display value
                            document.getElementById('amount').value = `$${currentMin} - $${currentMax}`;
                        }
                    }

                    // Integration with filter system
                    if (window.productCardHelpers) {
                        window.shopFilterSystem.applyFiltersClientSide = function() {
                            const activeFilters = this.getActiveFilters();
                            window.productCardHelpers.animateFilterChange();

                            setTimeout(() => {
                                const visibleCount = window.productCardHelpers.filterProducts(activeFilters);
                                console.log(`Filtered products: ${visibleCount} visible`);
                            }, 100);
                        };
                    }

                    // Update product count in filter section
                    const updateFilterCount = () => {
                        const visibleCount = window.productCardHelpers.getVisibleProductCount();
                        const filterCountElement = document.querySelector('.filter-results-count');

                        if (filterCountElement) {
                            filterCountElement.textContent = `${visibleCount} products found`;
                        }
                    };

                    // Call on page load
                    updateFilterCount();
                }
            });
        }

        // Mark as initialized
        window.productCardSystemInitialized = true;
        console.log('Product card system initialized successfully');

    })(); // End of IIFE

    // Inject additional styles only once
    if (!document.getElementById('product-card-additional-styles')) {
        const additionalStyles = `
            .filter-loading-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(255, 255, 255, 0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 1000;
                backdrop-filter: blur(2px);
            }

            .loading-spinner {
                text-align: center;
                color: #f7941d;
            }

            .spinner {
                width: 40px;
                height: 40px;
                border: 4px solid #e9ecef;
                border-top: 4px solid #f7941d;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 10px;
            }

            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            .loading-spinner p {
                margin: 0;
                font-weight: 500;
                color: #666;
            }

            .filter-checkbox:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            .filter-checkbox:disabled + label {
                opacity: 0.6;
                cursor: not-allowed;
            }
        `;

        const styleSheet = document.createElement('style');
        styleSheet.id = 'product-card-additional-styles';
        styleSheet.textContent = additionalStyles;
        document.head.appendChild(styleSheet);
    }
</script>
@endpush