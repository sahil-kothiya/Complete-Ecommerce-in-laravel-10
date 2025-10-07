@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Variant Options</h6>
        <a href="{{ route('variant-option.create') }}" class="btn btn-primary btn-sm" data-toggle="tooltip" title="Add New Option">
            <i class="fas fa-plus"></i> Add Option
        </a>
    </div>
    <div class="card-body">
        @include('backend.layouts.notification')
        <div class="table-responsive">
            @if($options->count() > 0)
            <table class="table table-bordered w-100" id="options-dataTable">
                <thead>
                    <tr>
                        <th>S.N.</th>
                        <th>Type</th>
                        <th>Display Value</th>
                        <th>Value</th>
                        <th>Hex Color</th>
                        <th>Sort Order</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($options as $option)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $option->variantType->display_name }}</td>
                        <td>{{ $option->display_value }}</td>
                        <td>{{ $option->value }}</td>
                        <td><span style="background-color: {{ $option->hex_color }}; width: 20px; height: 20px; display: inline-block;"></span></td>
                        <td>{{ $option->sort_order }}</td>
                        <td>
                            @if($option->status == 'active')
                                <span class="badge badge-success">Active</span>
                            @else
                                <span class="badge badge-danger">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $option->created_at->format('M d, Y') }}</td>
                        <td>
                            <a href="{{ route('variant-option.edit', $option->id) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i></a>
                            <form action="{{ route('variant-option.destroy', $option->id) }}" method="POST" class="d-inline">
                                @csrf @method('DELETE')
                                <button type="button" class="btn btn-danger btn-sm dltBtn" data-id="{{ $option->id }}"><i class="fas fa-trash-alt"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p class="text-center">No variant options found.</p>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Same DataTable and SweetAlert script as types/index -->
<script>
$(document).ready(function() {
    $('#options-dataTable').DataTable({
        paging: true,
        info: true,
        searching: true,
        responsive: true,
        order: [[0, 'desc']],
        columnDefs: [{ orderable: false, targets: [8] }]
    });
    // SweetAlert delete handler (same as above)
});
</script>
@endpush