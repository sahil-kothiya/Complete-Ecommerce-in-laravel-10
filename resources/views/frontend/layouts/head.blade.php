<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="@yield('meta_description', 'Your one-stop shop for quality products')">
<meta name="keywords" content="@yield('meta_keywords', 'e-commerce, shopping, products')">
<meta name="author" content="Your Company Name">
<title>@yield('title', 'E-Shop')</title>

<!-- Favicon -->
<link rel="icon" type="image/png" href="{{ asset('images/favicon.webp') }}">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- StyleSheet -->
<link rel="manifest" href="/manifest.json">
<!-- Bootstrap -->
<link rel="stylesheet" href="{{asset('frontend/css/bootstrap.css')}}">
<!-- Magnific Popup -->
<link rel="stylesheet" href="{{asset('frontend/css/magnific-popup.min.css')}}">
<!-- Font Awesome -->
<link rel="stylesheet" href="{{asset('frontend/css/font-awesome.css')}}">
<!-- Fancybox -->
<link rel="stylesheet" href="{{asset('frontend/css/jquery.fancybox.min.css')}}">
<!-- Themify Icons -->
<link rel="stylesheet" href="{{asset('frontend/css/themify-icons.css')}}">
<!-- Nice Select CSS -->
<link rel="stylesheet" href="{{asset('frontend/css/niceselect.css')}}">
<!-- Animate CSS -->
<link rel="stylesheet" href="{{asset('frontend/css/animate.css')}}">
<!-- Flex Slider CSS -->
<link rel="stylesheet" href="{{asset('frontend/css/flex-slider.min.css')}}">
<!-- Owl Carousel -->
<link rel="stylesheet" href="{{asset('frontend/css/owl-carousel.css')}}">
<!-- Slicknav -->
<link rel="stylesheet" href="{{asset('frontend/css/slicknav.min.css')}}">
<!-- Jquery Ui -->
<link rel="stylesheet" href="{{asset('frontend/css/jquery-ui.css')}}">

<!-- Eshop StyleSheet -->
<link rel="stylesheet" href="{{asset('frontend/css/reset.css')}}">
<link rel="stylesheet" href="{{asset('frontend/css/style.css')}}">
<link rel="stylesheet" href="{{asset('frontend/css/responsive.css')}}">
<script type='text/javascript' src='https://platform-api.sharethis.com/js/sharethis.js#property=5f2e5abf393162001291e431&product=inline-share-buttons' async='async'></script>
<style>
    /* Multilevel dropdown */
    .dropdown-submenu {
        position: relative;
    }

    .dropdown-submenu>a:after {
        content: "\f0da";
        float: right;
        border: none;
        font-family: 'FontAwesome';
    }

    .dropdown-submenu>.dropdown-menu {
        top: 0;
        left: 100%;
        margin-top: 0px;
        margin-left: 0px;
    }

    /* Style the 'new' badge properly */
    .navbar-nav li {
        position: relative;
    }

    .navbar-nav li a {
        display: inline-block;
        position: relative;
        padding-right: 35px;
        /* space for the badge */
    }

    .navbar-nav li .new {
        background: #ff6600;
        color: #fff;
        font-size: 10px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 3px;
        text-transform: uppercase;
        position: absolute;
        top: 0;
        right: 0;
        transform: translate(50%, -50%);
    }

    ul.nav.main-menu.menu.navbar-nav {
        display: -webkit-box;
    }

    /* Handle nested dropdown submenu */
    .dropdown-submenu>.dropdown-menu {
        display: none;
        margin-left: 0;
    }

    .dropdown-submenu:hover>.dropdown-menu {
        display: block;
    }

    .dropdown-submenu>a::after {
        content: '›';
        float: right;
    }

    .sr-only {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: -1px;
        padding: 0;
        overflow: hidden;
        clip: rect(0, 0, 0, 0);
        border: 0;
    }

    /* Autocomplete Styles */
    .search-container {
        position: relative;
        width: 100%;
    }

    .autocomplete-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: #fff;
        border: 1px solid #ddd;
        border-top: none;
        border-radius: 0 0 4px 4px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        max-height: 300px;
        overflow-y: auto;
    }

    .autocomplete-dropdown ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .autocomplete-item {
        padding: 10px 15px;
        border-bottom: 1px solid #f0f0f0;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .autocomplete-item:hover,
    .autocomplete-item.selected {
        background-color: #f8f9fa;
    }

    .autocomplete-item:last-child {
        border-bottom: none;
    }

    .autocomplete-item .item-content {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .autocomplete-item .title {
        font-weight: 500;
        color: #333;
        flex: 1;
        margin-right: 10px;
    }

    .autocomplete-item .price {
        color: #e74c3c;
        font-weight: bold;
    }

    .autocomplete-item .price del {
        color: #999;
        font-weight: normal;
        margin-left: 5px;
    }

    .autocomplete-item.loading,
    .autocomplete-item.no-results {
        text-align: center;
        color: #666;
        font-style: italic;
    }

    .navbar-collapse.mk-ct {
        align-items: center;
        /* padding: 0% 0% 0% 15%; */
    }

    .cnt-us {
        padding-left: 6%;
    }

    ul.nav.main-menu.menu.navbar-nav>li {
        margin: 0% 1% 0% 6%;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .autocomplete-dropdown {
            max-height: 200px;
        }

        .autocomplete-item {
            padding: 8px 12px;
        }

        .autocomplete-item .item-content {
            flex-direction: column;
            align-items: flex-start;
        }

        .autocomplete-item .title {
            margin-right: 0;
            margin-bottom: 5px;
        }
    }
</style>
@stack('styles')

@yield('meta')