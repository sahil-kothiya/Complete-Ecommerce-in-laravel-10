@extends('backend.layouts.master')

@section('main-content')
<div class="card">
    <h5 class="card-header">Filter Management</h5>
    <div class="card-body">
        @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
        @endif
        @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($filters as $filter)
                    <tr>
                        <td>{{ $filter->id }}</td>
                        <td>{{ $filter->name }}</td>
                        <td>{{ $filter->title }}</td>
                        <td>{{ ucfirst($filter->status) }}</td>
                        <td>
                            <a href="{{ route('filter.edit', $filter->id) }}" class="btn btn-primary btn-sm">Edit</a>
                            <!-- <form action="{{ route('filter.destroy', $filter->id) }}" method="POST" style="display:inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this filter?')">Delete</button>
                            </form> -->
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $filters->links() }}
    </div>
</div>
@endsection