<div class="product-card-container mb-4 isotope-item category-{{ $product->cat_id }} px-2">
    <div class="card h-100 border-0 d-flex flex-column product-card shadow-sm rounded">
        <div class="position-relative bg-light" style="aspect-ratio: 1 / 1;">
            <div class="slider-wrapper w-100 h-100" data-slider>
                <div class="slider-track d-flex h-100">
                    @foreach($product->images as $img)
                    @php
                    $pathInfo = pathinfo($img->image_path);
                    $directory = $pathInfo['dirname'];
                    $filename = $pathInfo['filename'];
                    $extension = $pathInfo['extension'];

                    // Generate srcset for responsive images
                    $srcset = [];
                    $sizes = [160, 235, 320, 480];
                    foreach ($sizes as $size) {
                    $responsivePath = "{$directory}/{$filename}_{$size}x{$size}.webp";
                    if (file_exists(public_path($responsivePath))) {
                    $srcset[] = asset($responsivePath) . " {$size}w";
                    }
                    }

                    // Add original as fallback
                    $srcset[] = asset($img->image_path) . " 370w";
                    $srcsetString = implode(', ', $srcset);
                    @endphp
                    <img
                        src="{{ asset($img->image_path) }}"
                        srcset="{{ $srcsetString }}"
                        sizes="(max-width: 576px) 280px, (max-width: 768px) 235px, (max-width: 992px) 200px, (max-width: 1200px) 180px, 160px"
                        class="slider-image"
                        alt="{{ $product->title }}"
                        loading="lazy"
                        width="235"
                        height="235"
                        decoding="async"
                        fetchpriority="low"
                        onerror="this.src='{{ asset('images/no-image.png') }}';">
                    @endforeach
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
                <a href="{{ route('product-detail', $product->slug) }}" class="text-dark">
                    {{ Str::limit($product->title, 50) }}
                </a>
            </h6>

            <div class="mb-2">
                @if($product->discount > 0)
                <span class="text-primary font-weight-bold">
                    ${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}
                </span>
                <small class="text-muted ml-2"><del>${{ number_format($product->price, 2) }}</del></small>
                @else
                <span class="font-weight-bold text-dark">${{ number_format($product->price, 2) }}</span>
                @endif
            </div>

            <div class="mt-auto">
                <a href="{{ route('add-to-cart', $product->slug) }}"
                    class="btn btn-sm btn-block btn-dark text-uppercase mb-2 {{ $product->stock <= 0 ? 'disabled' : '' }}">
                    {{ $product->stock <= 0 ? 'Out of Stock' : 'Add to Cart' }}
                </a>
                <div class="d-flex justify-content-between small text-muted">
                    <a href="{{ route('add-to-wishlist', $product->slug) }}"><i class="ti-heart"></i> Wishlist</a>
                    <!-- Fixed: Added href attribute to make it crawlable -->
                    <!-- <a href="{{ route('product-detail', $product->slug) }}"
                        data-toggle="modal"
                        data-target="#productModal{{ $product->id }}"
                        onclick="event.preventDefault();">
                        <i class="ti-eye"></i> Quick View
                    </a> -->
                    <a href="#" onclick="event.preventDefault(); $('#productModal{{ $product->id }}').modal('show');">
                        <i class="ti-eye"></i> Quick View
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .product-listing-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        /* Adjust space as needed */
    }

    .slider-wrapper {
        overflow: hidden;
        height: 100%;
        position: relative;
    }

    .slider-track {
        display: flex;
        width: 100%;
        height: 100%;
        transition: transform 0.5s ease-in-out;
    }

    .slider-image {
        width: 100%;
        height: 100%;
        object-fit: cover;
        flex-shrink: 0;
        /* Optimize image rendering and loading */
        image-rendering: auto;
        transform: translateZ(0);
        will-change: transform;
        backface-visibility: hidden;
    }

    .badge-status {
        position: absolute;
        top: 8px;
        left: 8px;
        font-size: 0.7rem;
        padding: 0.3rem 0.6rem;
        z-index: 10;
    }

    /* For older browser fallback: aspect-ratio polyfill */
    [style*="aspect-ratio"] {
        position: relative;
    }

    [style*="aspect-ratio"]::before {
        content: "";
        display: block;
        padding-bottom: calc(100% / (1 / 1));
        /* 1:1 ratio */
    }

    [style*="aspect-ratio"]>*:first-child {
        position: absolute;
        top: 0;
        left: 0;
        bottom: 0;
        right: 0;
    }

    @media (min-width: 1200px) {
        .col-lg-5th {
            flex: 0 0 20%;
            max-width: 20%;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("[data-slider]").forEach(wrapper => {
            const track = wrapper.querySelector('.slider-track');
            const images = wrapper.querySelectorAll('.slider-image');
            const total = images.length;

            if (total <= 1) return;

            let index = 0;
            let interval;

            const slide = () => {
                track.style.transform = `translateX(-${index * 100}%)`;
            };

            wrapper.addEventListener("mouseenter", () => {
                index = 0;
                interval = setInterval(() => {
                    index = (index + 1) % total;
                    slide();
                }, 1000);
            });

            wrapper.addEventListener("mouseleave", () => {
                clearInterval(interval);
                index = 0;
                slide(); // back to first image
            });
        });

        $('#productModal{{ $product->id }}').on('hidden.bs.modal', function() {
            this.setAttribute('inert', '');
        });

        $('#productModal{{ $product->id }}').on('show.bs.modal', function() {
            this.removeAttribute('inert');
        });
    });
</script>
@endpush