<!-- Active Filters Display -->
<div id="active-filters-section" class="single-widget active-filters">
    <h3 class="title">Filters
        <button type="button" class="clear-all-filters btn-link" title="Clear All">Clear All</button>
    </h3>
    <div id="active-filters-list" class="active-filters-container">
        <!-- Active filters will be dynamically populated here -->
    </div>
</div>

<!-- Price Filter Widget -->
@if(!isset($mainCategory) || $mainCategory->filters->where('name', 'price')->count())
<div class="single-widget range">
    <h3 class="title">Shop by Price</h3>
    <div class="price-filter">
        <div class="price-filter-inner">
            <div id="slider-range" data-min="0" data-max="{{ $max_price ?? 1000 }}" tabindex="10"></div>
            <div class="product_filter">
                <div class="label-input range-input">
                    <span class="range-label">Range:</span>
                    <input type="text" id="amount" class="price-range-display" readonly tabindex="11" />
                    <input type="hidden" name="price_range" id="price_range" value="{{ request('price_range') }}" />
                </div>
                <!-- <button type="button" class="filter_button" tabindex="12">Apply Filter</button> -->
            </div>
        </div>
    </div>
</div>
@endif

@if(!isset($mainCategory) || $mainCategory->filters->where('name', 'brand')->count())
<div class="single-widget mainCategory">
    <h3 class="title">Brands</h3>
    @php
    $brands = isset($mainCategory)
    ? $mainCategory->brands->where('status', 'active')->sortBy('title')
    : App\Models\Brand::where('status', 'active')->orderBy('title')->get();
    @endphp
    @if($brands->count() > 0)
    <ul class="categor-list">
        @foreach($brands as $index => $brand)
        <li>
            <label>
                @php
                $selectedBrands = is_array(request('brand')) ? request('brand') : (request('brand') ? explode(',', request('brand')) : []);
                @endphp
                <input type="checkbox" name="brand[]" value="{{ $brand->slug }}"
                    data-filter-type="brand"
                    data-filter-label="{{ $brand->title }}"
                    {{ in_array($brand->slug, $selectedBrands) ? 'checked' : '' }}
                    tabindex="{{ 13 + $index }}"
                    class="filter-checkbox">
                {{ $brand->title }}
            </label>
        </li>
        @endforeach
    </ul>
    @else
    <div class="no-brands">
        <div class="empty-state text-center p-3">
            <i class="fa fa-tags mb-2" style="font-size: 20px;"></i>
            <p>No brands available</p>
            <small>Active brands will appear here</small>
        </div>
    </div>
    @endif
</div>
@endif

<!-- Customer Ratings Widget -->
@if(!isset($mainCategory) || $mainCategory->filters->where('name', 'rating')->count())
<div class="single-widget rating">
    <h3 class="title">Customer Ratings</h3>
    <ul class="categor-list">
        @foreach([4, 3, 2, 1] as $index => $rating)
        <li>
            <label>
                @php
                $minRatings = is_array(request('min_rating')) ? request('min_rating') : (request('min_rating') ? explode(',', request('min_rating')) : []);
                @endphp
                <input type="checkbox" name="min_rating[]" value="{{ $rating }}"
                    data-filter-type="rating"
                    data-filter-label="{{ $rating }} ★ & above"
                    {{ in_array((string)$rating, $minRatings) ? 'checked' : '' }}
                    tabindex="{{ 20 + $index }}"
                    class="filter-checkbox">
                {{ $rating }} ★ & above
            </label>
        </li>
        @endforeach
    </ul>
</div>
@endif

<!-- Discounts Widget -->
@if(!isset($mainCategory) || $mainCategory->filters->where('name', 'discount')->count())
<div class="single-widget discount">
    <h3 class="title">Discounts</h3>
    <ul class="categor-list">
        @foreach([50, 30, 20, 10, 5] as $index => $discount)
        <li>
            <label>
                @php
                $minDiscounts = is_array(request('min_discount')) ? request('min_discount') : (request('min_discount') ? explode(',', request('min_discount')) : []);
                @endphp
                <input type="checkbox" name="min_discount[]" value="{{ $discount }}"
                    data-filter-type="discount"
                    data-filter-label="{{ $discount }}% & above"
                    {{ in_array((string)$discount, $minDiscounts) ? 'checked' : '' }}
                    tabindex="{{ 24 + $index }}"
                    class="filter-checkbox">
                {{ $discount }}% & above
            </label>
        </li>
        @endforeach
    </ul>
</div>
@endif

<!-- Recently Viewed Products Widget -->
@if(!isset($mainCategory) || $mainCategory->filters->where('name', 'recently-viewed')->count())
<div class="single-widget recent-products-widget">
    <h3 class="title">Recently Viewed</h3>
    <div class="recent-products-container">
        @if(isset($recent_products) && $recent_products->count() > 0)
        <div class="recent-products-list">
            @foreach($recent_products->take(4) as $index => $product)
            <div class="recent-product-item">
                <div class="product-thumbnail">
                    <a href="{{ route('product-detail', $product['slug'] ?? '') }}" tabindex="{{ 31 + $index * 4 }}">
                        <img src="{{ $product['image_url'] ?? asset('frontend/images/placeholder.png') }}"
                            alt="{{ $product['title'] ?? 'Product' }}"
                            loading="lazy">
                    </a>
                    @if(isset($product['discount']) && $product['discount'] > 0)
                    <span class="discount-badge">-{{ $product['discount'] }}%</span>
                    @endif
                </div>
                <div class="product-details">
                    <h6 class="product-name">
                        <a href="{{ route('product-detail', $product['slug'] ?? '') }}" tabindex="{{ 32 + $index * 4 }}">
                            {{ Str::limit($product['title'] ?? 'Untitled', 40) }}
                        </a>
                    </h6>
                    <div class="product-price">
                        @if(isset($product['discount']) && $product['discount'] > 0)
                        <span class="new-price">${{ number_format((float)($product['discounted_price'] ?? $product['price'] ?? 0), 2) }}</span>
                        <del class="old-price">${{ number_format((float)($product['price'] ?? 0), 2) }}</del>
                        @else
                        <span class="new-price">${{ number_format((float)($product['price'] ?? 0), 2) }}</span>
                        @endif
                    </div>
                    @if(isset($product['rating']) && $product['rating'] > 0)
                    <div class="product-rating">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star{{ $i <= $product['rating'] ? '' : '-o' }}"></i>
                            @endfor
                            <span class="rating-text">({{ $product['rating_count'] ?? 0 }})</span>
                    </div>
                    @endif
                    <div class="product-actions">
                        @if(($product['stock'] ?? 0) > 0)
                        <a href="{{ route('add-to-cart', $product['slug'] ?? '') }}"
                            class="btn-cart"
                            title="Add to Cart"
                            tabindex="{{ 33 + $index * 4 }}">
                            <i class="ti-shopping-cart"></i>
                        </a>
                        @else
                        <span class="btn-cart disabled" title="Out of Stock">
                            <i class="ti-shopping-cart"></i>
                        </span>
                        @endif
                        <a href="#"
                            class="btn-view"
                            title="Quick View"
                            onclick="event.preventDefault(); $('#productModal{{ $product['id'] ?? '' }}').modal('show');"
                            tabindex="{{ 34 + $index * 4 }}">
                            <i class="ti-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="empty-recent-products">
            <div class="empty-icon">
                <i class="ti-time"></i>
            </div>
            <p class="empty-text">No recently viewed products</p>
            <small class="empty-subtext">Products you view will appear here</small>
        </div>
        @endif
    </div>
    @if(isset($recent_products) && $recent_products->count() > 4)
    {{-- <div class="view-all-container">
        <a href="{{ route('recent-products') }}" class="view-all-link" tabindex="36">
    View All Recent Products <i class="ti-arrow-right"></i>
    </a>
</div> --}}
@endif
</div>
@endif

@push('styles')
<style>
    /* Active Filters Section */
    .active-filters {
        background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(247, 148, 29, 0.1);
    }

    .active-filters .title {
        color: #f7941d;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .clear-all-filters {
        background: none;
        border: none;
        color: #007bff;
        font-size: 12px;
        cursor: pointer;
        padding: 0;
        font-weight: 500;
        transition: color 0.3s ease;
        text-decoration: none;
    }

    .clear-all-filters:hover {
        color: #0056b3;
        text-decoration: underline;
    }

    .active-filters-container {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .active-filter-tag {
        display: inline-flex;
        align-items: center;
        background: #e07c1a;
        color: #ffffff;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        line-height: 1.2;
        cursor: pointer;
        transition: all 0.3s ease;
        border: none;
        text-decoration: none;
        white-space: nowrap;
    }

    .active-filter-tag:hover {
        background: #e07c1a;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(247, 148, 29, 0.3);
        text-decoration: line-through;
    }

    .active-filter-tag i {
        margin-left: 6px;
        font-size: 10px;
        opacity: 0.8;
    }

    .active-filter-tag.price-filter {
        background: #e07c1a;
    }

    .active-filter-tag.price-filter:hover {
        background: #e07c1a;
        ;
    }

    .active-filter-tag.rating-filter {
        background: #e07c1a;
    }

    .active-filter-tag.rating-filter:hover {
        background: #e07c1a;
    }

    .active-filter-tag.discount-filter {
        background: #e07c1a;
    }

    .active-filter-tag.discount-filter:hover {
        background: #e07c1a;
    }

    /* Filter Animation */
    .active-filter-tag {
        animation: slideInFromTop 0.3s ease-out;
    }

    @keyframes slideInFromTop {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Recently Viewed Products Scrollable */
    .recent-products-container {
        position: relative;
        padding: 10px 0;
        overflow: hidden;
    }

    .recent-products-list {
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        max-height: 400px;
        gap: 15px;
        padding: 10px;
        scrollbar-width: thin;
        scrollbar-color: #f7941d #e9ecef;
    }

    .recent-products-list::-webkit-scrollbar {
        width: 6px;
    }

    .recent-products-list::-webkit-scrollbar-track {
        background: #e9ecef;
        border-radius: 3px;
    }

    .recent-products-list::-webkit-scrollbar-thumb {
        background: #f7941d;
        border-radius: 3px;
    }

    .recent-products-list::-webkit-scrollbar-thumb:hover {
        background: #e07c1a;
    }

    .recent-product-item {
        background: #ffffff;
        border: 1px solid #e9ecef;
        border-radius: 6px;
        padding: 10px;
        transition: all 0.3s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .recent-product-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
    }

    .product-thumbnail {
        position: relative;
        overflow: hidden;
        border-radius: 4px;
        flex: 0 0 100px;
    }

    .product-thumbnail img {
        width: 100%;
        height: 100px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .product-thumbnail:hover img {
        transform: scale(1.05);
    }

    .discount-badge {
        position: absolute;
        top: 10px;
        left: 10px;
        background: #f7941d;
        color: #ffffff;
        padding: 2px 8px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 3px;
    }

    .product-details {
        padding: 10px;
        flex: 1;
    }

    .product-name a {
        font-size: 14px;
        font-weight: 600;
        color: #333;
        text-decoration: none;
    }

    .product-name a:hover {
        color: #f7941d;
    }

    .product-price .new-price {
        font-size: 14px;
        font-weight: 600;
        color: #f7941d;
    }

    .product-price .old-price {
        font-size: 12px;
        color: #999;
        margin-left: 5px;
    }

    .product-rating {
        margin: 5px 0;
        font-size: 12px;
        color: #f7941d;
    }

    .rating-text {
        font-size: 11px;
        color: #666;
    }

    .product-actions {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }

    .btn-cart,
    .btn-view {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        background: #f7941d;
        color: #ffffff;
        border-radius: 4px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-cart:hover,
    .btn-view:hover {
        background: #e07c1a;
    }

    .btn-cart.disabled {
        background: #ccc;
        cursor: not-allowed;
    }

    .btn-cart i,
    .btn-view i {
        font-size: 16px;
    }

    /* Navigation Arrows */
    .scroll-arrow {
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        background: #f7941d;
        color: #ffffff;
        border: none;
        border-radius: 50%;
        width: 35px;
        height: 35px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        opacity: 0.8;
        transition: all 0.3s ease;
        z-index: 10;
    }

    .scroll-arrow:hover {
        opacity: 1;
        background: #e07c1a;
    }

    .prev-arrow {
        top: 10px;
    }

    .next-arrow {
        bottom: 10px;
    }

    .scroll-arrow i {
        font-size: 14px;
    }

    .scroll-arrow:disabled {
        background: #ccc;
        cursor: not-allowed;
        opacity: 0.5;
    }

    /* Price Filter */
    .price-filter {
        padding: 15px 0;
    }

    .price-filter-inner {
        padding: 15px;
        background: #ffffff;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    #slider-range {
        margin: 10px 0 15px 0;
        height: 6px;
        background: #e9ecef;
        border: none;
        border-radius: 3px;
        position: relative;
    }

    .ui-slider .ui-slider-handle {
        width: 18px;
        height: 18px;
        background: #f7941d;
        border: 2px solid #ffffff;
        border-radius: 50%;
        top: -6px;
        cursor: grab;
        outline: none;
        box-shadow: 0 1px 4px rgba(247, 148, 29, 0.3);
        transition: all 0.2s ease;
    }

    .ui-slider .ui-slider-handle:hover,
    .ui-slider .ui-slider-handle:focus {
        transform: scale(1.05);
        box-shadow: 0 2px 6px rgba(247, 148, 29, 0.4);
    }

    .ui-slider .ui-slider-handle:active {
        cursor: grabbing;
    }

    .ui-slider .ui-slider-range {
        background: #f7941d;
        border-radius: 3px;
        height: 6px;
    }

    .product_filter {
        display: flex;
        flex-direction: column;
        gap: 8px;
        align-items: stretch;
    }

    .label-input.range-input {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: #ffffff;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.05);
        margin-bottom: 10px;
    }

    .range-label {
        font-weight: 500;
        color: #495057;
        font-size: 13px;
        min-width: 45px;
        white-space: nowrap;
    }

    #amount.price-range-display {
        flex: 1;
        border: none;
        background: transparent;
        padding: 4px;
        font-size: 14px;
        font-weight: 600;
        color: #f7941d;
        text-align: left;
        outline: none;
        cursor: default;
        min-width: 100px;
    }

    .filter_button {
        padding: 8px 16px;
        background: #f7941d;
        color: #ffffff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        box-shadow: 0 1px 3px rgba(247, 148, 29, 0.3);
        width: 100%;
    }

    .filter_button:hover {
        background: #e07c1a;
        box-shadow: 0 2px 6px rgba(247, 148, 29, 0.4);
    }

    .filter_button.loading {
        opacity: 0.7;
        cursor: not-allowed;
        pointer-events: none;
    }

    .filter_button.loading::after {
        content: '';
        width: 16px;
        height: 16px;
        margin-left: 8px;
        border: 2px solid #ffffff;
        border-radius: 50%;
        border-top-color: transparent;
        animation: spin 1s linear infinite;
        display: inline-block;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }

    /* Responsive Design */
    @media (max-width: 576px) {
        .active-filters-container {
            gap: 6px;
        }

        .active-filter-tag {
            font-size: 11px;
            padding: 4px 8px;
        }

        .recent-products-list {
            max-height: 300px;
            gap: 10px;
            padding: 5px;
        }

        .recent-product-item {
            flex-direction: column;
            align-items: center;
        }

        .product-thumbnail {
            flex: 0 0 auto;
            width: 100%;
            max-width: 150px;
        }

        .product-thumbnail img {
            height: 120px;
        }

        .product-details {
            text-align: center;
        }

        .scroll-arrow {
            width: 30px;
            height: 30px;
        }

        .scroll-arrow i {
            font-size: 12px;
        }

        .price-filter-inner {
            padding: 12px;
        }

        .product_filter {
            gap: 12px;
        }

        .label-input.range-input {
            flex-direction: column;
            text-align: center;
            padding: 10px;
        }

        .range-label {
            min-width: auto;
        }

        #amount.price-range-display {
            width: 100%;
            text-align: center;
        }

        .filter_button {
            width: 100%;
            padding: 12px;
        }
    }

    /* Accessibility */
    .scroll-arrow:focus,
    .filter_button:focus,
    .clear-all-filters:focus,
    .active-filter-tag:focus {
        outline: 2px solid #f7941d;
        outline-offset: 2px;
    }

    .label-input.range-input:focus-within {
        border-color: #f7941d;
        box-shadow: 0 0 0 3px rgba(247, 148, 29, 0.1);
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

                    // Reset product visibility on client-side
                    window.productCardHelpers.resetProductVisibility();

                    // Get the base category path or fallback to /product-grids
                    const categorySlug = document.querySelector('meta[name="category-slug"]')?.content || '';
                    const basePath = categorySlug ? `/product-cat/${categorySlug}` : '/product-grids';

                    // Update URL without filter parameters
                    window.location.href = basePath;
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
                            amountDisplay.value = `$${minDefault} - $${maxDefault}`;
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
    this.showLoadingState();

    const params = new URLSearchParams();
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

    // Include category_slug
    const categorySlug = document.querySelector('meta[name="category-slug"]')?.content || '';
    if (categorySlug) {
        params.set('category_slug', categorySlug);
    }

    // Send filters to backend for encryption
    fetch('/encrypt-filters', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify(Object.fromEntries(params)),
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Server returned ${response.status}: ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.encryptedFilters) {
                // Fetch filtered products
                fetch(`/apply-filters/${data.encryptedFilters}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(response => response.json())
                    .then(filterData => {
                        if (filterData.success) {
                            // Update product grid
                            const productGrid = document.querySelector('.product-grid-container');
                            if (productGrid) {
                                productGrid.innerHTML = filterData.html;
                            }
                            // Update URL without reloading
                            const newUrl = categorySlug ? `/product-cat/${categorySlug}?${params.toString()}` : `/product-grids?${params.toString()}`;
                            window.history.pushState({}, '', newUrl);
                            // Update product count
                            window.productCardHelpers.toggleNoResultsMessage(filterData.total);
                        } else {
                            console.error('Filter application failed:', filterData.message);
                            window.productCardHelpers.toggleNoResultsMessage(0);
                        }
                        this.hideLoadingState();
                    })
                    .catch(error => {
                        console.error('Error fetching filtered products:', error);
                        this.hideLoadingState();
                    });
            } else {
                console.error('Encryption failed:', data.message);
                this.hideLoadingState();
            }
        })
        .catch(error => {
            console.error('Error encrypting filters:', error);
            this.hideLoadingState();
        });
}

// Add hideLoadingState method if not present
hideLoadingState() {
    const loadingOverlay = document.querySelector('.filter-loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.remove();
    }
    document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
        checkbox.disabled = false;
    });
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