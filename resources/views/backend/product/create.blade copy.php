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

					{{-- Featured --}}
					<div class="form-group form-check">
						<input type="checkbox" name="is_featured" id="is_featured" class="form-check-input" value="1" {{ old('is_featured') ? 'checked' : '' }}>
						<label for="is_featured" class="form-check-label">Is Featured</label>
					</div>

					{{-- Category --}}
					<div class="form-group">
						<label for="cat_id">Category <span class="text-danger">*</span></label>
						<select name="cat_id" id="cat_id" class="form-control">
							<option value="">-- Select any category --</option>
							@foreach($categories as $cat_data)
							<option value="{{ $cat_data->id }}">{{ $cat_data->title }}</option>
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
						<select name="size[]" class="form-control selectpicker" multiple data-live-search="true">
							<option value="S">Small (S)</option>
							<option value="M">Medium (M)</option>
							<option value="L">Large (L)</option>
							<option value="XL">Extra Large (XL)</option>
						</select>
						@error('size')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Brand --}}
					<div class="form-group">
						<label for="brand_id">Brand</label>
						<select name="brand_id" class="form-control">
							<option value="">-- Select Brand --</option>
							@foreach($brands as $brand)
							<option value="{{ $brand->id }}">{{ $brand->title }}</option>
							@endforeach
						</select>
						@error('brand_id')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Condition --}}
					<div class="form-group">
						<label for="condition">Condition</label>
						<select name="condition" class="form-control">
							<option value="">-- Select Condition --</option>
							<option value="default">Default</option>
							<option value="new">New</option>
							<option value="hot">Hot</option>
						</select>
						@error('condition')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Quantity --}}
					<div class="form-group">
						<label for="quantity">Quantity <span class="text-danger">*</span></label>
						<input type="number" id="quantity" name="stock" class="form-control" value="{{ old('stock') }}" min="0" placeholder="Enter quantity">
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
						<div id="holder" class="img-fluid" style="margin-top:15px;"></div>
						@error('photo')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

				</div>
			</div>

			{{-- Buttons --}}
			<div class="form-group mt-3">
				<button type="reset" class="btn btn-warning">Reset</button>
				<button type="submit" class="btn btn-success">Submit</button>
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
	#holder {
		position: relative;
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

	/* Ensure container has enough space for zoomed images */
	#holder {
		min-height: 140px;
		padding: 10px;
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

		// Clear previous images
		$holder.empty();

		if (!imageInput) {
			console.log('No image input found');
			return;
		}

		let images = imageInput.split(',');
		console.log('Images found:', images);

		images.forEach(function(url, index) {
			url = url.trim();
			if (url) {
				let img = $('<img />', {
					src: url,
					class: 'img-thumbnail image-preview',
					alt: 'Product Image ' + (index + 1),
					'data-index': index
				});

				// Add error handling for broken images
				img.on('error', function() {
					$(this).attr('src', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTIwIiBoZWlnaHQ9IjEyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkJyb2tlbiBJbWFnZTwvdGV4dD48L3N2Zz4=');
				});

				$holder.append(img);
			}
		});

		console.log('Images added to holder');
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
		let checkInterval = setInterval(function() {
			checkCount++;
			let currentValue = $('#thumbnail').val();
			if (currentValue && currentValue.trim()) {
				console.log('Value detected:', currentValue);
				updateImagePreview();
				clearInterval(checkInterval);
			} else if (checkCount > 20) { // Stop checking after 10 seconds
				clearInterval(checkInterval);
			}
		}, 500);
	});

	$(document).ready(function() {
		console.log('Document ready');

		// Initialize image preview if there's already a value
		updateImagePreview();

		// Initialize Summernote
		$('#summary').summernote({
			tabsize: 2,
			height: 100
		});

		$('#description').summernote({
			tabsize: 2,
			height: 150
		});

		// Form submission validation
		$('form').on('submit', function(e) {
			$('#summary').val($('#summary').summernote('code'));
			$('#description').val($('#description').summernote('code'));

			if (!$('#thumbnail').val().trim()) {
				e.preventDefault();
				alert('Photo is required.');
				$('#thumbnail').focus();
			}
		});
	});

	// Category change handler
	$('#cat_id').change(function() {
		let cat_id = $(this).val();
		if (!cat_id) {
			$('#child_cat_div').addClass('d-none');
			return;
		}

		$.ajax({
			url: "/admin/category/" + cat_id + "/child",
			type: "POST",
			data: {
				_token: "{{ csrf_token() }}",
				id: cat_id
			},
			success: function(response) {
				if (typeof response !== 'object') {
					response = $.parseJSON(response);
				}

				let html_option = "<option value=''>-- Select sub category --</option>";

				if (response.status && response.data) {
					$('#child_cat_div').removeClass('d-none');
					$.each(response.data, function(id, title) {
						html_option += "<option value='" + id + "'>" + title + "</option>";
					});
				} else {
					$('#child_cat_div').addClass('d-none');
				}

				$('#child_cat_id').html(html_option);
			},
			error: function(xhr, status, error) {
				console.error('AJAX Error:', error);
				$('#child_cat_div').addClass('d-none');
			}
		});
	});
</script>
@endpush