<!-- Product Card -->
@php
    // Normalize product data (handles both fresh models and cached plain objects/arrays)
    $productData = is_array($product) ? (object) $product : $product;

    // Skip rendering if product has no valid ID
    if (!isset($productData->id) || $productData->id === null || empty($productData->id)) {
        return; // Don't render anything for invalid products
}

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

    // Use backend-calculated stock (already computed in transformProductForDisplay)
    $totalStock = (int) ($productData->stock ?? 0);
    $maxDiscount = (float) ($productData->max_discount ?? 0);
@endphp
<div class="product-card-container mb-4 isotope-item category-{{ $productData->cat_id ?? '' }} px-3"
    @isset($tabindex) tabindex="{{ $tabindex }}" @endisset data-product-id="{{ $productData->id }}"
    data-product-brand="{{ is_object($productData->brand ?? null) ? $productData->brand->slug ?? '' : '' }}"
    data-product-base-price="{{ $productData->base_price ?? 0 }}" data-product-max-discount="{{ $maxDiscount }}"
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
                            $altText = $imgObj->alt_text ?? ($productData->title ?? 'Product');
                        @endphp

                        <img src="{{ $imgSrc }}"
                            srcset="{{ $imgSrc }} 235w, {{ $imgSrc }} 370w, {{ $imgSrc }} 470w"
                            sizes="(max-width: 576px) 100vw, (max-width: 768px) 50vw, (max-width: 992px) 33vw, 250px"
                            class="slider-image" alt="{{ $altText }}"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}" width="235" height="235"
                            decoding="async" fetchpriority="{{ $index === 0 ? 'high' : 'low' }}"
                            onerror="this.src='{{ asset('images/no-image.png') }}'; console.error('{{ $productData->id }} - Image load failed - src: {{ $imgSrc }}');"
                            onload="if({{ $index }} === 0) { console.log('{{ $productData->id }} - src: {{ $imgSrc }}'); if('{{ $imgSrc }}'.includes('no-image.png')) { console.warn('{{ $productData->id }} - Using fallback no-image.png - Check product images in DB'); } }">
                    @endforeach
                </div>
            </div>

            @if ($maxDiscount > 0)
                <span class="badge badge-primary badge-status" role="status"
                    aria-label="Discount">{{ number_format($maxDiscount, 0) }}% Off</span>
            @elseif($totalStock <= 0)
                <span class="badge badge-danger badge-status" role="status" aria-label="Stock status">Sold Out</span>
            @elseif(($productData->condition ?? '') === 'new')
                <span class="badge badge-success badge-status" role="status" aria-label="Product condition">New</span>
            @endif
        </div>

        <div class="card-body d-flex flex-column px-2 py-2">
            <h3 class="h6 text-dark text-truncate mb-2" style="font-size: 0.9rem; line-height: 1.3;">
                <a href="{{ route('product-detail', $productData->slug) }}" class="text-dark"
                    aria-label="View details for {{ Str::limit($productData->title ?? 'Product', 50) }}">
                    {{ Str::limit($productData->title ?? 'Product', 50) }}
                </a>
            </h3>

            <!-- Brand Display -->
            @if (isset($productData->brand) && $productData->brand)
                @php
                    $brand = is_array($productData->brand) ? (object) $productData->brand : $productData->brand;
                @endphp
                <small class="text-muted mb-1" style="font-size: 0.75rem;">
                    <i class="fa fa-tag" aria-hidden="true"></i> {{ $brand->title ?? '' }}
                </small>
            @endif

            <!-- Rating Display -->
            @if (isset($productData->rating_average) && $productData->rating_average > 0)
                <div class="mb-1">
                    <small class="text-warning" style="font-size: 0.75rem;">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star{{ $i <= $productData->rating_average ? '' : '-o' }}"
                                aria-hidden="true"></i>
                        @endfor
                        <span class="text-muted">({{ $productData->rating_count ?? 0 }})</span>
                    </small>
                </div>
            @endif

            <div class="mb-2 price-container" style="min-height: 24px;">
                <span class="text-primary font-weight-bold current-price" data-price></span>
                <small class="text-muted ml-2 original-price d-none" data-original-price><del></del></small>
            </div>

            @php
                $productStock = $totalStock;
                $inWishlist = class_exists('Helper') ? Helper::isProductInWishlist($productData->slug) : false;
                $hasVariants = $productData->has_variants ?? false;

                // Get default variant ID for variant products (first in-stock variant)
                $defaultVariantId = null;
                if ($hasVariants && $variants->count() > 0) {
                    $firstInStock = $variants->first(function ($v) {
                        $stock = is_object($v) ? $v->stock ?? 0 : $v['stock'] ?? 0;
                        return $stock > 0;
                    });

                    if ($firstInStock) {
                        $defaultVariantId = is_object($firstInStock)
                            ? $firstInStock->id ?? null
                            : $firstInStock['id'] ?? null;
                    } else {
                        // If no in-stock variant, use first variant
                        $firstVariant = $variants->first();
                        $defaultVariantId = is_object($firstVariant)
                            ? $firstVariant->id ?? null
                            : $firstVariant['id'] ?? null;
                    }
                }
            @endphp

            <div class="mt-auto">
                <form action="{{ route('single-add-to-cart') }}" method="POST" class="d-inline w-100">
                    @csrf
                    <input type="hidden" name="slug" value="{{ $productData->slug }}">
                    <input type="hidden" name="quantity" value="1">
                    @if ($hasVariants && $defaultVariantId)
                        <input type="hidden" name="variant_id" value="{{ $defaultVariantId }}">
                    @endif

                    <button type="submit"
                        class="btn btn-sm btn-block btn-dark text-uppercase mb-2 {{ $productStock <= 0 ? 'disabled' : '' }}"
                        style="padding: 0.4rem 0.5rem; font-size: 0.75rem;" {{ $productStock <= 0 ? 'disabled' : '' }}>
                        <i class="ti-shopping-cart mr-1" aria-hidden="true"></i>
                        {{ $productStock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                    </button>
                </form>

                <div class="d-flex justify-content-between align-items-center px-0" style="font-size: 0.7rem;">
                    <a href="{{ route('add-to-wishlist', $productData->slug) }}" class="text-decoration-none"
                        style="color: {{ $inWishlist ? '#dc3545' : '#6c757d' }};">
                        <i class="ti-heart mr-1" style="color: {{ $inWishlist ? '#dc3545' : '#6c757d' }}"
                            aria-hidden="true"></i> <span class="d-none d-md-inline">Wishlist</span>
                    </a>
                    <a href="#" class="text-decoration-none text-muted hover-text-dark"
                        onclick="event.preventDefault(); $('#productModal{{ $productData->id }}').modal('show');">
                        <i class="ti-eye mr-1" aria-hidden="true"></i> <span class="d-none d-md-inline">Quick
                            View</span>
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
            max-width: 280px;
            width: 100%;
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
            border: 1px solid #e0e0e0 !important;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
            border-color: #D97706 !important;
        }

        /* Optimize card body spacing */
        .card-body {
            padding: 0.75rem !important;
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
            padding-bottom: 0.5rem;
        }

        .current-price {
            font-size: 1.1rem !important;
            font-weight: 700 !important;
        }

        /* Button Enhancements */
        .btn-dark {
            background: #1F2937 !important;
            border-color: #1F2937 !important;
            transition: all 0.3s ease;
            font-weight: 600;
            line-height: 1.2;
        }

        .btn-dark:hover:not(.disabled) {
            background: #D97706 !important;
            border-color: #D97706 !important;
            transform: translateY(-1px);
        }

        .btn-dark.disabled {
            background: #6c757d !important;
            border-color: #6c757d !important;
            cursor: not-allowed;
            opacity: 0.7;
        }

        /* Wishlist and Quick View Links */
        .card-body .d-flex a {
            transition: all 0.3s ease;
            font-size: 0.7rem;
            white-space: nowrap;
        }

        .card-body .d-flex a:hover {
            color: #D97706 !important;
            transform: translateY(-1px);
        }

        .hover-text-dark:hover {
            color: #1F2937 !important;
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
                document.querySelectorAll('.product-card-container[data-product-id]').forEach(card => {
                    const productId = card.getAttribute('data-product-id');
                    const hasVariants = card.getAttribute('data-product-has-variants') === '1';
                    const basePrice = parseFloat(card.getAttribute('data-product-base-price') || '0');
                    const maxDiscount = parseFloat(card.getAttribute('data-product-max-discount') || '0');
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

                    const logSoldOutBadge = () => {
                        console.warn('Sold out badge applied', {
                            productId,
                            hasVariants,
                            basePrice,
                            maxDiscount,
                            totalStock,
                            effectiveDiscountPct,
                            variantsSample: variants.slice(0, 3)
                        });
                    };

                    if (hasVariants && variants.length) {
                        // For products with variants, use the backend-calculated maxDiscount
                        // and find the cheapest price among in-stock variants
                        const inStock = variants.filter(v => v.s > 0);
                        const targetList = inStock.length ? inStock : variants;

                        if (targetList.length) {
                            // Find cheapest base price (before discount)
                            const prices = targetList.map(v => v.p).filter(p => p > 0);

                            if (prices.length) {
                                const cheapestPrice = Math.min(...prices);

                                if (Number.isFinite(cheapestPrice) && cheapestPrice > 0 && totalStock > 0) {
                                    displayPrice = computeDiscounted(cheapestPrice, maxDiscount);
                                    originalPrice = maxDiscount > 0 ? cheapestPrice : null;
                                    effectiveDiscountPct = maxDiscount > 0 ? maxDiscount : 0;
                                }
                            }
                        }
                    } else {
                        // Simple product
                        if (totalStock > 0) {
                            displayPrice = computeDiscounted(basePrice, maxDiscount);
                            originalPrice = maxDiscount > 0 ? basePrice : null;
                            effectiveDiscountPct = maxDiscount > 0 ? maxDiscount : 0;
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

                    // Badge adjustments - prioritize discount over stock status
                    const badge = card.querySelector('.badge-status');
                    if (badge) {
                        // Priority: Discount → Sold Out → New
                        if (effectiveDiscountPct > 0) {
                            badge.textContent = Math.round(effectiveDiscountPct) + '% Off';
                            badge.classList.remove('badge-danger', 'badge-success');
                            badge.classList.add('badge-primary');
                        } else if (totalStock <= 0) {
                            badge.textContent = 'Sold Out';
                            badge.classList.remove('badge-primary', 'badge-success');
                            badge.classList.add('badge-danger');
                            logSoldOutBadge();
                        }
                        // Keep 'New' badge as-is if no discount and stock available
                    } else if (effectiveDiscountPct > 0) {
                        // Create a badge if none exists and discount applies
                        const imgWrapper = card.querySelector('.position-relative');
                        if (imgWrapper) {
                            const span = document.createElement('span');
                            span.className = 'badge badge-primary badge-status';
                            span.textContent = Math.round(effectiveDiscountPct) + '% Off';
                            imgWrapper.appendChild(span);
                        }
                    } else if (totalStock <= 0) {
                        // Create sold out badge if no badge exists
                        const imgWrapper = card.querySelector('.position-relative');
                        if (imgWrapper) {
                            const span = document.createElement('span');
                            span.className = 'badge badge-danger badge-status';
                            span.textContent = 'Sold Out';
                            imgWrapper.appendChild(span);
                            logSoldOutBadge();
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
