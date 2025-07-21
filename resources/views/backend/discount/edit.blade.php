@extends('backend.layouts.master')

@section('main-content')
<div class="card">
	<h5 class="card-header">Edit Discount</h5>
	<div class="card-body">
		<form id="discountForm" method="POST" action="{{ route('discount.update', $discount->id) }}">
			@csrf
			@method('PATCH')

			{{-- Title --}}
			<div class="form-group">
				<label for="title">Title <span class="text-danger">*</span></label>
				<input id="title" type="text" name="title" class="form-control" placeholder="Enter title"
					value="{{ old('title', $discount->title) }}">
				@error('title')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- Discount Type --}}
			<div class="form-group">
				<label for="type">Discount Type <span class="text-danger">*</span></label>
				<select name="type" id="type" class="form-control">
					<option value="percentage" {{ old('type', $discount->type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
					<option value="amount" {{ old('type', $discount->type) == 'amount' ? 'selected' : '' }}>Fixed Amount</option>
				</select>
				@error('type')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- Discount Value --}}
			<div class="form-group">
				<label for="value">Discount Value <span class="text-danger">*</span></label>
				<input id="value" type="number" step="0.01" name="value" class="form-control"
					placeholder="e.g. 10 or 100.00"
					value="{{ old('value', $discount->value) }}" min="0">
				@error('value')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- Start Time --}}
			<div class="form-group">
				<label for="starts_at">Starts At <span class="text-danger">*</span></label>
				<input type="datetime-local" name="starts_at" id="starts_at" class="form-control"
					value="{{ old('starts_at', $discount->starts_at ? $discount->starts_at->format('Y-m-d\TH:i') : '') }}">
				@error('starts_at')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- End Time --}}
			<div class="form-group">
				<label for="ends_at">Ends At <span class="text-danger">*</span></label>
				<input type="datetime-local" name="ends_at" id="ends_at" class="form-control"
					value="{{ old('ends_at', $discount->ends_at ? $discount->ends_at->format('Y-m-d\TH:i') : '') }}">
				@error('ends_at')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- Categories --}}
			<div class="form-group">
				<label for="categories">Applicable Categories <span class="text-danger">*</span></label>
				<select name="categories[]" id="categories" class="form-control" multiple>
					@foreach($categories as $category)
					<option value="{{ $category->id }}"
						{{ in_array($category->id, old('categories', $selectedCategoryIds)) ? 'selected' : '' }}>
						{{ $category->title }}
					</option>
					@endforeach
				</select>
				@error('categories')<span class="text-danger">{{ $message }}</span>@enderror
			</div>

			{{-- Is Active --}}
			<div class="form-group form-check">
				<input type="checkbox" name="is_active" id="is_active" class="form-check-input" value="1"
					{{ old('is_active', $discount->is_active) ? 'checked' : '' }}>
				<label for="is_active" class="form-check-label">Active</label>
			</div>

			{{-- Buttons --}}
			<div class="form-group mb-3">
				<button type="reset" class="btn btn-warning">Reset</button>
				<button type="submit" class="btn btn-success">Update</button>
			</div>
		</form>
	</div>
</div>
@endsection

@push('scripts')
<script>
	document.addEventListener('DOMContentLoaded', function() {
		const typeSelect = document.getElementById('type');
		const valueInput = document.getElementById('value');

		function enforceMaxValue() {
			if (typeSelect.value === 'percentage') {
				valueInput.setAttribute('max', 100);
				if (parseFloat(valueInput.value) > 100) {
					valueInput.value = 100;
				}
			} else {
				valueInput.removeAttribute('max');
			}
		}

		typeSelect.addEventListener('change', enforceMaxValue);
		valueInput.addEventListener('input', enforceMaxValue);
		enforceMaxValue();

		// jQuery Validation
		$('#discountForm').validate({
			rules: {
				title: {
					required: true,
					minlength: 3
				},
				type: {
					required: true
				},
				value: {
					required: true,
					number: true,
					min: 0,
					max: function() {
						return $('#type').val() === 'percentage' ? 100 : undefined;
					}
				},
				starts_at: {
					required: true
				},
				ends_at: {
					required: true
				},
				'categories[]': {
					required: true,
					minlength: 1
				}
			},
			messages: {
				title: {
					required: "Title is required",
					minlength: "Title must be at least 3 characters long"
				},
				type: "Please select a discount type",
				value: {
					required: "Discount value is required",
					number: "Please enter a valid number",
					min: "Value must be 0 or greater",
					max: "Percentage discount cannot exceed 100"
				},
				starts_at: "Start time is required",
				ends_at: "End time is required",
				'categories[]': "Select at least one category"
			},
			errorElement: 'span',
			errorClass: 'text-danger',
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