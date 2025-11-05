<div class="shopping-item">
    <div class="dropdown-cart-header">
        <span>{{ Helper::wishlistCount() ?? 0 }} Items</span>
        <a href="{{ route('wishlist') }}">View Wishlist</a>
    </div>

    <ul class="shopping-list">
        @forelse(Helper::getAllProductFromWishlist() as $index => $data)
            @php
                // Determine image: variant image → product image → default
                $imageUrl = asset('default.jpg');
                if ($data->variant && $data->variant->images->isNotEmpty()) {
                    $imageUrl = $data->variant->images->first()->url ?? $imageUrl;
                } elseif ($data->product && $data->product->images->isNotEmpty()) {
                    $imageUrl = $data->product->images->first()->url ?? $imageUrl;
                }

                // Variant display name
                $variantName = $data->variant?->display_name ?? '';

                // Price (variant price with discount)
                $price = $data->variant
                    ? $data->variant->discounted_price
                    : ($data->product->base_price - ($data->product->base_price * ($data->product->base_discount ?? 0) / 100));

                $productSlug = $data->product->slug;
                $productTitle = $data->product->title;
            @endphp

            <li>
                <!-- Remove Button -->
                <form action="{{ route('wishlist-delete', $data->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('POST')
                    <button type="submit" class="remove btn-link p-0 border-0 bg-transparent" title="Remove">
                        <i class="fa fa-remove"></i>
                    </button>
                </form>

                <!-- Product Image -->
                <a class="cart-img" href="{{ route('product-detail', $productSlug) }}" tabindex="{{ 3 + $index * 3 }}">
                    <img src="{{ $imageUrl }}" alt="{{ $productTitle }}" loading="lazy" style="width:60px;height:60px;object-fit:cover;">
                </a>

                <div class="cart-item-details">
                    <!-- Product Title -->
                    <h4 class="mb-1">
                        <a href="{{ route('product-detail', $productSlug) }}" class="text-dark text-decoration-none">
                            {{ Str::limit($productTitle, 40) }}
                        </a>
                    </h4>

                    <!-- Variant Info -->
                    @if($variantName)
                        <p class="text-muted small mb-1">
                            <em>{{ $variantName }}</em>
                        </p>
                    @endif

                    <!-- Price -->
                    <p class="quantity mb-0">
                        <span class="amount text-primary fw-bold">
                            ${{ number_format($price, 2) }}
                        </span>
                    </p>
                </div>
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
        <a href="{{ route('cart') }}"
           class="btn animate"
           tabindex="{{ 2 + (Helper::getAllProductFromWishlist()->count() * 3) + 1 }}">
            Cart
        </a>
    </div>
</div>