<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
	@include('frontend.layouts.head')
</head>

<body class="js">
	@include('frontend.layouts.notification')
	@include('frontend.layouts.header')
	@yield('main-content')
	@include('frontend.layouts.footer')

		<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
		<!-- Popper.js (required for Bootstrap 4) -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" crossorigin="anonymous"></script>
		<!-- Bootstrap JS (required for modal) -->
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.0.0-beta.2/js/bootstrap.min.js" crossorigin="anonymous"></script>
		@stack('scripts')
</body>

</html>