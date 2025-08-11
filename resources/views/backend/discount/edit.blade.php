@extends('backend.layouts.master')

@section('main-content')
<div class="card">
	<h5 class="card-header">Edit Discount</h5>
	<div class="card-body">
		<form id="discountForm" method="POST" action="{{ route('discount.update', $discount->id) }}">
			@csrf
			@method('PATCH')
			<div class="row">
				<div class="col-md-6">
					{{-- Title --}}
					<div class="form-group">
						<label for="title" class="col-form-label">Title <span class="text-danger">*</span></label>
						<input id="title" type="text" name="title" class="form-control" placeholder="Enter title"
							value="{{ old('title', $discount->title) }}">
						@error('title')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Discount Type --}}
					<div class="form-group">
						<label for="type" class="col-form-label">Discount Type <span class="text-danger">*</span></label>
						<select name="type" id="type" class="form-control">
							<option value="percentage" {{ old('type', $discount->type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
							<option value="amount" {{ old('type', $discount->type) == 'amount' ? 'selected' : '' }}>Fixed Amount</option>
						</select>
						@error('type')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Discount Value --}}
					<div class="form-group">
						<label for="value" class="col-form-label">Discount Value <span class="text-danger">*</span></label>
						<input id="value" type="number" step="0.01" name="value" class="form-control"
							placeholder="e.g. 10 or 100.00"
							value="{{ old('value', $discount->value) }}" min="0" max="100">
						@error('value')<span class="text-danger">{{ $message }}</span>@enderror
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
				</div>

				<div class="col-md-6">
					{{-- Start Time --}}
					<div class="form-group">
						<label for="starts_at" class="col-form-label">Starts At <span class="text-danger">*</span></label>
						<input type="datetime-local" name="starts_at" id="starts_at" class="form-control"
							value="{{ old('starts_at', $discount->starts_at ? $discount->starts_at->format('Y-m-d\TH:i') : '') }}">
						@error('starts_at')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- End Time --}}
					<div class="form-group">
						<label for="ends_at" class="col-form-label">Ends At <span class="text-danger">*</span></label>
						<input type="datetime-local" name="ends_at" id="ends_at" class="form-control"
							value="{{ old('ends_at', $discount->ends_at ? $discount->ends_at->format('Y-m-d\TH:i') : '') }}">
						@error('ends_at')<span class="text-danger">{{ $message }}</span>@enderror
					</div>

					{{-- Categories --}}
					<div class="form-group">
						<label for="categories" class="col-form-label">Apply to Categories <span class="text-danger">*</span></label>
						<select name="categories[]" id="categories" class="form-control selectpicker" multiple data-live-search="true">
							@foreach($categories as $category)
							<option value="{{ $category->id }}"
								{{ in_array($category->id, old('categories', $selectedCategoryIds)) ? 'selected' : '' }}>
								{{ $category->title }}
							</option>
							@endforeach
						</select>
						@error('categories')<span class="text-danger">{{ $message }}</span>@enderror
					</div>
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
		const startsAtInput = document.querySelector('input[name="starts_at"]');
		const endsAtInput = document.querySelector('input[name="ends_at"]');

		// Function to show notification messages
		function showNotification(message, type = 'info') {
			// Remove existing notifications
			$('.notification-toast').remove();

			// Create notification element
			const notificationClass = type === 'success' ? 'alert-success' :
				type === 'error' ? 'alert-danger' : 'alert-info';

			const $notification = $(`
            <div class="alert ${notificationClass} notification-toast alert-dismissible" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <strong>${type === 'success' ? 'Success!' : type === 'error' ? 'Error!' : 'Info!'}</strong> ${message}
            </div>
        `);

			// Add to page
			$('body').append($notification);

			// Auto-hide after 4 seconds
			setTimeout(function() {
				$notification.fadeOut(400, function() {
					$(this).remove();
				});
			}, 4000);
		}

		function updateMax() {
			if (typeSelect.value === 'percentage') {
				valueInput.setAttribute('max', 100);
				valueInput.setAttribute('placeholder', 'Enter percentage (0-100)');
				if (parseFloat(valueInput.value) > 100) {
					valueInput.value = 100;
					showNotification('Percentage discount cannot exceed 100%. Set to 100.', 'error');
				}
			} else {
				valueInput.removeAttribute('max');
				valueInput.setAttribute('placeholder', 'Enter fixed amount');
			}
		}

		// Enhanced value validation function
		function validateDiscountValue() {
			let discountValue = parseFloat(valueInput.value);
			let $valueField = $(valueInput);
			let $errorSpan = $valueField.next('.discount-value-error');

			// Remove existing error span if it exists
			if ($errorSpan.length === 0) {
				$valueField.after('<span class="text-danger discount-value-error" style="font-size: 0.875rem;"></span>');
				$errorSpan = $valueField.next('.discount-value-error');
			}

			// Clear previous error styling
			$valueField.removeClass('is-invalid');
			$errorSpan.text('').hide();

			if (isNaN(discountValue) || discountValue === '') {
				return true; // Allow empty values for now, required validation will handle
			}

			// Validate discount range
			if (discountValue < 0) {
				$valueField.addClass('is-invalid').val(0);
				$errorSpan.text('Discount value cannot be negative. Set to 0.').show();
				showNotification('Discount value cannot be negative.', 'error');
				return false;
			}

			// Check percentage limit
			if (typeSelect.value === 'percentage' && discountValue > 100) {
				$valueField.addClass('is-invalid').val(100);
				$errorSpan.text('Percentage discount cannot exceed 100%. Set to 100.').show();
				showNotification('Percentage discount cannot exceed 100%.', 'error');
				return false;
			}

			// Check fixed amount reasonable limit (optional)
			if (typeSelect.value === 'amount' && discountValue > 100) {
				$valueField.addClass('is-invalid');
				$errorSpan.text('Fixed amount seems too high. Please verify.').show();
				showNotification('Fixed discount amount seems unusually high.', 'error');
				return false;
			}

			return true;
		}

		// Date validation function
		function validateDates() {
			const startsAt = new Date(startsAtInput.value);
			const endsAt = new Date(endsAtInput.value);
			const now = new Date();
			let isValid = true;

			// Clear previous errors
			$(startsAtInput).removeClass('is-invalid');
			$(endsAtInput).removeClass('is-invalid');
			$('.date-error').remove();

			if (startsAtInput.value && endsAtInput.value) {
				if (startsAt >= endsAt) {
					$(endsAtInput).addClass('is-invalid');
					$(endsAtInput).after('<span class="text-danger date-error" style="font-size: 0.875rem;">End date must be after start date.</span>');
					showNotification('End date must be after start date.', 'error');
					isValid = false;
				}

				// Warning for past dates
				if (startsAt < now) {
					showNotification('Start date is in the past. This discount will be active immediately.', 'info');
				}
			}

			return isValid;
		}

		// When type is changed
		typeSelect.addEventListener('change', function() {
			updateMax();
			validateDiscountValue();
		});

		// Value input validation
		valueInput.addEventListener('input', function() {
			validateDiscountValue();
		});

		valueInput.addEventListener('blur', function() {
			validateDiscountValue();
		});

		// Prevent typing non-numeric characters
		valueInput.addEventListener('keypress', function(e) {
			// Allow: backspace, delete, tab, escape, enter, decimal point
			if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1 ||
				// Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
				(e.keyCode === 65 && e.ctrlKey === true) ||
				(e.keyCode === 67 && e.ctrlKey === true) ||
				(e.keyCode === 86 && e.ctrlKey === true) ||
				(e.keyCode === 88 && e.ctrlKey === true) ||
				// Allow: home, end, left, right
				(e.keyCode >= 35 && e.keyCode <= 39)) {
				return;
			}
			// Ensure that it is a number and stop the keypress
			if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
				e.preventDefault();
			}
		});

		// Date validation
		startsAtInput.addEventListener('change', validateDates);
		endsAtInput.addEventListener('change', validateDates);

		// Initialize on page load
		updateMax();

		// jQuery Validation for Edit Discount Form with enhanced rules
		$('#discountForm').validate({
			rules: {
				title: {
					required: true,
					minlength: 3,
					maxlength: 255
				},
				type: {
					required: true
				},
				value: {
					required: true,
					number: true,
					min: 0,
					max: function() {
						return typeSelect.value === 'percentage' ? 100 : 999999;
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
					required: "Please enter a title.",
					minlength: "Title must be at least 3 characters long.",
					maxlength: "Title must be less than 255 characters."
				},
				type: {
					required: "Please select a discount type."
				},
				value: {
					required: "Please enter a discount value.",
					number: "Please enter a valid number.",
					min: "Value must be at least 0.",
					max: function() {
						return typeSelect.value === 'percentage' ?
							"Percentage cannot exceed 100%." :
							"Amount seems too high.";
					}
				},
				starts_at: {
					required: "Please select the start date and time."
				},
				ends_at: {
					required: "Please select the end date and time."
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
			},
			submitHandler: function(form) {
				// Final validation before submission
				if (!validateDiscountValue() || !validateDates()) {
					showNotification('Please fix all validation errors before submitting.', 'error');
					return false;
				}

				// Additional check for discount value
				let discountValue = parseFloat(valueInput.value);
				if (!isNaN(discountValue)) {
					if (discountValue < 0) {
						showNotification('Discount value cannot be negative.', 'error');
						valueInput.focus();
						return false;
					}

					if (typeSelect.value === 'percentage' && discountValue > 100) {
						showNotification('Percentage discount cannot exceed 100%.', 'error');
						valueInput.focus();
						return false;
					}
				}

				// If all validations pass, submit the form
				form.submit();
			}
		});

		// Reset button functionality
		$('button[type="reset"]').on('click', function() {
			// Clear all error messages and styling
			$('.is-invalid').removeClass('is-invalid');
			$('.discount-value-error, .date-error').remove();
			$('.notification-toast').remove();

			// Reset form and reinitialize
			setTimeout(function() {
				updateMax();
			}, 100);
		});
	});
</script>
@endpush