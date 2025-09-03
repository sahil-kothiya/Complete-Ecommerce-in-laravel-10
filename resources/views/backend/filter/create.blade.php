@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <h5 class="card-header">Add Filter</h5>
    <div class="card-body">
        <form id="filterForm" method="POST" action="{{ route('filter.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    {{-- Filter Name --}}
                    <div class="form-group">
                        <label for="name" class="col-form-label">Name <span class="text-danger">*</span></label>
                        <input id="name" type="text" name="name" placeholder="Enter filter name (e.g., Price)" value="{{ old('name') }}" class="form-control" tabindex="1">
                        @error('name')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Filter Title --}}
                    <div class="form-group">
                        <label for="title" class="col-form-label">Title <span class="text-danger">*</span></label>
                        <input id="title" type="text" name="title" placeholder="Enter filter title (e.g., Price Range)" value="{{ old('title') }}" class="form-control" tabindex="2">
                        @error('title')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    {{-- Filter Description --}}
                    <div class="form-group">
                        <label for="description" class="col-form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" placeholder="Enter description" tabindex="3">{{ old('description') }}</textarea>
                        @error('description')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Status --}}
                    <div class="form-group">
                        <label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="status" class="form-control" tabindex="4">
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Buttons --}}
            <div class="form-group mb-3">
                <button type="reset" class="btn btn-warning" tabindex="5">Reset</button>
                <button class="btn btn-success" type="submit" tabindex="6">Submit</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 350px;
    }
    .form-control:focus, .btn:focus {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
<script>
    $(document).ready(function() {
        // Function to show notification messages
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            $('.notification-toast').remove();

            // Create notification element
            const notificationClass = type === 'success' ? 'alert-success' :
                                     type === 'error' ? 'alert-danger' : 'alert-info';

            const $notification = $(`
                <div class="alert ${notificationClass} notification-toast alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close" tabindex="0">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
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

        // jQuery Validation for Add Filter Form
        $('#filterForm').validate({
            rules: {
                name: {
                    required: true,
                    maxlength: 255
                },
                title: {
                    required: true,
                    maxlength: 255
                },
                description: {
                    maxlength: 1000
                },
                status: {
                    required: true
                }
            },
            messages: {
                name: {
                    required: "Please enter a filter name.",
                    maxlength: "Filter name must be less than 255 characters."
                },
                title: {
                    required: "Please enter a filter title.",
                    maxlength: "Filter title must be less than 255 characters."
                },
                description: {
                    maxlength: "Description must be less than 1000 characters."
                },
                status: {
                    required: "Please select a status."
                }
            },
            errorElement: 'span',
            errorPlacement: function(error, element) {
                error.addClass('text-danger');
                error.insertAfter(element);
            },
            highlight: function(element) {
                $(element).addClass('is-invalid');
            },
            unhighlight: function(element) {
                $(element).removeClass('is-invalid');
            },
            submitHandler: function(form) {
                // Show success notification after successful validation
                showNotification('Filter submitted successfully!', 'success');
                form.submit();
            }
        });

        // Reset button functionality
        $('button[type="reset"]').on('click', function() {
            // Clear all error messages and styling
            $('.is-invalid').removeClass('is-invalid');
            $('.text-danger').not('[class*="alert"]').remove();
            $('.notification-toast').remove();
            $('#filterForm').validate().resetForm();
        });
    });
</script>
@endpush