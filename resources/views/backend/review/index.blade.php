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
		<h6 class="m-0 font-weight-bold text-primary float-left" tabindex="1">Review Lists</h6>
	</div>
	<div class="card-body">
		<div class="table-responsive">
			@if(count($reviews)>0)
			<table class="table table-bordered" id="order-dataTable" width="100%" cellspacing="0">
				<thead>
					<tr>
						<th tabindex="2">S.N.</th>
						<th tabindex="3">Review By</th>
						<th tabindex="4">Product Title</th>
						<th tabindex="5">Review</th>
						<th tabindex="6">Rate</th>
						<th tabindex="7">Date</th>
						<th tabindex="8">Status</th>
						<th tabindex="9">Action</th>
					</tr>
				</thead>
				<tbody>
					@php $tabIndex = 10; @endphp
					@foreach($reviews as $review)
					<tr>
						<td tabindex="{{ $tabIndex++ }}">{{$loop->iteration}}</td>
						<td tabindex="{{ $tabIndex++ }}">{{$review->user_info['name']}}</td>
						<td tabindex="{{ $tabIndex++ }}">{{$review->product->title}}</td>
						<td tabindex="{{ $tabIndex++ }}">{{$review->review}}</td>
						<td tabindex="{{ $tabIndex++ }}">
							<ul style="list-style:none">
								@for($i=1; $i<=5;$i++)
									@if($review->rate >=$i)
									<li style="float:left;color:#F7941D;" tabindex="{{ $tabIndex++ }}"><i class="fa fa-star"></i></li>
									@else
									<li style="float:left;color:#F7941D;" tabindex="{{ $tabIndex++ }}"><i class="far fa-star"></i></li>
									@endif
								@endfor
							</ul>
						</td>
						<td tabindex="{{ $tabIndex++ }}">{{$review->created_at->format('M d D, Y g: i a')}}</td>
						<td tabindex="{{ $tabIndex++ }}">
							@if($review->status=='active')
							<span class="badge badge-success">{{$review->status}}</span>
							@else
							<span class="badge badge-warning">{{$review->status}}</span>
							@endif
						</td>
						<td>
							<a href="{{route('review.edit',$review->id)}}" 
							   class="btn btn-primary btn-sm float-left mr-1" 
							   style="height:30px; width:30px;border-radius:50%" 
							   data-toggle="tooltip" 
							   title="edit" 
							   data-placement="bottom"
							   tabindex="{{ $tabIndex++ }}">
								<i class="fas fa-edit"></i>
							</a>
							<form method="POST" action="{{route('review.destroy',[$review->id])}}">
								@csrf
								@method('delete')
								<button class="btn btn-danger btn-sm dltBtn" 
								        data-id={{$review->id}} 
								        style="height:30px; width:30px;border-radius:50%" 
								        data-toggle="tooltip" 
								        data-placement="bottom" 
								        title="Delete"
								        tabindex="{{ $tabIndex++ }}">
									<i class="fas fa-trash-alt"></i>
								</button>
							</form>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
			<span style="float:right" tabindex="{{ $tabIndex++ }}">{{$reviews->links()}}</span>
			@else
			<h6 class="text-center" tabindex="2">No reviews found!!!</h6>
			@endif
		</div>
	</div>
</div>
@endsection

@push('styles')
<link href="{{asset('backend/vendor/datatables/dataTables.bootstrap4.min.css')}}" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css" />
<style>
	div.dataTables_wrapper div.dataTables_paginate {
		display: none;
	}
</style>
@endpush

@push('scripts')

<!-- Page level plugins -->
<script src="{{asset('backend/vendor/datatables/jquery.dataTables.min.js')}}"></script>
<script src="{{asset('backend/vendor/datatables/dataTables.bootstrap4.min.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>

<!-- Page level custom scripts -->
<script src="{{asset('backend/js/demo/datatables-demo.js')}}"></script>
<script>
	$('#order-dataTable').DataTable({
		"columnDefs": [{
			"orderable": false,
			"targets": [5, 6]
		}]
	});

	// Sweet alert

	function deleteData(id) {

	}
</script>
<script>
	$(document).ready(function() {
		$.ajaxSetup({
			headers: {
				'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
			}
		});
		$('.dltBtn').click(function(e) {
			var form = $(this).closest('form');
			var dataID = $(this).data('id');
			// alert(dataID);
			e.preventDefault();
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
		})
	})
</script>
@endpush