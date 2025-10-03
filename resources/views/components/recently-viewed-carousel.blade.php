{{-- resources/views/components/recently-viewed-carousel.blade.php --}}
<!-- Recently Viewed Section (Flipkart/Amazon Style Carousel) -->
<div class="recently-viewed-section">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div>
                <h2 class="carousel-title mb-0">{{ $title }}</h2>
            </div>
            <div class="carousel-nav-btns">
                <x-carousel-nav-button 
                    :id="$prevButtonId"
                    aria-label="Previous products"
                    icon="ti-angle-left"
                    :direction="-1"
                    :background-color="$defaultBackgroundColor"
                    :text-color="$defaultTextColor"
                    :hover-scale="$defaultHoverScale"
                    :hover-shadow="$defaultHoverShadow"
                    :auto-scroll-speed="$defaultAutoScrollSpeed"
                    :scroll-amount="$defaultScrollAmount"
                    :shimmer-animation="$defaultShimmer" />

                <x-carousel-nav-button 
                    :id="$nextButtonId"
                    aria-label="Next products"
                    icon="ti-angle-right"
                    :direction="1"
                    :background-color="$defaultBackgroundColor"
                    :text-color="$defaultTextColor"
                    :hover-scale="$defaultHoverScale"
                    :hover-shadow="$defaultHoverShadow"
                    :auto-scroll-speed="$defaultAutoScrollSpeed"
                    :scroll-amount="$defaultScrollAmount"
                    :shimmer-animation="$defaultShimmer" />
            </div>
        </div>
        <div class="carousel-viewport position-relative">
            <div class="carousel-track flipkart-carousel" :id="$carouselId">
                @if($products && $products->count() > 0)
                    @foreach($products as $index => $product)
                        @php
                            $inWishlist = Helper::isProductInWishlist($product->slug);
                            $tabindex = 47 + $index * 4;
                        @endphp
                        <div class="carousel-item flipkart-card" tabindex="{{ $tabindex }}">
                            <div class="flipkart-card-img-wrap">
                                <a href="{{ route('product-detail', $product->slug) }}">
                                    <img src="{{ $product->images->first() ? asset($product->images->first()->image_path) : asset('images/no-image.png') }}"
                                        alt="{{ $product->title }}"
                                        class="flipkart-card-img"
                                        loading="lazy">
                                </a>
                                @if($product->discount > 0)
                                    <span class="flipkart-discount-badge">{{ $product->discount }}% Off</span>
                                @endif
                                <div class="flipkart-card-icons">
                                    <a href="{{ route('add-to-wishlist', $product->slug) }}"
                                       class="flipkart-icon-btn"
                                       title="Add to Wishlist">
                                        <i class="ti-heart" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="flipkart-card-body">
                                <a href="{{ route('product-detail', $product->slug) }}"
                                   class="flipkart-card-title">{{ Str::limit($product->title, 40) }}</a>
                                <div class="flipkart-card-price">
                                    @if($product->discount > 0)
                                        <span class="flipkart-price-discounted">${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
                                        <span class="flipkart-price-original">${{ number_format($product->price, 2) }}</span>
                                    @else
                                        <span class="flipkart-price-discounted">${{ number_format($product->price, 2) }}</span>
                                    @endif
                                </div>
                                <div class="add-to-cart mt-2 d-flex align-items-center gap-2">
                                    <a href="{{ route('add-to-cart', $product->slug) }}"
                                       class="btn btn-sm btn-dark text-uppercase text-center {{ $product->stock <= 0 ? 'disabled' : '' }}">
                                        <i class="ti-shopping-cart"></i> {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="carousel-item text-center" style="min-width: {{ $itemMinWidth }}; max-width: {{ $itemMaxWidth }}; opacity: 0.7;">
                        <div class="p-4">{{ $noProductsMessage }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
<!-- End Recently Viewed Carousel -->

@push('styles')
<link rel="stylesheet" href="{{ asset('css/product-detail.css') }}">
@endpush