@extends('backend.layouts.master')

@section('main-content')
<!-- Product List Card -->
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Product Lists</h6>
        <a href="{{ route('product.create') }}" class="btn btn-primary btn-sm" data-toggle="tooltip" data-placement="bottom" title="Add Product">
            <i class="fas fa-plus"></i> Add Product
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            @if($products->count() > 0)
                <table class="table table-bordered w-100" id="product-dataTable" cellspacing="0">
                    <thead>
                        <tr>
                            <th>S.N.</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Is Featured</th>
                            <th>Price</th>
                            <th>Discount</th>
                            <th>Size</th>
                            <th>Condition</th>
                            <th>Brand</th>
                            <th>SKU</th>
                            <th>Stock</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th>S.N.</th>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Is Featured</th>
                            <th>Price</th>
                            <th>Discount</th>
                            <th>Size</th>
                            <th>Condition</th>
                            <th>Brand</th>
                            <th>SKU</th>
                            <th>Stock</th>
                            <th>Photo</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </tfoot>
                    <tbody>
                        @foreach($products as $product)
                            @php
                                $sub_cat_info = DB::table('categories')->select('title')->where('id', $product->child_cat_id)->first();
                                $brand = DB::table('brands')->select('title')->where('id', $product->brand_id)->first();
                                $primaryImage = $product->images->firstWhere('is_primary', 1);
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $product->title }}</td>
                                <td>
                                    {{ $product->cat_info['title'] }}
                                    @if($sub_cat_info)
                                        <sub>{{ $sub_cat_info->title }}</sub>
                                    @endif
                                </td>
                                <td>{{ $product->is_featured ? 'Yes' : 'No' }}</td>
                                <td>Rs. {{ number_format($product->price, 2) }} /-</td>
                                <td>{{ $product->discount }}% OFF</td>
                                <td>{{ $product->size ?? 'N/A' }}</td>
                                <td>{{ $product->condition ?? 'N/A' }}</td>
                                <td>{{ $brand->title ?? 'N/A' }}</td>
                                <td>{{ $product->sku ?? 'N/A' }}</td>
                                <td>
                                    @if($product->stock > 0)
                                        <span class="badge badge-primary">{{ $product->stock }}</span>
                                    @else
                                        <span class="badge badge-danger">{{ $product->stock }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($primaryImage)
                                        <img src="{{ asset($primaryImage->image_path) }}" 
                                             class="img-fluid zoom" 
                                             style="max-width:80px;" 
                                             alt="{{ $primaryImage->alt_text ?? $product->title . ' - Product Image' }}"
                                             onerror="this.src='{{ asset('backend/img/thumbnail-default.jpg') }}'; this.alt='Default Image';">
                                    @else
                                        <img src="{{ asset('backend/img/thumbnail-default.jpg') }}" 
                                             class="img-fluid" 
                                             style="max-width:80px;" 
                                             alt="Default Image">
                                    @endif
                                </td>
                                <td>
                                    @if($product->status == 'active')
                                        <span class="badge badge-success">{{ $product->status }}</span>
                                    @else
                                        <span class="badge badge-warning">{{ $product->status }}</span>
                                    @endif
                                </td>
                                <td class="d-flex">
                                    <a href="{{ route('product.edit', $product->id) }}" 
                                       class="btn btn-primary btn-sm mr-1" 
                                       style="height:30px; width:30px; border-radius:50%" 
                                       data-toggle="tooltip" 
                                       title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="{{ route('product.destroy', $product->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-sm dltBtn" 
                                                data-id="{{ $product->id }}" 
                                                style="height:30px; width:30px; border-radius:50%" 
                                                data-toggle="tooltip" 
                                                title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Laravel Pagination -->
                <div class="row mt-3">
                    <div class="col-md-12 d-flex justify-content-end">
                        {{ $products->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @else
                <h6 class="text-center">No Products found! Please create a product.</h6>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<link href="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert2.min.css" />
<style>
    .zoom {
        transition: transform 0.2s;
    }

    .zoom:hover {
        transform: scale(5);
        z-index: 1000;
        position: relative;
    }

    .table-responsive {
        overflow-x: auto;
        width: 100%;
    }

    .table th, .table td {
        vertical-align: middle;
    }

    .badge {
        font-size: 0.85em;
        padding: 5px 10px;
    }

    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('backend/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert2.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#product-dataTable').DataTable({
            paging: false,
            info: false,
            searching: true,
            scrollX: true,
            order: [[0, 'desc']],
            columnDefs: [{
                orderable: false,
                targets: [11, 12, 13] // Photo, Status, Action columns
            }],
            responsive: true
        });

        // CSRF Token Setup for AJAX
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Handle Delete Button Click with SweetAlert2
        $('.dltBtn').click(function(e) {
            e.preventDefault();
            const form = $(this).closest('form');
            const dataID = $(this).data('id');

            Swal.fire({
                title: 'Are you sure?',
                text: 'Once deleted, you will not be able to recover this product!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });

        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
    });
</script>
@endpush