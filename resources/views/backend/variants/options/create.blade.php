@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Add New Variant Option</h6>
    </div>
    <div class="card-body">
        @include('backend.layouts.notification')

        <form id="variantOptionForm" action="{{ route('variant-option.store') }}" method="POST">
            @csrf

            <!-- Row 1 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="variant_type_id">Type <span class="text-danger">*</span></label>
                        <select name="variant_type_id" id="variant_type_id"
                                class="form-control @error('variant_type_id') is-invalid @enderror"
                                required>
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}"
                                        {{ old('variant_type_id') == $type->id ? 'selected' : '' }}>
                                    {{ $type->display_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('variant_type_id')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="display_value">Display Value <span class="text-danger">*</span></label>
                        <input type="text" name="display_value" id="display_value"
                               class="form-control @error('display_value') is-invalid @enderror"
                               value="{{ old('display_value') }}" required>
                        @error('display_value')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Row 2 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="value">Value <span class="text-danger">*</span></label>
                        <input type="text" name="value" id="value"
                               class="form-control @error('value') is-invalid @enderror"
                               value="{{ old('value') }}" required>
                        @error('value')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="hex_color">Hex Color (Optional)</label>
                        <input type="color" name="hex_color" id="hex_color"
                               class="form-control @error('hex_color') is-invalid @enderror"
                               value="{{ old('hex_color', '#000000') }}">
                        @error('hex_color')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Row 3 -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order"
                               class="form-control @error('sort_order') is-invalid @enderror"
                               value="{{ old('sort_order', 0) }}" min="0">
                        @error('sort_order')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status"
                                class="form-control @error('status') is-invalid @enderror">
                            <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        @error('status')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @else
                            <span class="invalid-feedback"></span>
                        @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Save Option</button>
            <a href="{{ route('variant-option.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .invalid-feedback {
        font-size: 80% !important;
        color: #e74a3b !important;
    }
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>

<script>
$(document).ready(function () {
    // Custom hex color validator
    $.validator.addMethod("hexColor", function(value, element) {
        return this.optional(element) || /^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/.test(value);
    }, "Please enter a valid hex color (e.g., #ff0000 or #fff).");

    $("#variantOptionForm").validate({
        rules: {
            variant_type_id: { required: true },
            display_value: { required: true, minlength: 1, maxlength: 100 },
            value: { required: true, minlength: 1, maxlength: 100 },
            hex_color: { hexColor: true },
            sort_order: { digits: true, min: 0 },
            status: { required: true }
        },
        messages: {
            variant_type_id: { required: "Please select a variant type." },
            display_value: {
                required: "Display value is required.",
                maxlength: "Display value cannot exceed 100 characters."
            },
            value: {
                required: "Value is required.",
                maxlength: "Value cannot exceed 100 characters."
            },
            sort_order: {
                digits: "Sort order must be a number.",
                min: "Sort order cannot be negative."
            },
            status: { required: "Please select a status." }
        },
        errorElement: "span",
        errorPlacement: function(error, element) {
            // Replace the existing empty .invalid-feedback with the new error
            const feedback = element.closest('.form-group').find('.invalid-feedback');
            feedback.replaceWith(error.addClass('invalid-feedback d-block'));
        },
        highlight: function(element) {
            $(element).addClass('is-invalid').removeClass('is-valid');
        },
        unhighlight: function(element) {
            $(element).removeClass('is-invalid').addClass('is-valid');
            // Clear error message on success
            $(element).closest('.form-group').find('.invalid-feedback').text('').removeClass('d-block');
        },
        success: function(label) {
            label.addClass('valid');
        }
    });
});
</script>
@endpush