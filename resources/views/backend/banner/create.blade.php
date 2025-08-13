@extends('backend.layouts.master')

@section('title','E-SHOP || Banner Create')

@section('main-content')

<div class="card">
	<h5 class="card-header">Add Banner</h5>
	<div class="card-body">
		<form method="post" action="{{route('banner.store')}}">
			@csrf

			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{old('title')}}" class="form-control" required>
						@error('title')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="description">Description</label>
						<textarea id="description" name="description" class="form-control">{{ old('description') }}</textarea>
						@error('description')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					<div class="form-group">
						<label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
						<select name="status" class="form-control" required>
							<option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
							<option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
						</select>
						@error('status')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group mb-3">
						<button type="reset" class="btn btn-warning">Reset</button>
						<button class="btn btn-success" type="submit">Submit</button>
					</div>
				</div>

				<div class="col-md-6">
					<div class="form-group">
						<label for="discount_id" class="col-form-label">Discount </label>
						<select name="discount_id" class="form-control">
							<option value="">-- Select Discount --</option>
							@foreach($discounts as $discount)
							<option value="{{ $discount->id }}" {{ old('discount_id') == $discount->id ? 'selected' : '' }}>
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
							<input id="thumbnail" class="form-control" type="text" name="photo" value="{{old('photo')}}" required readonly>
						</div>
						<div id="holder" style="margin-top:15px;max-height:100px;"></div>
						@error('photo')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="link_type" class="col-form-label">Link Type </label>
						<select name="link_type" class="form-control">
							<option value="">-- Select Link Type --</option>
							<option value="product" {{ old('link_type') == 'product' ? 'selected' : '' }}> Product </option>
							<option value="category" {{ old('link_type') == 'category' ? 'selected' : '' }}> Category </option>
							<option value="url" {{ old('link_type') == 'url' ? 'selected' : '' }}> URL </option>
						</select>
						@error('link_type')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>			

					<div class="form-group">
						<label for="link" class="col-form-label">Redirect URL / SKU </label>
						<input id="link" type="text" name="link" placeholder="e.g. /product/sku-123 OR /category/electronics OR https://example.com" value="{{ old('link') }}" class="form-control">
						@error('link')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

				</div>
			</div>

		</form>
	</div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="{{asset('backend/summernote/summernote.min.css')}}">
@endpush

@push('scripts')
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{asset('backend/summernote/summernote.min.js')}}"></script>
<script>
	$('#lfm').filemanager('image');

	$(document).ready(function() {
		$('#description').summernote({
			placeholder: "Write short description.....",
			tabsize: 2,
			height: 150
		});

		// On form submit validation
        $('form').on('submit', function(e) {
            let link = $.trim($('input[name="link"]').val());
            let linkType = $.trim($('select[name="link_type"]').val());

            if (link !== '' && linkType === '') {
                e.preventDefault();
                alert('Please select Link Type when providing a Link.');
                $('select[name="link_type"]').focus();
            }
        });
	});
</script>
@endpush