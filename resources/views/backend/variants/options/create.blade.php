@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Add New Variant Option</h6>
    </div>
    <div class="card-body">
        @include('backend.layouts.notification')
        <form action="{{ route('variant-option.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="variant_type_id">Type <span class="text-danger">*</span></label>
                        <select name="variant_type_id" id="variant_type_id" class="form-control @error('variant_type_id') is-invalid @enderror" required>
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ old('variant_type_id') == $type->id ? 'selected' : '' }}>{{ $type->display_name }}</option>
                            @endforeach
                        </select>
                        @error('variant_type_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="display_value">Display Value <span class="text-danger">*</span></label>
                        <input type="text" name="display_value" id="display_value" class="form-control @error('display_value') is-invalid @enderror" value="{{ old('display_value') }}" required>
                        @error('display_value')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="value">Value <span class="text-danger">*</span></label>
                        <input type="text" name="value" id="value" class="form-control @error('value') is-invalid @enderror" value="{{ old('value') }}" required>
                        @error('value')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="hex_color">Hex Color (Optional)</label>
                        <input type="color" name="hex_color" id="hex_color" class="form-control @error('hex_color') is-invalid @enderror" value="{{ old('hex_color') }}">
                        @error('hex_color')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" class="form-control" value="{{ old('sort_order', 0) }}" min="0">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select name="status" id="status" class="form-control">
                            <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Save Option</button>
            <a href="{{ route('variant-option.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection