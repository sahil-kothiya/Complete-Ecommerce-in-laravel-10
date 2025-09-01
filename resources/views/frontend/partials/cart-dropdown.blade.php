<!-- Shopping cart container -->
<div class="shopping-item">
    <!-- Cart header with item count and view cart link -->
    <div class="dropdown-cart-header">
        <span>{{ $cartCount ?? 0 }} Items</span>
        <a href="{{ route('cart') }}" tabindex="1">View Cart</a>
    </div>
    <!-- List of cart items -->
    <ul class="shopping-list">
        @foreach(Helper::getAllProductFromCart() as $index => $data)
        @php
        $firstImage = $data->product->images->first(); // Fetch first product image
        $imagePath = $firstImage ? asset($firstImage->image_path) : asset('default.jpg'); // Resolve image path with fallback
        @endphp
        <!-- Individual cart item -->
        <li>
            <a href="{{ route('cart-delete', $data->id) }}" class="remove" title="Remove this item" tabindex="{{ 2 + $index * 3 }}"><i class="fa fa-remove"></i></a>
            <a class="cart-img" href="#" tabindex="{{ 3 + $index * 3 }}"><img src="{{ $imagePath }}" alt="{{ $data->product['title'] }}" loading="lazy"></a>
            <h4><a href="{{ route('product-detail', $data->product['slug']) }}" target="_blank" tabindex="{{ 4 + $index * 3 }}">{{ $data->product['title'] }}</a></h4>
            <p class="quantity">{{ $data->quantity }} x <span class="amount">${{ number_format($data->price, 2) }}</span></p>
        </li>
        @endforeach
    </ul>
    <!-- Cart footer with total and checkout button -->
    <div class="bottom">
        <div class="total">
            <span>Total</span>
            <span class="total-amount">${{ number_format(Helper::totalCartPrice(), 2) }}</span>
        </div>
        <a href="{{ route('checkout') }}" class="btn animate" tabindex="{{ 2 + count(Helper::getAllProductFromCart()) * 3 }}">Checkout</a>
    </div>
</div>