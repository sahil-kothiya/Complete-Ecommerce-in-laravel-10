<!-- Related Products Section (Flipkart/Amazon Style Carousel) -->
<div class="related-carousel-section">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap">
            <div>
                <h2 class="carousel-title mb-0">Similar Products</h2>
            </div>
            <div class="carousel-nav-btns">
                <button type="button" class="carousel-nav-btn" id="carouselPrev" aria-label="Previous products">
                    <i class="ti-angle-left"></i>
                </button>
                <button type="button" class="carousel-nav-btn" id="carouselNext" aria-label="Next products">
                    <i class="ti-angle-right"></i>
                </button>
            </div>
        </div>
        <div class="carousel-viewport position-relative">
            <div class="carousel-track flipkart-carousel" id="relatedCarousel">
                @if($related_products && count($related_products))
                @foreach($related_products as $product)
                @php
                $inWishlist = Helper::isProductInWishlist($product->slug);
                $tabindex = 47;
                @endphp
                <div class="carousel-item flipkart-card" tabindex="{{$tabindex}}">
                    <div class="flipkart-card-img-wrap">
                        <a href="{{ route('product-detail', $product->slug) }}">
                            <img src="{{ $product->images->first() ? asset($product->images->first()->image_path) : asset('images/no-image.png') }}" alt="{{ $product->title }}" class="flipkart-card-img" loading="lazy">
                        </a>
                        @if($product->discount > 0)
                        <span class="flipkart-discount-badge">{{ $product->discount }}% Off</span>
                        @endif
                        <div class="flipkart-card-icons">
                            <a href="{{ route('add-to-wishlist', $product->slug) }}" class="flipkart-icon-btn" title="Add to Wishlist">
                                <i class="ti-heart" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i>
                            </a>
                            <!-- <a href="#" class="flipkart-icon-btn" title="Quick View" onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');"><i class="ti-eye"></i></a> -->
                        </div>
                    </div>
                    <div class="flipkart-card-body">
                        <a href="{{ route('product-detail', $product->slug) }}" class="flipkart-card-title">{{ Str::limit($product->title, 40) }}</a>
                        <div class="flipkart-card-price">
                            @if($product->discount > 0)
                            <span class="flipkart-price-discounted">${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
                            <span class="flipkart-price-original">${{ number_format($product->price, 2) }}</span>
                            @else
                            <span class="flipkart-price-discounted">${{ number_format($product->price, 2) }}</span>
                            @endif
                        </div>
                        <div class="add-to-cart mt-2 d-flex align-items-center gap-2">
                            <a href="{{ route('add-to-cart', $product->slug) }}" class="btn btn-sm btn-dark text-uppercase text-center {{ $product->stock <= 0 ? 'disabled' : '' }}">
                                <i class="ti-shopping-cart"></i> {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                            </a>
                        </div>
                    </div>
                </div>
                @php
                $tabindex++;
                @endphp
                @endforeach
                @else
                <div class="carousel-item text-center" style="min-width:180px;max-width:180px;opacity:0.7;">
                    <div class="p-4">No related products found.</div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
<!-- End Related Products Carousel -->

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
</style>
@endpush

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {
        /* ===============================
			RELATED PRODUCTS CAROUSEL WITH AUTO-SCROLL ON HOVER
		=============================== */
		const track = document.getElementById('relatedCarousel');
		const viewport = track?.closest('.carousel-viewport');
		const prevBtn = document.getElementById('carouselPrev');
		const nextBtn = document.getElementById('carouselNext');

		// Auto-scroll variables
		let autoScrollInterval = null;
		const autoScrollSpeed = 150; // milliseconds between scrolls
		const scrollAmount = 5; // pixels per scroll step

		function getItemWidth() {
			const item = track?.querySelector('.carousel-item');
			if (!item) return 180;
			const style = window.getComputedStyle(item);
			return item.offsetWidth + parseInt(style.marginRight || 0) + parseInt(style.marginLeft || 0);
		}

		function scrollByCard(dir = 1) {
			if (viewport) {
				viewport.scrollBy({
					left: dir * getItemWidth(),
					behavior: 'smooth'
				});
			}
		}

		function startAutoScroll(direction) {
			stopAutoScroll(); // Clear any existing interval

			autoScrollInterval = setInterval(() => {
				if (viewport) {
					viewport.scrollBy({
						left: direction * scrollAmount,
						behavior: 'auto'
					});
				}
			}, autoScrollSpeed);
		}

		function stopAutoScroll() {
			if (autoScrollInterval) {
				clearInterval(autoScrollInterval);
				autoScrollInterval = null;
			}
		}

		// Previous button events
		if (prevBtn) {
			prevBtn.addEventListener('click', () => scrollByCard(-1));

			prevBtn.addEventListener('mouseenter', () => {
				prevBtn.classList.add('auto-scrolling');
				startAutoScroll(-1); // Scroll left
			});

			prevBtn.addEventListener('mouseleave', () => {
				prevBtn.classList.remove('auto-scrolling');
				stopAutoScroll();
			});
		}

		// Next button events
		if (nextBtn) {
			nextBtn.addEventListener('click', () => scrollByCard(1));

			nextBtn.addEventListener('mouseenter', () => {
				nextBtn.classList.add('auto-scrolling');
				startAutoScroll(1); // Scroll right
			});

			nextBtn.addEventListener('mouseleave', () => {
				nextBtn.classList.remove('auto-scrolling');
				stopAutoScroll();
			});
		}

		// Stop auto-scroll when user manually interacts with carousel
		if (viewport) {
			viewport.addEventListener('wheel', stopAutoScroll);
			viewport.addEventListener('touchstart', stopAutoScroll);
			viewport.addEventListener('mousedown', stopAutoScroll);
		}

		// Cleanup on page unload
		window.addEventListener('beforeunload', stopAutoScroll);

    });
</script>
@endpush