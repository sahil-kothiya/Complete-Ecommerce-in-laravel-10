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

					{{-- Images --}}
					<div class="form-group">
						<label for="inputPhoto">Upload New Image(s)</label>
						<div class="input-group mb-2">
							<span class="input-group-btn">
								<a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary text-white">
									<i class="fas fa-image"></i> Choose
								</a>
							</span>
							<input id="thumbnail" class="form-control" type="text" name="photo"
								value="{{ $product->images->pluck('image_path')->implode(',') }}"
								{{ !$product->images->count() ? 'required' : '' }}>
						</div>
						@if($product->images->count())
						<small class="form-text text-muted">Currently {{ $product->images->count() }} image(s) uploaded. Uploading new will replace them.</small>
						@else
						<small class="form-text text-danger">At least one image is required.</small>
						@endif
						<div id="holder" style="margin-top:15px; max-height:100px;"></div>
						@error('photo')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Existing Images --}}
					@if($product->images->count())
					<div class="form-group">
						<label>Existing Images:</label>
						<div class="row">
							@foreach($product->images as $image)
							<div class="col-md-3 text-center mb-3">
								<img src="{{ asset($image->image_path) }}" class="img-fluid img-thumbnail" style="max-height: 100px;" alt="Product Image">
								@if($image->is_primary)
								<small class="d-block text-success">Primary</small>
								@endif
							</div>
							@endforeach
						</div>
					</div>
					@endif

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
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>

<script>
	$('#lfm').filemanager('image');

	$(document).ready(function() {
		$('#summary').summernote({
			tabsize: 2,
			height: 150
		});
		$('#description').summernote({
			tabsize: 2,
			height: 150
		});

		$('form').on('submit', function() {
			$('#summary').val($('#summary').summernote('code'));
			$('#description').val($('#description').summernote('code'));
		});

		const childCatId = '{{ $product->child_cat_id }}';
		const catId = $('#cat_id').val();

		if (catId) loadSubCategories(catId, childCatId);

		$('#cat_id').change(function() {
			const newCatId = $(this).val();
			loadSubCategories(newCatId, null);
		});

		function loadSubCategories(catId, selectedId = null) {
			if (!catId) return;

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
				}
			});
		}
	});
</script>
@endpush