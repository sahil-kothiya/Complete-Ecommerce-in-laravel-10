@extends('backend.layouts.master')

@section('main-content')
<!-- Main content area for adding a new product -->
<div class="card shadow-sm border-0">
    <h5 class="card-header">Add New Product</h5>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('product.store') }}" enctype="multipart/form-data" id="product-form">
            @csrf
            <!-- Core Product Details Section -->
            <section class="mb-5">
                <h6 class="mb-3 text-uppercase font-weight-bold">Core Product Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="inputTitle">Title <span class="text-danger">*</span></label>
                            <input type="text" id="inputTitle" name="title" value="{{ old('title') }}" class="form-control" placeholder="Enter product title" required tabindex="1">
                            @error('title')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="slug">Slug <span class="text-danger">*</span> <small class="text-muted">(Auto-generated)</small></label>
                            <input type="text" id="slug" name="slug" value="{{ old('slug') }}" class="form-control" placeholder="product-slug" readonly required tabindex="2">
                            @error('slug')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="summary">Summary <span class="text-danger">*</span></label>
                            <textarea id="summary" name="summary" class="form-control summernote" rows="3" tabindex="3">{{ old('summary') }}</textarea>
                            @error('summary')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control summernote" rows="5" tabindex="4">{{ old('description') }}</textarea>
                            @error('description')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <!-- Category and Brand Section -->
            <section class="mb-5">
                <h6 class="mb-3 text-uppercase font-weight-bold">Category & Brand</h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cat_id">Category <span class="text-danger">*</span></label>
                            <select name="cat_id" id="cat_id" class="form-control" required tabindex="5">
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('cat_id') == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                                @endforeach
                            </select>
                            @error('cat_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4 d-none" id="child_cat_div">
                        <div class="form-group">
                            <label for="child_cat_id">Sub Category</label>
                            <select name="child_cat_id" id="child_cat_id" class="form-control" tabindex="6">
                                <option value="">Select Sub Category</option>
                            </select>
                            @error('child_cat_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="brand_id">Brand</label>
                            <div class="input-group">
                                <select name="brand_id" id="brand_id" class="form-control selectpicker" data-live-search="true" tabindex="7">
                                    <option value="">Select Brand</option>
                                    @foreach($brands as $brand)
                                    <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" id="addBrandBtn" class="btn btn-outline-primary" tabindex="8"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            @error('brand_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <!-- Product Attributes (Non-Variant) Section -->
            <section class="mb-5" id="non-variant-section">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Attributes (Non-Variant)</h6>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_price">Price (NRS) <span class="text-danger">*</span></label>
                            <input type="number" id="base_price" name="base_price" step="0.01" min="0" value="{{ old('base_price') }}" class="form-control" placeholder="Enter price" required tabindex="9">
                            @error('base_price')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_discount">Discount (%)</label>
                            <input type="number" id="base_discount" name="base_discount" min="0" max="100" value="{{ old('base_discount') }}" class="form-control" placeholder="Enter discount" tabindex="10">
                            @error('base_discount')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_stock">Stock <span class="text-danger">*</span></label>
                            <input type="number" id="base_stock" name="base_stock" min="0" value="{{ old('base_stock') }}" class="form-control" placeholder="Enter stock" required tabindex="11">
                            @error('base_stock')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_sku">SKU <span class="text-danger">*</span></label>
                            <input type="text" id="base_sku" name="base_sku" value="{{ old('base_sku') }}" class="form-control" placeholder="Enter unique SKU" required tabindex="12">
                            @error('base_sku')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="condition">Condition</label>
                            <select name="condition" id="condition" class="form-control" tabindex="13">
                                <option value="default" {{ old('condition') == 'default' ? 'selected' : '' }}>Default</option>
                                <option value="new" {{ old('condition') == 'new' ? 'selected' : '' }}>New</option>
                                <option value="hot" {{ old('condition') == 'hot' ? 'selected' : '' }}>Hot</option>
                            </select>
                            @error('condition')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="size">Sizes</label>
                            <select name="size[]" id="size" class="form-control selectpicker" multiple data-live-search="true" tabindex="14">
                                @foreach(['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
                                <option value="{{ $key }}" {{ in_array($key, old('size', [])) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('size')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group form-check mt-4">
                            <input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured') ? 'checked' : '' }} tabindex="15">
                            <label for="is_featured" class="form-check-label">Featured Product</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required tabindex="16">
                                <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <!-- Images Section (Non-Variant) -->
            <section class="mb-5" id="non-variant-images">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Images</h6>
                <div class="form-group">
                    <label for="photo">Photos <span class="text-danger">*</span> <small class="text-muted">(Select at least one image)</small></label>
                    <div class="input-group">
                        <input type="text" id="photo" name="photo" class="form-control" value="{{ old('photo') }}" placeholder="Comma-separated image URLs" readonly required tabindex="17">
                        <div class="input-group-append">
                            <a id="lfm" data-input="photo" data-preview="holder" class="btn btn-primary"><i class="fa fa-picture-o"></i> Choose Images</a>
                        </div>
                    </div>
                    @error('photo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    <div id="image-preview-area" class="mt-3 d-flex flex-wrap gap-3"></div>
                </div>
                <div class="form-group form-check" id="alt-text-toggle" style="display: none;">
                    <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input" tabindex="18">
                    <label for="enable_alt_text" class="form-check-label">Enable Alt Text for Images <small class="text-muted">(Improves SEO & Accessibility)</small></label>
                </div>
                <div id="alt-text-section" class="row mt-3" style="display: none;"></div>
            </section>

            <!-- Variants Section -->
            <section class="mb-5">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Variants</h6>
                <div class="form-group form-check">
                    <input type="checkbox" name="has_variants" id="has_variants" value="1" class="form-check-input" {{ old('has_variants') ? 'checked' : '' }} tabindex="19">
                    <label for="has_variants" class="form-check-label">Enable Variants <small class="text-muted">(e.g., Colors, Sizes)</small></label>
                </div>
                <div id="variants-panel" class="p-4 bg-light rounded" style="display: none;">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold">Select Variant Types & Options</label>
                            <button type="button" id="load-types" class="btn btn-secondary btn-sm">Load Variant Types</button>
                        </div>
                        <div id="type-selections" class="d-flex flex-wrap gap-3"></div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold">Generated Variants</label>
                            <button type="button" id="generate-preview" class="btn btn-info btn-sm">Generate Variants</button>
                        </div>
                        <div id="variant-preview" class="table-responsive"></div>
                    </div>
                </div>
            </section>

            <!-- Submit Button -->
            <div class="form-group text-right">
                <button type="submit" class="btn btn-success btn-lg" tabindex="20">Add Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1" aria-labelledby="addBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form id="addBrandForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBrandModalLabel">Add New Brand</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_brand_title">Brand Name <span class="text-danger">*</span></label>
                        <input type="text" name="title" id="new_brand_title" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save Brand</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .card {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .form-control,
    .btn {
        border-radius: 0.25rem;
    }

    .form-control.is-invalid {
        border-color: #dc3545;
    }

    .invalid-feedback {
        font-size: 0.875rem;
        color: #dc3545;
    }

    #image-preview-area {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .image-container {
        position: relative;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        padding: 0.5rem;
        background: #fff;
    }

    .image-preview {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 0.25rem;
    }

    .image-not-found {
        width: 120px;
        height: 120px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px dashed #dee2e6;
        border-radius: 0.25rem;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 0.875rem;
    }

    .primary-badge {
        position: absolute;
        top: 0.25rem;
        left: 0.25rem;
    }

    .alt-text-item {
        background: #f8f9fa;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        margin-bottom: 1rem;
    }

    .alt-text-preview {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 0.25rem;
        margin-right: 1rem;
    }

    .alt-text-fallback {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px dashed #dee2e6;
        border-radius: 0.25rem;
        background: #f8f9fa;
        color: #6c757d;
        font-size: 0.75rem;
        text-align: center;
    }

    .char-count {
        font-size: 0.875rem;
    }

    .variant-type-group {
        flex: 1 1 200px;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        transition: all 0.2s;
    }

    .variant-type-group:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .table th,
    .table td {
        vertical-align: middle;
    }

    .btn-success,
    .btn-primary,
    .btn-info,
    .btn-secondary {
        transition: all 0.2s;
    }

    .btn-success:hover,
    .btn-primary:hover,
    .btn-info:hover,
    .btn-secondary:hover {
        transform: translateY(-2px);
    }

    .notification-toast {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .select2-container .select2-selection--multiple {
        min-height: 38px;
        border: 1px solid #ced4da;
        border-radius: 0.25rem;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #007bff;
        border-color: #007bff;
        color: #fff;
    }

    @media (max-width: 768px) {
        #type-selections {
            flex-direction: column;
        }

        .variant-type-group {
            width: 100%;
        }

        .image-preview,
        .image-not-found {
            width: 100px;
            height: 100px;
        }
    }
</style>
@endpush

@push('scripts')
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    $(document).ready(function() {
        // Initialize Summernote
        $('.summernote').summernote({
            height: 200,
            tabsize: 2,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link']],
                ['view', ['fullscreen', 'codeview']]
            ]
        });

        // Initialize File Manager
        $('#lfm').filemanager('image');
        $('.lfm-variant').each(function() {
            $(this).filemanager('image');
        });

        // Auto-generate slug
        $('#inputTitle').on('input', function() {
            const title = $(this).val().trim();
            const slug = title.toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-')
                .substring(0, 100);
            $('#slug').val(slug);
        });

        // Toggle variants panel
        $('#has_variants').change(function() {
            const isChecked = this.checked;
            $('#variants-panel').toggle(isChecked);
            $('#non-variant-section, #non-variant-images').toggle(!isChecked);
            if (isChecked) {
                $('#base_price, #base_discount, #base_stock, #base_sku, #photo').removeAttr('required');
                $('#load-types').click();
            } else {
                $('#base_price, #base_stock, #base_sku, #photo').attr('required', true);
            }
        });

        // Initialize variant state
        if ($('#has_variants').is(':checked')) {
            $('#has_variants').trigger('change');
        }

        // Load variant types
        $('#load-types').click(function() {
            $.get('{{ route("variant-type.api") }}', function(types) {
                let html = '';
                types.forEach(type => {
                    html += `
                        <div class="variant-type-group">
                            <label class="font-weight-bold">${type.display_name}</label>
                            <select class="form-control type-select" data-type-id="${type.id}" multiple name="variant_options[${type.id}][]"></select>
                        </div>`;
                });
                $('#type-selections').html(html);
                $('.type-select').each(function() {
                    const typeId = $(this).data('type-id');
                    // Initialize Select2 for each variant type select
                    $(this).select2({
                        placeholder: `Select ${$(this).prev().text()} options`,
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('#variants-panel')
                    });
                    $.get(`/admin/variant-options/${typeId}/api`, function(options) {
                        let optHtml = '<option value="">Select Options</option>';
                        options.forEach(opt => {
                            optHtml += `<option value="${opt.id}">${opt.display_value}</option>`;
                        });
                        $(`select[data-type-id="${typeId}"]`).html(optHtml).trigger('change');
                    }).fail(() => showNotification('Failed to load variant options.', 'error'));
                });
            }).fail(() => showNotification('Failed to load variant types.', 'error'));
        });

        // Generate variant preview
        $('#generate-preview').click(function() {
            const selections = {};
            $('.type-select').each(function() {
                const selected = $(this).val() || [];
                if (selected.length) selections[$(this).data('type-id')] = selected;
            });
            if (!Object.keys(selections).length) {
                showNotification('Please select at least one variant type and option.', 'error');
                return;
            }
            $.post('{{ route("product.preview-variants") }}', {
                _token: '{{ csrf_token() }}',
                selections: selections
            }, function(response) {
                let html = `
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Variant Name</th>
                                <th>SKU <span class="text-danger">*</span></th>
                                <th>Price (NRS) <span class="text-danger">*</span></th>
                                <th>Discount (%)</th>
                                <th>Stock <span class="text-danger">*</span></th>
                                <th>Images <span class="text-danger">*</span></th>
                            </tr>
                        </thead>
                        <tbody>`;
                response.variants.forEach((variant, idx) => {
                    html += `
                        <tr>
                            <td>${variant.name}</td>
                            <td><input type="text" name="variants[${idx}][sku]" value="${variant.sku || ''}" class="form-control" required></td>
                            <td><input type="number" name="variants[${idx}][price]" step="0.01" min="0" value="${variant.price || ''}" class="form-control" required></td>
                            <td><input type="number" name="variants[${idx}][discount]" min="0" max="100" value="${variant.discount || ''}" class="form-control"></td>
                            <td><input type="number" name="variants[${idx}][stock]" min="0" value="${variant.stock || ''}" class="form-control" required></td>
                            <td>
                                <div class="input-group">
                                    <input type="text" name="variants[${idx}][images]" id="variant-images-${idx}" class="form-control" readonly required>
                                    <div class="input-group-append">
                                        <a class="btn btn-primary lfm-variant" data-input="variant-images-${idx}" data-preview="variant-holder-${idx}"><i class="fa fa-picture-o"></i> Choose</a>
                                    </div>
                                </div>
                                <div id="variant-holder-${idx}" class="mt-2 d-flex flex-wrap gap-2"></div>
                            </td>
                        </tr>`;
                });
                html += `</tbody></table>`;
                $('#variant-preview').html(html);
                $('.lfm-variant').filemanager('image');
            }).fail(() => showNotification('Failed to generate variants.', 'error'));
        });

        // Form validation
        $('#product-form').submit(function(e) {
            // Remove previous custom validation errors
            $('.custom-invalid-feedback').remove();
            $('.form-control').removeClass('custom-is-invalid');

            let valid = true;
            const hasVariants = $('#has_variants').is(':checked');

            // Basic required fields
            if (!$('#inputTitle').val().trim()) {
                valid = false;
                showFieldError('#inputTitle', 'Title is required.');
            }

            if (!$('#slug').val().trim()) {
                valid = false;
                showFieldError('#slug', 'Slug is required.');
            }

            if (!$('#summary').summernote('code').trim() || $('#summary').summernote('code') === '<p><br></p>') {
                valid = false;
                showFieldError('#summary', 'Summary is required.');
            }

            if (!$('#cat_id').val()) {
                valid = false;
                showFieldError('#cat_id', 'Category is required.');
            }

            if (!$('#status').val()) {
                valid = false;
                showFieldError('#status', 'Status is required.');
            }

            // Variant-specific validation
            if (hasVariants) {
                const variantRows = $('#variant-preview tbody tr');

                if (variantRows.length === 0) {
                    valid = false;
                    showNotification('Please generate variants before submitting.', 'error');
                    e.preventDefault();
                    return false;
                }

                variantRows.each(function(index) {
                    const row = $(this);
                    const sku = row.find('input[name*="[sku]"]').val();
                    const price = row.find('input[name*="[price]"]').val();
                    const stock = row.find('input[name*="[stock]"]').val();
                    const images = row.find('input[name*="[images]"]').val();
                    const discount = row.find('input[name*="[discount]"]').val();

                    if (!sku || !sku.trim()) {
                        valid = false;
                        row.find('input[name*="[sku]"]').addClass('custom-is-invalid');
                        showNotification(`SKU is required for variant ${index + 1}`, 'error');
                    }

                    if (!price || parseFloat(price) < 0) {
                        valid = false;
                        row.find('input[name*="[price]"]').addClass('custom-is-invalid');
                        showNotification(`Valid price is required for variant ${index + 1}`, 'error');
                    }

                    if (!stock || parseInt(stock) < 0) {
                        valid = false;
                        row.find('input[name*="[stock]"]').addClass('custom-is-invalid');
                        showNotification(`Valid stock is required for variant ${index + 1}`, 'error');
                    }

                    if (!images || !images.trim()) {
                        valid = false;
                        row.find('input[name*="[images]"]').addClass('custom-is-invalid');
                        showNotification(`Images are required for variant ${index + 1}`, 'error');
                    }

                    if (discount && (parseFloat(discount) < 0 || parseFloat(discount) > 100)) {
                        valid = false;
                        row.find('input[name*="[discount]"]').addClass('custom-is-invalid');
                        showNotification(`Discount must be between 0 and 100 for variant ${index + 1}`, 'error');
                    }
                });
            } else {
                // Non-variant validation
                const basePrice = $('#base_price').val();
                const baseStock = $('#base_stock').val();
                const baseSku = $('#base_sku').val();
                const photo = $('#photo').val();
                const baseDiscount = $('#base_discount').val();

                if (!basePrice || parseFloat(basePrice) <= 0) {
                    valid = false;
                    showFieldError('#base_price', 'Price must be greater than 0.');
                }

                if (!baseStock || parseInt(baseStock) < 0) {
                    valid = false;
                    showFieldError('#base_stock', 'Stock cannot be negative.');
                }

                if (!baseSku || !baseSku.trim()) {
                    valid = false;
                    showFieldError('#base_sku', 'SKU is required.');
                }

                if (!photo || !photo.trim()) {
                    valid = false;
                    showFieldError('#photo', 'At least one image is required.');
                }

                if (baseDiscount && (parseFloat(baseDiscount) < 0 || parseFloat(baseDiscount) > 100)) {
                    valid = false;
                    showFieldError('#base_discount', 'Discount must be between 0 and 100.');
                }

                // Alt text validation
                if ($('#enable_alt_text').is(':checked')) {
                    let altTextValid = true;
                    $('textarea[name^="alt_text["]').each(function() {
                        const val = $(this).val().trim();
                        if (!val) {
                            altTextValid = false;
                            $(this).addClass('custom-is-invalid');
                            $(this).after('<span class="custom-invalid-feedback text-danger d-block">Alt text is required.</span>');
                        } else if (val.length > 125) {
                            altTextValid = false;
                            $(this).addClass('custom-is-invalid');
                            $(this).after('<span class="custom-invalid-feedback text-danger d-block">Alt text must not exceed 125 characters.</span>');
                        }
                    });
                    if (!altTextValid) {
                        valid = false;
                    }
                }
            }

            if (!valid) {
                e.preventDefault();
                showNotification('Please fix all validation errors before submitting.', 'error');
                $('html, body').animate({
                    scrollTop: $('.custom-is-invalid:first').offset().top - 100
                }, 500);
                return false;
            }

            // Update Summernote content before submission
            $('#summary').val($('#summary').summernote('code'));
            $('#description').val($('#description').summernote('code'));

            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        });

        function showFieldError(selector, message) {
            const $field = $(selector);
            $field.addClass('custom-is-invalid');

            // Remove any existing custom error message for this field
            $field.siblings('.custom-invalid-feedback').remove();

            // Add new error message
            $field.after(`<span class="custom-invalid-feedback text-danger d-block mt-1">${message}</span>`);
        }

        function showNotification(message, type = 'info') {
            $('.notification-toast').remove();
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'error' ? 'alert-danger' : 'alert-info';

            const $notification = $(`
            <div class="alert ${alertClass} notification-toast alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
            </div>
        `);

            $('body').append($notification);

            setTimeout(() => {
                $notification.fadeOut(400, () => $notification.remove());
            }, 5000);
        }

        // Clear custom validation errors on input
        $(document).on('input change', '.custom-is-invalid', function() {
            $(this).removeClass('custom-is-invalid');
            $(this).siblings('.custom-invalid-feedback').remove();
        });

        // Variant form validation helper
        $(document).on('blur', '#variant-preview input[required]', function() {
            const $input = $(this);
            const val = $input.val().trim();
            const name = $input.attr('name');

            $input.removeClass('custom-is-invalid');

            if (!val) {
                $input.addClass('custom-is-invalid');
            } else if (name.includes('discount')) {
                const discount = parseFloat(val);
                if (discount < 0 || discount > 100) {
                    $input.addClass('custom-is-invalid');
                }
            } else if (name.includes('price') || name.includes('stock')) {
                const num = parseFloat(val);
                if (num < 0) {
                    $input.addClass('custom-is-invalid');
                }
            }
        });

        function updateImagePreview() {
            const imageInput = $('#photo').val().trim();
            const $holder = $('#image-preview-area');
            const $altToggle = $('#alt-text-toggle');
            const $altSection = $('#alt-text-section');
            const $altContainer = $('#alt-text-section');

            $holder.empty();
            $altContainer.empty();

            if (!imageInput) {
                $holder.hide();
                $altToggle.hide();
                $altSection.hide();
                return;
            }

            const images = imageInput.split(',').map(url => url.trim()).filter(url => url);
            $holder.show();
            $altToggle.show();

            images.forEach((url, index) => {
                const container = $('<div class="image-container"></div>');
                const img = $('<img />', {
                    src: url,
                    class: 'image-preview',
                    alt: `Product Image ${index + 1}`,
                    'data-index': index,
                    'data-is-primary': index === 0,
                    'data-fallback-text': `Product Image ${index + 1}`
                });

                if (index === 0) {
                    container.append('<div class="primary-badge"><span class="badge badge-success">Primary</span></div>');
                }

                img.on('error', function() {
                    const fallback = $('<div class="image-not-found"><i class="fa fa-image"></i><span>Image not available</span></div>');
                    container.append(fallback);
                    $(this).remove();
                });

                container.append(img);
                $holder.append(container);

                if ($('#enable_alt_text').is(':checked')) {
                    createAltTextInput(url, index, $altContainer);
                }
            });

            if ($('#enable_alt_text').is(':checked')) {
                $altSection.show();
            }
        }

        function createAltTextInput(imageUrl, index, container) {
            const productTitle = $('#inputTitle').val() || 'Product';
            const suggestedAlt = `${productTitle} - ${index === 0 ? 'Main Image' : `Image ${index + 1}`}`;
            const isPrimary = index === 0;
            const tabIndex = 21 + index;

            const html = `
                <div class="col-md-6 alt-text-item" data-index="${index}">
                    <div class="d-flex align-items-start">
                        <img src="${imageUrl}" class="alt-text-preview mr-3" alt="Preview" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="alt-text-fallback d-none"><i class="fa fa-image"></i><span>Image not available</span></div>
                        <div class="flex-fill">
                            <label class="font-weight-bold">Alt Text for Image ${index + 1} ${isPrimary ? '<span class="badge badge-success badge-sm ml-1">Primary</span>' : ''}</label>
                            <textarea name="alt_text[${index}]" class="form-control alt-text-input" placeholder="Describe this image (e.g., ${suggestedAlt})" data-index="${index}" tabindex="${tabIndex}">${suggestedAlt}</textarea>
                            <small class="form-text text-muted">Max 125 characters. Include product name for SEO.</small>
                            <div class="mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" data-index="${index}" tabindex="${tabIndex + 1}">Auto-generate</button>
                                <span class="ml-2 char-count">0/125 characters</span>
                            </div>
                        </div>
                    </div>
                </div>`;
            container.append(html);
            updateCharCount(index);
        }

        function updateCharCount(index) {
            const $textarea = $(`textarea[data-index="${index}"]`);
            const $charCount = $textarea.closest('.alt-text-item').find('.char-count');
            const length = $textarea.val().length;
            $charCount.text(`${length}/125 characters`);
            $charCount.toggleClass('text-danger', length > 125).toggleClass('text-muted', length <= 125);
        }

        $('#enable_alt_text').change(function() {
            const isEnabled = this.checked;
            const $altSection = $('#alt-text-section');
            if (isEnabled) {
                updateImagePreview();
                $altSection.show().addClass('alt-text-section-show');
            } else {
                $altSection.hide().removeClass('alt-text-section-show');
            }
        });

        $('#photo').on('input change', updateImagePreview);
        $('#lfm').on('click', function() {
            const originalValue = $('#photo').val();
            let checkCount = 0;
            const interval = setInterval(() => {
                checkCount++;
                if ($('#photo').val() !== originalValue) {
                    updateImagePreview();
                    clearInterval(interval);
                } else if (checkCount > 20) {
                    clearInterval(interval);
                }
            }, 500);
        });

        $(document).on('click', '.auto-generate-alt', function() {
            const index = $(this).data('index');
            const productTitle = $('#inputTitle').val() || 'Product';
            const category = $('#cat_id option:selected').text();
            const brand = $('#brand_id option:selected').text();
            let autoAlt = productTitle;
            if (brand && brand !== 'Select Brand') autoAlt += ` by ${brand}`;
            if (category && category !== 'Select Category') autoAlt += ` - ${category}`;
            autoAlt += index === 0 ? ' - Main Image' : ` - Image ${index + 1}`;
            $(`textarea[name="alt_text[${index}]"]`).val(autoAlt.substring(0, 125));
            updateCharCount(index);
        });

        $(document).on('input', '.alt-text-input', function() {
            updateCharCount($(this).data('index'));
        });

        $('#cat_id').change(function() {
            const catId = $(this).val();
            $('#child_cat_div').addClass('d-none');
            $('#child_cat_id').html('<option value="">Select Sub Category</option>');
            if (catId) {
                $.get('{{ route("category.child", ":id") }}'.replace(':id', catId), function(response) {
                    if (response.status && response.data.length) {
                        let options = '<option value="">Select Sub Category</option>';
                        response.data.forEach(child => {
                            options += `<option value="${child.id}">${child.title}</option>`;
                        });
                        $('#child_cat_id').html(options);
                        $('#child_cat_div').removeClass('d-none');
                    }
                }).fail(() => showNotification('Failed to load subcategories. Please try again.', 'error'));
            }
        });

        if ($('#cat_id').val()) {
            $('#cat_id').trigger('change');
        }

        $('#addBrandBtn').click(function() {
            $('#new_brand_title').val('');
            $('#addBrandModal').modal('show');
        });

        $('#addBrandForm').submit(function(e) {
            e.preventDefault();
            const brandName = $('#new_brand_title').val().trim();
            if (!brandName) {
                showNotification('Brand name is required.', 'error');
                return;
            }
            $.post('{{ route("brand.store.ajax") }}', {
                _token: '{{ csrf_token() }}',
                title: brandName
            }, function(res) {
                if (res.status === 'success') {
                    $('#brand_id').append(`<option value="${res.data.id}" selected>${res.data.title}</option>`);
                    $('#brand_id').selectpicker('refresh');
                    $('#addBrandModal').modal('hide');
                    showNotification('Brand added successfully!', 'success');
                } else {
                    showNotification(res.message || 'Error adding brand.', 'error');
                }
            }).fail(() => showNotification('Failed to add brand.', 'error'));
        });

        function showNotification(message, type = 'info') {
            $('.notification-toast').remove();
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
            const $notification = $(`
                <div class="alert ${alertClass} notification-toast">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
                    <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
                </div>
            `);
            $('body').append($notification);
            setTimeout(() => $notification.fadeOut(400, () => $notification.remove()), 4000);
        }
    });
</script>
@endpush