@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Variant Types</h6>
        <a href="{{ route('variant-type.create') }}" class="btn btn-primary btn-sm" data-toggle="tooltip" title="Add New Type">
            <i class="fas fa-plus"></i> Add Type
        </a>
    </div>
    <div class="card-body">
        @include('backend.layouts.notification')
        <div class="table-responsive">
            @if($types->count() > 0)
            <table class="table table-bordered w-100" id="types-dataTable">
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Name</th>
                        <th>Display Name</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($types as $type)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $type->name }}</td>
                        <td>{{ $type->display_name }}</td>
                        <td>{{ $type->sort_order }}</td>
                        <td>
                            @if($type->status == 'active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('variant-type.show', $type->id) }}" class="btn btn-info btn-sm" data-toggle="tooltip" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="{{ route('variant-type.edit', $type->id) }}" class="btn btn-primary btn-sm" data-toggle="tooltip" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('variant-type.destroy', $type->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="button" class="btn btn-danger btn-sm dltBtn" data-id="{{ $type->id }}" data-toggle="tooltip" title="Delete">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-center">No variant types found.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert2.min.css" />

<style>
    .table th, .table td { vertical-align: middle; }
    .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('backend/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    $('#types-dataTable').DataTable({
        paging: true,
        info: true,
        searching: true,
        responsive: true,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [6] }] // Actions column
    });

    $('.dltBtn').click(function(e) {
        e.preventDefault();
        const form = $(this).closest('.delete-form');
        const id = $(this).data('id');

        Swal.fire({
            title: 'Are you sure?',
            text: 'Once deleted, you will not be able to recover this type! (Options will be cascaded deleted.)',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Submit via AJAX for better error handling
                const formData = new FormData(form[0]);
                $.ajax({
                    url: form.attr('action'),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        Swal.fire('Deleted!', 'Variant type has been deleted.', 'success');
                        form.closest('tr').fadeOut();  // Remove row from table
                    },
                    error: function(xhr) {
                        const errorMsg = xhr.responseJSON?.message || 'Failed to delete. Please try again.';
                        Swal.fire('Error!', errorMsg, 'error');
                    }
                });
            }
        });
    });

    $('[data-toggle="tooltip"]').tooltip();
});
</script>
@endpush