<!-- Recently Viewed Section (Flipkart/Amazon Style Carousel) -->
<div class="recently-viewed-section">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div>
                <h2 class="carousel-title mb-0">Recently Viewed</h2>
            </div>
            <div class="carousel-nav-btns">
                <button type="button" class="carousel-nav-btn" id="recentCarouselPrev" aria-label="Previous products">
                    <i class="ti-angle-left"></i>
                </button>
                <button type="button" class="carousel-nav-btn" id="recentCarouselNext" aria-label="Next products">
                    <i class="ti-angle-right"></i>
                </button>
            </div>
        </div>
        <div class="carousel-viewport position-relative">
            <div class="carousel-track flipkart-carousel" id="recentCarousel">
                @if ($recent_products && count($recent_products))
                    @foreach ($recent_products as $index => $product)
                        @php
                            $inWishlist = Helper::isProductInWishlist($product->slug);
                            $tabindex = 47 + $index * 4;
                            $basePrice = $product->base_price ?? 0;
                            $baseDiscount = $product->base_discount ?? 0;
                            $discountedPrice =
                                $baseDiscount > 0 ? $basePrice - ($basePrice * $baseDiscount) / 100 : $basePrice;
                            $baseStock = $product->base_stock ?? 0;
                            $hasVariants = $product->has_variants ?? false;

                            // Determine image source using url accessors
                            $imageUrl = asset('images/no-image.png'); // Default fallback

                            if ($hasVariants && $product->activeVariants && $product->activeVariants->first()) {
                                // Use variant image url accessor
                                $variantImage = $product->activeVariants->first()->primaryImage;
                                if ($variantImage) {
                                    $imageUrl = $variantImage->url;
                                }
                            }

                            // Fallback to product image if variant image not found or no variants
                            if ($imageUrl === asset('images/no-image.png')) {
                                $productImage = $product->images->first();
                                if ($productImage) {
                                    $imageUrl = $productImage->url;
                                }
                            }
                        @endphp
                        <div class="carousel-item flipkart-card" tabindex="{{ $tabindex }}">
                            <div class="flipkart-card-img-wrap">
                                <a href="{{ route('product-detail', $product->slug) }}">
                                    <img src="{{ $imageUrl }}" alt="{{ $product->title }}" class="flipkart-card-img"
                                        loading="lazy" onerror="this.src='{{ asset('images/no-image.png') }}'">
                                </a>
                                @if ($baseDiscount > 0)
                                    <span class="flipkart-discount-badge">{{ $baseDiscount }}% Off</span>
                                @endif
                                <div class="flipkart-card-icons">
                                    <a href="{{ route('add-to-wishlist', $product->slug) }}" class="flipkart-icon-btn"
                                        title="Add to Wishlist">
                                        <i class="ti-heart" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i>
                                    </a>
                                </div>
                            </div>
                            <div class="flipkart-card-body">
                                <a href="{{ route('product-detail', $product->slug) }}"
                                    class="flipkart-card-title">{{ Str::limit($product->title, 40) }}</a>
                                <div class="flipkart-card-price">
                                    @if ($baseDiscount > 0)
                                        <span
                                            class="flipkart-price-discounted">${{ number_format($discountedPrice, 2) }}</span>
                                        <span
                                            class="flipkart-price-original">${{ number_format($basePrice, 2) }}</span>
                                    @else
                                        <span
                                            class="flipkart-price-discounted">${{ number_format($basePrice, 2) }}</span>
                                    @endif
                                </div>
                                <div class="add-to-cart mt-2 d-flex align-items-center gap-2">
                                    <a href="{{ route('add-to-cart', $product->slug) }}"
                                        class="btn btn-sm btn-dark text-uppercase text-center {{ ($hasVariants ? $product->inStockVariants->count() > 0 : $baseStock > 0) ? '' : 'disabled' }}">
                                        <i class="ti-shopping-cart"></i>
                                        {{ $hasVariants ? ($product->inStockVariants->count() > 0 ? 'Add to Cart' : 'Out of Stock') : ($baseStock > 0 ? 'Add to Cart' : 'Out of Stock') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="carousel-item text-center" style="min-width:180px;max-width:180px;opacity:0.7;">
                        <div class="p-4">No recently viewed products found.</div>
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
        /* Enhanced button hover effects for auto-scroll */
        .carousel-nav-btn {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .carousel-nav-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .carousel-nav-btn.auto-scrolling {
            background-color: #007bff !important;
            color: white !important;
        }

        .carousel-nav-btn.auto-scrolling::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 1s infinite;
        }

        @keyframes shimmer {
            0% {
                left: -100%;
            }

            100% {
                left: 100%;
            }
        }

        /* Ensure images are properly sized */
        .flipkart-card-img {
            width: 100%;
            height: auto;
            object-fit: cover;
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            /* ===============================
                RECENTLY VIEWED CAROUSEL WITH AUTO-SCROLL ON HOVER
            =============================== */
            const track = document.getElementById('recentCarousel');
            const viewport = track?.closest('.carousel-viewport');
            const prevBtn = document.getElementById('recentCarouselPrev');
            const nextBtn = document.getElementById('recentCarouselNext');

            // Validate required elements exist
            if (!track || !viewport || !prevBtn || !nextBtn) {
                console.warn('Carousel elements not found');
                return;
            }

            // Auto-scroll variables
            let autoScrollInterval = null;
            const autoScrollSpeed = 50; // milliseconds between scrolls (smoother)
            const scrollAmount = 2; // pixels per scroll step (smoother)

            function getItemWidth() {
                const item = track.querySelector('.carousel-item');
                if (!item) return 180;
                const style = window.getComputedStyle(item);
                return item.offsetWidth + parseInt(style.marginRight || 0) + parseInt(style.marginLeft || 0);
            }

            function scrollByCard(dir = 1) {
                const itemWidth = getItemWidth();
                viewport.scrollBy({
                    left: dir * itemWidth,
                    behavior: 'smooth'
                });
            }

            function startAutoScroll(direction) {
                stopAutoScroll(); // Clear any existing interval

                autoScrollInterval = setInterval(() => {
                    const maxScroll = viewport.scrollWidth - viewport.clientWidth;
                    const currentScroll = viewport.scrollLeft;

                    // Stop at boundaries
                    if ((direction < 0 && currentScroll <= 0) ||
                        (direction > 0 && currentScroll >= maxScroll)) {
                        stopAutoScroll();
                        return;
                    }

                    viewport.scrollBy({
                        left: direction * scrollAmount,
                        behavior: 'auto'
                    });
                }, autoScrollSpeed);
            }

            function stopAutoScroll() {
                if (autoScrollInterval) {
                    clearInterval(autoScrollInterval);
                    autoScrollInterval = null;
                }
                // Remove active state from both buttons
                prevBtn.classList.remove('auto-scrolling');
                nextBtn.classList.remove('auto-scrolling');
            }

            // Previous button events
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                scrollByCard(-1);
            });

            prevBtn.addEventListener('mouseenter', () => {
                prevBtn.classList.add('auto-scrolling');
                startAutoScroll(-1); // Scroll left
            });

            prevBtn.addEventListener('mouseleave', stopAutoScroll);

            // Next button events
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                scrollByCard(1);
            });

            nextBtn.addEventListener('mouseenter', () => {
                nextBtn.classList.add('auto-scrolling');
                startAutoScroll(1); // Scroll right
            });

            nextBtn.addEventListener('mouseleave', stopAutoScroll);

            // Stop auto-scroll when user manually interacts with carousel
            viewport.addEventListener('wheel', stopAutoScroll);
            viewport.addEventListener('touchstart', stopAutoScroll);
            viewport.addEventListener('mousedown', stopAutoScroll);
            viewport.addEventListener('scroll', () => {
                // Update button states based on scroll position
                const maxScroll = viewport.scrollWidth - viewport.clientWidth;
                const currentScroll = viewport.scrollLeft;

                prevBtn.disabled = currentScroll <= 0;
                nextBtn.disabled = currentScroll >= maxScroll;
            });

            // Cleanup on page unload
            window.addEventListener('beforeunload', stopAutoScroll);

            // Initial button state check
            setTimeout(() => {
                const maxScroll = viewport.scrollWidth - viewport.clientWidth;
                prevBtn.disabled = viewport.scrollLeft <= 0;
                nextBtn.disabled = viewport.scrollLeft >= maxScroll;
            }, 100);
        });
    </script>
@endpush
