@extends('backend.layouts.master')
@section('title','E-SHOP || Brand Edit')
@section('main-content')

<div class="card">
	<h5 class="card-header">Edit Brand</h5>
	<div class="card-body">
		<form method="post" action="{{route('brand.update',$brand->id)}}">
			@csrf
			@method('PATCH')
			<div class="row">
				{{-- Left Column --}}
				<div class="col-md-6">
					<div class="form-group">
						<label for="inputTitle" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="inputTitle" type="text" name="title" placeholder="Enter title" value="{{$brand->title}}" class="form-control">
						@error('title')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>
					<div class="form-group">
						<label for="code" class="col-form-label">Code</label>
						<input type="text" id="code" name="code" class="form-control text-uppercase" value="{{ $brand->code }}" readonly>
					</div>
					<div class="form-group mb-3">
						<button class="btn btn-success" type="submit">Update</button>
					</div>
				</div>

				{{-- Right Column --}}
				<div class="col-md-6">
					<div class="form-group">
						<label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
						<select name="status" class="form-control">
							<option value="active" {{(($brand->status=='active') ? 'selected' : '')}}>Active</option>
							<option value="inactive" {{(($brand->status=='inactive') ? 'selected' : '')}}>Inactive</option>
						</select>
						@error('status')
						<span class="text-danger">{{$message}}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="code_locked" class="col-form-label">Lock Code</label><br>
						<input type="checkbox" name="code_locked" id="code_locked" value="1" {{ $brand->code_locked ? 'checked' : '' }}>
						<label for="code_locked">Prevent automatic code changes</label>
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
	});
</script>
@endpush