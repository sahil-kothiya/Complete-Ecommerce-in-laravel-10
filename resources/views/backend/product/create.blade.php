@section('main-content')

<!-- Main content area for adding a new product -->
<div class="card">
    <h5 class="card-header">Add Product</h5>
    <div class="card-body">
        <form method="POST" action="{{ route('product.store') }}">
            @csrf
            <div class="row">
                <!-- Left Column: Core product details -->
                <div class="col-md-6">
                    <!-- Product Title Input -->
                    <div class="form-group">
                        <label for="inputTitle">Title <span class="text-danger">*</span></label>
                        <input type="text" id="inputTitle" name="title" value="{{ old('title') }}" class="form-control" placeholder="Enter title" required tabindex="1">
                        @error('title')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Product Summary Input -->
                    <div class="form-group">
                        <label for="summary">Summary <span class="text-danger">*</span></label>
                        <textarea id="summary" name="summary" class="form-control" required tabindex="2">{{ old('summary') }}</textarea>
                        @error('summary')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="row">
                        <!-- Featured Product Checkbox -->
                        <div class="col-md-3">
                            <div class="form-group form-check">
                                <input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured') ? 'checked' : '' }} tabindex="3">
                                <label for="is_featured" class="form-check-label">Is Featured</label>
                            </div>
                        </div>

                        <!-- Product Category Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="cat_id">Category <span class="text-danger">*</span></label>
                                <select name="cat_id" id="cat_id" class="form-control" required tabindex="4">
                                    <option value="">-Select Category-</option>
                                    @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ old('cat_id') == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                                    @endforeach
                                </select>
                                @error('cat_id')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Price Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="price">Price (NRS) <span class="text-danger">*</span></label>
                                <input type="number" id="price" name="price" class="form-control" value="{{ old('price') }}" placeholder="Enter price" required tabindex="5">
                                @error('price')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Discount Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="discount">Discount (%)</label>
                                <input type="number" id="discount" name="discount" class="form-control" value="{{ old('discount') }}" min="0" max="100" placeholder="Enter discount" tabindex="6">
                                @error('discount')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Product Size Selection -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="size">Size</label>
                                @php $selectedSizes = old('size', []); @endphp
                                <select name="size[]" class="form-control selectpicker" multiple data-live-search="true" tabindex="7">
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
                                    <select name="brand_id" id="brand_id" class="form-control" tabindex="8">
                                        <option value="">-Select Brand-</option>
                                        @foreach($brands as $brand)
                                        <option value="{{ $brand->id }}" {{ old('brand_id') == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
                                        @endforeach
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" id="addBrandBtn" class="btn btn-outline-primary" tabindex="9">
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
                                <select name="condition" class="form-control" tabindex="10">
                                    <option value="">-Select Condition-</option>
                                    @foreach(['default' => 'Default', 'new' => 'New', 'hot' => 'Hot'] as $value => $label)
                                    <option value="{{ $value }}" {{ old('condition') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('condition')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>

                        <!-- Product Quantity Input -->
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="stock">Quantity <span class="text-danger">*</span></label>
                                <input type="number" id="stock" name="stock" class="form-control" value="{{ old('stock') }}" min="0" placeholder="Enter quantity" required tabindex="11">
                                @error('stock')<span class="text-danger">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <!-- Product Status Selection -->
                    <div class="form-group">
                        <label for="status">Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required tabindex="12">
                            <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                            <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                        </select>
                        @error('status')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <!-- Right Column: Additional product details -->
                <div class="col-md-6">
                    <!-- Product Description Input -->
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" tabindex="13">{{ old('description') }}</textarea>
                        @error('description')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Product Photos Input -->
                    <div class="form-group">
                        <label for="inputPhoto">Photos <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-btn">
                                <a id="lfm" data-input="thumbnail" data-preview="holder" class="btn btn-primary" tabindex="14">
                                    <i class="fa fa-picture-o"></i> Choose
                                </a>
                            </span>
                            <input type="text" id="thumbnail" name="photo" class="form-control" placeholder="Comma-separated image URLs" value="{{ old('photo') }}" readonly required tabindex="15">
                        </div>
                        <small class="form-text text-muted">Select multiple images. They will be comma-separated.</small>
                        <div id="image-preview-area" style="margin-top: 15px;">
                            <div id="holder" class="img-fluid"></div>
                        </div>
                        @error('photo')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <!-- Alt Text Toggle Checkbox -->
                    <div class="form-group form-check" id="alt-text-toggle" style="display: none;">
                        <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input" tabindex="16">
                        <label for="enable_alt_text" class="form-check-label">
                            <i class="fa fa-image"></i> Enable Alt Text for Images
                            <small class="text-muted d-block">Check to add descriptive text for images (improves SEO & accessibility)</small>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Alt Text Configuration Section -->
            <div id="alt-text-section" class="row" style="display: none;">
                <div class="col-12">
                    <hr>
                    <h6 class="text-primary">
                        <i class="fa fa-image"></i> Image Alt Text Configuration
                    </h6>
                    <p class="text-muted small">Add descriptive alt text for images to improve SEO and accessibility.</p>
                    <div id="alt-text-container" class="row"></div>
                </div>
            </div>

            <!-- Form Submission Button -->
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-success" tabindex="17">Add Product</button>
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
                    <button type="button" class="close" data-dismiss="modal" tabindex="18">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_brand_title">Brand Name</label>
                        <input type="text" name="title" id="new_brand_title" class="form-control" required tabindex="19">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" tabindex="20">Save</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal" tabindex="21">Cancel</button>
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

    #holder {
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

        .image-preview,
        .image-not-found {
            height: 100px;
            width: 100px;
            font-size: 10px;
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

    #alt-text-toggle.show {
        display: block !important;
        animation: fadeIn 0.3s ease-in;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
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
        setTimeout(() => $notification.fadeOut(400, function() {
            $(this).remove();
        }), 4000);
    }

    // Toggle alt text section visibility
    function toggleAltTextSection() {
        const isEnabled = $('#enable_alt_text').is(':checked');
        const $altSection = $('#alt-text-section');
        if (isEnabled) {
            $altSection.addClass('alt-text-section-show').show();
            updateImagePreview();
        } else {
            $altSection.addClass('alt-text-section-hide');
            setTimeout(() => $altSection.hide().removeClass('alt-text-section-hide alt-text-section-show'), 300);
        }
    }

    // Update image preview and alt text inputs
    function updateImagePreview() {
        const imageInput = $('#thumbnail').val().trim();
        const $holder = $('#holder');
        const $altToggle = $('#alt-text-toggle');
        const $altSection = $('#alt-text-section');
        const $altContainer = $('#alt-text-container');

        $holder.empty();
        $altContainer.empty();

        if (!imageInput) {
            $holder.hide();
            $altToggle.removeClass('show').hide();
            $altSection.hide();
            return;
        }

        const images = imageInput.split(',').map(url => url.trim()).filter(url => url);
        $holder.show();
        $altToggle.addClass('show').show();

        images.forEach((url, index) => {
            const container = $('<div class="image-container"></div>');
            const img = $('<img />', {
                src: url,
                class: 'img-thumbnail image-preview',
                alt: `Product Image ${index + 1}`,
                'data-index': index,
                'data-is-primary': index === 0,
                'data-fallback-text': `Product Image ${index + 1}`
            });

            if (index === 0) {
                container.append('<div class="primary-badge"><small class="badge badge-success">Primary</small></div>');
            }

            img.on('error', function() {
                handleImageError(this);
            });
            container.append(img);
            $holder.append(container);

            if ($('#enable_alt_text').is(':checked')) {
                createAltTextInput(url, index, $altContainer);
            }
        });

        if ($('#enable_alt_text').is(':checked')) {
            $altSection.addClass('alt-text-section-show').show();
        }
    }

    // Create alt text input for an image
    function createAltTextInput(imageUrl, index, container) {
        const productTitle = $('#inputTitle').val() || 'Product';
        const suggestedAlt = `${productTitle} - ${index === 0 ? 'Main Image' : `Image ${index + 1}`}`;
        const isPrimary = index === 0;
        const counterClass = isPrimary ? 'image-counter primary-counter' : 'image-counter';
        const primaryBadge = isPrimary ? '<span class="badge badge-success badge-sm ml-1">Primary</span>' : '';
        const tabIndex = 22 + index; // Start after static form elements

        const altTextHtml = `
        <div class="col-md-6 alt-text-item" data-index="${index}">
            <div class="d-flex align-items-start">
                <div class="${counterClass}">${index + 1}</div>
                <div class="alt-text-image-container">
                    <img src="${imageUrl}" class="alt-text-preview mr-3" alt="Preview" 
                         onerror="handleAltTextImageError(this, '${suggestedAlt}')">
                </div>
                <div class="flex-fill">
                    <label class="font-weight-bold mb-2">
                        Alt Text for Image ${index + 1}:${primaryBadge}
                    </label>
                    <textarea name="alt_text[${index}]" class="form-control alt-text-input" 
                              placeholder="Describe this image (e.g., ${suggestedAlt})"
                              data-index="${index}" tabindex="${tabIndex}">${suggestedAlt}</textarea>
                    <small class="form-text text-muted">
                        Good alt text: descriptive, concise (125 chars or less), includes product name
                    </small>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" 
                                data-index="${index}" data-type="new" tabindex="${tabIndex + 1}">
                            <i class="fa fa-magic"></i> Auto-generate
                        </button>
                        <span class="ml-2 text-muted char-count">0/125 characters</span>
                    </div>
                </div>
            </div>
        </div>
    `;

        container.append(altTextHtml);
        updateCharCount(index);
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

        // Initialize image preview if data exists
        if ($('#thumbnail').val()) {
            updateImagePreview();
        }

        // Handle alt text toggle
        $('#enable_alt_text').on('change', toggleAltTextSection);

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

        // Auto-generate alt text
        $(document).on('click', '.auto-generate-alt', function() {
            const index = $(this).data('index');
            const productTitle = $('#inputTitle').val() || 'Product';
            const category = $('#cat_id option:selected').text();
            const brand = $('#brand_id option:selected').text();
            let autoAlt = productTitle;
            if (brand && brand !== '-Select Brand-') autoAlt += ` by ${brand}`;
            if (category && category !== '-Select Category-') autoAlt += ` - ${category}`;
            autoAlt += index === 0 ? ' - Main Product Image' : ` - Product Image ${index + 1}`;
            $(`textarea[name="alt_text[${index}]"]`).val(autoAlt);
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
                const altTexts = $('textarea[name^="alt_text["]');
                const emptyAltTexts = altTexts.filter(function() {
                    return $(this).val().trim() === '';
                });
                if (emptyAltTexts.length > 0) {
                    e.preventDefault();
                    showNotification('Please provide alt text for all images or uncheck "Enable Alt Text" option.', 'error');
                    emptyAltTexts.first().focus();
                    return false;
                }
            }
        });

        // Load subcategories dynamically
        $('#cat_id').change(function() {
            const catId = $(this).val();
            if (!catId) {
                $('#child_cat_div').addClass('d-none');
                $('#child_cat_id').html('<option value="">-Select sub category-</option>');
                return;
            }

            $.ajax({
                url: `/admin/category/${catId}/child`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status && response.data.length > 0) {
                        let options = '<option value="">-Select sub category-</option>';
                        response.data.forEach(child => {
                            options += `<option value="${child.id}">${child.title}</option>`;
                        });
                        $('#child_cat_id').html(options);
                        $('#child_cat_div').removeClass('d-none');
                    } else {
                        $('#child_cat_div').addClass('d-none');
                    }
                },
                error: function() {
                    $('#child_cat_div').addClass('d-none');
                }
            });
        });

        // Load subcategories if category is pre-selected
        const selectedCatId = $('#cat_id').val();
        if (selectedCatId) {
            $('#cat_id').trigger('change');
        }

        // Show Add Brand Modal
        $('#addBrandBtn').click(function() {
            $('#new_brand_title').val('');
            $('#addBrandModal').modal('show');
        });

        // Handle brand form submission
        $('#addBrandForm').submit(function(e) {
            e.preventDefault();
            const brandName = $('#new_brand_title').val().trim();
            if (!brandName) return;

            $.ajax({
                url: "{{ route('brand.store.ajax') }}",
                type: "POST",
                data: {
                    _token: "{{ csrf_token() }}",
                    title: brandName
                },
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