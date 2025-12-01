<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="@yield('meta_description', 'Your one-stop shop for quality products')">
<meta name="keywords" content="@yield('meta_keywords', 'e-commerce, shopping, products')">
<meta name="author" content="Your Company Name">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'E-Shop')</title>

<!-- DNS Prefetch & Preconnect for faster resource loading -->
<link rel="dns-prefetch" href="https://fonts.googleapis.com">
<link rel="dns-prefetch" href="https://fonts.gstatic.com">
<link rel="dns-prefetch" href="https://stackpath.bootstrapcdn.com">
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link rel="dns-prefetch" href="https://code.jquery.com">
<link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="https://stackpath.bootstrapcdn.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

<!-- Favicon -->
<link rel="icon" type="image/png" href="{{ asset('images/favicon.webp') }}">

<!-- Preload Critical CSS - Bootstrap & Main Styles -->
<link rel="preload" href="https://stackpath.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css"
    as="style" crossorigin="anonymous">
<link rel="preload" href="{{ asset('frontend/css/style.min.css') }}" as="style">

<!-- Critical inline CSS for above-the-fold content -->
<style>
    /* Critical CSS - Inline for fastest First Contentful Paint */
    body {
        margin: 0;
        padding: 0;
        font-family: sans-serif;
        background: #fff
    }

    .header {
        background: #fff;
        border-bottom: 1px solid #eee
    }

    .section {
        padding: 40px 0
    }

    img {
        max-width: 100%;
        height: auto;
        display: block
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 15px
    }
</style>

<!-- Bootstrap - Critical CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.0.0-beta.2/css/bootstrap.min.css"
    crossorigin="anonymous">

<!-- Font Awesome - Async load for better performance -->
<link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
    as="style" onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
</noscript>

<!-- Themify Icons -->
<link rel="stylesheet" href="{{ asset('frontend/css/themify-icons.css') }}">

<!-- jQuery UI - Async load -->
<link rel="preload" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" as="style"
    onload="this.onload=null;this.rel='stylesheet'">
<noscript>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
</noscript>

<!-- Eshop StyleSheet -->
<link rel="stylesheet" href="{{ asset('frontend/css/reset.min.css') }}">
<link rel="stylesheet" href="{{ asset('frontend/css/style.min.css') }}">

<!-- Accessibility Improvements -->
<link rel="stylesheet" href="{{ asset('frontend/css/accessibility-improvements.css') }}">

<!-- Polyfill for async CSS loading -->
<script>
    ! function(e) {
        "use strict";
        var t = function(t, n, r) {
            function o(e) {
                return i.body ? e() : void setTimeout(function() {
                    o(e)
                })
            }

            function a() {
                d.addEventListener && d.removeEventListener("load", a), d.media = r || "all"
            }
            var l, i = e.document,
                d = i.createElement("link");
            if (n) l = n;
            else {
                var s = (i.body || i.getElementsByTagName("head")[0]).childNodes;
                l = s[s.length - 1]
            }
            var u = i.styleSheets;
            d.rel = "stylesheet", d.href = t, d.media = "only x", o(function() {
                l.parentNode.insertBefore(d, n ? l : l.nextSibling)
            });
            var f = function(e) {
                for (var t = d.href, n = u.length; n--;)
                    if (u[n].href === t) return e();
                setTimeout(function() {
                    f(e)
                })
            };
            return d.addEventListener && d.addEventListener("load", a), d.onloadcssdefined = f, f(a), d
        };
        "undefined" != typeof exports ? exports.loadCSS = t : e.loadCSS = t
    }("undefined" != typeof global ? global : this);
</script>
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
        margin: 0% 6% 0% 6%;
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
