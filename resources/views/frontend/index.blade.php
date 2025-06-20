@extends('frontend.layouts.master')
@section('title','E-SHOP || HOME PAGE')
@section('main-content')
<!-- Slider Area -->
@if(count($banners)>0)
<section id="Gslider" class="carousel slide" data-ride="carousel">
    <ol class="carousel-indicators">
        @foreach($banners as $key=>$banner)
        <li data-target="#Gslider" data-slide-to="{{$key}}" class="{{(($key==0)? 'active' : '')}}"></li>
        @endforeach

    </ol>
    <div class="carousel-inner" role="group" aria-label="Homepage banner carousel">
        @foreach($banners as $key=>$banner)
        <div class="carousel-item {{(($key==0)? 'active' : '')}}" role="group" aria-roledescription="slide" aria-label="Slide {{ $key + 1 }} of {{ count($banners) }}">
            @php
            list($width, $height) = getimagesize(public_path($banner->photo));
            @endphp
            <img rel="preload" as="image" class="first-slide" src="{{ asset($banner->photo) }}" fetchpriority="high" alt="{{ $banner->title }}" loading="lazy" width="{{ $width }}" height="{{ $height }}">

            <div class="carousel-caption d-none d-md-block text-left">
                <h1 class="wow fadeInDown">{{$banner->title}}</h1>
                <p>{!! html_entity_decode($banner->description) !!}</p>
                <a class="btn btn-lg ws-btn wow fadeInUpBig" href="{{route('product-grids')}}" role="button">Shop Now<i class="far fa-arrow-alt-circle-right"></i></i></a>
            </div>
        </div>
        @endforeach
    </div>
    <a class="carousel-control-prev" href="#Gslider" role="button" data-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#Gslider" role="button" data-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="sr-only">Next</span>
    </a>
</section>
@endif

<!--/ End Slider Area -->

<!-- Start Small Banner  -->
<section class="small-banner section">
    <div class="container-fluid">
        <div class="row">

            @foreach($categoryBanners as $cat)
            <!-- Single Banner -->
            <div class="col-lg-4 col-md-6 col-12">
                <div class="single-banner">
                    <img src="{{ asset($cat->photo) }}" alt="{{ $cat->title }}" loading="lazy">
                    <div class="content">
                        <h3>{{ $cat->title }}</h3>
                        <a href="{{ route('product-cat', $cat->slug) }}">Discover Now</a>
                    </div>
                </div>
            </div>
            @endforeach

        </div>
    </div>
</section>
<!-- End Small Banner -->
<!-- Start Product Area -->
<div class="product-area section">
    <div class="container">
        <div class="row mb-4">
            <div class="col-12">
                <div class="section-title text-center">
                    <h2>Trending Items</h2>
                </div>
            </div>
        </div>

        <!-- Category Filter Buttons -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <ul class="nav nav-tabs filter-tope-group" id="myTab" role="tablist">
                    <button class="btn how-active1" style="background-color: #343a40; color: #ffffff;" data-filter="*" role="tab">All Products</button>
                    @foreach($categories as $cat)
                    <button class="btn" style="background:none;color:black;" data-filter=".{{ $cat->id }}" role="tab">
                        {{ $cat->title }}
                    </button>
                    @endforeach
                </ul>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row tab-content isotope-grid" id="myTabContent">
            @forelse($product_lists as $product)
            @php
            $defaultImage = $product->images->first();
            $imageUrl = $defaultImage ? asset($defaultImage->image_path) : asset('images/no-image.png');
            $afterDiscount = $product->price - ($product->price * $product->discount / 100);
            @endphp

            <div class="col-sm-6 col-md-4 col-lg-3 p-b-35 isotope-item {{ $product->cat_id }}">
                <div class="single-product">
                    <div class="product-img">
                        <a href="{{ route('product-detail', $product->slug) }}">
                            <img class="default-img" src="{{ $imageUrl }}" alt="{{ $product->title }}" loading="lazy">
                            <img class="hover-img" src="{{ $imageUrl }}" alt="{{ $product->title }}" loading="lazy">

                            @switch(true)
                            @case($product->stock <= 0)
                                <span class="out-of-stock">Sold Out</span>
                                @break
                                @case($product->condition == 'new')
                                <span class="new" aria-label="This product is new">New</span>
                                @break
                                @case($product->condition == 'hot')
                                <span class="hot">Hot</span>
                                @break
                                @default
                                <span class="price-dec">{{ $product->discount }}% Off</span>
                                @endswitch
                        </a>

                        <div class="button-head">
                            <div class="product-action">
                                <a data-toggle="modal" data-target="#{{ $product->id }}" title="Quick View" href="#">
                                    <i class="ti-eye"></i><span>Quick Shop</span>
                                </a>
                                <a title="Wishlist" href="{{ route('add-to-wishlist', $product->slug) }}">
                                    <i class="ti-heart"></i><span>Add to Wishlist</span>
                                </a>
                            </div>
                            <div class="product-action-2">
                                <a title="Add to cart" href="{{ route('add-to-cart', $product->slug) }}">Add to Cart</a>
                            </div>
                        </div>
                    </div>

                    <div class="product-content">
                        <h3>
                            <a href="{{ route('product-detail', $product->slug) }}">
                                {{ \Illuminate\Support\Str::limit($product->title, 50) }}
                            </a>
                        </h3>
                        <div class="product-price">
                            <span>${{ number_format($afterDiscount, 2) }}</span>
                            <del style="padding-left:4%;">${{ number_format($product->price, 2) }}</del>
                        </div>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center">
                <p>No trending products available.</p>
            </div>
            @endforelse
        </div>
    </div>
</div>
<!-- End Product Area -->

{{-- @php
    $featured=DB::table('products')->where('is_featured',1)->where('status','active')->orderBy('id','DESC')->limit(1)->get();
@endphp --}}
<!-- Start Midium Banner  -->
<section class="midium-banner">
    <div class="container">
        <div class="row">
            @if($featured)
            @foreach($featured as $data)
            <!-- Single Banner  -->
            <div class="col-lg-6 col-md-6 col-12">
                <div class="single-banner">
                    @php
                    $firstImage = $data->images->first();
                    $imageUrl = $firstImage ? asset($firstImage->image_path) : asset('images/no-image.png');
                    @endphp
                    <img src="{{ $imageUrl }}" alt="product image">

                    <div class="content">
                        <p>{{$data->cat_info['title']}}</p>
                        <h3>{{$data->title}} <br>Up to<span> {{$data->discount}}%</span></h3>
                        <a href="{{route('product-detail',$data->slug)}}">Shop Now</a>
                    </div>
                </div>
            </div>
            <!-- /End Single Banner  -->
            @endforeach
            @endif
        </div>
    </div>
</section>
<!-- End Midium Banner -->

<!-- Start Most Popular -->
<div class="product-area most-popular section">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title">
                    <h2>Hot Item</h2>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="owl-carousel popular-slider">
                    @foreach($product_lists as $product)
                    @if($product->condition=='hot')
                    <!-- Start Single Product -->
                    <div class="single-product">
                        <div class="product-img">
                            <a href="{{route('product-detail',$product->slug)}}">
                                @php
                                $defaultImage = $product->images->first();
                                $imageUrl = $defaultImage ? asset($defaultImage->image_path) : asset('images/no-image.png');
                                @endphp

                                <img class="default-img" src="{{ $imageUrl }}" alt="product image">
                                <img class="hover-img" src="{{ $imageUrl }}" alt="product image">

                                {{-- <span class="out-of-stock">Hot</span> --}}
                            </a>
                            <div class="button-head">
                                <div class="product-action">
                                    <a data-toggle="modal" data-target="#{{$product->id}}" title="Quick View" href="#"><i class=" ti-eye"></i><span>Quick Shop</span></a>
                                    <a title="Wishlist" href="{{route('add-to-wishlist',$product->slug)}}"><i class=" ti-heart "></i><span>Add to Wishlist</span></a>
                                </div>
                                <div class="product-action-2">
                                    <a href="{{route('add-to-cart',$product->slug)}}">Add to cart</a>
                                </div>
                            </div>
                        </div>
                        <div class="product-content">
                            <h3><a href="{{route('product-detail',$product->slug)}}">{{$product->title}}</a></h3>
                            <div class="product-price">
                                <span class="old">${{number_format($product->price,2)}}</span>
                                @php
                                $after_discount=($product->price-($product->price*$product->discount)/100)
                                @endphp
                                <span>${{number_format($after_discount,2)}}</span>
                            </div>
                        </div>
                    </div>
                    <!-- End Single Product -->
                    @endif
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Most Popular Area -->

<!-- Start Shop Home List  -->
<section class="shop-home-list section">
    <div class="container">
        <div class="row">

            <div class="col-lg-12 col-md-12 col-12">
                <div class="row">
                    <div class="col-12">
                        <div class="shop-section-title">
                            <h1>Latest Items</h1>
                        </div>
                    </div>
                </div>
                <div class="row">
                    @foreach($product_lists as $product)
                    <div class="col-md-4">
                        <!-- Start Single List  -->
                        <div class="single-list">
                            <div class="row">
                                <div class="col-lg-6 col-md-6 col-12">
                                    <div class="list-image overlay">
                                        @php
                                        $image = $product->images->first();
                                        @endphp
                                        <img src="{{ $image->image_path ?? '/default.png' }}" alt="{{ $product->title }}">
                                        <a href="{{ route('add-to-cart', $product->slug) }}" class="buy"><i class="fa fa-shopping-bag"></i></a>
                                    </div>
                                </div>
                                <div class="col-lg-6 col-md-6 col-12 no-padding">
                                    <div class="content">
                                        <h4 class="title"><a href="#">{{ $product->title }}</a></h4>
                                        <p class="price with-discount">${{ number_format($product->discount, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Single List  -->
                    </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</section>
<!-- End Shop Home List  -->

<!-- Start Shop Blog  -->
<section class="shop-blog section">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="section-title">
                    <h2>From Our Blog</h2>
                </div>
            </div>
        </div>
        <div class="row">
            @if($posts)
            @foreach($posts as $post)
            <div class="col-lg-4 col-md-6 col-12">
                <!-- Start Single Blog  -->
                <div class="shop-single-blog">
                    <img src="{{$post->photo}}" alt="{{$post->photo}}">
                    <div class="content">
                        <p class="date">{{$post->created_at->format('d M , Y. D')}}</p>
                        <a href="{{route('blog.detail',$post->slug)}}" class="title">{{$post->title}}</a>
                        <a href="{{route('blog.detail',$post->slug)}}" class="more-btn">Continue Reading</a>
                    </div>
                </div>
                <!-- End Single Blog  -->
            </div>
            @endforeach
            @endif

        </div>
    </div>
</section>
<!-- End Shop Blog  -->

<!-- Start Shop Services Area -->
<section class="shop-services section home">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-6 col-12">
                <!-- Start Single Service -->
                <div class="single-service">
                    <i class="ti-rocket"></i>
                    <h4>Free shiping</h4>
                    <p>Orders over $100</p>
                </div>
                <!-- End Single Service -->
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <!-- Start Single Service -->
                <div class="single-service">
                    <i class="ti-reload"></i>
                    <h4>Free Return</h4>
                    <p>Within 30 days returns</p>
                </div>
                <!-- End Single Service -->
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <!-- Start Single Service -->
                <div class="single-service">
                    <i class="ti-lock"></i>
                    <h4>Sucure Payment</h4>
                    <p>100% secure payment</p>
                </div>
                <!-- End Single Service -->
            </div>
            <div class="col-lg-3 col-md-6 col-12">
                <!-- Start Single Service -->
                <div class="single-service">
                    <i class="ti-tag"></i>
                    <h4>Best Peice</h4>
                    <p>Guaranteed price</p>
                </div>
                <!-- End Single Service -->
            </div>
        </div>
    </div>
</section>
<!-- End Shop Services Area -->

{{-- @include('frontend.layouts.newsletter') --}}

<!-- Modal -->
@if($product_lists)
@foreach($product_lists as $key=>$product)
<div class="modal fade" id="{{$product->id}}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span class="ti-close" aria-hidden="true"></span></button>
            </div>
            <div class="modal-body">
                <div class="row no-gutters">
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                        <!-- Product Slider -->
                        <div class="product-gallery">
                            <div class="quickview-slider-active">
                                @forelse($product->images as $image)
                                <div class="single-slider">
                                    <img src="{{ asset($image->image_path) }}" alt="product image">
                                </div>
                                @empty
                                <div class="single-slider">
                                    <img src="{{ asset('images/no-image.png') }}" alt="No image found">
                                </div>
                                @endforelse
                            </div>
                        </div>
                        <!-- End Product slider -->
                    </div>
                    <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                        <div class="quickview-content">
                            <h2>{{$product->title}}</h2>
                            <div class="quickview-ratting-review">
                                <div class="quickview-ratting-wrap">
                                    <div class="quickview-ratting">
                                        {{-- <i class="yellow fa fa-star"></i>
                                                    <i class="yellow fa fa-star"></i>
                                                    <i class="yellow fa fa-star"></i>
                                                    <i class="yellow fa fa-star"></i>
                                                    <i class="fa fa-star"></i> --}}
                                        @php
                                        $rate=DB::table('product_reviews')->where('product_id',$product->id)->avg('rate');
                                        $rate_count=DB::table('product_reviews')->where('product_id',$product->id)->count();
                                        @endphp
                                        @for($i=1; $i<=5; $i++)
                                            @if($rate>=$i)
                                            <i class="yellow fa fa-star"></i>
                                            @else
                                            <i class="fa fa-star"></i>
                                            @endif
                                            @endfor
                                    </div>
                                    <a href="#"> ({{$rate_count}} customer review)</a>
                                </div>
                                <div class="quickview-stock">
                                    @if($product->stock >0)
                                    <span><i class="fa fa-check-circle-o"></i> {{$product->stock}} in stock</span>
                                    @else
                                    <span><i class="fa fa-times-circle-o text-danger"></i> {{$product->stock}} out stock</span>
                                    @endif
                                </div>
                            </div>
                            @php
                            $after_discount=($product->price-($product->price*$product->discount)/100);
                            @endphp
                            <h3><small><del class="text-muted">${{number_format($product->price,2)}}</del></small> ${{number_format($after_discount,2)}} </h3>
                            <div class="quickview-peragraph">
                                <p>{!! html_entity_decode($product->summary) !!}</p>
                            </div>
                            @if($product->size)
                            <div class="size">
                                <div class="row">
                                    <div class="col-lg-6 col-12">
                                        <h5 class="title">Size</h5>
                                        <select>
                                            @php
                                            $sizes=explode(',',$product->size);
                                            // dd($sizes);
                                            @endphp
                                            @foreach($sizes as $size)
                                            <option>{{$size}}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{-- <div class="col-lg-6 col-12">
                                                        <h5 class="title">Color</h5>
                                                        <select>
                                                            <option selected="selected">orange</option>
                                                            <option>purple</option>
                                                            <option>black</option>
                                                            <option>pink</option>
                                                        </select>
                                                    </div> --}}
                                </div>
                            </div>
                            @endif
                            <form action="{{route('single-add-to-cart')}}" method="POST" class="mt-4">
                                @csrf
                                <div class="quantity">
                                    <!-- Input Order -->
                                    <div class="input-group">
                                        <div class="button minus">
                                            <button type="button" class="btn btn-primary btn-number" disabled="disabled" data-type="minus" data-field="quant[1]">
                                                <i class="ti-minus"></i>
                                            </button>
                                        </div>
                                        <input type="hidden" name="slug" value="{{$product->slug}}">
                                        <input type="text" name="quant[1]" class="input-number" data-min="1" data-max="1000" value="1">
                                        <div class="button plus">
                                            <button type="button" class="btn btn-primary btn-number" data-type="plus" data-field="quant[1]">
                                                <i class="ti-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <!--/ End Input Order -->
                                </div>
                                <div class="add-to-cart">
                                    <button type="submit" class="btn">Add to cart</button>
                                    <a href="{{route('add-to-wishlist',$product->slug)}}" class="btn min"><i class="ti-heart"></i></a>
                                </div>
                            </form>
                            <div class="default-social">
                                <!-- ShareThis BEGIN -->
                                <div class="sharethis-inline-share-buttons"></div><!-- ShareThis END -->
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
<!-- Modal end -->
@endsection

@push('styles')
<script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=5f2e5abf393162001291e431&product=inline-share-buttons' async='async'></script>
<style>
    /* Banner Sliding */
    #Gslider .carousel-inner {
        background: #000000;
        color: black;
    }

    #Gslider .carousel-inner {
        height: 550px;
    }

    #Gslider .carousel-inner img {
        width: 100% !important;
        opacity: .8;
    }

    #Gslider .carousel-inner .carousel-caption {
        bottom: 60%;
    }

    #Gslider .carousel-inner .carousel-caption h1 {
        font-size: 50px;
        font-weight: bold;
        line-height: 100%;
        color: #F7941D;
    }

    #Gslider .carousel-inner .carousel-caption p {
        font-size: 18px;
        color: black;
        margin: 28px 0 28px 0;
    }

    #Gslider .carousel-indicators {
        bottom: 70px;
    }

    .filter-tope-group button {
        background-color: transparent;
        color: black;
        border: 1px solid #ccc;
        margin: 0 5px 10px 0;
        padding: 8px 16px;
        transition: background 0.3s, color 0.3s;
    }

    .filter-tope-group button.how-active1 {
        background-color: #F7941D !important;
        color: white !important;
        border-color: #F7941D !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
    $(document).ready(function() {
        var $topeContainer = $('.isotope-grid');
        var $filterButtons = $('.filter-tope-group button');

        // Isotope init
        $topeContainer.isotope({
            itemSelector: '.isotope-item',
            layoutMode: 'fitRows',
            percentPosition: true,
            animationEngine: 'best-available',
            masonry: {
                columnWidth: '.isotope-item'
            }
        });

        // Filter handler
        $filterButtons.on('click', function() {
            var filterValue = $(this).attr('data-filter');

            // Filter items
            $topeContainer.isotope({
                filter: filterValue
            });

            // Update active class
            $filterButtons.removeClass('how-active1');
            $(this).addClass('how-active1');
        });
    });
</script>
<script>
    function cancelFullScreen(el) {
        var requestMethod = el.cancelFullScreen || el.webkitCancelFullScreen || el.mozCancelFullScreen || el.exitFullscreen;
        if (requestMethod) { // cancel full screen.
            requestMethod.call(el);
        } else if (typeof window.ActiveXObject !== "undefined") { // Older IE.
            var wscript = new ActiveXObject("WScript.Shell");
            if (wscript !== null) {
                wscript.SendKeys("{F11}");
            }
        }
    }

    function requestFullScreen(el) {
        // Supports most browsers and their versions.
        var requestMethod = el.requestFullScreen || el.webkitRequestFullScreen || el.mozRequestFullScreen || el.msRequestFullscreen;

        if (requestMethod) { // Native full screen.
            requestMethod.call(el);
        } else if (typeof window.ActiveXObject !== "undefined") { // Older IE.
            var wscript = new ActiveXObject("WScript.Shell");
            if (wscript !== null) {
                wscript.SendKeys("{F11}");
            }
        }
        return false
    }
</script>

@endpush