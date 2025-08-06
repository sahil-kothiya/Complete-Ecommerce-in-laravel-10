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
						<div id="holder" style="margin-top:15px;max-height:100px;"></div>
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
			height: 100
		});

		$('#description').summernote({
			tabsize: 2,
			height: 150
		});

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

	$('#cat_id').change(function() {
		let cat_id = $(this).val();
		if (!cat_id) return;

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
			}
		});
	});
</script>
@endpush