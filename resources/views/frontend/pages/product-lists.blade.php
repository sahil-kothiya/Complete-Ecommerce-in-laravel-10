@extends('frontend.layouts.master')

@section('title','E-SHOP || PRODUCT PAGE')

@section('main-content')

<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{route('home')}}">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0);">Shop List</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End Breadcrumbs -->

<form action="{{route('shop.filter')}}" method="POST">
    @csrf
    <!-- Product Style 1 -->
    <section class="product-area shop-sidebar shop-list shop section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="shop-sidebar">
                        <!-- Category Widget -->
                        <div class="single-widget category">
                            <h3 class="title">Categories</h3>
                            <ul class="categor-list">
                                @php
                                    $menu = App\Models\Category::getAllParentWithChild();
                                @endphp
                                @if($menu)
                                    @foreach($menu as $cat_info)
                                        @if($cat_info->child_cat?->count())
                                            <li>
                                                <a href="{{route('product-cat',$cat_info->slug)}}">{{$cat_info->title}}</a>
                                                <ul>
                                                    @foreach($cat_info->child_cat as $sub_menu)
                                                        <li><a href="{{route('product-sub-cat',[$cat_info->slug,$sub_menu->slug])}}">{{$sub_menu->title}}</a></li>
                                                    @endforeach
                                                </ul>
                                            </li>
                                        @else
                                            <li><a href="{{route('product-cat',$cat_info->slug)}}">{{$cat_info->title}}</a></li>
                                        @endif
                                    @endforeach
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9 col-md-8 col-12">
                    <div class="row">
                        <div class="col-12">
                            <!-- Shop Top -->
                            <div class="shop-top">
                                <div class="shop-shorter">
                                    <div class="single-shorter">
                                        <label>Show :</label>
                                        <select class="show" name="show" onchange="this.form.submit();">
                                            <option value="">Default</option>
                                            <option value="9" @if(request('show') == '9') selected @endif>09</option>
                                            <option value="15" @if(request('show') == '15') selected @endif>15</option>
                                            <option value="21" @if(request('show') == '21') selected @endif>21</option>
                                            <option value="30" @if(request('show') == '30') selected @endif>30</option>
                                        </select>
                                    </div>
                                    <div class="single-shorter">
                                        <label>Sort By :</label>
                                        <select class='sortBy' name='sortBy' onchange="this.form.submit();">
                                            <option value="">Default</option>
                                            <option value="title" @if(request('sortBy') == 'title') selected @endif>Name</option>
                                            <option value="price" @if(request('sortBy') == 'price') selected @endif>Price</option>
                                            <option value="category" @if(request('sortBy') == 'category') selected @endif>Category</option>
                                            <option value="brand" @if(request('sortBy') == 'brand') selected @endif>Brand</option>
                                        </select>
                                    </div>
                                </div>
                                <ul class="view-mode">
                                    <li><a href="{{route('product-grids')}}"><i class="fa fa-th-large"></i></a></li>
                                    <li class="active"><a href="javascript:void(0)"><i class="fa fa-th-list"></i></a></li>
                                </ul>
                            </div>
                            <!--/ End Shop Top -->
                        </div>
                    </div>
                    <div class="row">
                        @if($products->count())
                            @foreach($products as $product)
                                <!-- Start Single List -->
                                <div class="col-12">
                                    <div class="row">
                                        <div class="col-lg-4 col-md-6 col-sm-6">
                                            <div class="single-product">
                                                <div class="product-img">
                                                    <a href="{{route('product-detail',$product->slug)}}">
                                                        @php
                                                            $photo = explode(',',$product->photo)[0];
                                                        @endphp
                                                        <img class="default-img" src="{{$photo}}" alt="{{$product->title}}">
                                                        <img class="hover-img" src="{{$photo}}" alt="{{$product->title}}">
                                                    </a>
                                                    <div class="button-head">
                                                        <div class="product-action">
                                                            <a data-toggle="modal" data-target="#{{$product->id}}" title="Quick View" href="#"><i class="ti-eye"></i><span>Quick Shop</span></a>
                                                            <a title="Wishlist" href="{{route('add-to-wishlist',$product->slug)}}"><i class="ti-heart"></i><span>Add to Wishlist</span></a>
                                                        </div>
                                                        <div class="product-action-2">
                                                            <a title="Add to cart" href="{{route('add-to-cart',$product->slug)}}">Add to cart</a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-8 col-md-6 col-12">
                                            <div class="list-content">
                                                <div class="product-content">
                                                    <div class="product-price">
                                                        @php
                                                            $after_discount = $product->price * (1 - $product->discount/100);
                                                        @endphp
                                                        <span>${{number_format($after_discount,2)}}</span>
                                                        <del>${{number_format($product->price,2)}}</del>
                                                    </div>
                                                    <h3 class="title"><a href="{{route('product-detail',$product->slug)}}">{{$product->title}}</a></h3>
                                                </div>
                                                <p class="des pt-2">{!! html_entity_decode($product->summary) !!}</p>
                                                <a href="{{route('add-to-cart',$product->slug)}}" class="btn cart">Buy Now!</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- End Single List -->
                            @endforeach
                        @else
                            <h4 class="text-warning" style="margin:100px auto;">No products found.</h4>
                        @endif
                    </div>
                    <div class="row">
                        <div class="col-12 d-flex justify-content-center">
                            {{ $products->appends(request()->query())->links('vendor.pagination.bootstrap-4') }}

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--/ End Product Style 1 -->
</form>

<!-- Modal -->
@foreach($products as $product)
    <div class="modal fade" id="{{$product->id}}" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span class="ti-close" aria-hidden="true"></span></button>
                </div>
                <div class="modal-body">
                    <div class="row no-gutters">
                        <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                            <div class="product-gallery">
                                <div class="quickview-slider-active">
                                    @foreach(explode(',',$product->photo) as $data)
                                        <div class="single-slider">
                                            <img src="{{$data}}" alt="{{$product->title}}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
                            <div class="quickview-content">
                                <h2>{{$product->title}}</h2>
                                <div class="quickview-ratting Minnie">
                                    <div class="quickview-ratting-wrap">
                                        <div class="quickview-ratting">
                                            @php
                                                $rate = DB::table('product_reviews')->where('product_id', $product->id)->avg('rate');
                                                $rate_count = DB::table('product_reviews')->where('product_id', $product->id)->count();
                                            @endphp
                                            @for($i = 1; $i <= 5; $i++)
                                                <i class="fa fa-star {{ $rate >= $i ? 'yellow' : '' }}"></i>
                                            @endfor
                                        </div>
                                        <a href="#"> ({{$rate_count}} customer review)</a>
                                    </div>
                                    <div class="quickview-stock">
                                        @if($product->stock > 0)
                                            <span><i class="fa fa-check-circle-o"></i> {{$product->stock}} in stock</span>
                                        @else
                                            <span><i class="fa fa-times-circle-o text-danger"></i> Out of stock</span>
                                        @endif
                                    </div>
                                </div>
                                @php
                                    $after_discount = $product->price * (1 - $product->discount/100);
                                @endphp
                                <h3><small><del class="text-muted">${{number_format($product->price,2)}}</del></small> ${{number_format($after_discount,2)}}</h3>
                                <div class="quickview-peragraph">
                                    <p>{!! html_entity_decode($product->summary) !!}</p>
                                </div>
                                @if($product->size)
                                    <div class="size">
                                        <h4>Size</h4>
                                        <ul>
                                            @foreach(explode(',',$product->size) as $size)
                                                <li><a href="#" class="one">{{$size}}</a></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                <form action="{{route('single-add-to-cart')}}" method="POST">
                                    @csrf
                                    <div class="quantity">
                                        <div class="input-group">
                                            <div class="button minus">
                                                <button type="button" class="btn btn-primary btn-number" data-type="minus" data-field="quant[1]">
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
                                    </div>
                                    <div class="add-to-cart">
                                        <button type="submit" class="btn">Add to cart</button>
                                        <a href="{{route('add-to-wishlist',$product->slug)}}" class="btn min"><i class="ti-heart"></i></a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach
<!-- Modal end -->

@endsection

@push('styles')
<style>
    .pagination {
        display: inline-flex;
    }
</style>
@endpush