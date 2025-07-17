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
		<h6 class="m-0 font-weight-bold text-primary float-left">Discount List</h6>
		<a href="{{ route('discount.create') }}" class="btn btn-primary btn-sm float-right" data-toggle="tooltip" title="Add Discount">
			<i class="fas fa-plus"></i> Add Discount
		</a>
	</div>
	<div class="card-body">
		<div class="table-responsive">
			@if(count($discounts) > 0)
			<table class="table table-bordered" id="discount-dataTable" width="100%" cellspacing="0">
				<thead>
					<tr>
						<th>S.N.</th>
						<th>Title</th>
						<th>Type</th>
						<th>Value</th>
						<th>Start</th>
						<th>End</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</thead>
				<tfoot>
					<tr>
						<th>S.N.</th>
						<th>Title</th>
						<th>Type</th>
						<th>Value</th>
						<th>Start</th>
						<th>End</th>
						<th>Status</th>
						<th>Action</th>
					</tr>
				</tfoot>
				<tbody>
					@foreach($discounts as $discount)
					<tr>
						<td>{{ $loop->iteration + ($discounts->currentPage() - 1) * $discounts->perPage() }}</td>
						<td>{{ $discount->title }}</td>
						<td>{{ ucfirst($discount->type) }}</td>
						<td>
							{{ $discount->type === 'percentage' ? $discount->value . '%' : '$' . number_format($discount->value, 2) }}
						</td>
						<td>{{ $discount->starts_at->format('d M Y, h:i A') }}</td>
						<td>{{ $discount->ends_at->format('d M Y, h:i A') }}</td>
						<td>
							@if($discount->is_active)
							<span class="badge badge-success">Active</span>
							@else
							<span class="badge badge-secondary">Inactive</span>
							@endif
						</td>
						<td>
							<a href="{{ route('discount.edit', $discount->id) }}" class="btn btn-primary btn-sm mr-1" style="height:30px; width:30px; border-radius:50%;" title="Edit">
								<i class="fas fa-edit"></i>
							</a>
							<form method="POST" action="{{ route('discount.destroy', $discount->id) }}" style="display:inline;">
								@csrf
								@method('DELETE')
								<button class="btn btn-danger btn-sm dltBtn" data-id="{{ $discount->id }}" style="height:30px; width:30px; border-radius:50%;" title="Delete">
									<i class="fas fa-trash-alt"></i>
								</button>
							</form>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
			<span class="float-right">{{ $discounts->links() }}</span>
			@else
			<h6 class="text-center">No discounts found! Please create one.</h6>
			@endif
		</div>
	</div>
</div>
@endsection

@push('styles')
<link href="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.css') }}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css" />
@endpush

@push('scripts')
<script src="{{ asset('backend/vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('backend/vendor/datatables/dataTables.bootstrap4.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script src="{{ asset('backend/js/demo/datatables-demo.js') }}"></script>

<script>
	$('#discount-dataTable').DataTable({
		"columnDefs": [{
			"orderable": false,
			"targets": [7]
		}]
	});

	$(document).ready(function() {
		$('.dltBtn').click(function(e) {
			var form = $(this).closest('form');
			var dataID = $(this).data('id');
			e.preventDefault();
			swal({
					title: "Are you sure?",
					text: "Once deleted, this discount cannot be recovered!",
					icon: "warning",
					buttons: true,
					dangerMode: true,
				})
				.then((willDelete) => {
					if (willDelete) {
						form.submit();
					} else {
						swal("Your discount is safe!");
					}
				});
		});
	});
</script>
@endpush