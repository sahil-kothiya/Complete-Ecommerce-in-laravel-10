<!-- resources/views/backend/category/edit.blade.php -->
@extends('backend.layouts.master')

@section('main-content')

<div class="card">
	<h5 class="card-header">Edit Category</h5>
	<div class="card-body">
		<form method="post" action="{{ route('category.update', $category->id) }}">
			@csrf
			@method('PATCH')
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{ old('title', $category->title) }}" class="form-control" tabindex="1">
						@error('title')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="summary" class="col-form-label">Summary</label>
						<textarea class="form-control" id="summary" name="summary" tabindex="2">{{ old('summary', $category->summary) }}</textarea>
						@error('summary')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="row">
						<div class="col-md-5">
							<div class="form-group">
								<label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
								<select name="status" class="form-control" tabindex="3">
									<option value="active" {{ old('status', $category->status) == 'active' ? 'selected' : '' }}>Active</option>
									<option value="inactive" {{ old('status', $category->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
								</select>
								@error('status')
								<span class="text-danger">{{ $message }}</span>
								@enderror
							</div>
						</div>

						<div class="col-md-5">
							<div class="form-group">
								<label for="sort_order" class="col-form-label">Sort Order</label>
								<input id="sort_order" type="number" name="sort_order" placeholder="Enter sort order" value="{{ old('sort_order', $category->sort_order) }}" class="form-control" tabindex="4">
								@error('sort_order')
								<span class="text-danger">{{ $message }}</span>
								@enderror
							</div>
						</div>
						<div class="col-md-2">
							<div class="form-group">
								<label for="is_featured" class="col-form-label">Featured</label><br>
								<input type="checkbox" name="is_featured" id="is_featured" value="1" {{ old('is_featured', $category->is_featured) ? 'checked' : '' }} tabindex="5"> Yes
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-5">
							<div class="form-group">
								<label for="code" class="col-form-label">Code</label>
								<input type="text" id="code" name="code" class="form-control text-uppercase" maxlength="3" value="{{ old('code', $category->code) }}" readonly>
							</div>
						</div>

						<div class="col-md-7">
							<div class="form-group">
								<label for="code_locked" class="col-form-label">Lock Code</label><br>
								<input type="checkbox" name="code_locked" id="code_locked" value="1" {{ old('code_locked', $category->code_locked) ? 'checked' : '' }} tabindex="6">
								<label for="code_locked">Prevent automatic code changes</label>
							</div>
						</div>
					</div>

					<div class="form-group" id="parent_cat_div">
						<label for="parent_id">Parent Category</label>
						<select name="parent_id" class="form-control" tabindex="7">
							<option value="">--Select any category--</option>
							@foreach($all_cats as $cat)
							<option value="{{ $cat->id }}" {{ old('parent_id', $category->parent_id) == $cat->id ? 'selected' : '' }} {{ $cat->id == $category->id ? 'disabled' : '' }}>{{ $cat->title }}</option>
							@endforeach
						</select>
						@error('parent_id')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>
				</div>

				<div class="col-md-6">
					<div class="form-group">
						<label for="seo_title" class="col-form-label">SEO Title</label>
						<input id="seo_title" type="text" name="seo_title" placeholder="Enter SEO title" value="{{ old('seo_title', $category->seo_title) }}" class="form-control" tabindex="8">
						@error('seo_title')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="seo_description" class="col-form-label">SEO Description</label>
						<textarea class="form-control" id="seo_description" name="seo_description" tabindex="9">{{ old('seo_description', $category->seo_description) }}</textarea>
						@error('seo_description')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="inputPhoto" class="col-form-label">Photo</label>
						<div class="input-group">
							<span class="input-group-btn">
								<a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary" tabindex="10">
									<i class="fa fa-picture-o"></i> Choose
								</a>
							</span>
							<input id="thumbnail" class="form-control" type="text" name="photo" value="{{ old('photo', $category->photo) }}" readonly>
						</div>
						<small class="form-text text-muted">Select a single image.</small>

						<div id="image-preview-area" style="margin-top: 15px;">
							@if($category->photo)
							<div id="existing-images">
								<label class="text-muted small">Current Image:</label>
								<div id="existing-holder" class="img-fluid">
									<img src="{{ $category->photo ? asset($category->photo) : '' }}"
										class="img-thumbnail image-preview"
										alt="Category Image"
										data-fallback-text="{{ $category->title }} - Image"
										onerror="handleImageError(this)">
								</div>
							</div>
							@endif

							<div id="new-images" style="display: none;">
								<label class="text-muted small">New Image Preview:</label>
								<div id="holder" class="img-fluid"></div>
							</div>
						</div>
						@error('photo')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label>Enabled Filters</label>
						<div class="d-flex flex-wrap">
							@foreach($available_filters as $key => $label)
							<div class="form-check form-check-inline">
								<input class="form-check-input" type="checkbox" name="enabled_filters[]" value="{{ $key }}" id="filter_{{ $key }}"
									{{ in_array($key, old('enabled_filters', is_array($category->enabled_filters) ? $category->enabled_filters : [])) ? 'checked' : '' }} tabindex="11">
								<label class="form-check-label" for="filter_{{ $key }}">
									{{ $label }}
								</label>
							</div>
							@endforeach
						</div>
						@error('enabled_filters')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="brands">Associated Brands</label>
						<select name="brands[]" id="brands" class="form-control selectpicker" multiple data-live-search="true" tabindex="12">
							@foreach($brands as $brand)
							<option value="{{ $brand->id }}" {{ in_array($brand->id, old('brands', $category->brands->pluck('id')->toArray() ?? [])) ? 'selected' : '' }}>{{ $brand->title }}</option>
							@endforeach
						</select>
						<small class="form-text text-muted">Select brands available in this category. Hold Ctrl/Cmd to select multiple.</small>
						@error('brands')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>
				</div>
			</div>

			<div class="form-group mb-3">
				<button class="btn btn-success" type="submit" tabindex="13">Update</button>
			</div>
		</form>
	</div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.min.css" />
<style>
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

	/* Inline checkbox styling */
	.form-check-inline {
		margin-right: 1.5rem;
		margin-bottom: 0.5rem;
	}

	.form-check-inline .form-check-label {
		margin-left: 0.25rem;
		font-size: 0.9rem;
	}

	@media (max-width: 576px) {
		.form-check-inline {
			margin-right: 1rem;
		}
	}
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script>
	$(document).ready(function() {
		console.log('Document ready - Category Edit Form');

		$('#brands').selectpicker({
			liveSearch: true,
			noneSelectedText: 'Select brands'
		});

		$('#summary').summernote({
			placeholder: "Write short description.....",
			tabsize: 2,
			height: 150
		});

		$('#seo_description').summernote({
			placeholder: "Write SEO description.....",
			tabsize: 2,
			height: 150
		});

		$('#lfm').filemanager('image');

		let originalImagePath = '{{ $category->photo }}';

		function handleImageError(img) {
			const fallbackText = img.getAttribute('data-fallback-text') || 'Image not available';
			const container = img.parentElement;

			const fallback = document.createElement('div');
			fallback.className = 'image-not-found';
			fallback.innerHTML = `
                <i class="fa fa-image"></i>
                <span>${fallbackText}</span>
            `;

			container.insertBefore(fallback, img);
			img.style.display = 'none';

			console.warn('Image failed to load:', img.src);
		}

		function updateImagePreview() {
			console.log('updateImagePreview called');
			let imageInput = $('#thumbnail').val().trim();
			let $holder = $('#holder');
			let $newImagesDiv = $('#new-images');

			$holder.empty();

			if (!imageInput) {
				console.log('No image input found');
				$newImagesDiv.hide();
				return;
			}

			if (imageInput === originalImagePath) {
				$newImagesDiv.hide();
				return;
			}

			$newImagesDiv.show();

			let url = imageInput.trim();
			if (url) {
				if (url.startsWith('/storage')) {
					url = '{{ config('
					app.url ') }}' + url;
				}
				let container = $('<div class="image-container"></div>');
				let img = $('<img />', {
					src: url,
					class: 'img-thumbnail image-preview',
					alt: 'New Category Image',
					'data-fallback-text': 'New Image'
				});

				img.on('error', function() {
					handleImageError(this);
				});

				container.append(img);
				$holder.append(container);
			}

			console.log('New image preview created');
		}

		$('#thumbnail').on('input change', function() {
			console.log('Thumbnail input changed');
			updateImagePreview();
		});

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

		$('form').on('submit', function() {
			$('#summary').val($('#summary').summernote('code'));
			$('#seo_description').val($('#seo_description').summernote('code'));
		});
	});
</script>
@endpush