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
    <link rel="stylesheet" href="{{ asset('css/shop-system.css') }}">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/shop-system.js') }}"></script>
@endpush