{{-- Quick View Modal --}}
@php
    // Normalize product data (handles both fresh models and cached plain objects/arrays)
    $productData = is_array($product) ? (object) $product : $product;
    $images = collect($productData->images ?? []);
    $productId = $productData->id ?? 0;
    $productTitle = $productData->title ?? 'Product';
    $productSlug = $productData->slug ?? '';
    $productStock = $productData->stock ?? 0;
    $productSummary = $productData->summary ?? '';
    $productSize = $productData->size ?? '';

    // Handle price and discount for both base and variant products
    $basePrice = $productData->base_price ?? ($productData->price ?? 0);
    $baseDiscount = $productData->base_discount ?? ($productData->discount ?? 0);
@endphp
<div class="modal fade" id="productModal{{ $productId }}" tabindex="-1" role="dialog"
    aria-labelledby="productModalLabel{{ $productId }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title font-weight-bold" id="productModalLabel{{ $productId }}">
                    {{ $productTitle }}
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span>&times;</span>
                </button>
            </div>

            <div class="modal-body pt-2">
                <div class="row">
                    {{-- Left Side: Product Images --}}
                    <div class="col-md-6">
                        <div class="quickview-carousel">
                            @if ($images->count() > 0)
                                @php
                                    $firstImage = $images->first();
                                    $firstImageObj = is_array($firstImage) ? (object) $firstImage : $firstImage;
                                    $firstImageUrl = $firstImageObj->image_path ?? asset('images/no-image.jpg');
                                @endphp
                                <div class="main-image mb-3">
                                    <img id="mainImage{{ $productId }}" src="{{ $firstImageUrl }}"
                                        class="img-fluid w-100 rounded main-product-image" alt="{{ $productTitle }}"
                                        loading="lazy">
                                </div>
                                @if ($images->count() > 1)
                                    <div class="thumbnail-images">
                                        @foreach ($images as $index => $image)
                                            @php
                                                $imgObj = is_array($image) ? (object) $image : $image;
                                                $imgUrl = $imgObj->image_path ?? asset('images/no-image.jpg');
                                            @endphp
                                            <img src="{{ $imgUrl }}"
                                                class="img-thumbnail thumbnail-image {{ $index === 0 ? 'active' : '' }}"
                                                alt="{{ $productTitle }}" data-main-image="{{ $imgUrl }}"
                                                data-product-id="{{ $productId }}" loading="lazy">
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <div class="main-image">
                                    <img src="{{ asset('images/no-image.jpg') }}" class="img-fluid w-100 rounded"
                                        alt="No image available" loading="lazy">
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Right Side: Product Details --}}
                    <div class="col-md-6">
                        <div class="quickview-content">
                            {{-- Product Title --}}
                            <h5 class="product-title mb-3">{{ $productTitle }}</h5>

                            {{-- Rating --}}
                            @php
                                $rate = DB::table('product_reviews')->where('product_id', $productId)->avg('rate') ?? 0;
                                $rateCount = DB::table('product_reviews')->where('product_id', $productId)->count();
                            @endphp
                            <div class="product-rating mb-3">
                                <div class="stars">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="fa fa-star {{ $rate >= $i ? 'text-warning' : 'text-muted' }}"></i>
                                    @endfor
                                </div>
                                <small class="text-muted ml-2">({{ $rateCount }}
                                    {{ $rateCount == 1 ? 'review' : 'reviews' }})</small>
                            </div>

                            {{-- Price --}}
                            <div class="price-section mb-3">
                                @if ($baseDiscount > 0)
                                    <span class="current-price h4 text-danger font-weight-bold">
                                        ${{ number_format($basePrice - ($basePrice * $baseDiscount) / 100, 2) }}
                                    </span>
                                    <del
                                        class="original-price text-muted ml-2">${{ number_format($basePrice, 2) }}</del>
                                    <span class="discount-badge badge badge-primary ml-2">{{ $baseDiscount }}%
                                        OFF</span>
                                @else
                                    <span
                                        class="current-price h4 font-weight-bold text-dark">${{ number_format($basePrice, 2) }}</span>
                                @endif
                            </div>

                            {{-- Stock Status --}}
                            <div class="stock-status mb-3">
                                @if ($productStock > 0)
                                    <span class="badge badge-success">
                                        <i class="fa fa-check"></i> {{ $productStock }} in stock
                                    </span>
                                @else
                                    <span class="badge badge-danger">
                                        <i class="fa fa-times"></i> Out of stock
                                    </span>
                                @endif
                            </div>

                            {{-- Product Summary --}}
                            @if ($productSummary)
                                <div class="product-summary mb-3">
                                    <p class="text-muted">{!! Str::limit(strip_tags($productSummary), 150) !!}</p>
                                </div>
                            @endif

                            {{-- Size Selection --}}
                            @if ($productSize)
                                <div class="form-group mb-3">
                                    <label for="size-select-{{ $productId }}" class="font-weight-bold">Size:</label>
                                    <select class="form-control" id="size-select-{{ $productId }}" name="size">
                                        @foreach (explode(',', $productSize) as $size)
                                            <option value="{{ trim($size) }}">{{ trim($size) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            {{-- Add to Cart Form --}}
                            @if ($productStock > 0)
                                <form action="{{ route('single-add-to-cart') }}" method="POST" class="quickview-form">
                                    @csrf
                                    <input type="hidden" name="slug" value="{{ $productSlug }}">

                                    {{-- Quantity Controls --}}
                                    <div class="form-group mb-3">
                                        <label class="font-weight-bold mb-2">Quantity:</label>
                                        <div class="quantity-controls d-flex align-items-center">
                                            <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn"
                                                data-action="decrease" data-product-id="{{ $productId }}">
                                                <i class="fa fa-minus"></i>
                                            </button>
                                            <input type="number" id="quantity-{{ $productId }}"
                                                name="quant[{{ $productId }}]"
                                                class="form-control quantity-input mx-2 text-center" value="1"
                                                min="1" max="{{ $productStock }}" readonly>
                                            <button type="button" class="btn btn-outline-secondary btn-sm quantity-btn"
                                                data-action="increase" data-product-id="{{ $productId }}">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {{-- Action Buttons --}}
                                    <div class="action-buttons">
                                        <div class="row">
                                            <div class="col-9">
                                                <button type="submit" class="btn btn-primary btn-block btn-lg">
                                                    <i class="fa fa-shopping-cart mr-2"></i>Add to Cart
                                                </button>
                                            </div>
                                            <div class="col-3">
                                                <a href="{{ route('add-to-wishlist', $productSlug) }}"
                                                    class="btn btn-outline-secondary btn-lg btn-block"
                                                    title="Add to Wishlist">
                                                    <i class="fa fa-heart"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <div class="out-of-stock-message">
                                    <button class="btn btn-secondary btn-lg btn-block" disabled>
                                        <i class="fa fa-times mr-2"></i>Out of Stock
                                    </button>
                                </div>
                            @endif

                            {{-- Product Actions --}}
                            <div class="product-actions mt-4 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="{{ route('product-detail', $productSlug) }}"
                                        class="btn btn-link p-0 text-decoration-none">
                                        <i class="fa fa-eye mr-1"></i>View Full Details
                                    </a>
                                    <div class="social-share">
                                        <small class="text-muted">Share:</small>
                                        <a href="#" class="text-muted ml-2" title="Share on Facebook">
                                            <i class="fa fa-facebook"></i>
                                        </a>
                                        <a href="#" class="text-muted ml-2" title="Share on Twitter">
                                            <i class="fa fa-twitter"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
    <style>
        /* Quick View Modal Styles */
        .modal-content {
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
        }

        .modal-header .close {
            color: white;
            font-size: 1.5rem;
            opacity: 0.8;
            text-shadow: none;
        }

        .modal-header .close:hover {
            opacity: 1;
        }

        .modal-title {
            font-size: 1.3rem;
            margin: 0;
        }

        /* Image Gallery */
        .quickview-carousel {
            position: relative;
        }

        .main-image {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
        }

        .main-product-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .main-product-image:hover {
            transform: scale(1.05);
        }

        .thumbnail-images {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .thumbnail-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.3s ease;
        }

        .thumbnail-image:hover,
        .thumbnail-image.active {
            border-color: #667eea;
        }

        /* Product Details */
        .quickview-content {
            padding: 0 15px;
        }

        .product-title {
            color: #333;
            font-size: 1.4rem;
            line-height: 1.3;
        }

        .product-rating .stars {
            display: inline-block;
        }

        .price-section {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .current-price {
            color: #e74c3c;
        }

        .original-price {
            font-size: 1rem;
        }

        .discount-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.6rem;
        }

        .stock-status .badge {
            font-size: 0.9rem;
            padding: 0.5rem 0.8rem;
        }

        .product-summary p {
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* Quantity Controls */
        .quantity-controls {
            max-width: 140px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .quantity-btn {
            border: none;
            background: #f8f9fa;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s ease;
        }

        .quantity-btn:hover {
            background: #e9ecef;
        }

        .quantity-input {
            border: none;
            background: white;
            width: 60px;
            height: 40px;
            text-align: center;
            font-weight: bold;
        }

        .quantity-input:focus {
            box-shadow: none;
            outline: none;
        }

        /* Action Buttons */
        .action-buttons .btn {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            transition: transform 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Product Actions */
        .product-actions {
            font-size: 0.9rem;
        }

        .social-share a {
            transition: color 0.3s ease;
        }

        .social-share a:hover {
            color: #667eea !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-content {
                border-radius: 5px;
            }

            .quickview-content {
                padding: 0 10px;
                margin-top: 20px;
            }

            .main-product-image {
                height: 250px;
            }

            .product-title {
                font-size: 1.2rem;
            }

            .thumbnail-image {
                width: 50px;
                height: 50px;
            }
        }

        /* Loading Animation */
        .modal.fade .modal-dialog {
            transform: translate(0, -50px);
            transition: transform 0.3s ease-out;
        }

        .modal.show .modal-dialog {
            transform: translate(0, 0);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Quick View Modal functionality
            initQuickViewModal();
        });

        function initQuickViewModal() {
            // Handle thumbnail image clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('thumbnail-image')) {
                    const productId = e.target.dataset.productId;
                    const mainImageSrc = e.target.dataset.mainImage;
                    const mainImage = document.getElementById('mainImage' + productId);

                    if (mainImage) {
                        mainImage.src = mainImageSrc;

                        // Update active thumbnail
                        const thumbnails = document.querySelectorAll(
                            `[data-product-id="${productId}"].thumbnail-image`);
                        thumbnails.forEach(thumb => thumb.classList.remove('active'));
                        e.target.classList.add('active');
                    }
                }
            });

            // Handle quantity controls
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('quantity-btn') || e.target.parentElement.classList.contains(
                        'quantity-btn')) {
                    const btn = e.target.classList.contains('quantity-btn') ? e.target : e.target.parentElement;
                    const action = btn.dataset.action;
                    const productId = btn.dataset.productId;
                    const quantityInput = document.getElementById('quantity-' + productId);

                    if (quantityInput) {
                        let currentValue = parseInt(quantityInput.value);
                        const min = parseInt(quantityInput.min);
                        const max = parseInt(quantityInput.max);

                        if (action === 'increase' && currentValue < max) {
                            quantityInput.value = currentValue + 1;
                        } else if (action === 'decrease' && currentValue > min) {
                            quantityInput.value = currentValue - 1;
                        }
                    }
                }
            });

            // // Handle modal events
            // $(document).on('show.bs.modal', '[id^="productModal"]', function() {
            //     // Add any initialization code when modal opens
            //     console.log('Quick View modal opened');
            // });

            // $(document).on('hidden.bs.modal', '[id^="productModal"]', function() {
            //     // Reset modal state when closed
            //     const productId = this.id.replace('productModal', '');
            //     const quantityInput = document.getElementById('quantity-' + productId);
            //     if (quantityInput) {
            //         quantityInput.value = 1;
            //     }
            // });

            // Handle form submission with loading state
            document.addEventListener('submit', function(e) {
                if (e.target.classList.contains('quickview-form')) {
                    const submitBtn = e.target.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin mr-2"></i>Adding...';

                        // Re-enable button after 3 seconds (adjust based on your needs)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fa fa-shopping-cart mr-2"></i>Add to Cart';
                        }, 3000);
                    }
                }
            });
        }
    </script>
@endpush
