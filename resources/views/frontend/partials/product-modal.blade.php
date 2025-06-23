<div class="modal fade" id="productModal{{ $product->id }}" tabindex="-1" aria-labelledby="productModalLabel{{ $product->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="productModalLabel{{ $product->id }}">{{ $product->title }}</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="product-gallery">
                            <div class="quickview-carousel">
                                @forelse($product->images as $image)
                                <div class="single-image">
                                    <img src="{{ asset($image->image_path) }}" alt="{{ $product->title }}" loading="lazy">
                                </div>
                                @empty
                                <div class="single-image">
                                    <img src="{{ asset('images/no-image.jpg') }}" alt="No image available" loading="lazy">
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="quickview-content">
                            <h2>{{ $product->title }}</h2>
                            <div class="quickview-ratting-review">
                                @php
                                $rate = DB::table('product_reviews')->where('product_id', $product->id)->avg('rate');
                                $rateCount = DB::table('product_reviews')->where('product_id', $product->id)->count();
                                @endphp
                                <div class="rating">
                                    @for($i = 1; $i <= @5; $i++)
                                        <i class="fa fa-star {{ $rate >= $i ? 'yellow' : '' }}"></i>
                                        @endfor
                                        <a href="#">({{ $rateCount }} customer review{{ $rateCount == 1 ? '' : 's' }})</a>
                                </div>
                                <div class="quickview-stock">
                                    <span class="{{ $product->stock > 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="fa fa {{ $product->stock > 0 ? 'fa-check-circle-o' : 'fa-times-circle-o' }}"></i>

                                        {{ $product->stock > 0 ? "{$product->stock} in stock" : 'Out of stock' }}
                                    </span>
                                </div>

                                <h3 class="price">
                                    @if($product->discount > 0)
                                    <span>${{ number_format($product->price - ($product->price * $product->discount / 100), 2) }}</span>
                                    <del class="text-muted">${{ number_format($product->price, 2) }}</del>
                                    @else
                                    <span>${{ number_format($product->price, 2) }}</span>
                                    @endif
                                </h3>

                                @if($product->summary)
                                <div class="quickview-peragraph">
                                    <p>{!! $product->summary !!}</p>
                                </div>
                                @endif

                                @if($product->size)
                                <div class="size">
                                    <label for="size-select{{ $product->id }}">Size:</label>
                                    <select id="size-select{{ $product->id }}" class="form-control">
                                        @foreach(explode(',', $product->size) as $size)
                                        <option value="{{ trim($size) }}">{{ trim($size) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif

                                @if($product->stock > 0)
                                <form action="{{ route('single-add-to-cart') }}" method="POST" class="mt-3">
                                    @csrf
                                    <div class="quantity">
                                        <div class="input-group">
                                            <button type="button" class="btn btn-primary btn-number" data-type="minus" data-field="quant[${product->id}]"><i class="fa fa-minus"></i></i>
                                                <input type="hidden" name="slug" value="{{ $product->slug }}">
                                                <input type="number" name="quant[${product->id}]" class="form-control input-number" min="1" max="{{ $product->stock }}" value="1">
                                                <button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[${product->id}]"><i class="fa fa-plus"></i></i>
                                        </div>
                                    </div>
                                    <div class="add-to-cart mt-2">
                                        <button type="submit" class="btn">Add to Cart</button>
                                        <a href="{{ route('add-to-wishlist', $product->slug) }}" class="btn btn-outline-secondary"><i class="fa fa-heart"></i></a>
                                    </div>
                                </form>
                                @else
                                <div class="add-to-cart mt-2">
                                    <button class="btn btn-secondary" disabled>Out of Stock</button>
                                </div>
                                @endif

                                <div class="default-social mt-3">
                                    <div class="sharethis-inline-share-buttons"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>