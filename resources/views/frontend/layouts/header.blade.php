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
                    <div class="search-bar-wrapper py-3 px-3 position-relative">
                        <form action="{{ route('product.search') }}" method="GET" class="form-inline search-bar-form justify-content-center" autocomplete="off">
                            <div class="input-group mb-0 w-100 w-md-auto">
                                <input type="text"
                                    name="search"
                                    id="search-input"
                                    class="form-control search-input"
                                    placeholder="Search Products..."
                                    value="{{ request('search') }}"
                                    aria-label="Search products"
                                    aria-autocomplete="list"
                                    aria-controls="autocomplete-list"
                                    aria-expanded="false"
                                    role="combobox"
                                    autocomplete="off">
                            </div>
                            <div id="autocomplete-dropdown" class="autocomplete-dropdown position-absolute w-100 bg-white shadow-sm border rounded mt-1">
                                <ul id="autocomplete-list" class="list-group list-group-flush m-0"></ul>
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
                                <span class="total-count">{{ Helper::wishlistCount() ?? 0 }}</span>
                            </a>
                            @include('frontend.partials.wishlist-dropdown')
                        </div>
                        <div class="sinlge-bar shopping">
                            <a href="{{ route('cart') }}" class="single-icon" aria-label="Cart">
                                <i class="ti-bag"></i>
                                <span class="total-count">{{ Helper::cartCount() ?? 0 }}</span>
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
                    <ul class="nav main-menu menu navbar-nav mk-ct">
                        <li class="{{ request()->is('home') ? 'active' : '' }}"><a href="{{ route('home') }}">Home</a></li>
                        <li class="{{ request()->is('about-us') ? 'active' : '' }}"><a href="{{ route('about-us') }}">About Us</a></li>
                        <li class="{{ request()->is('product-grids', 'product-lists') ? 'active' : '' }}"><a href="{{ route('product-grids') }}">Products</a></li>
                        @include('frontend.partials.category-menu')
                        <!-- <li class="{{ request()->is('blog') ? 'active' : '' }}"><a href="{{ route('blog') }}">Blog</a></li> -->
                        <li class="{{ request()->is('contact') ? 'active' : '' }} cnt-us"><a href="{{ route('contact') }}">Contact Us</a></li>
                    </ul>
                </div>
            </nav>
        </div>
    </div>
</header>
@push('scripts')
<script>
    $(document).ready(function() {
        console.log('Elasticsearch script loaded');

        // Smooth scroll to sections
        $('a[href*="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top
                }, 1000);
            }
        });

        const $searchInput = $('#search-input');
        const $dropdown = $('#autocomplete-dropdown');
        const $list = $('#autocomplete-list');
        let searchTimeout;

        // Stop if critical elements are missing
        if (!$searchInput.length || !$dropdown.length || !$list.length) return;

        // Input event listener
        $searchInput.on('input', function() {
            const query = $(this).val().trim();
            $searchInput.attr('aria-expanded', query.length >= 2);
            debounceSearch(query);
        });

        // Handle focus event
        $searchInput.on('focus', function() {
            const query = $(this).val().trim();
            if (query.length >= 2) {
                showDropdown();
            }
        });

        // Debounce search
        function debounceSearch(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performAutocomplete(query), 300);
        }

        // Perform AJAX autocomplete
        function performAutocomplete(query) {
            if (query.length < 2) {
                hideDropdown();
                return;
            }

            $list.html('<li class="list-group-item loading">Searching...</li>');
            showDropdown();

            const autocompleteUrl = '/autocomplete';

            $.ajax({
                url: autocompleteUrl,
                method: 'GET',
                data: {
                    q: query
                },
                dataType: 'json',
                timeout: 10000,
                success: function(response) {
                    $list.empty();

                    if (response.success && Array.isArray(response.suggestions) && response.suggestions.length > 0) {
                        response.suggestions.forEach(item => {
                            if (!item.title || !item.slug) return;

                            const price = parseFloat(item.price) || 0;
                            const discount = parseFloat(item.discount) || 0;

                            let priceHTML = '';
                            if (price > 0) {
                                if (discount > 0) {
                                    const discountPrice = price - (price * discount / 100);
                                    priceHTML = `<span class="price">$${discountPrice.toFixed(2)} <del>$${price.toFixed(2)}</del></span>`;
                                } else {
                                    priceHTML = `<span class="price">$${price.toFixed(2)}</span>`;
                                }
                            }

                            $list.append(`
                                <li class="list-group-item autocomplete-item" data-slug="${item.slug}" role="option">
                                    <div class="item-content">
                                        <span class="title">${escapeHtml(item.title)}</span>
                                        ${priceHTML}
                                    </div>
                                </li>
                            `);
                        });
                    } else {
                        $list.html('<li class="list-group-item no-results">No products found</li>');
                    }
                    showDropdown();
                },
                error: function(xhr, status) {
                    let errorMessage = 'Error loading suggestions';
                    if (status === 'timeout') {
                        errorMessage = 'Request timed out';
                    } else if (xhr.status === 404) {
                        errorMessage = 'Autocomplete endpoint not found';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error';
                    }

                    $list.html(`<li class="list-group-item error">${errorMessage}</li>`);
                    showDropdown();
                }
            });
        }

        // Show dropdown
        function showDropdown() {
            $dropdown.removeClass('d-none').css('display', 'block');
            $searchInput.attr('aria-expanded', 'true');
        }

        // Hide dropdown
        function hideDropdown() {
            $dropdown.addClass('d-none').css('display', 'none');
            $searchInput.attr('aria-expanded', 'false');
        }

        // Handle suggestion clicks
        $list.on('click', '.autocomplete-item', function(e) {
            e.preventDefault();
            const slug = $(this).data('slug');
            if (slug) {
                window.location.href = `/product/${slug}`;
            }
        });

        // Keyboard navigation
        $searchInput.on('keydown', function(e) {
            const $items = $list.find('.autocomplete-item');
            const $active = $items.filter('.active');

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if ($active.length === 0) {
                    $items.first().addClass('active');
                } else {
                    $active.removeClass('active').next('.autocomplete-item').addClass('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if ($active.length === 0) {
                    $items.last().addClass('active');
                } else {
                    $active.removeClass('active').prev('.autocomplete-item').addClass('active');
                }
            } else if (e.key === 'Enter') {
                if ($active.length > 0) {
                    e.preventDefault();
                    $active.click();
                }
            } else if (e.key === 'Escape') {
                hideDropdown();
            }
        });

        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.search-bar-wrapper').length) {
                hideDropdown();
            }
        });

        // Escape HTML utility
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
</script>

@endpush