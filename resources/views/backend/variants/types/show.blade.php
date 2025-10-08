@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between">
        <h6 class="m-0 font-weight-bold text-primary">View Variant Type: {{ $type->display_name }}</h6>
        <div>
            <a href="{{ route('variant-type.edit', $type) }}" class="btn btn-primary btn-sm mr-2">Edit</a> {{-- Use model object for route --}}
            <a href="{{ route('variant-option.create') }}?type_id={{ $type->id }}" class="btn btn-success btn-sm">Add Option</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Name:</strong> {{ $type->name }}</p>
                <p><strong>Display Name:</strong> {{ $type->display_name }}</p>
                <p><strong>Sort Order:</strong> {{ $type->sort_order }}</p>
                <p><strong>Status:</strong> <span class="badge badge-{{ $type->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($type->status) }}</span></p>
                <p><strong>Created At:</strong> {{ $type->created_at->format('M d, Y H:i') }}</p>
                <p><strong>Updated At:</strong> {{ $type->updated_at->format('M d, Y H:i') }}</p>
            </div>
        </div>
        @if($type->options->count() > 0)
        <hr>
        <h6>Associated Options:</h6>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Display Value</th>
                        <th>Value</th>
                        <th>Hex Color</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($type->options as $option)
                    <tr>
                        <td>{{ $option->display_value }}</td>
                        <td>{{ $option->value }}</td>
                        <td>
                            @if($option->hex_color)
                                <span style="background-color: {{ $option->hex_color }}; width: 20px; height: 20px; display: inline-block; border: 1px solid #ccc; border-radius: 2px;"></span>
                                {{ $option->hex_color }}
                            @else
                                <span class="text-muted">N/A</span>
                            @endif
                        </td>
                        <td>{{ $option->sort_order }}</td>
                        <td><span class="badge badge-{{ $option->status == 'active' ? 'success' : 'secondary' }}">{{ ucfirst($option->status) }}</span></td>
                        <td>
                            <a href="{{ route('variant-option.edit', $option) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <button type="button" class="btn btn-danger btn-sm dltBtn" data-id="{{ $option->id }}" data-display="{{ $option->display_value }}" data-form-action="{{ route('variant-option.destroy', $option) }}" title="Delete Option">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="alert alert-info">
            <p>No options associated yet. <a href="{{ route('variant-option.create') }}?type_id={{ $type->id }}">Add one now</a>.</p>
        </div>
        @endif
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the option "<span id="option-name"></span>"? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('backend/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> {{-- Ensure jQuery is loaded for modal --}}
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script> {{-- Bootstrap JS for modal, if not in layout --}}

<script>
$(document).ready(function() {
    $('.dltBtn').click(function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const display = $(this).data('display');
        const action = $(this).data('form-action');

        // Set modal content
        $('#option-name').text(display);
        $('#deleteForm').attr('action', action);

        // Show modal
        $('#deleteModal').modal('show');
    });

    // Handle form submission in modal
    $('#deleteForm').submit(function(e) {
        // Optional: Add loading state
        $(this).find('button[type="submit"]').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Deleting...');
    });
});
</script>
@endpush