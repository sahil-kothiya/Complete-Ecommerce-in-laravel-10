<!-- Product Card -->
<div class="product-card-container mb-4 isotope-item category-{{ $product->cat_id }} px-3"
    data-product-id="{{ $product->id }}"
    data-product-brand="{{ $product->brand->slug ?? '' }}"
    data-product-price="{{ $product->discounted_price ?? $product->base_price }}"
    data-product-discount="{{ $product->max_discount ?? 0 }}"
    data-product-rating="{{ $product->rating_average ?? 0 }}">
    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
        <div class="position-relative bg-light" style="aspect-ratio: 1 / 1;">
            <div class="slider-wrapper w-100 h-100" data-slider>
                <div class="slider-track d-flex h-100">
                    @php
                        // -----------------------------------------------------------------
                        // 1. Build a collection of images that will be rendered in the slider
                        // -----------------------------------------------------------------
                        $images = collect();

                        // ---------- WITH VARIANTS ----------
                        if ($product->has_variants && $product->variants && $product->variants->count()) {
                            // Get the first active in-stock variant, or just first variant
                            $activeInStockVariants = $product->variants->where('status', 'active')->where('stock', '>', 0);
                            $firstVariant = $activeInStockVariants->first() ?? $product->variants->first();

                            if ($firstVariant) {
                                // a) Prefer the eager-loaded `images` relationship
                                if ($firstVariant->relationLoaded('images') && $firstVariant->images && $firstVariant->images->count()) {
                                    $images = $firstVariant->images;
                                }
                                // b) Fallback to the accessor `primaryImage` (single image)
                                elseif (isset($firstVariant->primaryImage) && $firstVariant->primaryImage) {
                                    $images = collect([$firstVariant->primaryImage]);
                                }
                            }
                        }

                        // ---------- WITHOUT VARIANTS ----------
                        if ($images->isEmpty()) {
                            // a) Prefer eager-loaded `images` relationship
                            if ($product->relationLoaded('images') && $product->images && $product->images->count()) {
                                $images = $product->images;
                            }
                            // b) Fallback to accessor `primaryImage`
                            elseif (isset($product->primaryImage) && $product->primaryImage) {
                                $images = collect([$product->primaryImage]);
                            }
                            // c) Check for primary_image array (set by controller)
                            elseif (isset($product->primary_image) && $product->primary_image) {
                                $images = collect([(object)[
                                    'image_path' => $product->primary_image['image_path'],
                                    'thumbnail_path' => $product->primary_image['thumbnail_path'] ?? $product->primary_image['image_path'],
                                    'alt_text' => $product->primary_image['alt_text'] ?? $product->title,
                                ]]);
                            }
                        }

                        // If still empty → show a placeholder
                        if ($images->isEmpty()) {
                            $images = collect([(object)[
                                'image_path' => 'images/no-image.png',
                                'alt_text' => $product->title,
                            ]]);
                        }
                    @endphp

                    @foreach($images as $index => $img)
                        @php
                            // Clean and normalize the image path
                            $imagePath = $img->image_path;
                            
                            // Remove 'storage/' prefix if it exists (we'll add it back)
                            $imagePath = preg_replace('#^storage/#', '', $imagePath);
                            
                            // Get path components
                            $pathInfo = pathinfo($imagePath);
                            $directory = $pathInfo['dirname'];
                            $filename = $pathInfo['filename'];
                            $extension = $pathInfo['extension'] ?? 'webp';
                            
                            // Build srcset for responsive images
                            $srcset = [];
                            
                            // Check if responsive versions exist
                            foreach ([160, 235, 320, 480] as $size) {
                                $responsivePath = "storage/{$directory}/{$filename}_{$size}x{$size}.webp";
                                if (file_exists(public_path($responsivePath))) {
                                    $srcset[] = asset($responsivePath) . " {$size}w";
                                }
                            }
                            
                            // Add original image as fallback
                            $originalPath = "storage/{$imagePath}";
                            $srcset[] = asset($originalPath) . " 370w";
                            
                            $srcsetString = implode(', ', $srcset);
                            
                            // Default image src
                            $imgSrc = asset($originalPath);
                            
                            // Use thumbnail if available
                            if (isset($img->thumbnail_path) && $img->thumbnail_path) {
                                $thumbnailPath = preg_replace('#^storage/#', '', $img->thumbnail_path);
                                $imgSrc = asset("storage/{$thumbnailPath}");
                            }
                        @endphp

                        <img
                            src="{{ $imgSrc }}"
                            srcset="{{ $srcsetString }}"
                            sizes="(max-width: 576px) 280px, (max-width: 768px) 235px, (max-width: 992px) 200px, (max-width: 1200px) 180px, 160px"
                            class="slider-image"
                            alt="{{ $img->alt_text ?? $product->title }}"
                            loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                            width="235"
                            height="235"
                            decoding="async"
                            fetchpriority="{{ $index === 0 ? 'high' : 'low' }}"
                            onerror="this.src='{{ asset('images/no-image.png') }}';">
                    @endforeach
                </div>
            </div>

            @if(isset($product->max_discount) && $product->max_discount > 0)
                <span class="badge badge-primary badge-status">{{ $product->max_discount }}% Off</span>
            @elseif($product->condition === 'new')
                <span class="badge badge-success badge-status">New</span>
            @elseif(($product->stock ?? 0) <= 0)
                <span class="badge badge-danger badge-status">Sold Out</span>
            @endif
        </div>

        <div class="card-body d-flex flex-column px-3 py-2">
            <h6 class="text-dark text-truncate mb-1">
                <a href="{{ route('product-detail', $product->slug) }}" class="text-dark">
                    {{ Str::limit($product->title, 50) }}
                </a>
            </h6>

            <!-- Brand Display -->
            @if(isset($product->brand))
                <small class="text-muted mb-1">
                    <i class="fa fa-tag"></i> {{ $product->brand->title }}
                </small>
            @endif

            <!-- Rating Display -->
            @if(isset($product->rating_average) && $product->rating_average > 0)
                <div class="mb-1">
                    <small class="text-warning">
                        @for($i = 1; $i <= 5; $i++)
                            <i class="fa fa-star{{ $i <= $product->rating_average ? '' : '-o' }}"></i>
                        @endfor
                        <span class="text-muted">({{ $product->rating_count ?? 0 }})</span>
                    </small>
                </div>
            @endif

            <div class="mb-2 price-container">
                @if(isset($product->discounted_price) && $product->discounted_price)
                    <span class="text-primary font-weight-bold current-price">
                        ${{ number_format($product->discounted_price, 2) }}
                    </span>
                    
                    @if(isset($product->max_discount) && $product->max_discount > 0 && isset($product->original_price))
                        <small class="text-muted ml-2 original-price">
                            <del>${{ number_format($product->original_price, 2) }}</del>
                        </small>
                    @endif
                @else
                    <span class="font-weight-bold text-muted current-price">Out of Stock</span>
                @endif
            </div>

            @php
                $productStock = $product->stock ?? 0;
                $inWishlist = class_exists('Helper') ? Helper::isProductInWishlist($product->slug) : false;
            @endphp

            <div class="mt-auto">
                <a href="{{ route('add-to-cart', $product->slug) }}"
                    class="btn btn-sm btn-block btn-dark text-uppercase mb-3 text-center {{ $productStock <= 0 ? 'disabled' : '' }}">
                    <i class="ti-shopping-cart mr-1"></i>
                    {{ $productStock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </a>

                <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                    <a href="{{ route('add-to-wishlist', $product->slug) }}"
                        class="text-decoration-none">
                        <i class="ti-heart mr-1" style="color: {{ $inWishlist ? 'red' : '#6c757d' }}"></i> Wishlist
                    </a>
                    <a href="#"
                        class="text-decoration-none text-muted hover-text-dark"
                        onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');">
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
        display: flex;
        width: 100%;
        height: 100%;
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