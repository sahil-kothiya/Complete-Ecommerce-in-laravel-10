<!-- Active Filters Display -->
<div id="active-filters-section" class="single-widget active-filters">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="title mb-0">Filters</h3>
        <button id="clear-all-filters" class="btn btn-sm btn-outline-secondary clear-all-btn" style="display: none;">
            <i class="fa fa-times-circle mr-1"></i> Clear All
        </button>
    </div>
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
        background: #d4691a;
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(247, 148, 29, 0.3);
        color: #ffffff;
        text-decoration: none;
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
        background: #d4691a;
    }

    .active-filter-tag.rating-filter {
        background: #e07c1a;
    }

    .active-filter-tag.rating-filter:hover {
        background: #d4691a;
    }

    .active-filter-tag.discount-filter {
        background: #e07c1a;
    }

    .active-filter-tag.discount-filter:hover {
        background: #d4691a;
    }

    /* Clear All Button */
    .clear-all-btn {
        border: 1px solid #e07c1a;
        color: #e07c1a;
        background: transparent;
        padding: 4px 8px;
        font-size: 11px;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .clear-all-btn:hover {
        background: #e07c1a;
        color: #ffffff;
        border-color: #e07c1a;
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

    /* Fade out animation for removal */
    .active-filter-tag.removing {
        animation: fadeOutAndSlide 0.3s ease-out forwards;
    }

    @keyframes fadeOutAndSlide {
        to {
            opacity: 0;
            transform: translateX(-20px) scale(0.8);
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

        .clear-all-btn {
            font-size: 10px;
            padding: 3px 6px;
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
    .active-filter-tag:focus,
    .clear-all-btn:focus {
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/shop-system.js') }}"></script>
@endpush