<div class="col-sm-6 col-md-4 col-lg-3 p-b-35 isotope-item category-{{ $product->cat_id }} {{ isset($isCarousel) && $isCarousel ? 'carousel-item' : '' }}">
    <div class="single-product">
        <div class="product-img">
            <a href="{{ route('product-detail', $product->slug) }}">
                <img
                    class="default-img"
                    src="{{ asset($product->images->first()?->image_path ?? 'images/no-product-image.jpg') }}"
                    alt="{{ $product->title }}"
                    loading="lazy">
                @if($product->stock <= 0)
                    <span class="out-of-stock">Sold Out</span>
                    @elseif($product->condition == 'new')
                    <span class="new">New</span>
                    @elseif($product->condition == 'hot')
                    <span class="hot">Hot</span>
                    @elseif($product->discount > 0)
                    <span class="price-dec">{{ $product->discount }}% Off</span>
                    @endif
            </a>
            <div class="button-head">
                <div class="product-action">
                    <a data-bs-toggle="modal" data-bs-target="#productModal{{ $product->id }}" title="Quick View" aria-label="Quick View {{ $product->title }}">
                        <i class="ti-eye"></i><span>Quick Shop</span></a>
                    <a href="{{ route('add-to-wishlist', $product->slug) }}" title="Wishlist" aria-label="Add {{ $product->title }} to wishlist">
                        <i class="ti-heart"></i><span>Add to Wishlist</span>
                    </a>
                </div>
                <div class="product-action-2">
                    @if($product->stock > 0)
                    <a href="{{ route('add-to-cart', $product->slug) }}" title="Add to cart" aria-label="Add {{ $product->title }} to cart">Add to Cart</a>
                    @else
                    <span class="out-of-stock-btn">Out of Stock</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="product-content">
            <h3><a href="{{ route('product-detail', $product->slug) }}">{{ Str::limit($product->title, 50) }}</a></h3>
            <div class="product-price">
                @if($product->discount > 0)
                <span class="current-price">${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
                <span class="original-price"><del>${{ number_format($product->price, 2) }}</del></span>
                @else
                <span class="current-price">${{ number_format($product->price, 2) }}</span>
                @endif
            </div>
        </div>
    </div>
</div>