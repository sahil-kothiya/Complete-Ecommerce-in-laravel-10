@extends('backend.layouts.master')

@section('main-content')
<!-- DataTales Example -->
<div class="card shadow mb-4">
  <div class="row">
    <div class="col-md-12">
      @include('backend.layouts.notification')
    </div>
  </div>
  <div class="card-header py-3">
    <h6 class="m-0 font-weight-bold text-primary float-left">Product Lists</h6>
    <a href="{{route('product.create')}}" class="btn btn-primary btn-sm float-right" data-toggle="tooltip" data-placement="bottom" title="Add User">
      <i class="fas fa-plus"></i> Add Product
    </a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      @if(count($products) > 0)
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
            <td>{{$product->id}}</td>
            <td>{{$product->title}}</td>
            <td>
              {{$product->cat_info['title']}}
              <sub>{{ $sub_cat_info->title ?? '' }}</sub>
            </td>
            <td>{{ $product->is_featured ? 'Yes' : 'No' }}</td>
            <td>Rs. {{$product->price}} /-</td>
            <td>{{$product->discount}}% OFF</td>
            <td>{{$product->size}}</td>
            <td>{{$product->condition}}</td>
            <td>{{ $brand->title ?? 'N/A' }}</td>
            <td>
              @if($product->stock > 0)
              <span class="badge badge-primary">{{$product->stock}}</span>
              @else
              <span class="badge badge-danger">{{$product->stock}}</span>
              @endif
            </td>
            <td>
              @if($primaryImage)
              <img src="{{ asset($primaryImage->image_path) }}" class="img-fluid zoom" style="max-width:80px;" alt="Product Image">
              @else
              <img src="{{ asset('backend/img/thumbnail-default.jpg') }}" class="img-fluid" style="max-width:80px;" alt="Default Image">
              @endif
            </td>
            <td>
              @if($product->status == 'active')
              <span class="badge badge-success">{{$product->status}}</span>
              @else
              <span class="badge badge-warning">{{$product->status}}</span>
              @endif
            </td>
            <td>
              <a href="{{route('product.edit', $product->id)}}" class="btn btn-primary btn-sm float-left mr-1" style="height:30px; width:30px; border-radius:50%" data-toggle="tooltip" title="edit"><i class="fas fa-edit"></i></a>
              <form method="POST" action="{{route('product.destroy', [$product->id])}}" class="float-left">
                @csrf
                @method('delete')
                <button class="btn btn-danger btn-sm dltBtn" data-id="{{$product->id}}" style="height:30px; width:30px; border-radius:50%" data-toggle="tooltip" title="Delete"><i class="fas fa-trash-alt"></i></button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      {{-- Laravel Pagination --}}
      <div class="row mt-3">
        <div class="col-md-12 d-flex justify-content-end">
          {{ $products->links('pagination::bootstrap-4') }}
        </div>
      </div>
      @else
      <h6 class="text-center">No Products found!!! Please create Product</h6>
      @endif
    </div>
  </div>
</div>
@endsection

@push('styles')
<link href="{{asset('backend/vendor/datatables/dataTables.bootstrap4.min.css')}}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css" />
<style>
  .zoom {
    transition: transform .2s;
  }

  .zoom:hover {
    transform: scale(5);
  }

  .table-responsive {
    overflow-x: auto;
    width: 100%;
  }
</style>
@endpush

@push('scripts')
<script src="{{asset('backend/vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('backend/vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>

<script>
  $('#product-dataTable').DataTable({
    paging: false, // ✅ Disable DataTables pagination
    info: false, // optional: hide bottom entry info
    searching: false, // optional: disable search
    scrollX: true,
    columnDefs: [{
      orderable: false,
      targets: [10, 11, 12]
    }]
  });

  $(document).ready(function() {
    $.ajaxSetup({
      headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
    });

    $('.dltBtn').click(function(e) {
      e.preventDefault();
      var form = $(this).closest('form');
      var dataID = $(this).data('id');

      swal({
          title: "Are you sure?",
          text: "Once deleted, you will not be able to recover this data!",
          icon: "warning",
          buttons: true,
          dangerMode: true,
        })
        .then((willDelete) => {
          if (willDelete) {
            form.submit();
          } else {
            swal("Your data is safe!");
          }
        });
    });
  });
</script>
@endpush