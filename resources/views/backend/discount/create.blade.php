@extends('backend.layouts.master')

@section('main-content')

<div class="card">
	<h5 class="card-header">Add Discount</h5>
	<div class="card-body">
		<form id="discountForm" method="POST" action="{{ route('discount.store') }}">
			@csrf
			<div class="row">
				<div class="col-md-6">
					<div class="form-group">
						<label for="title" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="title" type="text" name="title" placeholder="Enter title"
							value="{{ old('title') }}" class="form-control">
						@error('title')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="type" class="col-form-label">Discount Type <span class="text-danger">*</span></label>
						<select name="type" class="form-control">
							<option value="percentage" {{ old('type') == 'percentage' ? 'selected' : '' }}>Percentage</option>
							<option value="amount" {{ old('type') == 'amount' ? 'selected' : '' }}>Fixed Amount</option>
						</select>
						@error('type')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="value" class="col-form-label">Discount Value <span class="text-danger">*</span></label>
						<input id="value" type="number" step="0.01" name="value" placeholder="e.g. 10 or 100.00"
							value="{{ old('value') }}" class="form-control" max="100" min="0">
						@error('value')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>
					<div class="form-group form-check">
						<input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
							{{ old('is_active', true) ? 'checked' : '' }}>
						<label class="form-check-label" for="is_active">Active</label>
					</div>
					<div class="form-group mb-3">
						<button type="reset" class="btn btn-warning">Reset</button>
						<button class="btn btn-success" type="submit">Submit</button>
					</div>
				</div>

				<div class="col-md-6">
					<div class="form-group">
						<label for="starts_at" class="col-form-label">Starts At <span class="text-danger">*</span></label>
						<input id="starts_at" type="datetime-local" name="starts_at"
							value="{{ old('starts_at') }}" class="form-control">
						@error('starts_at')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>
					<div class="form-group">
						<label for="ends_at" class="col-form-label">Ends At <span class="text-danger">*</span></label>
						<input id="ends_at" type="datetime-local" name="ends_at"
							value="{{ old('ends_at') }}" class="form-control">
						@error('ends_at')
						<span class="text-danger">{{ $message }}</span>
						@enderror
					</div>

					<div class="form-group">
						<label for="categories">Apply to Categories</label>
						<select name="categories[]" class="form-control selectpicker" multiple data-live-search="true">
							@foreach($categories as $category)
							<option value="{{ $category->id }}" {{ (collect(old('categories'))->contains($category->id)) ? 'selected' : '' }}>
								{{ $category->title }}
							</option>
							@endforeach
						</select>
					</div>
					@error('categories')
					<span class="text-danger">{{ $message }}</span>
					@enderror
				</div>
			</div>
		</form>
	</div>
</div>

@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css" />
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const typeSelect = document.querySelector('select[name="type"]');
		const valueInput = document.querySelector('input[name="value"]');

		function updateMax() {
			if (typeSelect.value === 'percentage') {
				valueInput.setAttribute('max', 100);
				if (parseFloat(valueInput.value) > 100) {
					valueInput.value = 100;
				}
			} else {
				valueInput.removeAttribute('max');
			}
		}

		// When type is changed
		typeSelect.addEventListener('change', updateMax);

		// When value is changed manually
		valueInput.addEventListener('input', function() {
			if (typeSelect.value === 'percentage') {
				let val = parseFloat(this.value);
				if (val > 100) {
					this.value = 100;
				}
			}
		});

		updateMax(); // Initialize on page load

		// jQuery Validation for Add Discount Form
		$('#discountForm').validate({
			rules: {
				title: {
					required: true,
					maxlength: 255
				},
				type: {
					required: true
				},
				value: {
					required: true,
					number: true,
					min: 0,
					// max is dynamic, enforced manually
				},
				starts_at: {
					required: true,
					date: true
				},
				ends_at: {
					required: true,
					date: true
				},
				'categories[]': {
					required: true
				}
			},
			messages: {
				title: {
					required: "Please enter a title.",
					maxlength: "Title must be less than 255 characters."
				},
				type: {
					required: "Please select a discount type."
				},
				value: {
					required: "Please enter a discount value.",
					number: "Please enter a valid number.",
					min: "Value must be at least 0."
				},
				starts_at: {
					required: "Please select the start time."
				},
				ends_at: {
					required: "Please select the end time."
				},
				'categories[]': {
					required: "Please select at least one category."
				}
			},
			errorElement: 'span',
			errorPlacement: function(error, element) {
				if (element.prop('type') === 'checkbox') {
					error.insertAfter(element.next('label'));
				} else {
					error.addClass('text-danger');
					error.insertAfter(element);
				}
			},
			highlight: function(element) {
				$(element).addClass('is-invalid');
			},
			unhighlight: function(element) {
				$(element).removeClass('is-invalid');
			}
		});
	});
</script>
@endpush