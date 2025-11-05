<!-- Shopping cart container -->
<div class="shopping-item">
    <!-- Cart header with item count and view cart link -->
    <div class="dropdown-cart-header">
        <span>{{ Helper::cartCount() ?? 0 }} Items</span>
        <a href="{{ route('cart') }}" tabindex="1">View Cart</a>
    </div>
    <!-- List of cart items -->
    <ul class="shopping-list">
        @forelse(Helper::getAllProductFromCart() as $index => $data)
        @php
        // Determine the first image: prefer variant images if variant exists, fallback to product images
        $firstImage = null;
        if (isset($data->variant) && $data->variant && $data->variant->images && $data->variant->images->isNotEmpty()) {
            $firstImage = $data->variant->images->first();
        } elseif ($data->product && $data->product->images && $data->product->images->isNotEmpty()) {
            $firstImage = $data->product->images->first();
        }
        $imageUrl = $firstImage ? $firstImage->url : asset('default.jpg'); // Use model accessor for correct URL
        // Build product detail URL with variant_id if applicable for pre-selection
        $productUrl = route('product-detail', $data->product->slug);
        if ($data->variant_id) {
            $productUrl .= '?variant_id=' . $data->variant_id;
        }
        @endphp
        <!-- Individual cart item -->
        <li>
            <a href="{{ route('cart-delete', $data->id) }}" class="remove" title="Remove this item" tabindex="{{ 2 + $index * 3 }}"><i class="fa fa-remove"></i></a>
            <a class="cart-img" href="{{ $productUrl }}" tabindex="{{ 3 + $index * 3 }}"><img src="{{ $imageUrl }}" alt="{{ $data->product->title ?? 'Product Image' }}" loading="lazy"></a>
            <h4><a href="{{ $productUrl }}" target="_blank" tabindex="{{ 4 + $index * 3 }}">{{ $data->product->title ?? 'Product' }}</a></h4>
            <p class="quantity">{{ $data->quantity }} x <span class="amount">${{ number_format($data->price, 2) }}</span></p>
        </li>
        @empty
        <!-- Empty state if no cart items -->
        <li class="text-center py-3 text-muted">
            <em>Your cart is empty</em>
        </li>
        @endforelse
    </ul>
    <!-- Cart footer with total and checkout button -->
    <div class="bottom">
        <div class="total">
            <span>Total</span>
            <span class="total-amount">${{ number_format(Helper::totalCartPrice(), 2) }}</span>
        </div>
        <a href="{{ route('checkout') }}" class="btn animate" tabindex="{{ 2 + (Helper::getAllProductFromCart()->count() * 3) + 1 }}">Checkout</a>
    </div>
</div>