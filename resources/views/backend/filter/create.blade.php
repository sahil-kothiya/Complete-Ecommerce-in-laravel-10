@extends('backend.layouts.master')

@section('main-content')
<div class="card">
    <h5 class="card-header">Add Filter</h5>
    <div class="card-body">
        <form method="POST" action="{{ route('filter.store') }}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="name" class="col-form-label">Name <span class="text-danger">*</span></label>
                        <input id="name" type="text" name="name" placeholder="Enter filter name (e.g., price)" value="{{ old('name') }}" class="form-control" tabindex="1">
                        @error('name')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="title" class="col-form-label">Title <span class="text-danger">*</span></label>
                        <input id="title" type="text" name="title" placeholder="Enter filter title (e.g., Price Range)" value="{{ old('title') }}" class="form-control" tabindex="2">
                        @error('title')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="form-group">
                        <label for="description" class="col-form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" placeholder="Enter description" tabindex="3">{{ old('description') }}</textarea>
                        @error('description')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label for="status" class="col-form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" tabindex="4">
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')
                        <span class="text-danger">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-group mb-3">
                <button class="btn btn-success" type="submit" tabindex="5">Submit</button>
            </div>
        </form>
    </div>
</div>
@endsection