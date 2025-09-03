@extends('backend.layouts.master')
@section('title', 'E-SHOP | Brand Management')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Brand List</h6>
        <a href="{{ route('brand.create') }}" class="btn btn-primary btn-sm" data-toggle="tooltip" data-placement="bottom" title="Add Brand" tabindex="1">
            <i class="fas fa-plus"></i> Add Brand
        </a>
    </div>
    
    @include('backend.layouts.notification')

    <div class="card-body">
        <div class="table-responsive">
            @if($brands->isNotEmpty())
                <table class="table table-bordered" id="brand-dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($brands as $brand)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $brand->title }}</td>
                                <td>{{ $brand->slug }}</td>
                                <td>
                                    <span class="badge {{ $brand->status === 'active' ? 'badge-success' : 'badge-warning' }}">{{ $brand->status }}</span>
                                </td>
                                <td class="d-flex">
                                    <a href="{{ route('brand.edit', $brand->id) }}" class="btn btn-primary btn-sm mr-1 rounded-circle" style="width: 30px; height: 30px;" data-toggle="tooltip" title="Edit" data-placement="bottom" tabindex="{{ 2 + ($loop->iteration - 1) * 2 }}">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('brand.destroy', $brand->id) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm rounded-circle dltBtn" data-id="{{ $brand->id }}" style="width: 30px; height: 30px;" data-toggle="tooltip" title="Delete" data-placement="bottom" tabindex="{{ 3 + ($loop->iteration - 1) * 2 }}">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="d-flex justify-content-end">{{ $brands->links() }}</div>
            @else
                <h6 class="text-center">No brands found! Please create a brand.</h6>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.css" />
<style>
    .rounded-circle { transition: transform 0.2s; }
    .rounded-circle:hover { transform: scale(1.2); }
</style>
@endpush

@push('scripts')
<script src="{{ asset('backend/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
    $(document).ready(function () {
        // Initialize DataTable
        $('#brand-dataTable').DataTable({
            columnDefs: [{ orderable: false, targets: [3, 4] }]
        });

        // CSRF Token Setup
        $.ajaxSetup({
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
        });

        // Delete Confirmation with SweetAlert
        $('.dltBtn').click(function (e) {
            e.preventDefault();
            const form = $(this).closest('form');
            const dataID = $(this).data('id');

            swal({
                title: "Are you sure?",
                text: "Once deleted, this data cannot be recovered!",
                icon: "warning",
                buttons: true,
                dangerMode: true
            }).then((willDelete) => {
                if (willDelete) {
                    form.submit();
                }
            });
        });
    });
</script>
@endpush