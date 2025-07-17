@extends('backend.layouts.master')

@section('main-content')

<div class="card">
	<h5 class="card-header">Add Discount</h5>
	<div class="card-body">
		<form method="POST" action="{{ route('discount.store') }}">
			@csrf

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

			<div class="form-group form-check">
				<input type="checkbox" name="is_active" id="is_active" value="1" class="form-check-input"
					{{ old('is_active', true) ? 'checked' : '' }}>
				<label class="form-check-label" for="is_active">Active</label>
			</div>

			<div class="form-group mb-3">
				<button type="reset" class="btn btn-warning">Reset</button>
				<button class="btn btn-success" type="submit">Submit</button>
			</div>
		</form>
	</div>
</div>

@endsection
@push('scripts')
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
	});
</script>
@endpush