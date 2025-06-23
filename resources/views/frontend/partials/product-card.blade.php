{{-- resources/views/frontend/partials/product-card.blade.php --}}

@php
$defaultImage = $product->images->where('is_default', true)->first();
$fallbackImage = $product->images->first();
$imageUrl = $defaultImage->image_path ?? ($fallbackImage->image_path ?? 'frontend/img/default-product.png');
$discountedPrice = $product->price - ($product->price * $product->discount / 100);
@endphp

<div class="col-lg-4 col-md-6 col-12">
    <div class="single-product">
        <div class="product-img">
            <a href="{{route('product-detail', $product->slug)}}">
                <img class="default-img" src="{{ asset($imageUrl) }}" alt="{{ $product->title }}">
                <img class="hover-img" src="{{ asset($imageUrl) }}" alt="{{ $product->title }}">
                @if($product->discount)
                <span class="price-dec">{{$product->discount}}% Off</span>
                @endif
            </a>
            <div class="button-head">
                <div class="product-action">
                    <a data-toggle="modal" data-target="#{{$product->id}}" title="Quick View" href="#"><i class="ti-eye"></i><span>Quick Shop</span></a>
                    <a title="Wishlist" href="{{route('add-to-wishlist', $product->slug)}}" class="wishlist" data-id="{{$product->id}}"><i class="ti-heart"></i><span>Add to Wishlist</span></a>
                </div>
                <div class="product-action-2">
                    <a title="Add to cart" href="{{route('add-to-cart', $product->slug)}}">Add to cart</a>
                </div>
            </div>
        </div>
        <div class="product-content">
            <h3><a href="{{route('product-detail', $product->slug)}}">{{$product->title}}</a></h3>
            <span>${{number_format($discountedPrice, 2)}}</span>
            @if($product->discount > 0)
            <del style="padding-left:4%;">${{number_format($product->price, 2)}}</del>
            @endif
        </div>
    </div>
</div>