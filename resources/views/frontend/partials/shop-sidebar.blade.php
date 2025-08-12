{{-- resources/views/frontend/partials/shop-sidebar.blade.php --}}

<!-- Categories Widget -->
<!-- <div class="single-widget category">
    <h3 class="title">Categories</h3>
    <ul class="categor-list">
        @php
        $categoryList = $categories ?? \App\Models\Category::getAllParentWithChild();
        @endphp

        @foreach($categoryList as $cat)
        @php $category = (object) $cat; @endphp

        <li>
            <a href="{{ route('product-cat', $category->slug) }}">{{ $category->title }}</a>

            @if(isset($category->children) && count($category->children))
            <ul>
                @foreach($category->children as $subCat)
                @php $subCategory = (object) $subCat; @endphp
                <li>
                    <a href="{{ route('product-sub-cat', [$category->slug, $subCategory->slug]) }}">
                        {{ $subCategory->title }}
                    </a>
                </li>
                @endforeach
            </ul>
            @endif
        </li>
        @endforeach
    </ul>
</div> -->

<!-- Price Filter Widget -->
<div class="single-widget range">
    <h3 class="title">Shop by Price</h3>
    <div class="price-filter">
        <div class="price-filter-inner">
            <div id="slider-range" data-min="0" data-max="{{ $max_price ?? 1000 }}"></div>
            <div class="product_filter">
                <button type="submit" class="filter_button">Filter</button>
                <div class="label-input">
                    <span>Range:</span>
                    <input type="text" id="amount" readonly />
                    <input type="hidden" name="price_range" id="price_range" value="{{ request('price') }}" />
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Products Widget -->
<div class="single-widget">
    <div class="">
        <h3 class="title">Recently Viewed</h3>
        <!-- @if(isset($recent_products) && count($recent_products) > 0)
        <button class="clear-recent-btn" onclick="clearRecentProducts()" title="Clear Recent Products">
            <i class="fa fa-trash"></i>
        </button>
        @endif -->
    </div>

    <div class="recent-products-container">
        @if(isset($recent_products) && count($recent_products) > 0)
        @foreach($recent_products as $product)
        <div class="single-product card" data-product-id="{{ $product['id'] }}">
            <div class="card-body">
                <div class="image mb-2">
                    <a href="{{ route('product-detail', $product['slug']) }}">
                        <img src="{{ $product['image_url'] }}" alt="{{ $product['title'] }}" loading="lazy" class="img-fluid">
                    </a>
                </div>
                <div class="content">
                    <h5 class="product-title">
                        <a href="{{ route('product-detail', $product['slug']) }}">
                            {{ Str::limit($product['title'], 40) }}
                        </a>
                    </h5>
                    <p class="price">
                        @if($product['discount'] > 0)
                        <del class="text-muted">${{ number_format($product['price'], 2) }}</del>
                        @endif
                        <span class="current-price">${{ number_format($product['discounted_price'], 2) }}</span>
                    </p>
                    <div class="product-actions mt-2 d-flex">
                        <a href="{{ route('add-to-cart', $product['slug']) }}"
                            class="text-dark mr-2 {{ $product['stock'] <= 0 ? 'disabled' : '' }}"
                            title="{{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}">
                            <i class="ti-shopping-cart"></i>
                        </a>
                        <a href="#"
                            class="text-secondary"
                            title="Quick View"
                            onclick="event.preventDefault(); $('#productModal{{ $product['id'] }}').modal('show');">
                            <i class="ti-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        @endforeach
        @else
        <div class="no-recent-products">
            <div class="empty-state">
                <i class="fa fa-clock-o"></i>
                <p>No recently viewed products</p>
                <small>Products you view will appear here</small>
            </div>
        </div>
        @endif
    </div>

    @if(isset($recent_products) && count($recent_products) >= 5)
    <div class="view-all-recent">
        <a href="{{ route('recent-products') }}" class="view-all-btn">
            View All Recent Products <i class="fa fa-arrow-right"></i>
        </a>
    </div>
    @endif
</div>

<!-- Brands Widget -->
<div class="single-widget category">
    <h3 class="title">Brands</h3>
    <ul class="categor-list">
        @foreach(App\Models\Brand::where('status', 'active')->orderBy('title')->get() as $brand)
        <li><a href="{{ route('product-brand', $brand->slug) }}">{{ $brand->title }}</a></li>
        @endforeach
    </ul>
</div>