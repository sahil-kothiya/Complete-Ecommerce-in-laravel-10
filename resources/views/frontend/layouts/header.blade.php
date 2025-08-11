<header class="header shop">
    <!-- Topbar -->
    <div class="topbar">
        <div class="container">
            <div class="row">
                <div class="col-lg-6 col-md-12 col-12">
                    <ul class="list-main">
                        <li><i class="ti-headphone-alt"></i> 
                            <span class="text-placeholder" data-content="phone">{{ $settings->phone ?? '' }}</span>
                        </li>
                        <li><i class="ti-email"></i> 
                            <span class="text-placeholder" data-content="email">{{ $settings->email ?? '' }}</span>
                        </li>
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
                            <img src="{{ $settings->logo ?? asset('images/default-logo.png') }}" 
                                 alt="Logo" 
                                 loading="eager"
                                 width="120" 
                                 height="40"
                                 style="max-width: 100%; height: auto;">
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
                            <div id="autocomplete-dropdown" class="autocomplete-dropdown position-absolute w-100 bg-white shadow-sm border rounded mt-1 d-none">
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
                            <div class="wishlist-dropdown-placeholder">
                                @include('frontend.partials.wishlist-dropdown')
                            </div>
                        </div>
                        <div class="sinlge-bar shopping">
                            <a href="{{ route('cart') }}" class="single-icon" aria-label="Cart">
                                <i class="ti-bag"></i>
                                <span class="total-count">{{ Helper::cartCount() ?? 0 }}</span>
                            </a>
                            <div class="cart-dropdown-placeholder">
                                @include('frontend.partials.cart-dropdown')
                            </div>
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
                        <div class="category-menu-placeholder">
                            @include('frontend.partials.category-menu')
                        </div>
                        <li class="{{ request()->is('contact') ? 'active' : '' }} cnt-us"><a href="{{ route('contact') }}">Contact Us</a></li>
                    </ul>
                </div>
            </nav>
        </div>
    </div>
</header>

<style>
/* CLS Prevention Styles */
.text-placeholder {
    display: inline-block;
    min-width: 120px;
    min-height: 1.2em;
}

.text-placeholder[data-content="phone"] {
    min-width: 140px;
}

.text-placeholder[data-content="email"] {
    min-width: 180px;
}

/* Logo container fixed dimensions */
.logo {
    min-height: 50px;
    display: flex;
    align-items: center;
}

.logo img {
    transition: none !important;
    will-change: auto;
}

/* Dropdown containers with reserved space */
.wishlist-dropdown-placeholder,
.cart-dropdown-placeholder {
    position: relative;
    min-height: 0;
}

.category-menu-placeholder {
    display: inline-block;
}

/* Autocomplete dropdown optimization */
.autocomplete-dropdown {
    transform: translateZ(0);
    backface-visibility: hidden;
    max-height: 300px;
    overflow-y: auto;
    z-index: 9999;
}

.autocomplete-dropdown.d-none {
    display: none !important;
    visibility: hidden;
}

/* Search input stability */
.search-bar-wrapper {
    min-height: 60px;
    display: flex;
    align-items: center;
}

.search-input {
    min-height: 40px;
}

/* Right bar fixed width */
.right-bar {
    min-width: 120px;
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 15px;
}

.sinlge-bar {
    position: relative;
}

/* Main menu stability */
.header-inner {
    min-height: 60px;
}

.main-menu {
    min-height: 40px;
    display: flex;
    align-items: center;
}

/* Prevent font loading shifts */
.header {
    font-display: swap;
}

/* Icon stability */
.ti-headphone-alt,
.ti-email,
.ti-location-pin,
.ti-user,
.ti-power-off,
.fa-heart-o,
.ti-bag {
    display: inline-block;
    width: 16px;
    min-width: 16px;
    text-align: center;
}

/* Counter badges */
.total-count {
    position: absolute;
    top: -8px;
    right: -8px;
    min-width: 18px;
    min-height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    line-height: 1;
}

/* Mobile responsiveness without layout shifts */
@media (max-width: 991px) {
    .text-placeholder {
        min-width: 100px;
    }
    
    .right-bar {
        min-width: 100px;
    }
}

@media (max-width: 767px) {
    .topbar .list-main li {
        font-size: 12px;
    }
    
    .text-placeholder {
        min-width: 80px;
    }
    
    .logo {
        min-height: 40px;
    }
    
    .search-bar-wrapper {
        min-height: 50px;
    }
}
</style>

@push('scripts')
<script>
    $(document).ready(function() {
        // Preload critical elements to prevent CLS
        const preloadElements = () => {
            // Ensure dropdown is properly hidden on load
            const $dropdown = $('#autocomplete-dropdown');
            if ($dropdown.length) {
                $dropdown.addClass('d-none').css({
                    'display': 'none',
                    'visibility': 'hidden'
                });
            }
        };

        preloadElements();

        // Smooth scroll to sections with RAF for better performance
        $('a[href*="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.hash);
            if (target.length) {
                const targetOffset = target.offset().top;
                $('html, body').animate({
                    scrollTop: targetOffset
                }, {
                    duration: 1000,
                    easing: 'swing'
                });
            }
        });

        const $searchInput = $('#search-input');
        const $dropdown = $('#autocomplete-dropdown');
        const $list = $('#autocomplete-list');
        let searchTimeout;

        // Stop if critical elements are missing
        if (!$searchInput.length || !$dropdown.length || !$list.length) return;

        // Input event listener with throttling
        let isInputting = false;
        $searchInput.on('input', function() {
            if (isInputting) return;
            isInputting = true;
            
            requestAnimationFrame(() => {
                const query = $(this).val().trim();
                $searchInput.attr('aria-expanded', query.length >= 2 ? 'true' : 'false');
                debounceSearch(query);
                isInputting = false;
            });
        });

        // Handle focus event
        $searchInput.on('focus', function() {
            const query = $(this).val().trim();
            if (query.length >= 2) {
                showDropdown();
            }
        });

        // Debounce search with improved performance
        function debounceSearch(query) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => performAutocomplete(query), 300);
        }

        // Perform AJAX autocomplete with better error handling
        function performAutocomplete(query) {
            if (query.length < 2) {
                hideDropdown();
                return;
            }

            // Use DocumentFragment for better performance
            $list.html('<li class="list-group-item loading" style="min-height: 40px;">Searching...</li>');
            showDropdown();

            const autocompleteUrl = '/autocomplete';

            $.ajax({
                url: autocompleteUrl,
                method: 'GET',
                data: { q: query },
                dataType: 'json',
                timeout: 10000,
                cache: true,
                success: function(response) {
                    // Use DocumentFragment for efficient DOM manipulation
                    const fragment = document.createDocumentFragment();
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

                            const listItem = $(`
                                <li class="list-group-item autocomplete-item" data-slug="${item.slug}" role="option" style="min-height: 50px;">
                                    <div class="item-content">
                                        <span class="title">${escapeHtml(item.title)}</span>
                                        ${priceHTML}
                                    </div>
                                </li>
                            `);
                            
                            $list.append(listItem);
                        });
                    } else {
                        $list.html('<li class="list-group-item no-results" style="min-height: 40px;">No products found</li>');
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

                    $list.html(`<li class="list-group-item error" style="min-height: 40px;">${errorMessage}</li>`);
                    showDropdown();
                }
            });
        }

        // Show dropdown with better performance
        function showDropdown() {
            requestAnimationFrame(() => {
                $dropdown.removeClass('d-none').css({
                    'display': 'block',
                    'visibility': 'visible'
                });
                $searchInput.attr('aria-expanded', 'true');
            });
        }

        // Hide dropdown with better performance
        function hideDropdown() {
            requestAnimationFrame(() => {
                $dropdown.addClass('d-none').css({
                    'display': 'none',
                    'visibility': 'hidden'
                });
                $searchInput.attr('aria-expanded', 'false');
            });
        }

        // Handle suggestion clicks with event delegation
        $list.on('click', '.autocomplete-item', function(e) {
            e.preventDefault();
            const slug = $(this).data('slug');
            if (slug) {
                window.location.href = `/product-detail/${slug}`;
            }
        });

        // Improved keyboard navigation
        $searchInput.on('keydown', function(e) {
            const $items = $list.find('.autocomplete-item');
            const $active = $items.filter('.active');

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if ($active.length === 0) {
                        $items.first().addClass('active');
                    } else {
                        const $next = $active.removeClass('active').next('.autocomplete-item');
                        if ($next.length > 0) {
                            $next.addClass('active');
                        } else {
                            $items.first().addClass('active');
                        }
                    }
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    if ($active.length === 0) {
                        $items.last().addClass('active');
                    } else {
                        const $prev = $active.removeClass('active').prev('.autocomplete-item');
                        if ($prev.length > 0) {
                            $prev.addClass('active');
                        } else {
                            $items.last().addClass('active');
                        }
                    }
                    break;
                    
                case 'Enter':
                    if ($active.length > 0) {
                        e.preventDefault();
                        $active.click();
                    }
                    break;
                    
                case 'Escape':
                    hideDropdown();
                    $searchInput.blur();
                    break;
            }
        });

        // Hide dropdown when clicking outside with improved performance
        let clickOutsideTimeout;
        $(document).on('click', function(e) {
            clearTimeout(clickOutsideTimeout);
            clickOutsideTimeout = setTimeout(() => {
                if (!$(e.target).closest('.search-bar-wrapper').length) {
                    hideDropdown();
                }
            }, 10);
        });

        // Escape HTML utility function
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Performance monitoring (optional - remove in production)
        if (window.performance && window.performance.mark) {
            window.performance.mark('header-script-end');
        }
    });
</script>
@endpush