<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('frontend.layouts.head')
</head>

<body class="js">
    <!-- Skip to main content for accessibility -->
    <a href="#main-content" class="skip-to-main">Skip to main content</a>

    @include('frontend.layouts.notification')
    @include('frontend.layouts.header')

    <main id="main-content" role="main">
        @yield('main-content')
    </main>

    @include('frontend.layouts.footer')
    @stack('scripts')
</body>

</html>
