{{-- Updated resources/views/components/recently-viewed-carousel.blade.php --}}
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
                    :shimmer-animation="$defaultShimmer"
                    carousel-id="{{ $carouselId }}" />

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
                    :shimmer-animation="$defaultShimmer"
                    carousel-id="{{ $carouselId }}" />
            </div>
        </div>
        <div class="carousel-viewport position-relative">
            <div class="carousel-track flipkart-carousel" id="{{ $carouselId }}">
                @if($products && $products->count() > 0)
                    @foreach($products as $index => $product)
                        @php
                            $inWishlist = Helper::isProductInWishlist($product->slug);
                            $tabindex = 47 + $index * 4;
                            $hasVariants = $product->has_variants ?? false;
                            
                            // Price logic - prioritize base_price, fallback to price, then check variants
                            $basePrice = 0;
                            $baseDiscount = 0;
                            $baseStock = 0;
                            
                            if ($hasVariants && $product->activeVariants && $product->activeVariants->count() > 0) {
                                // Get price from first active variant
                                $firstVariant = $product->activeVariants->first();
                                $basePrice = $firstVariant->price ?? 0;
                                $baseDiscount = $firstVariant->discount ?? 0;
                                $baseStock = $product->inStockVariants ? $product->inStockVariants->count() : 0;
                            } else {
                                // Get price from product base fields or regular fields
                                $basePrice = $product->base_price ?? $product->price ?? 0;
                                $baseDiscount = $product->base_discount ?? $product->discount ?? 0;
                                $baseStock = $product->base_stock ?? $product->stock ?? 0;
                            }
                            
                            $discountedPrice = $baseDiscount > 0 ? $basePrice - ($basePrice * $baseDiscount / 100) : $basePrice;

                            // Determine image source with proper path handling
                            $imageUrl = asset('images/no-image.png'); // Default fallback
                            
                            if ($hasVariants && $product->activeVariants && $product->activeVariants->first()) {
                                // Check for variant image
                                $variantImage = $product->activeVariants->first()->primaryImage;
                                if ($variantImage && $variantImage->image_path) {
                                    // Check if storage path exists, otherwise use direct path
                                    $variantPath = 'products/variants/' . basename($variantImage->image_path);
                                    if (file_exists(public_path('storage/' . $variantPath))) {
                                        $imageUrl = asset('storage/' . $variantPath);
                                    } elseif (file_exists(public_path($variantImage->image_path))) {
                                        $imageUrl = asset($variantImage->image_path);
                                    }
                                }
                            }
                            
                            // Fallback to product image if variant image not found or no variants
                            if ($imageUrl === asset('images/no-image.png')) {
                                $productImage = $product->images->first();
                                if ($productImage && $productImage->image_path) {
                                    // Check if storage path exists, otherwise use direct path
                                    $productPath = 'products/' . basename($productImage->image_path);
                                    if (file_exists(public_path('storage/' . $productPath))) {
                                        $imageUrl = asset('storage/' . $productPath);
                                    } elseif (file_exists(public_path($productImage->image_path))) {
                                        $imageUrl = asset($productImage->image_path);
                                    }
                                }
                            }
                        @endphp
                        <div class="carousel-item flipkart-card" tabindex="{{ $tabindex }}">
                            <div class="flipkart-card-img-wrap">
                                <a href="{{ route('product-detail', $product->slug) }}">
                                    <img src="{{ $imageUrl }}"
                                        alt="{{ $product->title }}"
                                        class="flipkart-card-img"
                                        loading="lazy"
                                        onerror="this.src='{{ asset('images/no-image.png') }}'">
                                </a>
                                @if($baseDiscount > 0)
                                    <span class="flipkart-discount-badge">{{ number_format($baseDiscount, 0) }}% Off</span>
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
                                    @if($basePrice > 0)
                                        @if($baseDiscount > 0)
                                            <span class="flipkart-price-discounted">${{ number_format($discountedPrice, 2) }}</span>
                                            <span class="flipkart-price-original">${{ number_format($basePrice, 2) }}</span>
                                        @else
                                            <span class="flipkart-price-discounted">${{ number_format($basePrice, 2) }}</span>
                                        @endif
                                    @else
                                        <span class="flipkart-price-discounted text-muted">Price not available</span>
                                    @endif
                                </div>
                                <div class="add-to-cart mt-2 d-flex align-items-center gap-2">
                                    @if($basePrice > 0)
                                        <a href="{{ route('add-to-cart', $product->slug) }}"
                                           class="btn btn-sm btn-dark text-uppercase text-center {{ $baseStock > 0 ? '' : 'disabled' }}">
                                            <i class="ti-shopping-cart"></i> {{ $baseStock > 0 ? 'Add to Cart' : 'Out of Stock' }}
                                        </a>
                                    @else
                                        <button class="btn btn-sm btn-secondary text-uppercase text-center disabled">
                                            <i class="ti-info"></i> View Details
                                        </button>
                                    @endif
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
<style>
    /* Ensure images are properly sized */
    .flipkart-card-img {
        width: 100%;
        height: auto;
        object-fit: cover;
    }
    
    /* Price styling */
    .flipkart-card-price {
        min-height: 24px;
    }
    
    .flipkart-price-discounted {
        font-weight: 600;
    }
</style>
@endpush