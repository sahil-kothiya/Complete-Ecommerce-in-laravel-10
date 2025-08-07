@extends('backend.layouts.master')

@section('main-content')

<div class="card">
	<h5 class="card-header">Edit Product</h5>
	<div class="card-body">
		<form method="POST" action="{{ route('product.update', $product->id) }}">
			@csrf
			@method('PATCH')
			<div class="row">

				{{-- Left Column --}}
				<div class="col-md-6">
					{{-- Title --}}
					<div class="form-group">
						<label for="inputTitle">Title <span class="text-danger">*</span></label>
						<input type="text" id="inputTitle" name="title" value="{{ old('title', $product->title) }}" class="form-control" placeholder="Enter title">
						@error('title')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Summary --}}
					<div class="form-group">
						<label for="summary">Summary <span class="text-danger">*</span></label>
						<textarea id="summary" name="summary" class="form-control">{{ old('summary', $product->summary) }}</textarea>
						@error('summary')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Description --}}
					<div class="form-group">
						<label for="description">Description</label>
						<textarea id="description" name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
						@error('description')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Is Featured --}}
					<div class="form-group form-check">
						<input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
						<label for="is_featured" class="form-check-label">Is Featured</label>
					</div>

					{{-- Category --}}
					<div class="form-group">
						<label for="cat_id">Category <span class="text-danger">*</span></label>
						<select name="cat_id" id="cat_id" class="form-control">
							<option value="">-- Select any category --</option>
							@foreach($categories as $cat)
							<option value="{{ $cat->id }}" {{ $product->cat_id == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
							@endforeach
						</select>
						@error('cat_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Sub Category --}}
					<div class="form-group {{ $product->child_cat_id ? '' : 'd-none' }}" id="child_cat_div">
						<label for="child_cat_id">Sub Category</label>
						<select name="child_cat_id" id="child_cat_id" class="form-control">
							<option value="">-- Select sub category --</option>
						</select>
						@error('child_cat_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Price --}}
					<div class="form-group">
						<label for="price">Price (NRS) <span class="text-danger">*</span></label>
						<input type="number" id="price" name="price" class="form-control" value="{{ old('price', $product->price) }}" placeholder="Enter price">
						@error('price')<span class="text-danger">{{ $message }}</span>@enderror
					</div>
				</div>

				{{-- Right Column --}}
				<div class="col-md-6">
					{{-- Discount --}}
					<div class="form-group">
						<label for="discount">Discount (%)</label>
						<input type="number" id="discount" name="discount" class="form-control" value="{{ old('discount', $product->discount) }}" min="0" max="100" placeholder="Enter discount">
						@error('discount')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Sizes --}}
					<div class="form-group">
						<label for="size">Size</label>
						@php $selectedSizes = explode(',', $product->size ?? ''); @endphp
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
							<option value="{{ $brand->id }}" {{ $product->brand_id == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
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
							<option value="{{ $value }}" {{ $product->condition === $value ? 'selected' : '' }}>{{ $label }}</option>
							@endforeach
						</select>
						@error('condition')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Quantity --}}
					<div class="form-group">
						<label for="stock">Quantity <span class="text-danger">*</span></label>
						<input type="number" id="quantity" name="stock" class="form-control" value="{{ old('stock', $product->stock) }}" min="0" placeholder="Enter quantity">
						@error('stock')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Status --}}
					<div class="form-group">
						<label for="status">Status <span class="text-danger">*</span></label>
						<select name="status" class="form-control">
							<option value="active" {{ $product->status == 'active' ? 'selected' : '' }}>Active</option>
							<option value="inactive" {{ $product->status == 'inactive' ? 'selected' : '' }}>Inactive</option>
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
							<input type="text" id="thumbnail" name="photo" class="form-control" placeholder="Comma-separated image URLs" value="{{ $product->images->pluck('image_path')->implode(',') }}" readonly required>
						</div>
						<small class="form-text text-muted">Select multiple images. They will be comma-separated.</small>

						{{-- Combined image preview area --}}
						<div id="image-preview-area" style="margin-top: 15px;">
							{{-- Existing images display --}}
							@if($product->images->count())
							<div id="existing-images">
								<label class="text-muted small">Current Images:</label>
								<div id="existing-holder" class="img-fluid" style="margin-bottom: 10px;">
									@foreach($product->images as $image)
									<img src="{{ asset($image->image_path) }}"
										class="img-thumbnail image-preview"
										alt="Product Image"
										data-is-primary="{{ $image->is_primary ? 'true' : 'false' }}">
									@if($image->is_primary)
									<!-- <small class="d-block text-success text-center" style="font-size: 10px;">Primary</small> -->
									@endif
									@endforeach
								</div>
							</div>
							@endif

							{{-- New images preview --}}
							<div id="new-images" style="display: none;">
								<label class="text-muted small">New Images Preview:</label>
								<div id="holder" class="img-fluid"></div>
							</div>
						</div>

						@error('photo')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

				</div>
			</div>

			{{-- Submit --}}
			<div class="form-group mt-3">
				<button type="submit" class="btn btn-success">Update</button>
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

	/* Image preview styles - consistent with create form */
	#image-preview-area {
		position: relative;
	}

	#existing-holder,
	#holder {
		min-height: 140px;
		padding: 10px;
	}

	.image-preview {
		height: 120px;
		width: auto;
		display: inline-block;
		margin: 5px;
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
		border: 2px solid #28a745;
	}

	.image-preview[data-is-primary="true"]:hover {
		border: 2px solid #007bff;
	}
</style>
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>

<script>
	$('#lfm').filemanager('image');

	function updateImagePreview() {
		console.log('updateImagePreview called');
		let imageInput = $('#thumbnail').val().trim();
		let $holder = $('#holder');
		let $newImagesDiv = $('#new-images');

		// Clear previous new images
		$holder.empty();

		if (!imageInput) {
			console.log('No image input found');
			$newImagesDiv.hide();
			return;
		}

		// Check if the input contains the existing images (from server)
		let existingImages = '{{ $product->images->pluck("image_path")->implode(",") }}';

		// Only show new images preview if the input has changed from existing
		if (imageInput === existingImages) {
			$newImagesDiv.hide();
			return;
		}

		let images = imageInput.split(',');
		console.log('New images found:', images);

		// Show new images section
		$newImagesDiv.show();

		images.forEach(function(url, index) {
			url = url.trim();
			if (url) {
				let img = $('<img />', {
					src: url,
					class: 'img-thumbnail image-preview',
					alt: 'New Product Image ' + (index + 1),
					'data-index': index
				});

				// Add error handling for broken images
				img.on('error', function() {
					$(this).attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkJyb2tlbiBJbWFnZTwvdGV4dD48L3N2Zz4=');
				});

				$holder.append(img);
			}
		});

		console.log('New images added to holder');
	}

	// Monitor changes to the thumbnail input
	$('#thumbnail').on('input change', function() {
		console.log('Thumbnail input changed');
		updateImagePreview();
	});

	// Trigger update when file manager button is clicked
	$('#lfm').on('click', function() {
		console.log('File manager button clicked');
		// Use a longer delay and also check periodically
		let checkCount = 0;
		let originalValue = $('#thumbnail').val();
		let checkInterval = setInterval(function() {
			checkCount++;
			let currentValue = $('#thumbnail').val();
			if (currentValue && currentValue !== originalValue) {
				console.log('Value changed:', currentValue);
				updateImagePreview();
				clearInterval(checkInterval);
			} else if (checkCount > 20) { // Stop checking after 10 seconds
				clearInterval(checkInterval);
			}
		}, 500);
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

		// Form submission handling
		$('form').on('submit', function() {
			$('#summary').val($('#summary').summernote('code'));
			$('#description').val($('#description').summernote('code'));
		});

		// Sub-category handling
		const childCatId = '{{ $product->child_cat_id }}';
		const catId = $('#cat_id').val();

		if (catId) loadSubCategories(catId, childCatId);

		$('#cat_id').change(function() {
			const newCatId = $(this).val();
			loadSubCategories(newCatId, null);
		});

		function loadSubCategories(catId, selectedId = null) {
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
							const selected = (id == selectedId) ? 'selected' : '';
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
	});
</script>
@endpush