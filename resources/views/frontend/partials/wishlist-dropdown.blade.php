<div class="shopping-item">
    <div class="dropdown-cart-header">
        <span>{{ $wishlistCount ?? 0 }} Items</span>
        <a href="{{ route('wishlist') }}">View Wishlist</a>
    </div>
    <ul class="shopping-list">
        @forelse(Helper::getAllProductFromWishlist() as $index => $data)
        @php
        // Get first product image (no variant support in Wishlist yet)
        $firstImage = $data->product && $data->product->images && $data->product->images->isNotEmpty() 
            ? $data->product->images->first() 
            : null;
        $imageUrl = $firstImage ? $firstImage->url : asset('default.jpg'); // Use model accessor for correct URL
        @endphp
        <li>
            <a href="{{ route('wishlist-delete', $data->id) }}" class="remove" title="Remove this item" tabindex="{{ 2 + $index * 3 }}"><i class="fa fa-remove"></i></a>
            <a class="cart-img" href="{{ route('product-detail', $data->product->slug) }}" tabindex="{{ 3 + $index * 3 }}"><img src="{{ $imageUrl }}" alt="{{ $data->product->title ?? 'Product Image' }}" loading="lazy"></a>
            <h4><a href="{{ route('product-detail', $data->product->slug) }}" target="_blank" tabindex="{{ 4 + $index * 3 }}">{{ $data->product->title ?? 'Product' }}</a></h4>
            <p class="quantity">{{ $data->quantity }} x <span class="amount">${{ number_format($data->price, 2) }}</span></p>
        </li>
        @empty
        <!-- Empty state if no wishlist items -->
        <li class="text-center py-3 text-muted">
            <em>Your wishlist is empty</em>
        </li>
        @endforelse
    </ul>
    <div class="bottom">
        <div class="total">
            <span>Total</span>
            <span class="total-amount">${{ number_format(Helper::totalWishlistPrice(), 2) }}</span>
        </div>
        <a href="{{ route('cart') }}" class="btn animate" tabindex="{{ 2 + (Helper::getAllProductFromWishlist()->count() * 3) + 1 }}">Cart</a>
    </div>
</div>