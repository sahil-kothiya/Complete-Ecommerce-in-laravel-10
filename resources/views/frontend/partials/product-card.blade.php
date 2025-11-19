<!-- Product Card -->
@php
    // Normalize product data (handles both fresh models and cached plain objects/arrays)
    $productData = is_array($product) ? (object) $product : $product;

    $normalizeToArray = function ($value) {
        if ($value instanceof \Illuminate\Support\Collection) {
            return $value->all();
        }

        if (is_object($value)) {
            if ($value instanceof \Traversable) {
                return iterator_to_array($value);
            }

            if (property_exists($value, 'items') && is_array($value->items)) {
                return $value->items;
            }
        }

        if (is_array($value)) {
            return $value;
        }

        return [];
    };

    // Ensure collections are properly handled
    $variants = collect($normalizeToArray($productData->variants ?? []));
    $images = collect($normalizeToArray($productData->images ?? []));

    // Precompute lightweight variant meta for frontend pricing logic
    $variantMeta = [];
    if (($productData->has_variants ?? false) && $variants->count()) {
        $variantMeta = $variants
            ->map(
                fn($v) => [
                    'p' => (float) (is_object($v) ? $v->price ?? 0 : $v['price'] ?? 0),
                    'd' => (float) (is_object($v) ? $v->discount ?? 0 : $v['discount'] ?? 0),
                    's' => (int) (is_object($v) ? $v->stock ?? 0 : $v['stock'] ?? 0),
                ],
            )
            ->values();
    }

    $totalStock =
        $productData->has_variants ?? false
            ? $variants->sum(fn($v) => is_object($v) ? $v->stock ?? 0 : $v['stock'] ?? 0)
            : $productData->base_stock ?? ($productData->stock ?? 0);
    $baseDiscount = (float) ($productData->base_discount ?? 0);
@endphp
<div class="product-card-container mb-4 isotope-item category-{{ $productData->cat_id ?? '' }} px-3"
    data-product-id="{{ $productData->id }}"
    data-product-brand="{{ is_object($productData->brand ?? null) ? $productData->brand->slug ?? '' : '' }}"
    data-product-base-price="{{ $productData->base_price ?? 0 }}" data-product-base-discount="{{ $baseDiscount }}"
    data-product-has-variants="{{ $productData->has_variants ?? false ? '1' : '0' }}"
    data-product-variants='@json($variantMeta)' data-product-stock-total="{{ $totalStock }}"
    data-product-rating="{{ $productData->rating_average ?? 0 }}">
    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
        <div class="position-relative bg-light" style="aspect-ratio: 1 / 1;">
            <div class="slider-wrapper w-100 h-100" data-slider>
                <div class="slider-track d-flex h-100">
                    @php
                        // Images are already provided in the transformed product data
                        // Just ensure we have at least one image
                        if ($images->isEmpty()) {
                            $images = collect([
                                (object) [
                                    'image_path' => asset('images/no-image.png'),
                                    'thumbnail_path' => asset('images/no-image.png'),
                                    'alt_text' => $productData->title ?? 'Product',
                                ],
                            ]);
                        }

                        // Limit to 3 images for performance
                        $images = $images->take(3);
                    @endphp

                    @foreach ($images as $index => $img)
                        @php
                            $imgObj = is_array($img) ? (object) $img : $img;
                            $imgSrc = $imgObj->image_path ?? asset('images/no-image.png');
                            $thumbnailSrc = $imgObj->thumbnail_path ?? $imgSrc;
                            $altText = $imgObj->alt_text ?? ($productData->title ?? 'Product');
                        @endphp

                        <img src="{{ $thumbnailSrc }}" class="slider-image" alt="{{ $altText }}"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}" width="235" height="235"
                            decoding="async" fetchpriority="{{ $index === 0 ? 'high' : 'low' }}"
                            onerror="this.src='{{ asset('images/no-image.png') }}';">
                    @endforeach
                </div>
            </div>

            @if (isset($productData->max_discount) && $productData->max_discount > 0)
                <span class="badge badge-primary badge-status">{{ $productData->max_discount }}% Off</span>
            @elseif(($productData->condition ?? '') === 'new')
                <span class="badge badge-success badge-status">New</span>
            @elseif($totalStock <= 0)
                <span class="badge badge-danger badge-status">Sold Out</span>
            @endif
        </div>

        <div class="card-body d-flex flex-column px-3 py-2">
            <h6 class="text-dark text-truncate mb-1">
                <a href="{{ route('product-detail', $productData->slug) }}" class="text-dark">
                    {{ Str::limit($productData->title ?? 'Product', 50) }}
                </a>
            </h6>

            <!-- Brand Display -->
            @if (isset($productData->brand) && $productData->brand)
                @php
                    $brand = is_array($productData->brand) ? (object) $productData->brand : $productData->brand;
                @endphp
                <small class="text-muted mb-1">
                    <i class="fa fa-tag"></i> {{ $brand->title ?? '' }}
                </small>
            @endif

            <!-- Rating Display -->
            @if (isset($productData->rating_average) && $productData->rating_average > 0)
                <div class="mb-1">
                    <small class="text-warning">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star{{ $i <= $productData->rating_average ? '' : '-o' }}"></i>
                        @endfor
                        <span class="text-muted">({{ $productData->rating_count ?? 0 }})</span>
                    </small>
                </div>
            @endif

            <div class="mb-2 price-container">
                <span class="text-primary font-weight-bold current-price" data-price></span>
                <small class="text-muted ml-2 original-price d-none" data-original-price><del></del></small>
            </div>

            @php
                $productStock = $totalStock;
                $inWishlist = class_exists('Helper') ? Helper::isProductInWishlist($productData->slug) : false;
            @endphp

            <div class="mt-auto">
                <a href="{{ route('add-to-cart', $productData->slug) }}"
                    class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center {{ $productStock <= 0 ? 'disabled' : '' }}">
                    <i class="ti-shopping-cart mr-1"></i>
                    {{ $productStock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </a>

                <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                    <a href="{{ route('add-to-wishlist', $productData->slug) }}" class="text-decoration-none">
                        <i class="ti-heart mr-1" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i> Wishlist
                    </a>
                    <a href="#" class="text-decoration-none text-muted hover-text-dark"
                        onclick="event.preventDefault(); $('#productModal{{ $productData->id }}').modal('show');">
                        <i class="ti-eye mr-1"></i> Quick View
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        .price-container {
            display: flex;
        }

        .original-price {
            font-size: 0.7rem !important;
        }

        /* Product Listing Layout */
        .product-listing-wrapper {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
        }

        /* Filter Transition Effects */
        .product-card-container {
            transition: all 0.3s ease;
            will-change: transform, opacity;
        }

        .product-card-container.filtering {
            pointer-events: none;
        }

        .product-card-container.fade-out {
            opacity: 0;
            transform: scale(0.95);
        }

        .product-card-container.fade-in {
            opacity: 1;
            transform: scale(1);
        }

        /* Enhanced Product Card Hover Effects */
        .product-card {
            transition: all 0.3s ease;
            border: 1px solid transparent !important;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
            border-color: #f7941d !important;
        }

        /* Slider Container */
        .slider-wrapper {
            overflow: hidden;
            height: 100%;
            position: relative;
        }

        /* Slider Track */
        .slider-track {
            display: flex !important;
            width: 100% !important;
            height: 100% !important;
            transition: transform 0.5s ease-in-out;
        }

        /* Slider Image */
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

        /* Status Badge */
        .badge-status {
            position: absolute;
            top: 8px;
            left: 8px;
            font-size: 0.7rem;
            padding: 0.3rem 0.6rem;
            z-index: 10;
            font-weight: 600;
            border-radius: 4px;
        }

        .badge-primary {
            background: #f7941d !important;
            color: white;
        }

        .badge-success {
            background: #28a745 !important;
            color: white;
        }

        .badge-danger {
            background: #f7941d !important;
            color: white;
        }

        /* Enhanced Brand and Rating Display */
        .card-body small.text-muted {
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .text-warning i {
            color: #ffc107 !important;
        }

        /* Price Display Enhancement */
        .card-body .mb-2 {
            border-bottom: 1px solid #eee;
            padding-bottom: 0.5rem;
        }

        /* Button Enhancements */
        .btn-dark {
            background: #333 !important;
            border-color: #333 !important;
            transition: all 0.3s ease;
        }

        .btn-dark:hover:not(.disabled) {
            background: #f7941d !important;
            border-color: #f7941d !important;
            transform: translateY(-1px);
        }

        .btn-dark.disabled {
            background: #6c757d !important;
            border-color: #6c757d !important;
            cursor: not-allowed;
        }

        /* Wishlist and Quick View Links */
        .card-body .d-flex a {
            transition: all 0.3s ease;
            font-size: 0.75rem;
        }

        .card-body .d-flex a:hover {
            color: #f7941d !important;
            transform: translateY(-1px);
        }

        /* Aspect Ratio Polyfill */
        [style*="aspect-ratio"] {
            position: relative;
        }

        [style*="aspect-ratio"]::before {
            content: "";
            display: block;
            padding-bottom: calc(100% / (1 / 1));
        }

        [style*="aspect-ratio"]>*:first-child {
            position: absolute;
            top: 0;
            left: 0;
            bottom: 0;
            right: 0;
        }

        /* Loading States for Filtering */
        .products-loading {
            position: relative;
        }

        .products-loading::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
        }

        /* No Results Message */
        .no-products-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }

        .no-products-message i {
            font-size: 3rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .no-products-message h4 {
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .no-products-message p {
            color: #6c757d;
            margin-bottom: 1.5rem;
        }

        .no-products-message .btn {
            background: #f7941d;
            border-color: #f7941d;
            color: white;
        }

        .no-products-message .btn:hover {
            background: #e07c1a;
            border-color: #e07c1a;
        }

        /* Responsive Column */
        @media (min-width: 1200px) {
            .col-lg-5th {
                flex: 0 0 20%;
                max-width: 20%;
            }
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .product-card:hover {
                transform: none;
            }

            .slider-wrapper:hover .slider-image {
                transform: none;
            }

            .btn-dark:hover:not(.disabled) {
                transform: none;
            }

            .card-body .d-flex a:hover {
                transform: none;
            }
        }

        /* Accessibility Improvements */
        .product-card:focus-within {
            outline: 2px solid #f7941d;
            outline-offset: 2px;
        }

        .btn-dark:focus {
            box-shadow: 0 0 0 3px rgba(247, 148, 29, 0.3);
        }

        /* Print Styles */
        @media print {
            .product-card-container {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .badge-status,
            .card-body .d-flex {
                display: none !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <script>
        // Frontend dynamic pricing & stock rendering (single initialization guard)
        (() => {
            if (window.__productCardPricingInit)
                return; // prevent duplicate initialization when partial included multiple times
            window.__productCardPricingInit = true;

            const formatMoney = (num) => {
                return '$' + Number(num).toFixed(2);
            };

            const computeDiscounted = (price, discountPct) => {
                if (!price || !discountPct || discountPct <= 0) return Number(price);
                return Number(price) - (Number(price) * (Number(discountPct) / 100));
            };

            const renderCards = () => {
                document.querySelectorAll('.product-card-container').forEach(card => {
                    const hasVariants = card.getAttribute('data-product-has-variants') === '1';
                    const basePrice = parseFloat(card.getAttribute('data-product-base-price') || '0');
                    const baseDiscount = parseFloat(card.getAttribute('data-product-base-discount') || '0');
                    const variantsJson = card.getAttribute('data-product-variants');
                    const totalStock = parseInt(card.getAttribute('data-product-stock-total') || '0', 10);
                    let variants = [];
                    try {
                        variants = variantsJson ? JSON.parse(variantsJson) : [];
                    } catch (e) {
                        variants = [];
                    }

                    let displayPrice = null;
                    let originalPrice = null;
                    let effectiveDiscountPct = 0;

                    if (hasVariants && variants.length) {
                        // Consider only in-stock variants
                        const inStock = variants.filter(v => v.s > 0);
                        const targetList = inStock.length ? inStock :
                            variants; // fallback to all if none in stock
                        // Compute discounted prices for each
                        const priced = targetList.map(v => {
                            const discounted = computeDiscounted(v.p, v.d);
                            return {
                                discounted,
                                original: v.p,
                                d: v.d
                            };
                        });
                        // Pick cheapest discounted
                        priced.sort((a, b) => a.discounted - b.discounted);
                        const cheapest = priced[0];
                        if (cheapest) {
                            displayPrice = cheapest.discounted;
                            originalPrice = cheapest.d > 0 ? cheapest.original : null;
                            effectiveDiscountPct = cheapest.d > 0 ? cheapest.d : 0;
                        }
                    } else {
                        // Simple product
                        if (totalStock > 0) {
                            displayPrice = computeDiscounted(basePrice, baseDiscount);
                            originalPrice = baseDiscount > 0 ? basePrice : null;
                            effectiveDiscountPct = baseDiscount > 0 ? baseDiscount : 0;
                        }
                    }

                    // Update price DOM
                    const priceEl = card.querySelector('[data-price]');
                    const originalEl = card.querySelector('[data-original-price]');
                    if (!priceEl) return;

                    if (displayPrice !== null && totalStock > 0) {
                        priceEl.textContent = formatMoney(displayPrice);
                        if (originalPrice && originalEl) {
                            originalEl.classList.remove('d-none');
                            originalEl.querySelector('del').textContent = formatMoney(originalPrice);
                        } else if (originalEl) {
                            originalEl.classList.add('d-none');
                        }
                    } else {
                        priceEl.textContent = 'Out of Stock';
                        if (originalEl) originalEl.classList.add('d-none');
                    }

                    // Badge adjustments (optional, only if discount or stock state changed)
                    const badge = card.querySelector('.badge-status');
                    if (badge) {
                        if (effectiveDiscountPct > 0) {
                            badge.textContent = effectiveDiscountPct + '% Off';
                            badge.classList.remove('badge-danger');
                            badge.classList.add('badge-primary');
                        } else if (totalStock <= 0) {
                            badge.textContent = 'Sold Out';
                            badge.classList.remove('badge-primary', 'badge-success');
                            badge.classList.add('badge-danger');
                        } else if (!hasVariants && effectiveDiscountPct === 0 && badge.textContent
                            .toLowerCase().includes('off')) {
                            // Remove discount badge if no discount remains
                            badge.remove();
                        }
                    } else if (effectiveDiscountPct > 0) {
                        // Create a badge if none exists and discount applies
                        const imgWrapper = card.querySelector('.position-relative');
                        if (imgWrapper) {
                            const span = document.createElement('span');
                            span.className = 'badge badge-primary badge-status';
                            span.textContent = effectiveDiscountPct + '% Off';
                            imgWrapper.appendChild(span);
                        }
                    }
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', renderCards);
            } else {
                renderCards();
            }

            // ---------------------------------------------
            // Hover Auto-Scroll Image Slider Initialization
            // ---------------------------------------------
            const initHoverScrollers = () => {
                document.querySelectorAll('.slider-wrapper').forEach(wrapper => {
                    const track = wrapper.querySelector('.slider-track');
                    if (!track) return;
                    const images = Array.from(track.querySelectorAll('.slider-image'));
                    if (images.length <= 1) return; // nothing to scroll

                    // Ensure track & items sized for horizontal scroll
                    track.style.width = (images.length * 100) + '%';
                    images.forEach(img => {
                        img.style.flex = '0 0 100%';
                    });

                    let idx = 0;
                    let timer = null;

                    const advance = () => {
                        idx = (idx + 1) % images.length;
                        track.style.transform = `translateX(-${idx * 100}%)`;
                    };

                    const start = () => {
                        if (timer) return;
                        timer = setInterval(advance, 1800); // change every 1.8s
                    };

                    const stop = () => {
                        if (timer) {
                            clearInterval(timer);
                            timer = null;
                        }
                        idx = 0;
                        track.style.transform = 'translateX(0)';
                    };

                    wrapper.addEventListener('mouseenter', start);
                    wrapper.addEventListener('mouseleave', stop);
                    wrapper.addEventListener('focusin', start); // keyboard accessibility
                    wrapper.addEventListener('focusout', stop);
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initHoverScrollers);
            } else {
                initHoverScrollers();
            }
        })();
    </script>
@endpush
