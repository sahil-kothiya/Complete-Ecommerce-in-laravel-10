<div class="modal fade" id="productModal{{ $product->id }}" tabindex="-1" role="dialog" aria-labelledby="productModalLabel{{ $product->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">

            <div class="modal-header border-0">
                <h5 class="modal-title font-weight-bold" id="productModalLabel{{ $product->id }}">{{ $product->title }}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" role="img">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div class="row no-gutters align-items-start">

                    <!-- Left Side: Product Images -->
                    <div class="col-md-6 pr-md-3 mb-3 mb-md-0">
                        <div class="quickview-carousel border rounded p-2">
                            @forelse($product->images as $image)
                            <img src="{{ asset($image->image_path) }}" class="img-fluid mb-2 rounded" alt="{{ $product->title }}" loading="lazy">
                            @empty
                            <img src="{{ secure_asset('images/no-image.jpg') }}" class="img-fluid mb-2 rounded" alt="No image available" loading="lazy">
                            @endforelse
                        </div>
                    </div>

                    <!-- Right Side: Product Details -->
                    <div class="col-md-6 pl-md-3 quickview-content">

                        <!-- Title -->
                        <h5 class="font-weight-bold mb-2">{{ $product->title }}</h5>

                        <!-- Rating -->
                        @php
                        $rate = DB::table('product_reviews')->where('product_id', $product->id)->avg('rate');
                        $rateCount = DB::table('product_reviews')->where('product_id', $product->id)->count();
                        @endphp
                        <div class="mb-2">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fa fa-star {{ $rate >= $i ? 'text-warning' : 'text-muted' }}"></i>
                                @endfor
                                <small class="ml-1">({{ $rateCount }} review{{ $rateCount > 1 ? 's' : '' }})</small>
                        </div>

                        <!-- Stock -->
                        <p class="{{ $product->stock > 0 ? 'text-success' : 'text-danger' }} small font-weight-bold">
                            <i class="fa fa-{{ $product->stock > 0 ? 'check' : 'times' }}"></i>
                            {{ $product->stock > 0 ? $product->stock . ' in stock' : 'Out of stock' }}
                        </p>

                        <!-- Price -->
                        <p class="h5 mb-3">
                            @if($product->discount > 0)
                            <span class="text-danger font-weight-bold">${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
                            <del class="text-muted ml-2">${{ number_format($product->price, 2) }}</del>
                            @else
                            <span class="font-weight-bold">${{ number_format($product->price, 2) }}</span>
                            @endif
                        </p>

                        <!-- Summary -->
                        @if($product->summary)
                        <p class="text-muted small">{!! $product->summary !!}</p>
                        @endif

                        <!-- Size -->
                        @if($product->size)
                        <div class="form-group">
                            <label for="size-select-{{ $product->id }}">Size</label>
                            <select class="form-control" id="size-select-{{ $product->id }}">
                                @foreach(explode(',', $product->size) as $size)
                                <option>{{ trim($size) }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <!-- Cart Form -->
                        @if($product->stock > 0)
                        <form action="{{ route('single-add-to-cart') }}" method="POST">
                            @csrf
                            <input type="hidden" name="slug" value="{{ $product->slug }}">

                            <div class="form-group d-flex align-items-center">
                                <label class="mr-2 mb-0">Qty:</label>
                                <input type="number" name="quant[{{ $product->id }}]" class="form-control w-25" value="1" min="1" max="{{ $product->stock }}" aria-label="Quantity">
                            </div>

                            <div class="form-group d-flex">
                                <button type="submit" class="btn btn-primary">Add to Cart</button>
                                <a href="{{ route('add-to-wishlist', $product->slug) }}" class="btn btn-outline-secondary ml-2" aria-label="Add to wishlist"><i class="fa fa-heart"></i></a>
                            </div>
                        </form>
                        @else
                        <button class="btn btn-secondary" disabled>Out of Stock</button>
                        @endif

                        <!-- Social -->
                        <div class="mt-3">
                            <div class="sharethis-inline-share-buttons" aria-label="Share this product"></div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

@push('styles')
<style>
    .modal-body .row>div {
        padding: 10px 15px;
    }

    .quickview-carousel {
        max-height: 450px;
        overflow-y: auto;
    }

    .quickview-content h5,
    .quickview-content p,
    .quickview-content .form-group {
        margin-bottom: 12px;
    }

    .btn {
        min-width: 120px;
        padding: 8px 20px;
        font-weight: 500;
    }

    .modal-header .close {
        font-size: 1.4rem;
        padding: 0.5rem 1rem;
    }

    .quickview-carousel img {
        border: 1px solid #eee;
        background: #fff;
        padding: 5px;
    }
</style>
@endpush