<div class="shopping-item">
    <div class="dropdown-cart-header">
        <span>{{ Helper::wishlistCount() ?? 0 }} Items</span>
        <a href="{{ route('wishlist') }}">View Wishlist</a>
    </div>

    <ul class="shopping-list">
        @forelse(Helper::getAllProductFromWishlist() as $index => $data)
            @php
                // Determine the first image: prefer variant images if variant exists, fallback to product images
                $firstImage = null;
                if (
                    isset($data->variant) &&
                    $data->variant &&
                    $data->variant->images &&
                    $data->variant->images->isNotEmpty()
                ) {
                    $firstImage = $data->variant->images->first();
                } elseif ($data->product && $data->product->images && $data->product->images->isNotEmpty()) {
                    $firstImage = $data->product->images->first();
                }
                $imageUrl = $firstImage ? $firstImage->url : asset('default.jpg');

                // Build product detail URL with variant_id if applicable for pre-selection
                $productUrl = route('product-detail', $data->product->slug);
                if ($data->variant_id) {
                    $productUrl .= '?variant_id=' . $data->variant_id;
                }
            @endphp

            <li>
                <!-- Remove Button -->
                <form action="{{ route('wishlist-delete', $data->id) }}" method="POST" style="display:inline;">
                    @csrf
                    @method('POST')
                    <button type="submit" class="remove" title="Remove this item" tabindex="{{ 2 + $index * 3 }}"
                        style="background:none;border:none;cursor:pointer;padding:0;">
                        <i class="fa fa-remove"></i>
                    </button>
                </form>

                <!-- Product Image -->
                <a class="cart-img" href="{{ $productUrl }}" tabindex="{{ 3 + $index * 3 }}">
                    <img src="{{ $imageUrl }}" alt="{{ $data->product->title ?? 'Product Image' }}" loading="lazy">
                </a>

                <!-- Product Title -->
                <h4>
                    <a href="{{ $productUrl }}" target="_blank" tabindex="{{ 4 + $index * 3 }}">
                        {{ $data->product->title ?? 'Product' }}
                    </a>
                </h4>

                <!-- Price -->
                <p class="quantity">
                    <span class="amount">${{ number_format($data->price, 2) }}</span>
                </p>
            </li>
        @empty
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
        <a href="{{ route('cart') }}" class="btn animate"
            tabindex="{{ 2 + Helper::getAllProductFromWishlist()->count() * 3 + 1 }}">
            Cart
        </a>
    </div>
</div>
