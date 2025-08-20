@extends('backend.layouts.master')

@section('title','E-SHOP || Banner Edit')

@section('main-content')

<div class="card">
	<h5 class="card-header">Edit Banner</h5>
	<div class="card-body">
		<form method="post" action="{{route('banner.update',$banner->id)}}">
			@csrf
			@method('PATCH')

			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="inputTitle" type="text" name="title"
							placeholder="Enter title"
							value="{{ old('title', $banner->title) }}"
							class="form-control" required>
						@error('title')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="description">Description</label>
						<textarea id="description" name="description" class="form-control">{{ old('description', $banner->description) }}</textarea>
						@error('description')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					{{-- Link type, discount, and redirect in one row --}}
					<div class="row">
						{{-- Link type --}}
						<div class="col-md-4">
							<div class="form-group">
								<label for="link_type" class="col-form-label">Link Type</label>
								<select name="link_type" id="link_type" class="form-control">
									<option value="">-- Select Link Type --</option>
									<option value="product" {{ old('link_type', $banner->link_type) == 'product' ? 'selected' : '' }}>Product</option>
									<option value="category" {{ old('link_type', $banner->link_type) == 'category' ? 'selected' : '' }}>Category</option>
									<option value="url" {{ old('link_type', $banner->link_type) == 'url' ? 'selected' : '' }}>URL</option>
									<option value="discount" {{ old('link_type', $banner->link_type) == 'discount' ? 'selected' : '' }}>Discount</option>
								</select>
								@error('link_type')
								<span class="text-danger">{{ $message }}</span>
								@enderror
							</div>
						</div>

						{{-- Discount field (only if link_type = discount) --}}
						<div class="col-md-8" id="discount-field"
							style="display: {{ old('link_type', $banner->link_type) == 'discount' ? 'block' : 'none' }};">
							<div class="form-group">
								<label for="discount_id" class="col-form-label">Discount</label>
								<select name="discount_id" class="form-control">
									<option value="">-- Select Discount --</option>
									@foreach($discounts as $discount)
									<option value="{{ $discount->id }}"
										{{ old('discount_id', $banner->discounts->first()->id ?? null) == $discount->id ? 'selected' : '' }}>
										{{ $discount->title }} -
										{{ $discount->type === 'percentage' ? $discount->value . '%' : '₹' . number_format($discount->value, 2) }}
									</option>
									@endforeach
								</select>
								@error('discount_id')
								<span class="text-danger">{{ $message }}</span>
								@enderror
							</div>
						</div>

						{{-- Redirect field (only if product/category/url) --}}
						<div class="col-md-8" id="link-field"
							style="display: {{ old('link_type', $banner->link_type) && old('link_type', $banner->link_type) != 'discount' ? 'block' : 'none' }};">
							<div class="form-group">
								<label for="link" class="col-form-label">Redirect URL / SKU</label>
								<input id="link" type="text" name="link"
									placeholder="e.g. /product/sku-123 OR /category/electronics OR https://example.com"
									value="{{ old('link', $banner->link) }}"
									class="form-control">
								@error('link')
								<span class="text-danger">{{ $message }}</span>
								@enderror
							</div>
						</div>
					</div>

					<div class="form-group mb-3">
						<button type="reset" class="btn btn-warning">Reset</button>
						<button type="submit" class="btn btn-success">Update</button>
					</div>
				</div>


				<div class="col-md-6">
					<div class="form-group">
						<label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
						<select name="status" class="form-control" required>
							<option value="active" {{ old('status', $banner->status) == 'active' ? 'selected' : '' }}>Active</option>
							<option value="inactive" {{ old('status', $banner->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
						</select>
						@error('status')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="inputPhoto" class="col-form-label">Photo <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-btn">
								<a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary">
									<i class="fa fa-picture-o"></i> Choose
								</a>
							</span>
							<input id="thumbnail" class="form-control" type="text" name="photo" value="{{old('photo', $banner->photo)}}" required readonly>
						</div>
						<small class="form-text text-muted">Select banner image.</small>
						@error('photo')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					{{-- Combined image preview area --}}
					<div id="image-preview-area" style="margin-top: 15px;">
						{{-- Existing image display --}}
						@if($banner->photo)
						<div id="existing-images">
							<label class="text-muted small">Current Banner Image:</label>
							<div id="existing-holder" class="img-fluid" style="margin-bottom: 10px;">
								<div class="image-container">
									<img src="{{ asset($banner->photo) }}"
										class="img-thumbnail image-preview"
										alt="{{ $banner->title }} - Banner Image"
										data-fallback-text="{{ $banner->title }} - Banner Image"
										onerror="handleImageError(this)">
								</div>
							</div>
						</div>
						@endif

						{{-- New image preview --}}
						<div id="new-images" style="display: none;">
							<label class="text-muted small">New Banner Image Preview:</label>
							<div id="holder" class="img-fluid"></div>
						</div>
					</div>
				</div>
			</div>
		</form>
	</div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{asset('backend/summernote/summernote.min.css')}}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.32/sweetalert2.min.css">
<style>
	.table-responsive {
		overflow-x: auto;
		width: 100%;
	}

	/* Image preview styles - Same as product edit page */
	#image-preview-area {
		position: relative;
	}

	#existing-holder,
	#holder {
		min-height: 140px;
		padding: 10px;
		display: flex;
		flex-wrap: wrap;
		gap: 10px;
	}

	.image-container {
		display: inline-block;
		position: relative;
		margin: 5px;
		border-radius: 8px;
		background: #fff;
		padding: 5px;
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		transition: all 0.3s ease;
	}

	.image-container:hover {
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
		transform: translateY(-2px);
	}

	.image-preview {
		height: 120px;
		width: 120px;
		object-fit: cover;
		display: block;
		transition: all 0.3s ease;
		cursor: zoom-in;
		border-radius: 6px;
		position: relative;
		z-index: 1;
	}

	.image-preview:hover {
		transform: scale(1.05);
	}

	/* Image not found styles */
	.image-not-found {
		height: 120px;
		width: 120px;
		background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
		border: 2px dashed #dee2e6;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-direction: column;
		border-radius: 6px;
		color: #6c757d;
		font-size: 11px;
		text-align: center;
		padding: 10px;
	}

	.image-not-found i {
		font-size: 24px;
		margin-bottom: 5px;
		color: #adb5bd;
	}

	/* Responsive adjustments */
	@media (max-width: 768px) {
		.image-container {
			margin: 3px;
		}

		.image-preview {
			height: 100px;
			width: 100px;
		}

		.image-not-found {
			height: 100px;
			width: 100px;
			font-size: 10px;
		}
	}

	.image-container * {
		box-sizing: border-box;
	}

	/* Zoom effect on click */
	.image-preview.zoomed {
		position: fixed;
		top: 50%;
		left: 50%;
		transform: translate(-50%, -50%) scale(2);
		z-index: 9999;
		border-radius: 8px;
		box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
		cursor: zoom-out;
	}

	/* Overlay for zoomed image */
	.zoom-overlay {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0, 0, 0, 0.8);
		z-index: 9998;
		opacity: 0;
		transition: opacity 0.3s ease;
	}

	.zoom-overlay.show {
		opacity: 1;
	}

	/* SweetAlert2 custom styles */
	.swal2-popup {
		border-radius: 10px;
		font-family: inherit;
	}

	.swal2-title {
		font-size: 1.5rem;
		font-weight: 600;
	}

	.swal2-content {
		font-size: 1rem;
	}

	/* Animation for showing preview */
	.preview-show {
		animation: slideDown 0.3s ease-out;
	}

	@keyframes slideDown {
		from {
			opacity: 0;
			max-height: 0;
			transform: translateY(-10px);
		}

		to {
			opacity: 1;
			max-height: 200px;
			transform: translateY(0);
		}
	}

	/* Smooth transitions for showing/hiding fields */
	#discount-field,
	#link-field {
		transition: all 0.3s ease;
	}
</style>
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.32/sweetalert2.all.min.js"></script>
<script>
	// File manager initialization
	$('#lfm').filemanager('image');

	// Global image error handling function
	function handleImageError(img) {
		const fallbackText = img.getAttribute('data-fallback-text') || 'Image not available';
		const container = img.parentElement;

		// Create fallback element
		const fallback = document.createElement('div');
		fallback.className = 'image-not-found';
		fallback.innerHTML = `
			<i class="fa fa-image"></i>
			<span>${fallbackText}</span>
		`;

		// Replace image with fallback
		container.insertBefore(fallback, img);
		img.style.display = 'none';

		console.warn('Image failed to load:', img.src);
	}

	// Function to show SweetAlert notifications
	function showNotification(message, type = 'info') {
		const config = {
			title: type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : type === 'warning' ? 'Warning!' : 'Info!',
			text: message,
			icon: type === 'success' ? 'success' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info',
			confirmButtonText: 'OK',
			confirmButtonColor: type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8',
			timer: type === 'success' ? 3000 : null,
			timerProgressBar: type === 'success',
			showCloseButton: true,
			focusConfirm: false
		};

		Swal.fire(config);
	}

	// Function to toggle discount/link fields based on link type
	function toggleLinkFields() {
		const linkType = $('#link_type').val();
		const $discountField = $('#discount-field');
		const $linkField = $('#link-field');

		if (linkType === 'discount') {
			$discountField.slideDown(300);
			$linkField.slideUp(300);
			// Clear link field when discount is selected
			$('#link').val('');
		} else if (linkType === '') {
			$discountField.slideUp(300);
			$linkField.slideUp(300);
			// Clear both fields when no link type is selected
			$('#link').val('');
			$('select[name="discount_id"]').val('');
		} else {
			$discountField.slideUp(300);
			$linkField.slideDown(300);
			// Clear discount field when other link types are selected
			$('select[name="discount_id"]').val('');
		}
	}

	// Function to update image preview
	function updateImagePreview() {
		console.log('updateImagePreview called');
		let imageInput = $('#thumbnail').val().trim();
		let $holder = $('#holder');
		let $newImagesDiv = $('#new-images');

		// Clear previous new image preview
		$holder.empty();

		if (!imageInput) {
			console.log('No image input found');
			$newImagesDiv.hide();
			return;
		}

		// Check if this is the same as existing image
		let existingImageSrc = $('#existing-holder .image-preview').attr('src');
		if (existingImageSrc && existingImageSrc.includes(imageInput.replace(/^\/+/, ''))) {
			console.log('Same as existing image, hiding new preview');
			$newImagesDiv.hide();
			return;
		}

		console.log('New banner image found:', imageInput);

		// Show new image section below current image with animation
		$newImagesDiv.addClass('preview-show').show();

		// Create image container with same structure as existing
		let container = $('<div class="image-container"></div>');

		// Create image preview with same classes as existing
		let img = $('<img />', {
			src: imageInput,
			class: 'img-thumbnail image-preview',
			alt: 'New Banner Image',
			'data-fallback-text': 'New Banner Image'
		});

		// Add error handling for broken images
		img.on('error', function() {
			handleImageError(this);
		});

		// Add zoom functionality
		img.on('click', function(e) {
			e.preventDefault();
			zoomImage(this);
		});

		container.append(img);
		$holder.append(container);

		console.log('New banner image preview created');
	}

	// Zoom functionality for images
	function zoomImage(img) {
		// Create overlay
		const $overlay = $('<div class="zoom-overlay"></div>');
		$('body').append($overlay);

		// Clone image for zoom effect
		const $zoomedImg = $(img).clone();
		$zoomedImg.removeClass('img-thumbnail').addClass('zoomed');
		$('body').append($zoomedImg);

		// Show overlay
		setTimeout(() => {
			$overlay.addClass('show');
		}, 10);

		// Close zoom on click
		$overlay.add($zoomedImg).on('click', function() {
			$overlay.removeClass('show');
			$zoomedImg.removeClass('zoomed');
			setTimeout(() => {
				$overlay.remove();
				$zoomedImg.remove();
			}, 300);
		});

		// Close on Escape key
		$(document).on('keyup.zoom', function(e) {
			if (e.keyCode === 27) { // Escape key
				$overlay.click();
				$(document).off('keyup.zoom');
			}
		});
	}

	// Document ready function
	$(document).ready(function() {
		console.log('Document ready - Banner Edit Form');

		// Initialize Summernote
		$('#description').summernote({
			placeholder: "Write short description.....",
			tabsize: 2,
			height: 150
		});

		// Initialize field visibility based on current link type value
		toggleLinkFields();

		// Handle link type change
		$('#link_type').on('change', function() {
			toggleLinkFields();
		});

		// Add zoom functionality to existing image
		$(document).on('click', '.image-preview', function(e) {
			e.preventDefault();
			zoomImage(this);
		});

		// Monitor changes to the thumbnail input
		$('#thumbnail').on('input change', function() {
			console.log('Thumbnail input changed');
			updateImagePreview();
		});

		// Trigger update when file manager button is clicked
		$('#lfm').on('click', function() {
			console.log('File manager button clicked');
			let checkCount = 0;
			let originalValue = $('#thumbnail').val();
			let checkInterval = setInterval(function() {
				checkCount++;
				let currentValue = $('#thumbnail').val();
				if (currentValue && currentValue !== originalValue) {
					console.log('Value changed:', currentValue);
					updateImagePreview();
					clearInterval(checkInterval);
				} else if (checkCount > 20) {
					clearInterval(checkInterval);
				}
			}, 500);
		});

		// Reset functionality - clear new image preview and reset field visibility when form is reset
		$('button[type="reset"]').on('click', function(e) {
			e.preventDefault();

			Swal.fire({
				title: 'Reset Form?',
				text: 'Are you sure you want to reset all form data to original values?',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#dc3545',
				cancelButtonColor: '#6c757d',
				confirmButtonText: 'Yes, Reset',
				cancelButtonText: 'Cancel'
			}).then((result) => {
				if (result.isConfirmed) {
					// Reset the form to original values
					$('#inputTitle').val('{{$banner->title}}');
					$('#link_type').val('{{$banner->link_type}}');
					$('#link').val('{{$banner->link}}');
					$('select[name="discount_id"]').val('{{$banner->discounts->first()->id ?? ""}}');
					$('select[name="status"]').val('{{$banner->status}}');
					$('#thumbnail').val('{{$banner->photo}}');

					// Clear Summernote content and reset to original
					$('#description').summernote('code', '{!! addslashes($banner->description) !!}');

					// Clear new image preview and reset field visibility
					setTimeout(function() {
						$('#new-images').hide();
						$('#holder').empty();
						toggleLinkFields(); // Reset field visibility
					}, 100);

					// Show success message
					showNotification('Form has been reset to original values!', 'success');
				}
			});
		});

		// Form submission handling
		$('form').on('submit', function(e) {
			$('#description').val($('#description').summernote('code'));

			// Updated validation for link and link_type
			let link = $.trim($('input[name="link"]').val());
			let linkType = $.trim($('select[name="link_type"]').val());
			let discountId = $.trim($('select[name="discount_id"]').val());

			// Validation for non-discount link types
			if (linkType !== 'discount' && linkType !== '' && link === '') {
				e.preventDefault();
				showNotification('Please provide a Link/URL when selecting ' + linkType + ' link type.', 'error');
				$('input[name="link"]').focus();
				return;
			}

			// Validation for discount link type
			if (linkType === 'discount' && discountId === '') {
				e.preventDefault();
				showNotification('Please select a Discount when choosing Discount link type.', 'error');
				$('select[name="discount_id"]').focus();
				return;
			}

			// Validation for providing link without link type
			if (link !== '' && linkType === '') {
				e.preventDefault();
				showNotification('Please select Link Type when providing a Link.', 'error');
				$('select[name="link_type"]').focus();
				return;
			}

			// If all validations pass, form will submit normally
		});
	});
</script>
@endpush