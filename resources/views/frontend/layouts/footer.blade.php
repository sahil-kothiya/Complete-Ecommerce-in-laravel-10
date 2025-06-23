<!-- Start Footer Area -->
<footer class="footer">
	<!-- Footer Top -->
	<div class="footer-top section">
		<div class="container">
			<div class="row">
				<div class="col-lg-5 col-md-6 col-12">
					<!-- Single Widget -->
					<div class="single-footer about">
						<div class="logo">
							<a href="index.html"><img src="{{asset('backend/img/logo2.webp')}}" alt="#"></a>
						</div>
						<p class="text">{{ $settings->short_des ?? '' }}</p>
						<p class="call">
							Got Question? Call us 24/7
							<span><a href="tel:{{ $settings->phone ?? '' }}">{{ $settings->phone ?? '' }}</a></span>
						</p>

					</div>
					<!-- End Single Widget -->
				</div>
				<div class="col-lg-2 col-md-6 col-12">
					<!-- Single Widget -->
					<div class="single-footer links">
						<h4>Information</h4>
						<ul>
							<li><a href="{{route('about-us')}}">About Us</a></li>
							<li><a href="#">Faq</a></li>
							<li><a href="#">Terms & Conditions</a></li>
							<li><a href="{{route('contact')}}">Contact Us</a></li>
							<li><a href="#">Help</a></li>
						</ul>
					</div>
					<!-- End Single Widget -->
				</div>
				<div class="col-lg-2 col-md-6 col-12">
					<!-- Single Widget -->
					<div class="single-footer links">
						<h4>Customer Service</h4>
						<ul>
							<li><a href="#">Payment Methods</a></li>
							<li><a href="#">Money-back</a></li>
							<li><a href="#">Returns</a></li>
							<li><a href="#">Shipping</a></li>
							<li><a href="#">Privacy Policy</a></li>
						</ul>
					</div>
					<!-- End Single Widget -->
				</div>
				<div class="col-lg-3 col-md-6 col-12">
					<!-- Single Widget -->
					<div class="single-footer social">
						<h4>Get In Tuch</h4>
						<!-- Single Widget -->
						<div class="contact">
							<ul>
								<li>{{$settings->address??''}}</li>
								<li>{{$settings->email??''}}</li>
								<li>{{$settings->phone??''}}</li>
							</ul>
						</div>
						<!-- End Single Widget -->
						<div class="sharethis-inline-follow-buttons"></div>
					</div>
					<!-- End Single Widget -->
				</div>
			</div>
		</div>
	</div>
	<!-- End Footer Top -->
	<div class="copyright">
		<div class="container">
			<div class="inner">
				<div class="row">
					<div class="col-lg-6 col-12">
						<div class="left">
							<p>Copyright © {{date('Y')}} All Rights Reserved.</p>
						</div>
					</div>
					<div class="col-lg-6 col-12">
						<div class="right">
							<img src="{{asset('backend/img/payments.webp')}}" alt="#">
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</footer>
<!-- /End Footer Area -->

<!-- Jquery -->
<script src="{{asset('frontend/js/jquery.min.js')}}"></script>
<script src="{{asset('frontend/js/jquery-migrate-3.0.0.js')}}"></script>
<script src="{{asset('frontend/js/jquery-ui.min.js')}}"></script>
<!-- Popper JS -->
<script src="{{asset('frontend/js/popper.min.js')}}"></script>
<!-- Bootstrap JS -->
<script src="{{asset('frontend/js/bootstrap.min.js')}}"></script>
<!-- Color JS -->
<!-- <script src="{{asset('frontend/js/colors.js')}}"></script> -->
<!-- Slicknav JS -->
<script src="{{asset('frontend/js/slicknav.min.js')}}"></script>
<!-- Owl Carousel JS -->
<script src="{{asset('frontend/js/owl-carousel.js')}}"></script>
<!-- Magnific Popup JS -->
<script src="{{asset('frontend/js/magnific-popup.js')}}"></script>
<!-- Waypoints JS -->
<script src="{{asset('frontend/js/waypoints.min.js')}}"></script>
<!-- Countdown JS -->
<script src="{{asset('frontend/js/finalcountdown.min.js')}}"></script>
<!-- Nice Select JS -->
<script src="{{asset('frontend/js/nicesellect.js')}}"></script>
<!-- Flex Slider JS -->
<script src="{{asset('frontend/js/flex-slider.js')}}"></script>
<!-- ScrollUp JS -->
<script src="{{asset('frontend/js/scrollup.js')}}"></script>
<!-- Onepage Nav JS -->
<script src="{{asset('frontend/js/onepage-nav.min.js')}}"></script>
{{-- Isotope --}}
<script src="{{asset('frontend/js/isotope/isotope.pkgd.min.js')}}"></script>
<!-- Easing JS -->
<script src="{{asset('frontend/js/easing.js')}}"></script>

<!-- Active JS -->
<script src="{{asset('frontend/js/active.js')}}"></script>


@stack('scripts')
<script>
	// Enhanced Autocomplete with debouncing - Fixed Version
	$(document).ready(function() {
		// Event listeners

		let searchTimeout;
		const $searchInput = $('#search-input');
		const $dropdown = $('#autocomplete-dropdown');
		const $list = $('#autocomplete-list');

		$searchInput.on('input', function() {
			// alert('asdgas');
			const query = $(this).val().trim();
			console.log('Input changed:', query);
			debounceSearch(query);
		});
		// Debug: Check if elements exist
		console.log('Search input found:', $searchInput.length);
		console.log('Dropdown found:', $dropdown.length);
		console.log('List found:', $list.length);

		// Debounced search function
		function debounceSearch(query) {
			clearTimeout(searchTimeout);
			searchTimeout = setTimeout(() => {
				performAutocomplete(query);
			}, 300); // 300ms delay
		}

		// Perform autocomplete search
		function performAutocomplete(query) {
			console.log('Performing autocomplete for:', query);

			if (query.length < 2) {
				hideDropdown();
				return;
			}

			// Show loading state
			$list.html('<li class="loading">Searching...</li>');
			showDropdown();

			$.ajax({
				url: '{{ route("autocomplete") }}',
				method: 'GET',
				data: {
					q: query
				},
				dataType: 'json', // Ensure we expect JSON
				success: function(response) {
					console.log('Autocomplete response:', response);

					if (response.success && response.suggestions && response.suggestions.length > 0) {
						displaySuggestions(response.suggestions);
					} else {
						$list.html('<li class="no-results">No products found</li>');
						showDropdown();
					}
				},
				error: function(xhr, status, error) {
					console.error('Autocomplete error:', error);
					console.error('Response:', xhr.responseText);
					hideDropdown();
				}
			});
		}

		// Display suggestions
		function displaySuggestions(suggestions) {
			$list.empty();

			suggestions.forEach(function(item) {
				const discountPrice = item.price - (item.price * (item.discount || 0) / 100);
				const priceHTML = (item.discount && item.discount > 0) ?
					`<span class="price">$${discountPrice.toFixed(2)} <del>$${parseFloat(item.price).toFixed(2)}</del></span>` :
					`<span class="price">$${parseFloat(item.price).toFixed(2)}</span>`;

				const $item = $(`
                <li class="autocomplete-item" data-slug="${item.slug}">
                    <div class="item-content">
                        <span class="title">${item.title}</span>
                        ${priceHTML}
                    </div>
                </li>
            `);

				$list.append($item);
			});

			showDropdown();
		}

		// Show/hide dropdown
		function showDropdown() {
			$dropdown.show();
		}

		function hideDropdown() {
			$dropdown.hide();
		}


		// Handle item selection
		$(document).on('click', '.autocomplete-item', function() {
			const slug = $(this).data('slug');
			console.log('Item clicked:', slug);
			window.location.href = `{{ url('/product-detail') }}/${slug}`;
		});

		// Hide dropdown when clicking outside
		$(document).on('click', function(e) {
			if (!$(e.target).closest('.search-container').length) {
				hideDropdown();
			}
		});

		// Handle keyboard navigation
		let selectedIndex = -1;
		$searchInput.on('keydown', function(e) {
			const $items = $('.autocomplete-item');

			switch (e.keyCode) {
				case 40: // Down arrow
					e.preventDefault();
					selectedIndex = Math.min(selectedIndex + 1, $items.length - 1);
					updateSelection($items);
					break;

				case 38: // Up arrow
					e.preventDefault();
					selectedIndex = Math.max(selectedIndex - 1, -1);
					updateSelection($items);
					break;

				case 13: // Enter
					if (selectedIndex >= 0) {
						e.preventDefault();
						$items.eq(selectedIndex).click();
					}
					break;

				case 27: // Escape
					hideDropdown();
					selectedIndex = -1;
					break;
			}
		});

		function updateSelection($items) {
			$items.removeClass('selected');
			if (selectedIndex >= 0) {
				$items.eq(selectedIndex).addClass('selected');
			}
		}
	});

	setTimeout(function() {
		$('.alert').slideUp();
	}, 5000);

	$(function() {
		// ------------------------------------------------------- //
		// Multi Level dropdowns
		// ------------------------------------------------------ //
		$("ul.dropdown-menu [data-toggle='dropdown']").on("click", function(event) {
			event.preventDefault();
			event.stopPropagation();

			$(this).siblings().toggleClass("show");


			if (!$(this).next().hasClass('show')) {
				$(this).parents('.dropdown-menu').first().find('.show').removeClass("show");
			}
			$(this).parents('li.nav-item.dropdown.show').on('hidden.bs.dropdown', function(e) {
				$('.dropdown-submenu .show').removeClass("show");
			});

		});
	});
</script>