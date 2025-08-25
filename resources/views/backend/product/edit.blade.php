@extends('backend.layouts.master')

@section('main-content')
<!-- Main content area for editing an existing product -->
<div class="card">
    <h5 class="card-header">Edit Product</h5>
    <div class="card-body">
        <form method="POST" action="{{ route('product.update', $product->id) }}">
            @csrf
            @method('PATCH')
            <div class="row">
                <!-- Left Column: Core product details -->
                <div class="col-md-6">
                    <!-- Product Title Input -->
                    <div class="form-group">
                        <label for="inputTitle">Title <span class="text-danger">*</span></label>
                        <input type="text" id="inputTitle" name="title" value="{{ old('title', $product->title) }}" class="form-control" placeholder="Enter title" required>
                        @error('title')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Product Summary Input -->
                    <div class="form-group">
                        <label for="summary">Summary <span class="text-danger">*</span></label>
                        <textarea id="summary" name="summary" class="form-control" required>{{ old('summary', $product->summary) }}</textarea>
                        @error('summary')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <!-- Featured Product Checkbox -->
                        <div class="col-md-3">
                            <div class="form-group form-check">
                                <input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}>
                                <label for="is_featured" class="form-check-label">Is Featured</label>
                            </div>
                        </div>

                        <!-- Product Category Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="cat_id">Category <span class="text-danger">*</span></label>
                                <select name="cat_id" id="cat_id" class="form-control" required>
                                    <option value="">-Select Category-</option>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}" {{ old('cat_id', $product->cat_id) == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                                    @endforeach
                                </select>
                                @error('cat_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Price Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="price">Price (NRS) <span class="text-danger">*</span></label>
                                <input type="number" id="price" name="price" class="form-control" value="{{ old('price', $product->price) }}" placeholder="Enter price" required>
                                @error('price')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Discount Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="discount">Discount (%)</label>
                                <input type="number" id="discount" name="discount" class="form-control" value="{{ old('discount', $product->discount) }}" min="0" max="100" placeholder="Enter discount">
                                @error('discount')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Product Size Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="size">Size</label>
                                @php $selectedSizes = old('size', explode(',', $product->size ?? '')); @endphp
                                <select name="size[]" class="form-control selectpicker" multiple data-live-search="true">
                                    @foreach(['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
                                        <option value="{{ $key }}" {{ in_array($key, $selectedSizes) ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('size')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Brand Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="brand_id">Brand</label>
                                <div class="input-group">
                                    <select name="brand_id" id="brand_id" class="form-control">
                                        <option value="">-Select Brand-</option>
                                        @foreach($brands as $brand)
                                            <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" id="addBrandBtn" class="btn btn-outline-primary">
                                            <i class="fa fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                @error('brand_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Condition Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="condition">Condition</label>
                                <select name="condition" class="form-control">
                                    <option value="">-Select Condition-</option>
                                    @foreach(['default' => 'Default', 'new' => 'New', 'hot' => 'Hot'] as $value => $label)
                                        <option value="{{ $value }}" {{ old('condition', $product->condition) === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('condition')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Quantity Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="stock">Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="stock" name="stock" class="form-control" value="{{ old('stock', $product->stock) }}" min="0" placeholder="Enter quantity" required>
                                @error('stock')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Product Status Selection -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status <span class="text-danger">*</span></label>
                                <select name="status" class="form-control" required>
                                    <option value="active" {{ old('status', $product->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', $product->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                                @error('status')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product SKU Input -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="sku">SKU</label>
                                <input type="text" id="sku" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}" placeholder="Enter SKU" readonly>
                                @error('sku')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Additional product details -->
                <div class="col-md-6">
                    <!-- Product Description Input -->
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control">{{ old('description', $product->description) }}</textarea>
                        @error('description')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Product Photos Input -->
                    <div class="form-group">
                        <label for="inputPhoto">Photos <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-btn">
                                <a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary">
                                    <i class="fa fa-picture-o"></i> Choose
                                </a>
                            </span>
                            <input type="text" id="thumbnail" name="photo" class="form-control" placeholder="Comma-separated image URLs" value="{{ old('photo', $product->images->pluck('image_path')->implode(',')) }}" readonly required>
                        </div>
                        <small class="form-text text-muted">Select multiple images. They will be comma-separated.</small>

                        <!-- Combined image preview area -->
                        <div id="image-preview-area" style="margin-top: 15px;">
                            <!-- Existing images display -->
                            @if($product->images->count())
                                <div id="existing-images">
                                    <label class="text-muted small">Current Images:</label>
                                    <div id="existing-holder" class="img-fluid" style="margin-bottom: 10px;">
                                        @foreach($product->images as $image)
                                            <div class="image-container" data-image-id="{{ $image->id }}">
                                                <img src="{{ asset($image->image_path) }}"
                                                     class="img-thumbnail image-preview"
                                                     alt="{{ $image->alt_text ?? 'Product Image' }}"
                                                     data-is-primary="{{ $image->is_primary ? 'true' : 'false' }}"
                                                     data-fallback-text="{{ $image->alt_text ?? $product->title . ' - Product Image' }}"
                                                     onerror="handleImageError(this)">
                                                <button type="button" class="btn btn-danger btn-sm delete-image-btn"
                                                        data-image-id="{{ $image->id }}"
                                                        data-product-id="{{ $product->id }}"
                                                        title="Delete Image">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                                @if($image->is_primary)
                                                    <div class="primary-badge">
                                                        <small class="badge badge-success">Primary</small>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- New images preview -->
                            <div id="new-images" style="display: none;">
                                <label class="text-muted small">New Images Preview:</label>
                                <div id="holder" class="img-fluid"></div>
                            </div>
                        </div>
                        @error('photo')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Alt Text Toggle Checkbox -->
                    <div class="form-group form-check">
                        <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input"
                               {{ old('enable_alt_text', $product->images->whereNotNull('alt_text')->count() > 0) ? 'checked' : '' }}>
                        <label for="enable_alt_text" class="form-check-label">
                            <i class="fa fa-image"></i> Enable Alt Text for Images
                            <small class="text-muted d-block">Check to add/edit descriptive text for images (improves SEO & accessibility)</small>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Current Alt Text Section -->
            @if($product->images->count())
                <div class="row" id="current-alt-section" style="{{ old('enable_alt_text', $product->images->whereNotNull('alt_text')->count() > 0) ? 'display: block;' : 'display: none;' }}">
                    <div class="col-12">
                        <hr>
                        <h6 class="text-primary">
                            <i class="fa fa-image"></i> Current Image Alt Text
                        </h6>
                        <p class="text-muted small">Update alt text for existing images to improve SEO and accessibility.</p>
                        <div id="current-alt-container" class="row">
                            @foreach($product->images as $index => $image)
                                <div class="col-md-6 alt-text-item" data-existing-id="{{ $image->id }}">
                                    <div class="d-flex align-items-start">
                                        <div class="image-counter {{ $image->is_primary ? 'primary-counter' : '' }}">{{ $index + 1 }}</div>
                                        <div class="alt-text-image-container">
                                            <img src="{{ asset($image->image_path) }}"
                                                 class="alt-text-preview mr-3"
                                                 alt="{{ $image->alt_text ?? 'Product Image Preview' }}"
                                                 onerror="handleAltTextImageError(this, '{{ $image->alt_text ?? $product->title . ' - Product Image' }}')">
                                        </div>
                                        <div class="flex-fill">
                                            <label class="font-weight-bold mb-2">
                                                Alt Text for Image {{ $index + 1 }}:
                                                @if($image->is_primary)
                                                    <span class="badge badge-success badge-sm">Primary</span>
                                                @endif
                                            </label>
                                            <textarea name="existing_alt_text[{{ $image->id }}]" class="form-control alt-text-input"
                                                      placeholder="Describe this image"
                                                      data-index="existing-{{ $image->id }}">{{ old('existing_alt_text.' . $image->id, $image->alt_text) }}</textarea>
                                            <small class="form-text text-muted">
                                                Good alt text: descriptive, concise (125 chars or less), includes product name
                                            </small>
                                            <div class="mt-2">
                                                <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" data-index="existing-{{ $image->id }}" data-type="existing">
                                                    <i class="fa fa-magic"></i> Auto-generate
                                                </button>
                                                <span class="ml-2 text-muted char-count">0/125 characters</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- New Images Alt Text Section -->
            <div id="alt-text-section" class="row" style="display: none;">
                <div class="col-12">
                    <hr>
                    <h6 class="text-primary">
                        <i class="fa fa-image"></i> New Image Alt Text Configuration
                    </h6>
                    <p class="text-muted small">Add descriptive alt text for new images to improve SEO and accessibility.</p>
                    <div id="alt-text-container" class="row"></div>
                </div>
            </div>

            <!-- Form Submission Button -->
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-success">Update Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Brand Modal -->
<div class="modal fade" id="addBrandModal" tabindex="-1" role="dialog" aria-labelledby="addBrandModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form id="addBrandForm">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBrandModalLabel">Add New Brand</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_brand_title">Brand Name</label>
                        <input type="text" name="title" id="new_brand_title" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<!-- External stylesheets -->
<link rel="stylesheet" href="{{ asset('backend/summernote/summernote.min.css') }}">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/css/bootstrap-select.css" />
<style>
    /* Ensure responsive table behavior */
    .table-responsive {
        overflow-x: auto;
        width: 100%;
    }

    /* Image preview container styling */
    #image-preview-area {
        position: relative;
    }

    #existing-holder, #holder {
        min-height: 140px;
        padding: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .image-container {
        display: inline-block;
        position: relative;
        margin: 5px;
        border-radius: 8px;
        background: #fff;
        padding: 5px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    .image-container:hover {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        transform: translateY(-2px);
    }

    .image-preview {
        height: 120px;
        width: 120px;
        object-fit: cover;
        display: block;
        transition: all 0.3s ease;
        cursor: zoom-in;
        border-radius: 6px;
        position: relative;
        z-index: 1;
    }

    .image-preview:hover {
        transform: scale(1.05);
    }

    .image-preview[data-is-primary="true"] {
        border: 3px solid #28a745;
    }

    .primary-badge {
        position: absolute;
        top: 0px;
        left: 0px;
        z-index: 5;
    }

    .primary-badge .badge {
        font-size: 10px;
        padding: 3px 6px;
    }

    /* Delete button styling */
    .delete-image-btn {
        position: absolute;
        top: -5px;
        right: -5px;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        z-index: 10;
        background-color: #dc3545 !important;
        border: 2px solid #fff !important;
        color: #fff !important;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.4);
        opacity: 0.7;
    }

    .image-container:hover .delete-image-btn {
        opacity: 1;
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.6);
    }

    .delete-image-btn:hover {
        background-color: #c82333 !important;
        transform: scale(1.2) !important;
        box-shadow: 0 6px 16px rgba(220, 53, 69, 0.8) !important;
    }

    .delete-image-btn:focus {
        outline: none;
        box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.25);
    }

    .image-container.deleting {
        opacity: 0.6;
        pointer-events: none;
        position: relative;
    }

    .image-container.deleting .delete-image-btn {
        display: none;
    }

    .image-container.deleting::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 8px;
        z-index: 15;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .image-container.deleting::after {
        content: '🗑️ Deleting...';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        color: #dc3545;
        font-weight: bold;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        z-index: 16;
        white-space: nowrap;
        border: 1px solid #dee2e6;
    }

    .image-not-found {
        height: 120px;
        width: 120px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px dashed #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        border-radius: 6px;
        color: #6c757d;
        font-size: 11px;
        text-align: center;
        padding: 10px;
    }

    .image-not-found i {
        font-size: 24px;
        margin-bottom: 5px;
        color: #adb5bd;
    }

    /* Responsive adjustments for smaller screens */
    @media (max-width: 768px) {
        .image-container {
            margin: 3px;
        }
        .image-preview, .image-not-found {
            height: 100px;
            width: 100px;
            font-size: 10px;
        }
        .delete-image-btn {
            width: 24px;
            height: 24px;
            font-size: 10px;
            top: -3px;
            right: -3px;
        }
    }

    /* Alt text section styling */
    .alt-text-item {
        margin-bottom: 20px;
        padding: 15px;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }

    .alt-text-item:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .alt-text-preview {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 4px;
        border: 2px solid #dee2e6;
    }

    .alt-text-image-container {
        position: relative;
    }

    .alt-text-preview.image-error {
        display: none;
    }

    .alt-text-fallback {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border: 2px dashed #dee2e6;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        color: #6c757d;
        font-size: 10px;
        text-align: center;
        padding: 5px;
        margin-right: 1rem;
    }

    .alt-text-fallback i {
        display: block;
        font-size: 16px;
        margin-bottom: 2px;
        color: #adb5bd;
    }

    .alt-text-input {
        resize: vertical;
        min-height: 60px;
    }

    .image-counter {
        background: #007bff;
        color: white;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: bold;
        margin-right: 10px;
    }

    .primary-counter {
        background: #28a745;
    }

    .badge-sm {
        font-size: 0.75em;
    }

    #enable_alt_text+label {
        cursor: pointer;
        padding: 8px 12px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        background-color: #f8f9fa;
        transition: all 0.3s ease;
    }

    #enable_alt_text:checked+label {
        background-color: #e7f3ff;
        border-color: #007bff;
    }

    /* Animation for alt text section */
    .alt-text-section-show {
        animation: slideDown 0.3s ease-out;
    }

    .alt-text-section-hide {
        animation: slideUp 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            max-height: 1000px;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 1;
            max-height: 1000px;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            max-height: 0;
            transform: translateY(-10px);
        }
    }

    /* Notification toast styling */
    .notification-toast {
        border-radius: 6px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
    }

    .notification-toast .close {
        color: inherit;
        opacity: 0.8;
    }

    .notification-toast .close:hover {
        opacity: 1;
    }
</style>
@endpush

@push('scripts')
<!-- External JavaScript dependencies -->
<script src="/vendor/laravel-filemanager/js/stand-alone-button.js"></script>
<script src="{{ asset('backend/summernote/summernote.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-select/1.13.1/js/bootstrap-select.min.js"></script>

<script>
    // Initialize Laravel File Manager for image selection
    $('#lfm').filemanager('image');

    // Store original image paths for comparison
    const originalImagePaths = '{{ $product->images->pluck("image_path")->implode(",") }}';

    // Handle image loading errors with fallback display
    function handleImageError(img) {
        const fallbackText = img.getAttribute('data-fallback-text') || 'Image not available';
        const container = img.parentElement;
        const fallback = document.createElement('div');
        fallback.className = 'image-not-found';
        fallback.innerHTML = `<i class="fa fa-image"></i><span>${fallbackText}</span>`;
        container.insertBefore(fallback, img);
        img.style.display = 'none';
    }

    // Handle alt text image errors with fallback display
    function handleAltTextImageError(img, fallbackText) {
        const container = img.parentElement;
        const fallback = document.createElement('div');
        fallback.className = 'alt-text-fallback';
        fallback.innerHTML = `<i class="fa fa-image"></i><span>${fallbackText}</span>`;
        container.appendChild(fallback);
        img.classList.add('image-error');
    }

    // Display notification messages
    function showNotification(message, type = 'info') {
        $('.notification-toast').remove();
        const notificationClass = type === 'success' ? 'alert-success' :
                                 type === 'error' ? 'alert-danger' : 'alert-info';
        const $notification = $(`
            <div class="alert ${notificationClass} notification-toast alert-dismissible" 
                 style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 350px;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
            </div>
        `);
        $('body').append($notification);
        setTimeout(() => $notification.fadeOut(400, function() { $(this).remove(); }), 4000);
    }

    // Update thumbnail input after image deletion
    function updateThumbnailInput() {
        const remainingImages = [];
        $('#existing-holder .image-container').each(function() {
            const imgSrc = $(this).find('img.image-preview').attr('src');
            if (imgSrc) {
                const relativePath = imgSrc.includes(window.location.origin) ? imgSrc.replace(window.location.origin + '/', '') : imgSrc;
                remainingImages.push(relativePath);
            }
        });
        $('#thumbnail').val(remainingImages.join(','));
    }

    // Remove alt text section for deleted image
    function updateAltTextSection(imageId) {
        const $altTextItem = $(`.alt-text-item[data-existing-id="${imageId}"]`);
        if ($altTextItem.length) {
            $altTextItem.fadeOut(300, function() {
                $(this).remove();
                if ($('#current-alt-container .alt-text-item').length === 0) {
                    $('#current-alt-section').fadeOut(300);
                }
            });
        }
    }

    // Toggle visibility of alt text sections
    function toggleAltTextSections() {
        const isEnabled = $('#enable_alt_text').is(':checked');
        const $currentAltSection = $('#current-alt-section');
        const $newAltSection = $('#alt-text-section');
        if (isEnabled) {
            $currentAltSection.addClass('alt-text-section-show').show();
            if ($('#holder').children().length > 0 || $('#thumbnail').val().trim() !== originalImagePaths) {
                $newAltSection.addClass('alt-text-section-show').show();
            }
        } else {
            $currentAltSection.addClass('alt-text-section-hide');
            $newAltSection.addClass('alt-text-section-hide');
            setTimeout(() => {
                $currentAltSection.hide().removeClass('alt-text-section-hide alt-text-section-show');
                $newAltSection.hide().removeClass('alt-text-section-hide alt-text-section-show');
            }, 300);
        }
    }

    // Update image preview for new images
    function updateImagePreview() {
        const imageInput = $('#thumbnail').val().trim();
        const $holder = $('#holder');
        const $newImagesDiv = $('#new-images');
        const $altSection = $('#alt-text-section');
        const $altContainer = $('#alt-text-container');

        $holder.empty();
        $altContainer.empty();

        if (!imageInput || imageInput === originalImagePaths) {
            $newImagesDiv.hide();
            $altSection.hide();
            return;
        }

        const images = imageInput.split(',').map(url => url.trim()).filter(url => url);
        $newImagesDiv.show();
        if ($('#enable_alt_text').is(':checked')) {
            $altSection.addClass('alt-text-section-show').show();
        }

        images.forEach((url, index) => {
            const container = $('<div class="image-container"></div>');
            const img = $('<img />', {
                src: url,
                class: 'img-thumbnail image-preview',
                alt: `New Product Image ${index + 1}`,
                'data-index': index,
                'data-fallback-text': `New Image ${index + 1}`
            });

            img.on('error', function() { handleImageError(this); });
            container.append(img);
            $holder.append(container);

            if ($('#enable_alt_text').is(':checked')) {
                createAltTextInput(url, index, $altContainer);
            }
        });
    }

    // Create alt text input for a new image
    function createAltTextInput(imageUrl, index, container) {
        const productTitle = $('#inputTitle').val() || 'Product';
        const suggestedAlt = `${productTitle} - Image ${index + 1}`;
        const altTextHtml = `
            <div class="col-md-6 alt-text-item" data-index="${index}">
                <div class="d-flex align-items-start">
                    <div class="image-counter">${index + 1}</div>
                    <div class="alt-text-image-container">
                        <img src="${imageUrl}" class="alt-text-preview mr-3" alt="Preview" 
                             onerror="handleAltTextImageError(this, '${suggestedAlt}')">
                    </div>
                    <div class="flex-fill">
                        <label class="font-weight-bold mb-2">Alt Text for New Image ${index + 1}:</label>
                        <textarea name="new_alt_text[]" class="form-control alt-text-input" 
                                  placeholder="Describe this image (e.g., ${suggestedAlt})"
                                  data-index="new-${index}">${suggestedAlt}</textarea>
                        <small class="form-text text-muted">
                            Good alt text: descriptive, concise (125 chars or less), includes product name
                        </small>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" 
                                    data-index="new-${index}" data-type="new">
                                <i class="fa fa-magic"></i> Auto-generate
                            </button>
                            <span class="ml-2 text-muted char-count">0/125 characters</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.append(altTextHtml);
        updateCharCount(`new-${index}`);
    }

    // Update character count for alt text input
    function updateCharCount(index) {
        const $textarea = $(`textarea[data-index="${index}"]`);
        const $charCount = $textarea.closest('.alt-text-item').find('.char-count');
        const length = $textarea.val().length;
        $charCount.text(`${length}/125 characters`);
        $charCount.toggleClass('text-danger', length > 125).toggleClass('text-muted', length <= 125);
    }

    $(document).ready(function() {
        // Initialize Summernote editors
        $('#summary, #description').summernote({
            tabsize: 2,
            height: 150
        });

        // Initialize character counts for existing alt text inputs
        @if($product->images->isNotEmpty())
            @foreach($product->images as $image)
                updateCharCount('existing-{{ $image->id }}');
            @endforeach
        @endif

        // Initialize alt text sections visibility
        toggleAltTextSections();

        // Handle alt text toggle
        $('#enable_alt_text').on('change', function() {
            toggleAltTextSections();
            if ($(this).is(':checked') && $('#thumbnail').val().trim() !== originalImagePaths) {
                updateImagePreview();
            }
        });

        // Monitor thumbnail input changes
        $('#thumbnail').on('input change', updateImagePreview);

        // Monitor file manager button clicks
        $('#lfm').on('click', function() {
            const originalValue = $('#thumbnail').val();
            let checkCount = 0;
            const checkInterval = setInterval(() => {
                checkCount++;
                const currentValue = $('#thumbnail').val();
                if (currentValue && currentValue !== originalValue) {
                    updateImagePreview();
                    clearInterval(checkInterval);
                } else if (checkCount > 20) {
                    clearInterval(checkInterval);
                }
            }, 500);
        });

        // Handle image deletion via AJAX
        $(document).on('click', '.delete-image-btn', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const imageId = $(this).data('image-id');
            const productId = $(this).data('product-id');
            const $imageContainer = $(this).closest('.image-container');
            const $button = $(this);

            if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                return;
            }

            $imageContainer.addClass('deleting');
            $button.prop('disabled', true);

            $.ajax({
                url: `/admin/product/${productId}/image/${imageId}/delete`,
                type: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (response.success) {
                        $imageContainer.fadeOut(400, function() {
                            $(this).remove();
                            if ($('#existing-holder .image-container').length === 0) {
                                $('#existing-images').fadeOut(300);
                            }
                            updateThumbnailInput();
                            showNotification('Image deleted successfully!', 'success');
                            updateAltTextSection(imageId);
                            originalImagePaths = $('#thumbnail').val();
                        });
                    } else {
                        $imageContainer.removeClass('deleting');
                        $button.prop('disabled', false);
                        showNotification(response.message || 'Failed to delete image. Please try again.', 'error');
                    }
                },
                error: function(xhr) {
                    $imageContainer.removeClass('deleting');
                    $button.prop('disabled', false);
                    let errorMessage = 'Failed to delete image. Please try again.';
                    if (xhr.responseJSON?.message) {
                        errorMessage = xhr.responseJSON.message;
                    } else if (xhr.status === 404) {
                        errorMessage = 'Image not found or already deleted.';
                    } else if (xhr.status === 403) {
                        errorMessage = 'You do not have permission to delete this image.';
                    } else if (xhr.status === 500) {
                        errorMessage = 'Server error occurred. Please try again later.';
                    }
                    showNotification(errorMessage, 'error');
                }
            });
        });

        // Auto-generate alt text
        $(document).on('click', '.auto-generate-alt', function() {
            const index = $(this).data('index');
            const type = $(this).data('type');
            const productTitle = $('#inputTitle').val() || 'Product';
            const category = $('#cat_id option:selected').text();
            const brand = $('#brand_id option:selected').text();
            let autoAlt = productTitle;
            if (brand && brand !== '-Select Brand-') autoAlt += ` by ${brand}`;
            if (category && category !== '-Select Category-') autoAlt += ` - ${category}`;
            autoAlt += type === 'existing' ? ' - Product Image' : ` - Image ${parseInt(index.replace('new-', '')) + 1}`;
            $(`textarea[data-index="${index}"]`).val(autoAlt);
            updateCharCount(index);
        });

        // Track alt text character count
        $(document).on('input', 'textarea.alt-text-input', function() {
            updateCharCount($(this).data('index'));
        });

        // Form submission validation
        $('form').on('submit', function(e) {
            const discountValue = parseFloat($('#discount').val());
            if (!isNaN(discountValue) && (discountValue < 0 || discountValue > 100)) {
                e.preventDefault();
                $('#discount').focus();
                showNotification('Please enter a valid discount between 0 and 100%.', 'error');
                return false;
            }

            $('#summary').val($('#summary').summernote('code'));
            $('#description').val($('#description').summernote('code'));

            if ($('#enable_alt_text').is(':checked')) {
                const emptyExistingAltTexts = $('textarea[name*="existing_alt_text"]').filter(function() {
                    return $(this).val().trim() === '';
                });
                if (emptyExistingAltTexts.length > 0) {
                    e.preventDefault();
                    showNotification('Please provide alt text for all existing images or uncheck "Enable Alt Text" option.', 'error');
                    emptyExistingAltTexts.first().focus();
                    return false;
                }

                const newAltTexts = $('textarea[name="new_alt_text[]"]');
                if (newAltTexts.length > 0) {
                    const emptyNewAltTexts = newAltTexts.filter(function() {
                        return $(this).val().trim() === '';
                    });
                    if (emptyNewAltTexts.length > 0) {
                        e.preventDefault();
                        showNotification('Please provide alt text for all new images or uncheck "Enable Alt Text" option.', 'error');
                        emptyNewAltTexts.first().focus();
                        return false;
                    }
                }
            }
        });

        // Load subcategories dynamically
        const childCatId = '{{ $product->child_cat_id }}';
        const catId = $('#cat_id').val();
        if (catId) loadSubCategories(catId, childCatId);

        $('#cat_id').change(function() {
            loadSubCategories($(this).val());
        });

        function loadSubCategories(catId, selectedId = null) {
            if (!catId) {
                $('#child_cat_div').addClass('d-none');
                $('#child_cat_id').html('<option value="">-Select sub category-</option>');
                return;
            }

            $.ajax({
                url: `/admin/category/${catId}/child`,
                method: 'POST',
                data: { _token: '{{ csrf_token() }}' },
                success: function(response) {
                    let html = '<option value="">-Select sub category-</option>';
                    if (response.status && response.data) {
                        $.each(response.data, function(id, title) {
                            const selected = id == selectedId ? 'selected' : '';
                            html += `<option value="${id}" ${selected}>${title}</option>`;
                        });
                        $('#child_cat_div').removeClass('d-none');
                    } else {
                        $('#child_cat_div').addClass('d-none');
                    }
                    $('#child_cat_id').html(html);
                },
                error: function() {
                    $('#child_cat_div').addClass('d-none');
                }
            });
        }

        // Handle brand form submission
        $('#addBrandBtn').click(function() {
            $('#new_brand_title').val('');
            $('#addBrandModal').modal('show');
        });

        $('#addBrandForm').submit(function(e) {
            e.preventDefault();
            const brandName = $('#new_brand_title').val().trim();
            if (!brandName) return;

            $.ajax({
                url: "{{ route('brand.store.ajax') }}",
                type: "POST",
                data: { _token: "{{ csrf_token() }}", title: brandName },
                success: function(res) {
                    if (res.status === 'success') {
                        $('#brand_id').append(`<option value="${res.data.id}" selected>${res.data.title}</option>`);
                        $('#brand_id').selectpicker('refresh');
                        $('#addBrandModal').modal('hide');
                        showNotification('Brand added successfully!', 'success');
                    } else {
                        showNotification(res.message || 'Error adding brand', 'error');
                    }
                },
                error: function() {
                    showNotification('Something went wrong while adding brand.', 'error');
                }
            });
        });

        // Validate discount input
        $('#discount').on('input blur', function() {
            const discountValue = parseFloat($(this).val());
            const $discountField = $(this);
            let $errorSpan = $discountField.next('.discount-error');
            if ($errorSpan.length === 0) {
                $discountField.after('<span class="text-danger discount-error" style="font-size: 0.875rem;"></span>');
                $errorSpan = $discountField.next('.discount-error');
            }

            $discountField.removeClass('is-invalid');
            $errorSpan.text('').hide();

            if (isNaN(discountValue)) return;

            if (discountValue < 0) {
                $discountField.addClass('is-invalid').val(0);
                $errorSpan.text('Discount cannot be negative. Set to 0.').show();
                showNotification('Discount cannot be negative.', 'error');
            } else if (discountValue > 100) {
                $discountField.addClass('is-invalid').val(100);
                $errorSpan.text('Discount cannot exceed 100%. Set to 100.').show();
                showNotification('Discount cannot exceed 100%.', 'error');
            }
        });

        // Restrict discount input to valid characters
        $('#discount').on('keypress', function(e) {
            if ([46, 8, 9, 27, 13, 110, 190].includes(e.keyCode) ||
                (e.keyCode === 65 && e.ctrlKey) ||
                (e.keyCode === 67 && e.ctrlKey) ||
                (e.keyCode === 86 && e.ctrlKey) ||
                (e.keyCode === 88 && e.ctrlKey) ||
                (e.keyCode >= 35 && e.keyCode <= 39)) {
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
        });
    });
</script>
@endpush