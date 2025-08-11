@extends('backend.layouts.master')

@section('main-content')

<div class="card">
	<h5 class="card-header">Add Product</h5>
	<div class="card-body">
		<form method="POST" action="{{ route('product.store') }}">
			@csrf
			<div class="row">

				{{-- Left Column --}}
				<div class="col-md-6">
					{{-- Title --}}
					<div class="form-group">
						<label for="inputTitle">Title <span class="text-danger">*</span></label>
						<input type="text" id="inputTitle" name="title" value="{{ old('title') }}" class="form-control" placeholder="Enter title">
						@error('title')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Summary --}}
					<div class="form-group">
						<label for="summary">Summary <span class="text-danger">*</span></label>
						<textarea id="summary" name="summary" class="form-control">{{ old('summary') }}</textarea>
						@error('summary')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Description --}}
					<div class="form-group">
						<label for="description">Description</label>
						<textarea id="description" name="description" class="form-control">{{ old('description') }}</textarea>
						@error('description')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Is Featured --}}
					<div class="form-group form-check">
						<input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured') ? 'checked' : '' }}>
						<label for="is_featured" class="form-check-label">Is Featured</label>
					</div>

					{{-- Category --}}
					<div class="form-group">
						<label for="cat_id">Category <span class="text-danger">*</span></label>
						<select name="cat_id" id="cat_id" class="form-control">
							<option value="">-- Select any category --</option>
							@foreach($categories as $cat)
							<option value="{{ $cat->id }}" {{ old('cat_id') == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
							@endforeach
						</select>
						@error('cat_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Sub Category --}}
					<div class="form-group d-none" id="child_cat_div">
						<label for="child_cat_id">Sub Category</label>
						<select name="child_cat_id" id="child_cat_id" class="form-control">
							<option value="">-- Select sub category --</option>
						</select>
						@error('child_cat_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Price --}}
					<div class="form-group">
						<label for="price">Price (NRS) <span class="text-danger">*</span></label>
						<input type="number" id="price" name="price" class="form-control" value="{{ old('price') }}" placeholder="Enter price">
						@error('price')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Discount --}}
					<div class="form-group">
						<label for="discount">Discount (%)</label>
						<input type="number" id="discount" name="discount" class="form-control" value="{{ old('discount') }}" min="0" max="100" placeholder="Enter discount">
						@error('discount')<span class="text-danger">{{ $message }}</span>@enderror
					</div>
				</div>

				{{-- Right Column --}}
				<div class="col-md-6">

					{{-- Sizes --}}
					<div class="form-group">
						<label for="size">Size</label>
						@php $selectedSizes = old('size', []); @endphp
						<select name="size[]" class="form-control selectpicker" multiple data-live-search="true">
							@foreach(['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
							<option value="{{ $key }}" {{ in_array($key, $selectedSizes) ? 'selected' : '' }}>{{ $label }}</option>
							@endforeach
						</select>
						@error('size')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Brand --}}
					<div class="form-group">
						<label for="brand_id">Brand</label>
						<div class="input-group">
							<select name="brand_id" id="brand_id" class="form-control">
								<option value="">-- Select Brand --</option>
								@foreach($brands as $brand)
								<option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
								@endforeach
							</select>
							<div class="input-group-append">
								<button type="button" id="addBrandBtn" class="btn btn-outline-primary">
									<i class="fa fa-plus"></i>
								</button>
							</div>
						</div>
						@error('brand_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Condition --}}
					<div class="form-group">
						<label for="condition">Condition</label>
						<select name="condition" class="form-control">
							<option value="">-- Select Condition --</option>
							@foreach(['default' => 'Default', 'new' => 'New', 'hot' => 'Hot'] as $value => $label)
							<option value="{{ $value }}" {{ old('condition') === $value ? 'selected' : '' }}>{{ $label }}</option>
							@endforeach
						</select>
						@error('condition')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Quantity --}}
					<div class="form-group">
						<label for="stock">Quantity <span class="text-danger">*</span></label>
						<input type="number" id="stock" name="stock" class="form-control" value="{{ old('stock') }}" min="0" placeholder="Enter quantity">
						@error('stock')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Status --}}
					<div class="form-group">
						<label for="status">Status <span class="text-danger">*</span></label>
						<select name="status" class="form-control">
							<option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
							<option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
						</select>
						@error('status')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Photos --}}
					<div class="form-group">
						<label for="inputPhoto">Photos <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-btn">
								<a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary">
									<i class="fa fa-picture-o"></i> Choose
								</a>
							</span>
							<input type="text" id="thumbnail" name="photo" class="form-control" placeholder="Comma-separated image URLs" value="{{ old('photo') }}" readonly required>
						</div>
						<small class="form-text text-muted">Select multiple images. They will be comma-separated.</small>

						{{-- Image preview area --}}
						<div id="image-preview-area" style="margin-top: 15px;">
							<div id="holder" class="img-fluid"></div>
						</div>

						@error('photo')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Alt Text Toggle Checkbox --}}
					<div class="form-group form-check" id="alt-text-toggle" style="display: none;">
						<input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input">
						<label for="enable_alt_text" class="form-check-label">
							<i class="fa fa-image"></i> Enable Alt Text for Images
							<small class="text-muted d-block">Check this to add descriptive text for images (improves SEO & accessibility)</small>
						</label>
					</div>

				</div>
			</div>

			{{-- Alt Text Section --}}
			<div id="alt-text-section" class="row" style="display: none;">
				<div class="col-12">
					<hr>
					<h6 class="text-primary">
						<i class="fa fa-image"></i> Image Alt Text Configuration
					</h6>
					<p class="text-muted small">Add descriptive alt text for images to improve SEO and accessibility.</p>
					<div id="alt-text-container" class="row">
						<!-- Alt text inputs will be generated dynamically here -->
					</div>
				</div>
			</div>

			{{-- Submit --}}
			<div class="form-group mt-3">
				<button type="submit" class="btn btn-success">Add Product</button>
			</div>
		</form>
	</div>
</div>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1" role="dialog" aria-labelledby="addBrandModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<form id="addBrandForm">
			@csrf
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="addBrandModalLabel">Add New Brand</h5>
					<button type="button" class="close" data-dismiss="modal">
						<span>&times;</span>
					</button>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for="new_brand_title">Brand Name</label>
						<input type="text" name="title" id="new_brand_title" class="form-control" required>
					</div>
				</div>
				<div class="modal-footer">
					<button type="submit" class="btn btn-success">Save</button>
					<button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
				</div>
			</div>
		</form>
	</div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css" />
<style>
	.table-responsive {
		overflow-x: auto;
		width: 100%;
	}

	/* Image preview styles - Fixed delete button positioning */
	#image-preview-area {
		position: relative;
	}

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

	/* Primary image indicator */
	.image-preview[data-is-primary="true"] {
		border: 3px solid #28a745;
	}

	.primary-badge {
		position: absolute;
		top: 0px;
		left: 0px;
		z-index: 5;
	}

	.primary-badge .badge {
		font-size: 10px;
		padding: 3px 6px;
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

	/* Additional fixes for image container */
	.image-container * {
		box-sizing: border-box;
	}

	/* Alt text section styles */
	.alt-text-item {
		margin-bottom: 20px;
		padding: 15px;
		border: 1px solid #e9ecef;
		border-radius: 5px;
		background-color: #f8f9fa;
		transition: all 0.3s ease;
	}

	.alt-text-item:hover {
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
	}

	.alt-text-preview {
		width: 80px;
		height: 80px;
		object-fit: cover;
		border-radius: 4px;
		border: 2px solid #dee2e6;
	}

	.alt-text-image-container {
		position: relative;
	}

	.alt-text-preview.image-error {
		display: none;
	}

	.alt-text-fallback {
		width: 80px;
		height: 80px;
		background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
		border: 2px dashed #dee2e6;
		display: flex;
		align-items: center;
		justify-content: center;
		border-radius: 4px;
		color: #6c757d;
		font-size: 10px;
		text-align: center;
		padding: 5px;
		margin-right: 1rem;
	}

	.alt-text-fallback i {
		display: block;
		font-size: 16px;
		margin-bottom: 2px;
		color: #adb5bd;
	}

	.alt-text-input {
		resize: vertical;
		min-height: 60px;
	}

	.image-counter {
		background: #007bff;
		color: white;
		border-radius: 50%;
		width: 25px;
		height: 25px;
		display: inline-flex;
		align-items: center;
		justify-content: center;
		font-size: 12px;
		font-weight: bold;
		margin-right: 10px;
	}

	.primary-counter {
		background: #28a745;
	}

	.badge-sm {
		font-size: 0.75em;
	}

	/* Alt text toggle styling */
	#enable_alt_text+label {
		cursor: pointer;
		padding: 8px 12px;
		border: 1px solid #dee2e6;
		border-radius: 4px;
		background-color: #f8f9fa;
		transition: all 0.3s ease;
	}

	#enable_alt_text:checked+label {
		background-color: #e7f3ff;
		border-color: #007bff;
	}

	/* Animation for alt text sections */
	.alt-text-section-show {
		animation: slideDown 0.3s ease-out;
	}

	.alt-text-section-hide {
		animation: slideUp 0.3s ease-out;
	}

	@keyframes slideDown {
		from {
			opacity: 0;
			max-height: 0;
			transform: translateY(-10px);
		}

		to {
			opacity: 1;
			max-height: 1000px;
			transform: translateY(0);
		}
	}

	@keyframes slideUp {
		from {
			opacity: 1;
			max-height: 1000px;
			transform: translateY(0);
		}

		to {
			opacity: 0;
			max-height: 0;
			transform: translateY(-10px);
		}
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

	/* Show alt text toggle only when images are selected */
	#alt-text-toggle.show {
		display: block !important;
		animation: fadeIn 0.3s ease-in;
	}

	@keyframes fadeIn {
		from {
			opacity: 0;
		}

		to {
			opacity: 1;
		}
	}
</style>
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>

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

	// Alt text preview image error handling
	function handleAltTextImageError(img, fallbackText) {
		const container = img.parentElement;

		// Create fallback element
		const fallback = document.createElement('div');
		fallback.className = 'alt-text-fallback';
		fallback.innerHTML = `
			<i class="fa fa-image"></i>
			<span>${fallbackText}</span>
		`;

		// Replace image with fallback
		container.appendChild(fallback);
		img.classList.add('image-error');
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

	function toggleAltTextSection() {
		const isEnabled = $('#enable_alt_text').is(':checked');
		const $altSection = $('#alt-text-section');

		console.log('Alt text toggle:', isEnabled);

		if (isEnabled) {
			// Show alt text section with animation and regenerate inputs
			$altSection.addClass('alt-text-section-show').show();
			updateImagePreview();
		} else {
			// Hide alt text section with animation
			$altSection.addClass('alt-text-section-hide');

			setTimeout(function() {
				$altSection.hide().removeClass('alt-text-section-hide alt-text-section-show');
			}, 300);
		}
	}

	function updateImagePreview() {
		console.log('updateImagePreview called');
		let imageInput = $('#thumbnail').val().trim();
		let $holder = $('#holder');
		let $altToggle = $('#alt-text-toggle');
		let $altSection = $('#alt-text-section');
		let $altContainer = $('#alt-text-container');

		// Clear previous content
		$holder.empty();
		$altContainer.empty();

		if (!imageInput) {
			console.log('No image input found');
			$holder.hide();
			$altToggle.removeClass('show').hide();
			$altSection.hide();
			return;
		}

		let images = imageInput.split(',');
		console.log('Images found:', images);

		// Show holder and images
		$holder.show();
		// Show alt text toggle only when images are present
		$altToggle.addClass('show').show();

		images.forEach(function(url, index) {
			url = url.trim();
			if (url) {
				// Create image container
				let container = $('<div class="image-container"></div>');

				// Create image preview
				let img = $('<img />', {
					src: url,
					class: 'img-thumbnail image-preview',
					alt: 'Product Image ' + (index + 1),
					'data-index': index,
					'data-is-primary': index === 0 ? 'true' : 'false',
					'data-fallback-text': 'Product Image ' + (index + 1)
				});

				// Add primary badge for first image
				if (index === 0) {
					let badge = $('<div class="primary-badge"><small class="badge badge-success">Primary</small></div>');
					container.append(badge);
				}

				// Add error handling for broken images
				img.on('error', function() {
					handleImageError(this);
				});

				container.append(img);
				$holder.append(container);

				// Create alt text input for each image only if enabled
				if ($('#enable_alt_text').is(':checked')) {
					createAltTextInput(url, index, $altContainer);
				}
			}
		});

		// Show alt text section only if checkbox is enabled and images are present
		if ($('#enable_alt_text').is(':checked')) {
			$altSection.addClass('alt-text-section-show').show();
		}

		console.log('Images and alt text inputs created');
	}

	function createAltTextInput(imageUrl, index, container) {
		// Get product title for auto-suggestion
		let productTitle = $('#inputTitle').val() || 'Product';
		let suggestedAlt = `${productTitle} - ${index === 0 ? 'Main Image' : 'Image ' + (index + 1)}`;
		let isPrimary = index === 0;
		let counterClass = isPrimary ? 'image-counter primary-counter' : 'image-counter';
		let primaryBadge = isPrimary ? '<span class="badge badge-success badge-sm ml-1">Primary</span>' : '';

		let altTextHtml = `
			<div class="col-md-6 alt-text-item" data-index="${index}">
				<div class="d-flex align-items-start">
					<div class="${counterClass}">${index + 1}</div>
					<div class="alt-text-image-container">
						<img src="${imageUrl}" class="alt-text-preview mr-3" alt="Preview" 
							 onerror="handleAltTextImageError(this, '${suggestedAlt}')">
					</div>
					<div class="flex-fill">
						<label class="font-weight-bold mb-2">
							Alt Text for Image ${index + 1}:${primaryBadge}
						</label>
						<textarea name="alt_text[${index}]" class="form-control alt-text-input" 
								  placeholder="Describe this image (e.g., ${suggestedAlt})"
								  data-index="${index}">${suggestedAlt}</textarea>
						<small class="form-text text-muted">
							Good alt text: descriptive, concise (125 chars or less), includes product name
						</small>
						<div class="mt-2">
							<button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" data-index="${index}" data-type="new">
								<i class="fa fa-magic"></i> Auto-generate
							</button>
							<span class="ml-2 text-muted char-count">0/125 characters</span>
						</div>
					</div>
				</div>
			</div>
		`;

		container.append(altTextHtml);
		updateCharCount(index);
	}

	function updateCharCount(index) {
		let $textarea = $(`textarea[data-index="${index}"]`);
		let $charCount = $textarea.closest('.alt-text-item').find('.char-count');
		let length = $textarea.val().length;
		$charCount.text(`${length}/125 characters`);

		if (length > 125) {
			$charCount.addClass('text-danger').removeClass('text-muted');
		} else {
			$charCount.addClass('text-muted').removeClass('text-danger');
		}
	}

	$(document).ready(function() {
		console.log('Document ready - Product Create Form');

		// Initialize Summernote
		$('#summary').summernote({
			tabsize: 2,
			height: 150
		});
		$('#description').summernote({
			tabsize: 2,
			height: 150
		});

		// Initialize image preview if old data exists
		if ($('#thumbnail').val()) {
			updateImagePreview();
		}

		// Alt text checkbox toggle handler
		$('#enable_alt_text').on('change', function() {
			toggleAltTextSection();
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

		// Auto-generate alt text
		$(document).on('click', '.auto-generate-alt', function() {
			let index = $(this).data('index');
			let productTitle = $('#inputTitle').val() || 'Product';
			let category = $('#cat_id option:selected').text();
			let brand = $('#brand_id option:selected').text();

			let autoAlt = productTitle;
			if (brand && brand !== '-- Select Brand --') autoAlt += ` by ${brand}`;
			if (category && category !== '-- Select any category --') autoAlt += ` - ${category}`;
			autoAlt += index === 0 ? ' - Main Product Image' : ` - Product Image ${index + 1}`;

			$(`textarea[name="alt_text[${index}]"]`).val(autoAlt);
			updateCharCount(index);
		});

		// Character count tracking for all alt text inputs
		$(document).on('input', 'textarea.alt-text-input', function() {
			let index = $(this).data('index');
			updateCharCount(index);
		});

		// Form submission handling
		$('form').on('submit', function(e) {

			// Validate discount before submission
			let discountValue = parseFloat($('#discount').val());
			if (!isNaN(discountValue) && (discountValue < 0 || discountValue > 100)) {
				e.preventDefault();
				$('#discount').focus();
				showNotification('Please enter a valid discount between 0 and 100%.', 'error');
				return false;
			}

			$('#summary').val($('#summary').summernote('code'));
			$('#description').val($('#description').summernote('code'));

			// Only validate alt text if the checkbox is checked
			if ($('#enable_alt_text').is(':checked')) {
				let altTexts = $('textarea[name^="alt_text["]');
				if (altTexts.length > 0) {
					let emptyAltTexts = altTexts.filter(function() {
						return $(this).val().trim() === '';
					});

					if (emptyAltTexts.length > 0) {
						e.preventDefault();
						showNotification('Please provide alt text for all images or uncheck "Enable Alt Text" option.', 'error');
						emptyAltTexts.first().focus();
						return false;
					}
				}
			}
		});

		// Sub-category handling
		$('#cat_id').change(function() {
			const catId = $(this).val();
			loadSubCategories(catId);
		});

		function loadSubCategories(catId) {
			if (!catId) {
				$('#child_cat_div').addClass('d-none');
				$('#child_cat_id').html('<option value="">-- Select sub category --</option>');
				return;
			}

			$.ajax({
				url: '/admin/category/' + catId + '/child',
				type: 'GET',
				dataType: 'json',
				success: function(response) {
					if (response.status && response.data.length > 0) {
						let options = '<option value="">-- Select sub category --</option>';
						response.data.forEach(function(child) {
							options += `<option value="${child.id}">${child.title}</option>`;
						});
						$('#child_cat_id').html(options);
						$('#child_cat_div').removeClass('d-none');
					} else {
						$('#child_cat_div').addClass('d-none');
					}
				},
				error: function() {
					$('#child_cat_div').addClass('d-none');
				}
			});
		}

		// Load subcategories if category is already selected (for old input)
		const selectedCatId = $('#cat_id').val();
		if (selectedCatId) {
			loadSubCategories(selectedCatId);
		}

		// Show Add Brand Modal
		$('#addBrandBtn').click(function() {
			$('#new_brand_title').val('');
			$('#addBrandModal').modal('show');
		});

		// Handle Brand Form Submission
		$('#addBrandForm').submit(function(e) {
			e.preventDefault();
			let brandName = $('#new_brand_title').val().trim();
			if (!brandName) return;

			$.ajax({
				url: "{{ route('brand.store.ajax') }}", // We'll create this route
				type: "POST",
				data: {
					_token: "{{ csrf_token() }}",
					title: brandName
				},
				success: function(res) {
					if (res.status === 'success') {
						// Add new brand to dropdown and select it
						$('#brand_id').append(`<option value="${res.data.id}" selected>${res.data.title}</option>`);
						$('#brand_id').selectpicker('refresh'); // if using bootstrap-select
						$('#addBrandModal').modal('hide');
						showNotification('Brand added successfully!', 'success');
					} else {
						showNotification(res.message || 'Error adding brand', 'error');
					}
				},
				error: function() {
					showNotification('Something went wrong while adding brand.', 'error');
				}
			});
		});

		// Discount validation
		$('#discount').on('input blur', function() {
			let discountValue = parseFloat($(this).val());
			let $discountField = $(this);
			let $errorSpan = $discountField.next('.discount-error');

			// Remove existing error span if it exists
			if ($errorSpan.length === 0) {
				$discountField.after('<span class="text-danger discount-error" style="font-size: 0.875rem;"></span>');
				$errorSpan = $discountField.next('.discount-error');
			}

			// Clear previous error styling
			$discountField.removeClass('is-invalid');
			$errorSpan.text('').hide();

			if (isNaN(discountValue) || discountValue === '') {
				return; // Allow empty values
			}

			// Validate discount range
			if (discountValue < 0) {
				$discountField.addClass('is-invalid').val(0);
				$errorSpan.text('Discount cannot be negative. Set to 0.').show();
				showNotification('Discount cannot be negative.', 'error');
			} else if (discountValue > 100) {
				$discountField.addClass('is-invalid').val(100);
				$errorSpan.text('Discount cannot exceed 100%. Set to 100.').show();
				showNotification('Discount cannot exceed 100%.', 'error');
			}
		});

		// Prevent typing non-numeric characters and handle edge cases
		$('#discount').on('keypress', function(e) {
			// Allow: backspace, delete, tab, escape, enter, decimal point
			if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
				// Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
				(e.keyCode === 65 && e.ctrlKey === true) ||
				(e.keyCode === 67 && e.ctrlKey === true) ||
				(e.keyCode === 86 && e.ctrlKey === true) ||
				(e.keyCode === 88 && e.ctrlKey === true) ||
				// Allow: home, end, left, right
				(e.keyCode >= 35 && e.keyCode <= 39)) {
				return;
			}
			// Ensure that it is a number and stop the keypress
			if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
				e.preventDefault();
			}
		});
		
	});
</script>
@endpush