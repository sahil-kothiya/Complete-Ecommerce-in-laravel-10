@extends('backend.layouts.master')

@section('main-content')
    <!-- Main content area for adding a new product -->
    <div class="card shadow-sm border-0">
        <h5 class="card-header">Add New Product</h5>
        <div class="card-body p-4">
            <!-- Validation Summary Alert (Hidden by default) -->
            <div id="validation-summary" class="alert alert-danger alert-dismissible fade" role="alert"
                style="display: none;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
                <h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Validation Errors</h5>
                <div id="validation-errors-list"></div>
            </div>

            <form method="POST" action="{{ route('product.store') }}" enctype="multipart/form-data" id="product-form"
                novalidate>
                @csrf
                <!-- Core Product Details Section -->
                <section class="mb-5">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Core Product Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="inputTitle">Title <span class="text-danger">*</span></label>
                                <input type="text" id="inputTitle" name="title" value="{{ old('title') }}"
                                    class="form-control" placeholder="Enter product title" tabindex="1">
                                @error('title')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="slug">Slug <span class="text-danger">*</span> <small
                                        class="text-muted">(Auto-generated)</small></label>
                                <input type="text" id="slug" name="slug" value="{{ old('slug') }}"
                                    class="form-control" placeholder="product-slug" readonly tabindex="2">
                                @error('slug')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="summary">Summary <span class="text-danger">*</span></label>
                                <textarea id="summary" name="summary" class="form-control summernote" rows="3" tabindex="3">{{ old('summary') }}</textarea>
                                @error('summary')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control summernote" rows="5" tabindex="4">{{ old('description') }}</textarea>
                                @error('description')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
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
                                <select name="cat_id" id="cat_id" class="form-control" tabindex="5">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ old('cat_id') == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                                    @endforeach
                                </select>
                                @error('cat_id')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4 d-none" id="child_cat_div">
                            <div class="form-group">
                                <label for="child_cat_id">Sub Category</label>
                                <select name="child_cat_id" id="child_cat_id" class="form-control" tabindex="6">
                                    <option value="">Select Sub Category</option>
                                </select>
                                @error('child_cat_id')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="brand_id">Brand</label>
                                <div class="input-group">
                                    <select name="brand_id" id="brand_id" class="form-control selectpicker"
                                        data-live-search="true" tabindex="7">
                                        <option value="">Select Brand</option>
                                        @foreach ($brands as $brand)
                                            <option value="{{ $brand->id }}"
                                                {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->title }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" id="addBrandBtn" class="btn btn-outline-primary"
                                            tabindex="8"><i class="fa fa-plus"></i></button>
                                    </div>
                                </div>
                                @error('brand_id')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="status">Status <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-control" tabindex="16">
                                    <option value="">Select Status</option>
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                                @error('status')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
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
                                <input type="number" id="base_price" name="base_price" step="0.01" min="0"
                                    value="{{ old('base_price') }}" class="form-control" placeholder="Enter price"
                                    tabindex="9">
                                @error('base_price')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_discount">Discount (%)</label>
                                <input type="number" id="base_discount" name="base_discount" min="0"
                                    max="100" value="{{ old('base_discount') }}" class="form-control"
                                    placeholder="Enter discount" tabindex="10">
                                @error('base_discount')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_stock">Stock <span class="text-danger">*</span></label>
                                <input type="number" id="base_stock" name="base_stock" min="0"
                                    value="{{ old('base_stock') }}" class="form-control" placeholder="Enter stock"
                                    tabindex="11">
                                @error('base_stock')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_sku">SKU <span class="text-danger">*</span></label>
                                <input type="text" id="base_sku" name="base_sku" value="{{ old('base_sku') }}"
                                    class="form-control" placeholder="Enter unique SKU" tabindex="12">
                                @error('base_sku')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="condition">Condition</label>
                                <select name="condition" id="condition" class="form-control" tabindex="13">
                                    <option value="default" {{ old('condition') == 'default' ? 'selected' : '' }}>Default
                                    </option>
                                    <option value="new" {{ old('condition') == 'new' ? 'selected' : '' }}>New</option>
                                    <option value="hot" {{ old('condition') == 'hot' ? 'selected' : '' }}>Hot</option>
                                </select>
                                @error('condition')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="size">Sizes</label>
                                <select name="size[]" id="size" class="form-control selectpicker" multiple
                                    data-live-search="true" tabindex="14">
                                    @foreach (['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ in_array($key, old('size', [])) ? 'selected' : '' }}>{{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('size')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group form-check mt-4">
                                <input type="checkbox" name="is_featured" id="is_featured" value="1"
                                    class="form-check-input" {{ old('is_featured') ? 'checked' : '' }} tabindex="15">
                                <label for="is_featured" class="form-check-label">Featured Product</label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Images Section (Non-Variant) -->
                <section class="mb-5" id="non-variant-images">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Product Images</h6>
                    <div class="form-group">
                        <label for="photo">Photos <span class="text-danger">*</span> <small class="text-muted">(Select
                                at least one image)</small></label>
                        <div class="input-group">
                            <input type="text" id="photo" name="photo" class="form-control"
                                value="{{ old('photo') }}" placeholder="Comma-separated image URLs" readonly
                                tabindex="17">
                            <div class="input-group-append">
                                <a id="lfm" data-input="photo" data-preview="holder" class="btn btn-primary"><i
                                        class="fa fa-picture-o"></i> Choose Images</a>
                            </div>
                        </div>
                        @error('photo')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                        <div id="image-preview-area" class="mt-3 d-flex flex-wrap gap-3"></div>
                    </div>
                    <div class="form-group form-check" id="alt-text-toggle" style="display: none;">
                        <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1"
                            class="form-check-input" tabindex="18">
                        <label for="enable_alt_text" class="form-check-label">Enable Alt Text for Images <small
                                class="text-muted">(Improves SEO & Accessibility)</small></label>
                    </div>
                    <div id="alt-text-section" class="row mt-3" style="display: none;"></div>
                </section>

                <!-- Variants Section -->
                <section class="mb-5">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Product Variants</h6>
                    <div class="form-group form-check">
                        <input type="checkbox" name="has_variants" id="has_variants" value="1"
                            class="form-check-input" {{ old('has_variants') ? 'checked' : '' }} tabindex="19">
                        <label for="has_variants" class="form-check-label">Enable Variants <small
                                class="text-muted">(e.g., Colors, Sizes)</small></label>
                    </div>
                    <div id="variants-panel" class="p-4 bg-light rounded" style="display: none;">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold">Select Variant Types & Options</label>
                                <button type="button" id="load-types" class="btn btn-secondary btn-sm">Load Variant
                                    Types</button>
                            </div>
                            <div id="type-selections" class="d-flex flex-wrap gap-3"></div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold">Generated Variants</label>
                                <button type="button" id="generate-preview" class="btn btn-info btn-sm">Generate
                                    Variants</button>
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
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
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

        .custom-is-invalid,
        .form-control.custom-is-invalid {
            border-color: #dc3545 !important;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .custom-invalid-feedback {
            display: block;
            font-size: 0.875rem;
            color: #dc3545;
            margin-top: 0.25rem;
        }

        .is-valid,
        .form-control.is-valid {
            border-color: #28a745 !important;
            padding-right: calc(1.5em + 0.75rem);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }

        .valid-feedback {
            display: block;
            font-size: 0.875rem;
            color: #28a745;
            margin-top: 0.25rem;
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

        #validation-summary {
            border-left: 4px solid #dc3545;
            animation: slideDown 0.4s ease-out;
        }

        #validation-summary .alert-heading {
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
        }

        #validation-summary ol {
            font-size: 0.9rem;
            line-height: 1.6;
        }

        #validation-summary ol li {
            padding: 0.25rem 0;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            10%,
            30%,
            50%,
            70%,
            90% {
                transform: translateX(-5px);
            }

            20%,
            40%,
            60%,
            80% {
                transform: translateX(5px);
            }
        }

        .shake-animation {
            animation: shake 0.6s;
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

        .remove-new-variant-btn {
            transition: all 0.2s;
        }

        .remove-new-variant-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
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
                $.get('{{ route('variant-type.api') }}', function(types) {
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
                            let optHtml =
                                '<option value="">Select Options</option>';
                            options.forEach(opt => {
                                optHtml +=
                                    `<option value="${opt.id}">${opt.display_value}</option>`;
                            });
                            $(`select[data-type-id="${typeId}"]`).html(optHtml)
                                .trigger('change');
                        }).fail(() => showNotification(
                            'Failed to load variant options.', 'error'));
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
                $.post('{{ route('product.preview-variants') }}', {
                    _token: '{{ csrf_token() }}',
                    selections: selections
                }, function(response) {
                    console.log('✅ Variant preview response:', response);

                    if (!response.variants || response.variants.length === 0) {
                        showNotification('No variants generated. Please check your selections.',
                            'error');
                        return;
                    }

                    let html = `
                    <table class="table table-bordered table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th style="width: 180px;">Variant Name</th>
                                <th style="width: 300px;">SKU <span class="text-danger">*</span></th>
                                <th style="width: 120px;">Price (NRS) <span class="text-danger">*</span></th>
                                <th style="width: 100px;">Discount (%)</th>
                                <th style="width: 100px;">Stock <span class="text-danger">*</span></th>
                                <th>Images <span class="text-danger">*</span></th>
                                <th style="width: 80px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>`;

                    // Add hidden inputs for variant_options to preserve selections
                    if (response.selections) {
                        Object.keys(response.selections).forEach(typeId => {
                            const optionIds = response.selections[typeId];
                            optionIds.forEach(optionId => {
                                html +=
                                    `<input type="hidden" name="variant_options[${typeId}][]" value="${optionId}">`;
                            });
                        });
                    }

                    response.variants.forEach((variant, idx) => {
                        const displayName = variant.name || 'Unnamed Variant';
                        const displaySku = variant.sku || '';

                        html += `
                        <tr data-variant-index="${idx}" data-new-variant="${displayName}">
                            <td class="font-weight-bold text-primary">${displayName}</td>
                            <td>
                                <input type="text"
                                       name="variants[${idx}][sku]"
                                       value="${displaySku}"
                                       class="form-control form-control-sm"
                                       placeholder="SKU">`;

                        // Add hidden inputs for option_ids to preserve the variant-to-option mapping
                        if (variant.option_ids && Array.isArray(variant.option_ids)) {
                            variant.option_ids.forEach(optionId => {
                                html +=
                                    `<input type="hidden" name="variants[${idx}][option_ids][]" value="${optionId}">`;
                            });
                        }

                        html += `
                            </td>
                            <td>
                                <input type="number"
                                       name="variants[${idx}][price]"
                                       step="0.01"
                                       min="0"
                                       value="${variant.price || ''}"
                                       class="form-control form-control-sm"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <input type="number"
                                       name="variants[${idx}][discount]"
                                       min="0"
                                       max="100"
                                       value="${variant.discount || ''}"
                                       class="form-control form-control-sm"
                                       placeholder="0">
                            </td>
                            <td>
                                <input type="number"
                                       name="variants[${idx}][stock]"
                                       min="0"
                                       value="${variant.stock || 10}"
                                       class="form-control form-control-sm"
                                       placeholder="10">
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text"
                                           name="variants[${idx}][images]"
                                           id="variant-images-${idx}"
                                           class="form-control"
                                           readonly
                                           placeholder="Choose images">
                                    <div class="input-group-append">
                                        <a class="btn btn-primary lfm-variant"
                                           data-input="variant-images-${idx}"
                                           data-preview="variant-holder-${idx}">
                                            <i class="fa fa-picture-o"></i> Choose
                                        </a>
                                    </div>
                                </div>
                                <div id="variant-holder-${idx}" class="mt-2 d-flex flex-wrap gap-2" style="gap: 0.5rem;"></div>
                            </td>
                            <td class="text-center">
                                <button type="button" class="btn btn-danger btn-sm remove-new-variant-btn" title="Remove Variant">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </td>
                        </tr>`;
                    });

                    html += `</tbody></table>`;

                    $('#variant-preview').html(html);
                    $('.lfm-variant').filemanager('image');

                    const count = response.variants.length;
                    const summary =
                        `✅ Generated ${count} variant${count > 1 ? 's' : ''} successfully!`;
                    showNotification(summary, 'success');

                    console.log(`📦 Variants generated: ${count}`);
                    console.log('Sample SKUs:', response.variants.slice(0, 3).map(v => v.sku));
                }).fail(function(xhr) {
                    console.error('❌ Variant generation failed:', xhr);
                    const errorMsg = xhr.responseJSON?.message ||
                        'Failed to generate variants. Please try again.';
                    showNotification(errorMsg, 'error');
                });
            });

            // Remove new variant (preview row)
            $(document).on('click', '.remove-new-variant-btn', function() {
                const $row = $(this).closest('tr');
                const name = $row.find('td:first').text().trim();

                // Direct removal with fade-out animation
                $row.fadeOut(300, function() {
                    $(this).remove();

                    // Reindex remaining variant rows
                    $('#variant-preview tbody tr').each(function(newIndex) {
                        $(this).attr('data-variant-index', newIndex);

                        // Update input names
                        $(this).find('input').each(function() {
                            const currentName = $(this).attr('name');
                            if (currentName && currentName.includes('variants[')) {
                                const fieldName = currentName.match(
                                    /\[([^\]]+)\]$/)[1];
                                $(this).attr('name',
                                    `variants[${newIndex}][${fieldName}]`);
                            }

                            // Update IDs for image inputs
                            const currentId = $(this).attr('id');
                            if (currentId && currentId.includes(
                                    'variant-images-')) {
                                $(this).attr('id', `variant-images-${newIndex}`);
                            }
                        });

                        // Update lfm-variant button data attributes
                        $(this).find('.lfm-variant').each(function() {
                            $(this).attr('data-input',
                                `variant-images-${newIndex}`);
                            $(this).attr('data-preview',
                                `variant-holder-${newIndex}`);
                        });

                        // Update preview holder ID
                        $(this).find('[id^="variant-holder-"]').attr('id',
                            `variant-holder-${newIndex}`);
                    });

                    showNotification(`Variant "${name}" removed.`, 'info');
                });
            });

            // Enhanced real-time field validation
            function validateField($field, rules) {
                const value = $field.val();
                const fieldName = $field.attr('name') || $field.attr('id');
                let error = '';

                // Clear previous validation states
                $field.removeClass('is-invalid custom-is-invalid is-valid');
                $field.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback').remove();

                // Skip validation if field is hidden or disabled
                if ($field.is(':hidden') || $field.is(':disabled')) {
                    return true;
                }

                // Required field validation
                if (rules.required && (!value || String(value).trim() === '')) {
                    error = `${rules.label || fieldName} is required.`;
                }
                // Email validation
                else if (rules.email && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    error = `Please enter a valid email address.`;
                }
                // Number validation
                else if (rules.number && value && (isNaN(value) || value === '')) {
                    error = `${rules.label || fieldName} must be a valid number.`;
                }
                // Integer validation
                else if (rules.integer && value && (!Number.isInteger(parseFloat(value)))) {
                    error = `${rules.label || fieldName} must be a whole number.`;
                }
                // Min value validation
                else if (rules.min !== undefined && value && parseFloat(value) < rules.min) {
                    error = `${rules.label || fieldName} must be at least ${rules.min}.`;
                }
                // Max value validation
                else if (rules.max !== undefined && value && parseFloat(value) > rules.max) {
                    error = `${rules.label || fieldName} cannot exceed ${rules.max}.`;
                }
                // Min length validation
                else if (rules.minLength !== undefined && value && String(value).length < rules.minLength) {
                    error = `${rules.label || fieldName} must be at least ${rules.minLength} characters.`;
                }
                // Max length validation
                else if (rules.maxLength !== undefined && value && String(value).length > rules.maxLength) {
                    error = `${rules.label || fieldName} must not exceed ${rules.maxLength} characters.`;
                }
                // Pattern validation
                else if (rules.pattern && value && !rules.pattern.test(value)) {
                    error = rules.patternMessage || `${rules.label || fieldName} format is invalid.`;
                }
                // URL validation
                else if (rules.url && value) {
                    try {
                        new URL(value);
                    } catch (_) {
                        error = `Please enter a valid URL.`;
                    }
                }
                // Custom validation function
                else if (rules.custom && typeof rules.custom === 'function') {
                    const customResult = rules.custom(value, $field);
                    if (customResult !== true) {
                        error = customResult || 'Validation failed.';
                    }
                }

                // Show error state
                if (error) {
                    $field.addClass('is-invalid custom-is-invalid');
                    $field.after(`<div class="invalid-feedback custom-invalid-feedback d-block">${error}</div>`);
                    return false;
                }

                // Show valid state if rules exist and field has value
                if (Object.keys(rules).length > 0 && value && String(value).trim() !== '') {
                    $field.addClass('is-valid');
                    if (rules.showValidFeedback !== false) {
                        $field.after(`<div class="valid-feedback d-block">✓ Looks good!</div>`);
                    }
                }

                return true;
            }

            // Validate Summernote editor
            function validateSummernote($editor, rules) {
                const content = $editor.summernote('code').trim();
                const $container = $editor.next('.note-editor');
                const label = rules.label || 'This field';

                // Clear previous validation
                $container.removeClass('is-invalid custom-is-invalid is-valid');
                $container.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback').remove();

                let error = '';

                // Check if required
                if (rules.required && (!content || content === '<p><br></p>' || content === '<p></p>' || content ===
                        '')) {
                    error = `${label} is required.`;
                }
                // Check min length
                else if (rules.minLength && content.replace(/<[^>]*>/g, '').length < rules.minLength) {
                    error = `${label} must be at least ${rules.minLength} characters.`;
                }
                // Check max length
                else if (rules.maxLength && content.replace(/<[^>]*>/g, '').length > rules.maxLength) {
                    error = `${label} must not exceed ${rules.maxLength} characters.`;
                }

                if (error) {
                    $container.addClass('is-invalid custom-is-invalid');
                    $container.after(
                        `<div class="invalid-feedback custom-invalid-feedback d-block">${error}</div>`);
                    return false;
                }

                // Show valid state
                if (content && content !== '<p><br></p>' && content !== '<p></p>') {
                    $container.addClass('is-valid');
                }

                return true;
            }

            // Attach comprehensive real-time validation to all fields

            // Product Title validation
            $('#inputTitle').on('blur keyup', function() {
                validateField($(this), {
                    required: true,
                    minLength: 3,
                    maxLength: 255,
                    label: 'Product Title'
                });
            });

            // Slug validation
            $('#slug').on('blur keyup', function() {
                validateField($(this), {
                    required: true,
                    minLength: 3,
                    maxLength: 255,
                    pattern: /^[a-z0-9-]+$/,
                    patternMessage: 'Slug can only contain lowercase letters, numbers, and hyphens.'
                });
            });

            // Summary validation (Summernote)
            $('#summary').on('summernote.blur summernote.change', function() {
                validateSummernote($(this), {
                    required: true,
                    minLength: 10,
                    label: 'Summary'
                });
            });

            // Description validation (Summernote) - optional but validate if filled
            $('#description').on('summernote.blur summernote.change', function() {
                const content = $(this).summernote('code').trim();
                if (content && content !== '<p><br></p>' && content !== '<p></p>') {
                    validateSummernote($(this), {
                        minLength: 10,
                        label: 'Description'
                    });
                }
            });

            // Category validation
            $('#cat_id').on('change blur', function() {
                validateField($(this), {
                    required: true,
                    label: 'Category'
                });
            });

            // Sub-category validation (optional)
            $('#child_cat_id').on('change blur', function() {
                if ($(this).children('option').length > 1) {
                    validateField($(this), {
                        label: 'Sub Category'
                    });
                }
            });

            // Brand validation (optional)
            $('#brand_id').on('change blur', function() {
                validateField($(this), {
                    label: 'Brand'
                });
            });

            // Status validation
            $('#status').on('change blur', function() {
                validateField($(this), {
                    required: true,
                    label: 'Status'
                });
            });

            // Base Price validation
            $('#base_price').on('blur keyup', function() {
                if (!$('#has_variants').is(':checked')) {
                    validateField($(this), {
                        required: true,
                        number: true,
                        min: 0.01,
                        label: 'Base Price'
                    });
                }
            });

            // Base Stock validation
            $('#base_stock').on('blur keyup', function() {
                if (!$('#has_variants').is(':checked')) {
                    validateField($(this), {
                        required: true,
                        number: true,
                        integer: true,
                        min: 0,
                        label: 'Base Stock'
                    });
                }
            });

            // Base SKU validation
            $('#base_sku').on('blur keyup', function() {
                if (!$('#has_variants').is(':checked')) {
                    validateField($(this), {
                        required: true,
                        minLength: 2,
                        maxLength: 100,
                        pattern: /^[A-Z0-9-_]+$/i,
                        patternMessage: 'SKU can only contain letters, numbers, hyphens, and underscores.',
                        label: 'SKU'
                    });
                }
            });

            // Base Discount validation
            $('#base_discount').on('blur keyup', function() {
                const value = $(this).val();
                if (value && value.trim() !== '') {
                    validateField($(this), {
                        number: true,
                        min: 0,
                        max: 100,
                        label: 'Discount'
                    });
                } else {
                    // Clear validation if empty (optional field)
                    $(this).removeClass('is-invalid custom-is-invalid is-valid');
                    $(this).siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback')
                        .remove();
                }
            });

            // Photo validation (exactly 3 images)
            $('#photo').on('change blur', function() {
                if (!$('#has_variants').is(':checked')) {
                    validateField($(this), {
                        required: true,
                        label: 'Product Images',
                        custom: function(value) {
                            if (!value || !value.trim()) {
                                return 'At least one product image is required.';
                            }
                            const urls = value.split(',').filter(url => url.trim());
                            if (urls.length === 0) {
                                return 'At least one product image is required.';
                            }
                            return true;
                        }
                    });
                }
            });

            // Clear validation when switching to variants
            $('#has_variants').on('change', function() {
                if ($(this).is(':checked')) {
                    // Clear non-variant field validations
                    $('#base_price, #base_stock, #base_sku, #photo, #base_discount').each(function() {
                        $(this).removeClass('is-invalid custom-is-invalid is-valid');
                        $(this).siblings(
                                '.invalid-feedback, .custom-invalid-feedback, .valid-feedback')
                            .remove();
                    });
                } else {
                    // Trigger validation for non-variant fields
                    setTimeout(function() {
                        $('#base_price').trigger('blur');
                        $('#base_stock').trigger('blur');
                        $('#base_sku').trigger('blur');
                        $('#photo').trigger('blur');
                    }, 100);
                }
            });

            // Enhanced variant field validation with real-time feedback
            $(document).on('blur keyup',
                '#variant-preview input[type="text"], #variant-preview input[type="number"]',
                function() {
                    const $input = $(this);
                    const name = $input.attr('name');
                    const rules = {};

                    if (name && name.includes('sku')) {
                        rules.required = true;
                        rules.minLength = 2;
                        rules.maxLength = 100;
                        rules.pattern = /^[A-Z0-9-_]+$/i;
                        rules.patternMessage =
                            'SKU can only contain letters, numbers, hyphens, and underscores.';
                        rules.label = 'Variant SKU';
                    } else if (name && name.includes('price')) {
                        rules.required = true;
                        rules.number = true;
                        rules.min = 0.01;
                        rules.label = 'Variant Price';
                    } else if (name && name.includes('stock')) {
                        rules.required = true;
                        rules.number = true;
                        rules.integer = true;
                        rules.min = 0;
                        rules.label = 'Variant Stock';
                    } else if (name && name.includes('discount')) {
                        const value = $input.val();
                        if (value && value.trim() !== '') {
                            rules.number = true;
                            rules.min = 0;
                            rules.max = 100;
                            rules.label = 'Variant Discount';
                        } else {
                            // Clear validation if empty (optional field)
                            $input.removeClass('is-invalid custom-is-invalid is-valid');
                            $input.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback')
                                .remove();
                            return;
                        }
                    }

                    if (Object.keys(rules).length > 0) {
                        validateField($input, rules);
                    }
                });

            // Validate variant images
            $(document).on('change blur', '#variant-preview input[name*="[images]"]', function() {
                validateField($(this), {
                    required: true,
                    label: 'Variant Images',
                    custom: function(value) {
                        if (!value || !value.trim()) {
                            return 'At least one variant image is required.';
                        }
                        const urls = value.split(',').filter(url => url.trim());
                        if (urls.length === 0) {
                            return 'At least one variant image is required.';
                        }
                        return true;
                    }
                });
            });

            // Real-time validation as user types (debounced)
            let validationTimeout;
            $(document).on('input', '#variant-preview input[type="text"], #variant-preview input[type="number"]',
                function() {
                    const $input = $(this);
                    clearTimeout(validationTimeout);
                    validationTimeout = setTimeout(function() {
                        $input.trigger('blur');
                    }, 500);
                });

            // Prevent multiple form submissions
            let isSubmitting = false;

            // Comprehensive form validation before submission
            $('#product-form').submit(function(e) {
                // Prevent double submission
                if (isSubmitting) {
                    e.preventDefault();
                    showNotification('Please wait, form is being submitted...', 'info');
                    return false;
                }

                // Remove previous custom validation errors
                $('.custom-invalid-feedback, .valid-feedback').remove();
                $('.form-control, .note-editor').removeClass('custom-is-invalid is-invalid is-valid');

                let valid = true;
                const errors = [];
                const hasVariants = $('#has_variants').is(':checked');

                // Validate all basic required fields

                // Product Title
                const title = $('#inputTitle').val().trim();
                if (!title) {
                    valid = false;
                    errors.push('Product Title is required');
                    showFieldError('#inputTitle', 'Product Title is required.');
                } else if (title.length < 3) {
                    valid = false;
                    errors.push('Product Title must be at least 3 characters');
                    showFieldError('#inputTitle', 'Product Title must be at least 3 characters.');
                } else if (title.length > 255) {
                    valid = false;
                    errors.push('Product Title must not exceed 255 characters');
                    showFieldError('#inputTitle', 'Product Title must not exceed 255 characters.');
                }

                // Slug
                const slug = $('#slug').val().trim();
                if (!slug) {
                    valid = false;
                    errors.push('Slug is required');
                    showFieldError('#slug', 'Slug is required.');
                } else if (slug.length < 3) {
                    valid = false;
                    errors.push('Slug must be at least 3 characters');
                    showFieldError('#slug', 'Slug must be at least 3 characters.');
                } else if (!/^[a-z0-9-]+$/.test(slug)) {
                    valid = false;
                    errors.push(
                        'Slug contains invalid characters (use only lowercase letters, numbers, and hyphens)'
                    );
                    showFieldError('#slug',
                        'Slug can only contain lowercase letters, numbers, and hyphens.');
                }

                // Summary (Summernote)
                const summaryContent = $('#summary').summernote('code').trim();
                const summaryText = summaryContent.replace(/<[^>]*>/g, '').trim();
                if (!summaryContent || summaryContent === '<p><br></p>' || summaryContent === '<p></p>' || !
                    summaryText) {
                    valid = false;
                    errors.push('Summary is required');
                    showFieldError('#summary', 'Summary is required.');
                    $('#summary').next('.note-editor').addClass('custom-is-invalid');
                } else if (summaryText.length < 10) {
                    valid = false;
                    errors.push('Summary must be at least 10 characters');
                    showFieldError('#summary', 'Summary must be at least 10 characters.');
                    $('#summary').next('.note-editor').addClass('custom-is-invalid');
                }

                // Description (optional but validate if provided)
                const descContent = $('#description').summernote('code').trim();
                const descText = descContent.replace(/<[^>]*>/g, '').trim();
                if (descContent && descContent !== '<p><br></p>' && descContent !== '<p></p>' && descText) {
                    if (descText.length < 10) {
                        valid = false;
                        errors.push('Description must be at least 10 characters if provided');
                        showFieldError('#description', 'Description must be at least 10 characters.');
                        $('#description').next('.note-editor').addClass('custom-is-invalid');
                    }
                }

                // Category
                if (!$('#cat_id').val()) {
                    valid = false;
                    errors.push('Category is required');
                    showFieldError('#cat_id', 'Please select a category.');
                }

                // Status
                if (!$('#status').val()) {
                    valid = false;
                    errors.push('Status is required');
                    showFieldError('#status', 'Please select a status.');
                }

                // Variant-specific validation
                if (hasVariants) {
                    const variantRows = $('#variant-preview tbody tr');

                    if (variantRows.length === 0) {
                        valid = false;
                        errors.push('Please generate variants before submitting');
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
                            errors.push(`Variant ${index + 1}: SKU is required`);
                            row.find('input[name*="[sku]"]').addClass('custom-is-invalid');
                        }

                        if (!price || parseFloat(price) <= 0) {
                            valid = false;
                            errors.push(`Variant ${index + 1}: Price must be greater than 0`);
                            row.find('input[name*="[price]"]').addClass('custom-is-invalid');
                        }

                        if (stock === '' || parseInt(stock) < 0) {
                            valid = false;
                            errors.push(`Variant ${index + 1}: Stock must be 0 or greater`);
                            row.find('input[name*="[stock]"]').addClass('custom-is-invalid');
                        }

                        if (!images || !images.trim()) {
                            valid = false;
                            errors.push(`Variant ${index + 1}: Images are required`);
                            row.find('input[name*="[images]"]').addClass('custom-is-invalid');
                        }

                        if (discount && (parseFloat(discount) < 0 || parseFloat(discount) > 100)) {
                            valid = false;
                            errors.push(`Variant ${index + 1}: Discount must be between 0 and 100`);
                            row.find('input[name*="[discount]"]').addClass('custom-is-invalid');
                        }
                    });
                } else {
                    // Non-variant product validation
                    const basePrice = $('#base_price').val();
                    const baseStock = $('#base_stock').val();
                    const baseSku = $('#base_sku').val().trim();
                    const photo = $('#photo').val().trim();
                    const baseDiscount = $('#base_discount').val();

                    // Base Price validation
                    if (!basePrice || basePrice.trim() === '') {
                        valid = false;
                        errors.push('Base Price is required for non-variant products');
                        showFieldError('#base_price', 'Base Price is required.');
                    } else if (isNaN(basePrice)) {
                        valid = false;
                        errors.push('Base Price must be a valid number');
                        showFieldError('#base_price', 'Price must be a valid number.');
                    } else if (parseFloat(basePrice) <= 0) {
                        valid = false;
                        errors.push('Base Price must be greater than 0');
                        showFieldError('#base_price', 'Price must be greater than 0.');
                    }

                    // Base Stock validation
                    if (baseStock === '') {
                        valid = false;
                        errors.push('Base Stock is required for non-variant products');
                        showFieldError('#base_stock', 'Base Stock is required.');
                    } else if (isNaN(baseStock)) {
                        valid = false;
                        errors.push('Base Stock must be a valid number');
                        showFieldError('#base_stock', 'Stock must be a valid number.');
                    } else if (!Number.isInteger(parseFloat(baseStock))) {
                        valid = false;
                        errors.push('Base Stock must be a whole number');
                        showFieldError('#base_stock', 'Stock must be a whole number.');
                    } else if (parseInt(baseStock) < 0) {
                        valid = false;
                        errors.push('Base Stock must be 0 or greater');
                        showFieldError('#base_stock', 'Stock must be 0 or greater.');
                    }

                    // Base SKU validation
                    if (!baseSku) {
                        valid = false;
                        errors.push('Base SKU is required for non-variant products');
                        showFieldError('#base_sku', 'SKU is required.');
                    } else if (baseSku.length < 2) {
                        valid = false;
                        errors.push('SKU must be at least 2 characters');
                        showFieldError('#base_sku', 'SKU must be at least 2 characters.');
                    } else if (!/^[A-Z0-9-_]+$/i.test(baseSku)) {
                        valid = false;
                        errors.push(
                            'SKU contains invalid characters (use only letters, numbers, hyphens, and underscores)'
                        );
                        showFieldError('#base_sku',
                            'SKU can only contain letters, numbers, hyphens, and underscores.');
                    }

                    // Photo validation
                    if (!photo) {
                        valid = false;
                        errors.push('Product images are required for non-variant products');
                        showFieldError('#photo', 'At least one image is required.');
                    } else {
                        const urls = photo.split(',').filter(url => url.trim());
                        if (urls.length === 0) {
                            valid = false;
                            errors.push('Product images are required');
                            showFieldError('#photo', 'At least one valid image URL is required.');
                        }
                    }

                    // Base Discount validation (optional)
                    if (baseDiscount && baseDiscount.trim() !== '') {
                        if (isNaN(baseDiscount)) {
                            valid = false;
                            errors.push('Discount must be a valid number');
                            showFieldError('#base_discount', 'Discount must be a valid number.');
                        } else if (parseFloat(baseDiscount) < 0) {
                            valid = false;
                            errors.push('Discount cannot be negative');
                            showFieldError('#base_discount', 'Discount cannot be negative.');
                        } else if (parseFloat(baseDiscount) > 100) {
                            valid = false;
                            errors.push('Discount cannot exceed 100%');
                            showFieldError('#base_discount', 'Discount cannot exceed 100%.');
                        }
                    }

                    // Alt text validation
                    if ($('#enable_alt_text').is(':checked')) {
                        let altTextValid = true;
                        $('textarea[name^="alt_text["]').each(function() {
                            const val = $(this).val().trim();
                            if (!val) {
                                altTextValid = false;
                                $(this).addClass('custom-is-invalid');
                                $(this).after(
                                    '<span class="custom-invalid-feedback text-danger d-block">Alt text is required.</span>'
                                );
                            } else if (val.length > 125) {
                                altTextValid = false;
                                $(this).addClass('custom-is-invalid');
                                $(this).after(
                                    '<span class="custom-invalid-feedback text-danger d-block">Alt text must not exceed 125 characters.</span>'
                                );
                            }
                        });
                        if (!altTextValid) {
                            valid = false;
                        }
                    }
                }

                if (!valid) {
                    e.preventDefault();
                    isSubmitting = false;

                    // Show validation summary panel at top of form
                    const errorCount = errors.length;
                    let errorListHtml =
                        `<p class="mb-2"><strong>Found ${errorCount} error${errorCount > 1 ? 's' : ''} that need${errorCount > 1 ? '' : 's'} to be fixed:</strong></p><ol class="mb-0 pl-3">`;
                    errors.forEach((error) => {
                        errorListHtml += `<li class="mb-1">${error}</li>`;
                    });
                    errorListHtml += '</ol>';

                    $('#validation-errors-list').html(errorListHtml);
                    $('#validation-summary').show().addClass('show');

                    // Also show notification toast
                    let errorSummary =
                        `<div style="text-align:left;"><strong>⚠️ ${errorCount} Validation Error${errorCount > 1 ? 's' : ''}</strong><br><small>Scroll up to see the full list</small></div>`;
                    showNotification(errorSummary, 'error');

                    // Scroll to validation summary first
                    $('html, body').animate({
                        scrollTop: $('#validation-summary').offset().top - 20
                    }, 600, 'swing', function() {
                        // Then scroll to first error field after a brief pause
                        setTimeout(function() {
                            const $firstError = $('.is-invalid, .custom-is-invalid')
                                .first();
                            if ($firstError.length) {
                                $('html, body').animate({
                                    scrollTop: $firstError.offset().top - 100
                                }, 400, 'swing', function() {
                                    // Flash the first error field
                                    $firstError.fadeOut(100).fadeIn(100).fadeOut(
                                        100).fadeIn(100);
                                });
                            }
                        }, 1000);
                    });

                    return false;
                }

                // Hide validation summary if previously shown
                $('#validation-summary').hide().removeClass('show');

                // All validations passed - prepare for submission
                isSubmitting = true;

                // Update Summernote content before submission
                $('#summary').val($('#summary').summernote('code'));
                $('#description').val($('#description').summernote('code'));

                // Show loading state with progress indicator
                const submitBtn = $(this).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                submitBtn.prop('disabled', true).html(
                    '<i class="fa fa-spinner fa-spin"></i> Saving Product...');

                // Keep other form controls enabled so the browser posts their values.

                // Show confirmation with data summary
                const dataType = hasVariants ?
                    `product with ${$('#variant-preview tbody tr').length} variant(s)` : 'product';
                showNotification(`✓ Validation passed! Submitting ${dataType}... Please wait.`, 'success');

                // Add timeout to reset if submission takes too long (30 seconds)
                setTimeout(function() {
                    if (isSubmitting) {
                        isSubmitting = false;
                        submitBtn.prop('disabled', false).html(originalBtnText);
                        showNotification('Request timeout. Please try again.', 'error');
                    }
                }, 30000);
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

                const iconClass = type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';

                const $notification = $(`
            <div class="alert ${alertClass} notification-toast alert-dismissible fade show" role="alert" style="max-width: 500px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
                <div><i class="fa ${iconClass}" style="margin-right: 8px;"></i>${message}</div>
            </div>
        `);

                $('body').append($notification);

                // Auto-dismiss after duration (longer for errors)
                const duration = type === 'error' ? 10000 : (type === 'success' ? 5000 : 4000);
                setTimeout(() => {
                    $notification.fadeOut(400, () => $notification.remove());
                }, duration);
            }

            // Clear custom validation errors on input/change
            $(document).on('input change', '.custom-is-invalid, .is-invalid', function() {
                $(this).removeClass('custom-is-invalid is-invalid');
                $(this).siblings('.custom-invalid-feedback, .invalid-feedback').remove();
            });

            // Clear valid state on change to re-validate
            $(document).on('input change', '.is-valid', function() {
                $(this).removeClass('is-valid');
                $(this).siblings('.valid-feedback').remove();
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
                    // Process URL to ensure it's properly formatted for display
                    let imageUrl = url;

                    // If URL is already a full URL (starts with http:// or https://), use it as is
                    if (!imageUrl.startsWith('http://') && !imageUrl.startsWith('https://')) {
                        // Remove any leading slashes and 'storage/' prefix if present
                        imageUrl = imageUrl.replace(/^\/+/, '').replace(/^storage\//, '');

                        // Construct the full storage URL
                        imageUrl = '{{ asset('storage') }}/' + imageUrl;
                    }

                    console.log('🖼️ Image URL processing:', {
                        original: url,
                        processed: imageUrl,
                        index: index
                    });

                    const container = $('<div class="image-container"></div>');
                    const img = $('<img />', {
                        src: imageUrl,
                        class: 'image-preview',
                        alt: `Product Image ${index + 1}`,
                        'data-index': index,
                        'data-is-primary': index === 0,
                        'data-fallback-text': `Product Image ${index + 1}`
                    });

                    if (index === 0) {
                        container.append(
                            '<div class="primary-badge"><span class="badge badge-success">Primary</span></div>'
                        );
                    }

                    img.on('error', function() {
                        const fallback = $(
                            '<div class="image-not-found"><i class="fa fa-image"></i><span>Image not available</span></div>'
                        );
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

                // Process URL for alt text preview as well
                let processedImageUrl = imageUrl;
                if (!processedImageUrl.startsWith('http://') && !processedImageUrl.startsWith('https://')) {
                    processedImageUrl = processedImageUrl.replace(/^\/+/, '').replace(/^storage\//, '');
                    processedImageUrl = '{{ asset('storage') }}/' + processedImageUrl;
                }

                const html = `
                <div class="col-md-6 alt-text-item" data-index="${index}">
                    <div class="d-flex align-items-start">
                        <img src="${processedImageUrl}" class="alt-text-preview mr-3" alt="Preview" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
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

            // Alt text validation
            $(document).on('blur keyup', '.alt-text-input', function() {
                const $textarea = $(this);
                const value = $textarea.val().trim();
                const isEnabled = $('#enable_alt_text').is(':checked');

                $textarea.removeClass('is-invalid custom-is-invalid is-valid');
                $textarea.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback').remove();

                if (isEnabled) {
                    if (!value) {
                        $textarea.addClass('is-invalid custom-is-invalid');
                        $textarea.after(
                            '<div class="invalid-feedback custom-invalid-feedback d-block">Alt text is required.</div>'
                        );
                    } else if (value.length > 125) {
                        $textarea.addClass('is-invalid custom-is-invalid');
                        $textarea.after(
                            '<div class="invalid-feedback custom-invalid-feedback d-block">Alt text must not exceed 125 characters.</div>'
                        );
                    } else if (value.length >= 5) {
                        $textarea.addClass('is-valid');
                    }
                }
            });

            $('#cat_id').change(function() {
                const catId = $(this).val();
                $('#child_cat_div').addClass('d-none');
                $('#child_cat_id').html('<option value="">Select Sub Category</option>');
                if (catId) {
                    $.get('{{ route('category.child', ':id') }}'.replace(':id', catId), function(
                        response) {
                        if (response.status && response.data.length) {
                            let options = '<option value="">Select Sub Category</option>';
                            response.data.forEach(child => {
                                options +=
                                    `<option value="${child.id}">${child.title}</option>`;
                            });
                            $('#child_cat_id').html(options);
                            $('#child_cat_div').removeClass('d-none');
                        }
                    }).fail(() => showNotification('Failed to load subcategories. Please try again.',
                        'error'));
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
                $.post('{{ route('brand.store.ajax') }}', {
                    _token: '{{ csrf_token() }}',
                    title: brandName
                }, function(res) {
                    if (res.status === 'success') {
                        $('#brand_id').append(
                            `<option value="${res.data.id}" selected>${res.data.title}</option>`
                        );
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
                const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' :
                    'alert-info';
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
