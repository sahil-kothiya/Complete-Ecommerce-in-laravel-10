{{-- resources/views/frontend/partials/shop-sidebar.blade.php --}}

<!-- Categories Widget -->
<div class="single-widget category">
    <h3 class="title">Categories</h3>
    <ul class="categor-list">
        @foreach(App\Models\Category::getAllParentWithChild() as $category)
        <li>
            <a href="{{ route('product-cat', $category->slug) }}">{{ $category->title }}</a>
            @if($category->children && $category->children->count() > 0)
            <ul>
                @foreach($category->children as $subCategory)
                <li><a href="{{ route('product-sub-cat', [$category->slug, $subCategory->slug]) }}">{{ $subCategory->title }}</a></li>
                @endforeach
            </ul>
            @endif
        </li>
        @endforeach
    </ul>
</div>

<!-- Price Filter Widget -->
<div class="single-widget range">
    <h3 class="title">Shop by Price</h3>
    <div class="price-filter">
        <div class="price-filter-inner">
            <div id="slider-range" data-min="0" data-max="{{DB::table('products')->max('price')}}"></div>
            <div class="product_filter">
                <button type="submit" class="filter_button">Filter</button>
                <div class="label-input">
                    <span>Range:</span>
                    <input type="text" id="amount" readonly />
                    <input type="hidden" name="price_range" id="price_range" value="{{request('price')}}" />
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Products Widget -->
<div class="single-widget recent-post">
    <h3 class="title">Recent Products</h3>
    @foreach($recent_products as $product)
    @php
    $image = $product->images->first()->image_path ?? 'frontend/img/default-product.png';
    $discountedPrice = $product->price - ($product->price * $product->discount / 100);
    @endphp
    <div class="single-post">
        <div class="image">
            <img src="{{ asset($image) }}" alt="{{ $product->title }}">
        </div>
        <div class="content">
            <h5><a href="{{ route('product-detail', $product->slug) }}">{{ $product->title }}</a></h5>
            <p class="price">
                @if($product->discount > 0)
                <del class="text-muted">${{ number_format($product->price, 2) }}</del>
                @endif
                ${{ number_format($discountedPrice, 2) }}
            </p>
        </div>
    </div>
    @endforeach
</div>

<!-- Brands Widget -->
<div class="single-widget category">
    <h3 class="title">Brands</h3>
    <ul class="categor-list">
        @foreach(DB::table('brands')->orderBy('title','ASC')->where('status','active')->get() as $brand)
        <li><a href="{{route('product-brand', $brand->slug)}}">{{$brand->title}}</a></li>
        @endforeach
    </ul>
</div>