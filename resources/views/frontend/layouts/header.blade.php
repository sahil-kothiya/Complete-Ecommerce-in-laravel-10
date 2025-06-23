<header class="header shop">
    <!-- Topbar -->
    <div class="topbar">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-12">
                    <ul class="list-main">
                        <li><i class="ti-headphone-alt"></i> {{ $settings->phone ?? '' }}</li>
                        <li><i class="ti-email"></i> {{ $settings->email ?? '' }}</li>
                    </ul>
                </div>
                <div class="col-lg-6 col-md-12 col-12">
                    <ul class="list-main">
                        <li><i class="ti-location-pin"></i> <a href="{{ route('order.track') }}">Track Order</a></li>
                        @auth
                        <li><i class="ti-user"></i> <a href="{{ Auth::user()->role === 'admin' ? route('admin') : route('user') }}" target="_blank">Dashboard</a></li>
                        <li><i class="ti-power-off"></i> <a href="{{ route('user.logout') }}">Logout</a></li>
                        @else
                        <li><i class="ti-power-off"></i> <a href="{{ route('login.form') }}">Login</a> / <a href="{{ route('register.form') }}">Register</a></li>
                        @endauth
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Middle Header -->
    <div class="middle-inner">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-2 col-md-2 col-12">
                    <div class="logo">
                        <a href="{{ route('home') }}">
                            <img src="{{ $settings->logo ?? asset('images/default-logo.png') }}" alt="Logo" loading="eager">
                        </a>
                    </div>
                    <div class="mobile-nav"></div>
                </div>
                <div class="col-lg-8 col-md-7 col-12">
                    <div class="search-bar-top">
                        <form action="{{ route('product.search') }}" method="GET" class="search-bar">
                            <select name="category" aria-label="Category filter">
                                <option value="">All Categories</option>
                                @foreach(Helper::getAllCategory() as $parent)
                                <option value="{{ $parent->slug }}">{{ $parent->title }}</option>
                                @if($parent->children->isNotEmpty())
                                @foreach($parent->children as $child)
                                <option value="{{ $child->slug }}">— {{ $child->title }}</option>
                                @endforeach
                                @endif
                                @endforeach
                            </select>
                            <div class="search-container">
                                <input type="text" name="search" id="search-input" placeholder="Search Products..." value="{{ request('search') }}" autocomplete="off" aria-label="Search products">
                                <!-- <button type="submit" aria-label="Search"><i class="ti-search"></i></button> -->
                                <div id="autocomplete-dropdown" class="autocomplete-dropdown" hidden>
                                    <ul id="autocomplete-list"></ul>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-lg-2 col-md-3 col-12">
                    <div class="right-bar">
                        @auth
                        <div class="sinlge-bar shopping">
                            <a href="{{ route('wishlist') }}" class="single-icon" aria-label="Wishlist">
                                <i class="fa fa-heart-o"></i>
                                <span class="total-count">{{ $wishlistCount ?? 0 }}</span>
                            </a>
                            @include('frontend.partials.wishlist-dropdown')
                        </div>
                        <div class="sinlge-bar shopping">
                            <a href="{{ route('cart') }}" class="single-icon" aria-label="Cart">
                                <i class="ti-bag"></i>
                                <span class="total-count">{{ $cartCount ?? 0 }}</span>
                            </a>
                            @include('frontend.partials.cart-dropdown')
                        </div>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Menu -->
    <div class="header-inner">
        <div class="container">
            <nav class="navbar navbar-expand-lg">
                <div class="navbar-collapse">
                    <ul class="nav main-menu menu navbar-nav">
                        <li class="{{ request()->is('home') ? 'active' : '' }}"><a href="{{ route('home') }}">Home</a></li>
                        <li class="{{ request()->is('about-us') ? 'active' : '' }}"><a href="{{ route('about-us') }}">About Us</a></li>
                        <li class="{{ request()->is('product-grids', 'product-lists') ? 'active' : '' }}"><a href="{{ route('product-grids') }}">Products</a></li>
                        @include('frontend.partials.category-menu')
                        <li class="{{ request()->is('blog') ? 'active' : '' }}"><a href="{{ route('blog') }}">Blog</a></li>
                        <li class="{{ request()->is('contact') ? 'active' : '' }}"><a href="{{ route('contact') }}">Contact Us</a></li>
                    </ul>
                </div>
            </nav>
        </div>
    </div>
</header>