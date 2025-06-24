{{-- resources/views/frontend/partials/shop-sidebar.blade.php --}}

<!-- Categories Widget -->
<div class="single-widget category">
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
</div>

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
<div class="single-widget recent-post">
    <h3 class="title">Recent Products</h3>
    @php
    $recentList = $recent_products ?? \App\Models\Product::where('status', 'active')
    ->with(['images' => fn($q) => $q->select(['id', 'image_path', 'product_id', 'is_primary'])->orderBy('is_primary', 'desc')])
    ->select(['id', 'title', 'slug', 'price', 'discount'])
    ->latest()->limit(3)->get();
    @endphp

    @foreach($recentList as $product)
    @php
    $product = is_array($product) ? (object) $product : $product;
    $images = $product->images ?? [];
    $firstImage = is_array($images) || $images instanceof \Illuminate\Support\Collection ? collect($images)->first() : null;
    $imgPath = is_array($firstImage) ? ($firstImage['image_path'] ?? '') : ($firstImage->image_path ?? '');
    $imgUrl = $imgPath ? asset($imgPath) : asset('frontend/img/default-product.png');
    $discounted = $product->price - ($product->price * $product->discount / 100);
    @endphp
    <div class="single-post">
        <div class="image">
            <img src="{{ $imgUrl }}" alt="{{ $product->title }}" loading="lazy">
        </div>
        <div class="content">
            <h5><a href="{{ route('product-detail', $product->slug) }}">{{ $product->title }}</a></h5>
            <p class="price">
                @if($product->discount > 0)
                <del class="text-muted">${{ number_format($product->price, 2) }}</del>
                @endif
                ${{ number_format($discounted, 2) }}
            </p>
        </div>
    </div>
    @endforeach
</div>

<!-- Brands Widget -->
<div class="single-widget category">
    <h3 class="title">Brands</h3>
    <ul class="categor-list">
        @foreach(\Illuminate\Support\Facades\DB::table('brands')->where('status', 'active')->orderBy('title')->get() as $brand)
        <li><a href="{{ route('product-brand', $brand->slug) }}">{{ $brand->title }}</a></li>
        @endforeach
    </ul>
</div>