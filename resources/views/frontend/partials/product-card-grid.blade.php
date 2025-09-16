<!-- Product Card -->
<div class="product-card-container mb-4 isotope-item category-{{ $product->cat_id }} px-3"
    data-product-id="{{ $product->id }}"
    data-product-brand="{{ $product->brand->slug ?? '' }}"
    data-product-price="{{ $product->price }}"
    data-product-discount="{{ $product->discount }}"
    data-product-rating="{{ $product->rating ?? 0 }}">
    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
        <div class="position-relative product-image-container">
            <div class="slider-wrapper w-100 h-100" data-slider>
                <div class="slider-track d-flex h-100">
                    @if(count($product->images) > 0)
                    @foreach($product->images as $index => $img)
                    @php
                    $pathInfo = pathinfo($img->image_path);
                    $directory = $pathInfo['dirname'];
                    $filename = $pathInfo['filename'];
                    $extension = $pathInfo['extension'];
                    $srcset = [];
                    $sizes = [160, 235, 320, 480];
                    foreach ($sizes as $size) {
                    $responsivePath = "{$directory}/{$filename}_{$size}x{$size}.webp";
                    if (file_exists(public_path($responsivePath))) {
                    $srcset[] = asset($responsivePath) . " {$size}w";
                    }
                    }
                    $srcset[] = asset($img->image_path) . " 370w";
                    $srcsetString = implode(', ', $srcset);
                    @endphp
                    <img
                        src="{{ asset($img->image_path) }}"
                        srcset="{{ $srcsetString }}"
                        class="slider-image"
                        alt="{{ $product->title }}"
                        loading="lazy"
                        width="235"
                        height="235"
                        decoding="async"
                        fetchpriority="low"
                        onerror="this.src='{{ asset('images/no-image.png') }}'; this.onerror=null;">
                    @endforeach
                    @else
                    <div class="no-image-placeholder">
                        <img
                            src="{{ asset('images/no-image.png') }}"
                            class="slider-image placeholder-image"
                            alt="{{ $product->title }} - No Image Available"
                            loading="lazy"
                            width="235"
                            height="235"
                            decoding="async"
                            fetchpriority="low">
                        <div class="no-image-text">
                            <i class="fa fa-image"></i>
                            <span>No Image</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            @if($product->discount > 0)
            <span class="badge badge-primary badge-status">{{ $product->discount }}% Off</span>
            @elseif($product->condition === 'new')
            <span class="badge badge-success badge-status">New</span>
            @elseif($product->stock <= 0)
                <span class="badge badge-danger badge-status">Sold Out</span>
                @endif
        </div>

        <div class="card-body d-flex flex-column px-3 py-2">
            <h6 class="text-dark text-truncate mb-1">
                <a href="{{ route('product-detail', $product->slug) }}" class="text-dark product-title">
                    {{ Str::limit($product->title, 50) }}
                </a>
            </h6>

            <!-- Brand Display -->
            @if($product->brand)
            <small class="text-muted mb-1 brand-info">
                <i class="fa fa-tag"></i> {{ $product->brand->title }}
            </small>
            @endif

            <!-- Rating Display -->
            @if($product->rating > 0)
            <div class="mb-1 rating-container">
                <small class="text-warning">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="fa fa-star{{ $i <= $product->rating ? '' : '-o' }}"></i>
                        @endfor
                        <span class="text-muted rating-count">({{ $product->rating_count ?? 0 }})</span>
                </small>
            </div>
            @endif

            <div class="mb-2 price-container">
                @if($product->discount > 0)
                <span class="text-primary font-weight-bold current-price">
                    ${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}
                </span>
                <small class="text-muted ml-2 original-price"><del>${{ number_format($product->price, 2) }}</del></small>
                @else
                <span class="font-weight-bold text-primary current-price">${{ number_format($product->price, 2) }}</span>
                @endif
            </div>

            @php
            $inWishlist = Helper::isProductInWishlist($product->slug);
            @endphp

            <div class="mt-auto action-buttons">
                <a href="{{ route('add-to-cart', $product->slug) }}"
                    class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center add-to-cart-btn {{ $product->stock <= 0 ? 'disabled' : '' }}">
                    <i class="ti-shopping-cart mr-1"></i>
                    {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </a>

                <div class="d-flex justify-content-between align-items-center small text-muted px-1 secondary-actions">
                    <a href="{{ route('add-to-wishlist', $product->slug) }}"
                        class="text-decoration-none wishlist-link">
                        <i class="ti-heart mr-1" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i> Wishlist
                    </a>
                    <a href="#"
                        class="text-decoration-none text-muted hover-text-dark quick-view-link"
                        onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');">
                        <i class="ti-eye mr-1"></i> Quick View
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .slider-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        flex-shrink: 0;
        image-rendering: auto;
        transform: translateZ(0);
        will-change: transform;
        backface-visibility: hidden;
        transition: transform 0.3s ease;
    }

    .slider-wrapper:hover .slider-image {
        transform: scale(1.05);
    }

    /* Product grid container */
    .product-grid-row {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        justify-content: flex-start;
    }
</style>