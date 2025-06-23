<div class="shopping-item">
    <div class="dropdown-cart-header">
        <span>{{ $wishlistCount ?? 0 }} Items</span>
        <a href="{{ route('wishlist') }}">View Wishlist</a>
    </div>
    <ul class="shopping-list">
        @foreach(Helper::getAllProductFromWishlist() as $data)
        @php $photo = explode(',', $data->product['photo'])[0]; @endphp
        <li>
            <a href="{{ route('wishlist-delete', $data->id) }}" class="remove" title="Remove this item"><i class="fa fa-remove"></i></a>
            <a class="cart-img" href="#"><img src="{{ $photo }}" alt="{{ $data->product['title'] }}" loading="lazy"></a>
            <h4><a href="{{ route('product-detail', $data->product['slug']) }}" target="_blank">{{ $data->product['title'] }}</a></h4>
            <p class="quantity">{{ $data->quantity }} x <span class="amount">${{ number_format($data->price, 2) }}</span></p>
        </li>
        @endforeach
    </ul>
    <div class="bottom">
        <div class="total">
            <span>Total</span>
            <span class="total-amount">${{ number_format(Helper::totalWishlistPrice(), 2) }}</span>
        </div>
        <a href="{{ route('cart') }}" class="btn animate">Cart</a>
    </div>
</div>