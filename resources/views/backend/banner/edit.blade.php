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
						<input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{old('title', $banner->title)}}" class="form-control" required>
						@error('title')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="description">Description</label>
						<textarea id="description" name="description" class="form-control">{{ old('description', $banner->description) }}</textarea>
						@error('description')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					<div class="form-group">
						<label for="link_type" class="col-form-label">Link Type </label>
						<select name="link_type" class="form-control">
							<option value="">-- Select Link Type --</option>
							<option value="product" {{ old('link_type', $banner->link_type) == 'product' ? 'selected' : '' }}> Product </option>
							<option value="category" {{ old('link_type', $banner->link_type) == 'category' ? 'selected' : '' }}> Category </option>
							<option value="url" {{ old('link_type', $banner->link_type) == 'url' ? 'selected' : '' }}> URL </option>
						</select>
						@error('link_type')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="link" class="col-form-label">Redirect URL / SKU </label>
						<input id="link" type="text" name="link" placeholder="e.g. /product/sku-123 OR /category/electronics OR https://example.com" value="{{ old('link', $banner->link) }}" class="form-control">
						@error('link')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group mb-3">
						<button type="reset" class="btn btn-warning">Reset</button>
						<button class="btn btn-success" type="submit">Update</button>
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
						<label for="discount_id" class="col-form-label">Discount </label>
						<select name="discount_id" class="form-control">
							<option value="">-- Select Discount --</option>
							@foreach($discounts as $discount)
							<option value="{{ $discount->id }}" {{ old('discount_id', $banner->discounts->first()->id ?? null) == $discount->id ? 'selected' : '' }}>
								{{ $discount->title }} -
								{{ $discount->type === 'percentage' ? $discount->value . '%' : '₹' . number_format($discount->value, 2) }}
							</option>
							@endforeach
						</select>
						@error('discount_id')
						<span class="text-danger">{{ $message }}</span>
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

	/* Notification styles */
	.notification-toast {
		border-radius: 6px;
		box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
	}

	.notification-toast .close {
		color: inherit;
		opacity: 0.8;
	}

	.notification-toast .close:hover {
		opacity: 1;
	}
</style>
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
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

	// Function to show notification messages
	function showNotification(message, type = 'info') {
		// Remove existing notifications
		$('.notification-toast').remove();

		// Create notification element
		const notificationClass = type === 'success' ? 'alert-success' :
			type === 'error' ? 'alert-danger' : 'alert-info';

		const $notification = $(`
			<div class="alert ${notificationClass} notification-toast alert-dismissible" 
				 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
				<strong>${type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Info!'}</strong> ${message}
			</div>
		`);

		// Add to page
		$('body').append($notification);

		// Auto-hide after 4 seconds
		setTimeout(function() {
			$notification.fadeOut(400, function() {
				$(this).remove();
			});
		}, 4000);
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

		// Show new image section below current image
		$newImagesDiv.show();

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

		// Reset functionality - clear new image preview when form is reset
		$('button[type="reset"]').on('click', function() {
			setTimeout(function() {
				$('#new-images').hide();
				$('#holder').empty();
			}, 100);
		});

		// Form submission handling
		$('form').on('submit', function(e) {
			$('#description').val($('#description').summernote('code'));
		});
	});
</script>
@endpush