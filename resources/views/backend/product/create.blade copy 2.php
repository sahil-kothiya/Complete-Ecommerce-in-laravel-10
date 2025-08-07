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
				</div>

				{{-- Right Column --}}
				<div class="col-md-6">
					{{-- Discount --}}
					<div class="form-group">
						<label for="discount">Discount (%)</label>
						<input type="number" id="discount" name="discount" class="form-control" value="{{ old('discount') }}" min="0" max="100" placeholder="Enter discount">
						@error('discount')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Sizes --}}
					<div class="form-group">
						<label for="size">Size</label>
						@php $selectedSizes = old('size') ? old('size') : []; @endphp
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
						<select name="brand_id" class="form-control">
							<option value="">-- Select Brand --</option>
							@foreach($brands as $brand)
							<option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
							@endforeach
						</select>
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
							<div id="holder" class="img-fluid" style="display: none;">
								<!-- Images will be displayed here -->
							</div>
						</div>

						@error('photo')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Alt Text Toggle Checkbox --}}
					<div class="form-group form-check" id="alt-text-toggle" style="display: none;">
						<input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input" {{ old('enable_alt_text') ? 'checked' : '' }}>
						<label for="enable_alt_text" class="form-check-label">
							<i class="fa fa-image"></i> Enable Alt Text for Images
							<small class="text-muted d-block">Check this to add descriptive text for images (improves SEO & accessibility)</small>
						</label>
					</div>

				</div>
			</div>

			{{-- Alt Text Section (appears when images are selected and checkbox is checked) --}}
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

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css" />
<style>
	.table-responsive {
		overflow-x: auto;
		width: 100%;
	}

	/* Image preview styles */
	#image-preview-area {
		position: relative;
	}

	#holder {
		min-height: 140px;
		padding: 10px;
		border: 2px dashed #dee2e6;
		border-radius: 4px;
		background-color: #f8f9fa;
	}

	#holder:empty::before {
		content: "No images selected";
		display: block;
		text-align: center;
		color: #6c757d;
		padding: 50px 0;
		font-style: italic;
	}

	.image-container {
		display: inline-block;
		position: relative;
		margin: 5px;
	}

	.image-preview {
		height: 120px;
		width: auto;
		display: block;
		transition: all 0.3s ease;
		cursor: zoom-in;
		border-radius: 4px;
		box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
		position: relative;
		z-index: 1;
	}

	.image-preview:hover {
		transform: scale(1.8);
		z-index: 999;
		box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
		border: 2px solid #007bff;
	}

	/* Primary image indicator */
	.image-preview[data-is-primary="true"] {
		border: 3px solid #28a745;
	}

	.image-preview[data-is-primary="true"]:hover {
		border: 3px solid #007bff;
	}

	.primary-badge {
		position: absolute;
		top: -5px;
		right: -5px;
		z-index: 2;
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
		border-radius: 4px;
		color: #6c757d;
		font-size: 12px;
		text-align: center;
		padding: 10px;
	}

	.image-not-found i {
		font-size: 24px;
		margin-bottom: 5px;
		color: #adb5bd;
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

	function toggleAltTextSection() {
		const isEnabled = $('#enable_alt_text').is(':checked');
		const $altSection = $('#alt-text-section');

		console.log('Alt text toggle:', isEnabled);

		if (isEnabled) {
			// Show alt text section with animation and regenerate inputs
			$altSection.addClass('alt-text-section-show').show();
			updateImagePreview(); // Regenerate alt text inputs
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

		// Show alt text toggle after images are loaded
		$altToggle.addClass('show');

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

		// Show alt text section only if checkbox is enabled
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

		// FIXED: The key issue was here - the name attribute should be alt_text[] (array notation)
		// but each textarea needs a unique name to maintain proper ordering
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
                    <input type="hidden" name="alt_text_order[]" value="${index}">
                    <textarea name="alt_text[${index}]" class="form-control alt-text-input" 
                              placeholder="Describe this image (e.g., ${suggestedAlt})"
                              data-index="${index}">${suggestedAlt}</textarea>
                    <small class="form-text text-muted">
                        Good alt text: descriptive, concise (125 chars or less), includes product name
                    </small>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" data-index="${index}">
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

		if (index === 0) {
			autoAlt += ' - Main Product Image';
		} else {
			autoAlt += ` - Product Image ${index + 1}`;
		}

		// FIXED: Updated selector to match new name structure
		$(`textarea[name="alt_text[${index}]"]`).val(autoAlt);
		updateCharCount(index);
	});


	// Character count tracking for all alt text inputs
	$(document).on('input', 'textarea[name^="alt_text["]', function() {
		let index = $(this).data('index');
		updateCharCount(index);
	});

	$(document).ready(function() {
		console.log('Document ready');

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

		// Form submission handling
		$('form').on('submit', function(e) {
			$('#summary').val($('#summary').summernote('code'));
			$('#description').val($('#description').summernote('code'));

			// Only validate alt text if the checkbox is checked
			if ($('#enable_alt_text').is(':checked')) {
				// FIXED: Updated selector to match new name structure
				let altTexts = $('textarea[name^="alt_text["]');
				if (altTexts.length > 0) {
					let emptyAltTexts = altTexts.filter(function() {
						return $(this).val().trim() === '';
					});

					if (emptyAltTexts.length > 0) {
						e.preventDefault();
						alert('Please provide alt text for all images or uncheck "Enable Alt Text" option.');
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
				return;
			}

			$.ajax({
				url: `/admin/category/${catId}/child`,
				method: 'POST',
				data: {
					_token: '{{ csrf_token() }}'
				},
				success: function(response) {
					if (typeof response !== 'object') response = JSON.parse(response);
					let html = `<option value="">-- Select sub category --</option>`;
					if (response.status && response.data) {
						$('#child_cat_div').removeClass('d-none');
						$.each(response.data, function(id, title) {
							const selected = (id == '{{ old('
								child_cat_id ') }}') ? 'selected' : '';
							html += `<option value="${id}" ${selected}>${title}</option>`;
						});
					} else {
						$('#child_cat_div').addClass('d-none');
					}
					$('#child_cat_id').html(html);
				},
				error: function(xhr, status, error) {
					console.error('AJAX Error:', error);
					$('#child_cat_div').addClass('d-none');
				}
			});
		}

		// Load subcategories if category is already selected (for old input)
		const selectedCatId = $('#cat_id').val();
		if (selectedCatId) {
			loadSubCategories(selectedCatId);
		}
	});
</script>
@endpush