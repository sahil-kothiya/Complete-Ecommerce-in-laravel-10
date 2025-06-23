{{-- resources/views/frontend/partials/product-modals.blade.php --}}

@if($products && $products->count() > 0)
@foreach($products as $product)
<div class="modal fade" id="{{ $product->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span class="ti-close" aria-hidden="true"></span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row no-gutters">
                    <!-- Product Images -->
                    <div class="col-lg-6 col-md-12">
                        <div class="product-gallery">
                            <div class="quickview-slider-active">
                                @forelse($product->images as $image)
                                <div class="single-slider">
                                    <img src="{{ asset($image->image_path) }}" alt="{{$product->title}}">
                                </div>
                                @empty
                                <div class="single-slider">
                                    <img src="{{ asset('images/no-image.png') }}" alt="No image available">
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Product Details -->
                    <div class="col-lg-6 col-md-12">
                        <div class="quickview-content">
                            <h2>{{ $product->title }}</h2>

                            <!-- Ratings -->
                            <div class="quickview-ratting-review">
                                <div class="quickview-ratting-wrap">
                                    <div class="quickview-ratting">
                                        @php
                                        $rate = DB::table('product_reviews')->where('product_id', $product->id)->avg('rate');
                                        $rateCount = DB::table('product_reviews')->where('product_id', $product->id)->count();
                                        @endphp
                                        @for($i = 1; $i <= 5; $i++)
                                            <i class="fa fa-star {{$rate >= $i ? 'yellow' : ''}}"></i>
                                            @endfor
                                    </div>
                                    <a href="#">({{ $rateCount }} customer review{{ $rateCount == 1 ? '' : 's' }})</a>
                                </div>
                                <div class="quickview-stock">
                                    @if($product->stock > 0)
                                    <span><i class="fa fa-check-circle-o"></i> {{ $product->stock }} in stock</span>
                                    @else
                                    <span><i class="fa fa-times-circle-o text-danger"></i> Out of stock</span>
                                    @endif
                                </div>
                            </div>

                            <!-- Price -->
                            @php $discountedPrice = $product->price - ($product->price * $product->discount / 100); @endphp
                            <h3>
                                @if($product->discount > 0)
                                <small><del class="text-muted">${{ number_format($product->price, 2) }}</del></small>
                                @endif
                                ${{ number_format($discountedPrice, 2) }}
                            </h3>

                            <!-- Summary -->
                            <div class="quickview-peragraph">
                                <p>{!! html_entity_decode($product->summary) !!}</p>
                            </div>

                            <!-- Size Options -->
                            @if($product->size)
                            <div class="size">
                                <div class="row">
                                    <div class="col-lg-6 col-12">
                                        <h5 class="title">Size</h5>
                                        <select>
                                            @foreach(explode(',', $product->size) as $size)
                                            <option>{{ trim($size) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            @endif

                            <!-- Add to Cart Form -->
                            <form action="{{ route('single-add-to-cart') }}" method="POST" class="mt-4">
                                @csrf
                                <div class="quantity">
                                    <div class="input-group">
                                        <div class="button minus">
                                            <button type="button" class="btn btn-primary btn-number" disabled data-type="minus" data-field="quant[1]">
                                                <i class="ti-minus"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="slug" value="{{ $product->slug }}">
                                        <input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1">
                                        <div class="button plus">
                                            <button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]">
                                                <i class="ti-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="add-to-cart">
                                    <button type="submit" class="btn">Add to cart</button>
                                    <a href="{{ route('add-to-wishlist', $product->slug) }}" class="btn min"><i class="ti-heart"></i></a>
                                </div>
                            </form>

                            <!-- Social Sharing -->
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
@endforeach
@endif