@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Edit Variant Option</h6>
    </div>
    <div class="card-body">
        @include('backend.layouts.notification')
        <form action="{{ route('variant-option.update', $option->id) }}" method="POST">
            @csrf
            @method('PUT')
            <!-- Same form fields as create.blade.php, but value="{{ old('field', $option->field) }}" -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="variant_type_id">Type <span class="text-danger">*</span></label>
                        <select name="variant_type_id" id="variant_type_id" class="form-control @error('variant_type_id') is-invalid @enderror" required>
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}" {{ old('variant_type_id', $option->variant_type_id) == $type->id ? 'selected' : '' }}>{{ $type->display_name }}</option>
                            @endforeach
                        </select>
                        @error('variant_type_id')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
                <!-- ... Repeat for other fields with old('field', $option->field) ... -->
            </div>
            <!-- ... Rest of form ... -->
            <button type="submit" class="btn btn-primary">Update Option</button>
            <a href="{{ route('variant-option.index') }}" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>
@endsection