@extends('backend.layouts.master')

@section('main-content')
    <div class="card shadow-sm border-0">
        <h5 class="card-header">Edit Product</h5>
        <div class="card-body p-4">
            <div id="validation-summary" class="alert alert-danger alert-dismissible fade" role="alert"
                style="display: none;">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span>&times;</span>
                </button>
                <h5 class="alert-heading"><i class="fa fa-exclamation-triangle"></i> Validation Errors</h5>
                <div id="validation-errors-list"></div>
            </div>

            <form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data"
                id="product-form" novalidate>
                @csrf
                @method('PATCH')

                <!-- Core Product Details Section -->
                <section class="mb-5">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Core Product Information</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="inputTitle">Title <span class="text-danger">*</span></label>
                                <input type="text" id="inputTitle" name="title"
                                    value="{{ old('title', $product->title) }}" class="form-control"
                                    placeholder="Enter product title" required tabindex="1">
                                @error('title')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="slug">Slug <span class="text-danger">*</span> <small
                                        class="text-muted">(Auto-generated)</small></label>
                                <input type="text" id="slug" name="slug"
                                    value="{{ old('slug', $product->slug) }}" class="form-control"
                                    placeholder="product-slug" readonly required tabindex="2">
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
                                <textarea id="summary" name="summary" class="form-control summernote" rows="3" tabindex="3">{{ old('summary', $product->summary) }}</textarea>
                                @error('summary')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" class="form-control summernote" rows="5" tabindex="4">{{ old('description', $product->description) }}</textarea>
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
                                <select name="cat_id" id="cat_id" class="form-control" required tabindex="5">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ old('cat_id', $product->cat_id) == $cat->id ? 'selected' : '' }}>
                                            {{ $cat->title }}</option>
                                    @endforeach
                                </select>
                                @error('cat_id')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-4 {{ $product->child_cat_id ? '' : 'd-none' }}" id="child_cat_div">
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
                                                {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>
                                                {{ $brand->title }}</option>
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
                                <select name="status" id="status" class="form-control" required tabindex="16">
                                    <option value="">Select Status</option>
                                    <option value="active"
                                        {{ old('status', $product->status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive"
                                        {{ old('status', $product->status) == 'inactive' ? 'selected' : '' }}>Inactive
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
                <section class="mb-5" id="non-variant-section"
                    style="{{ $product->has_variants ? 'display: none;' : 'display: block;' }}">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Product Attributes (Non-Variant)</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_price">Price (NRS) <span class="text-danger">*</span></label>
                                <input type="number" id="base_price" name="base_price" step="0.01" min="0"
                                    value="{{ old('base_price', $product->base_price) }}" class="form-control"
                                    placeholder="Enter price" tabindex="9">
                                @error('base_price')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_discount">Discount (%)</label>
                                <input type="number" id="base_discount" name="base_discount" min="0"
                                    max="100" value="{{ old('base_discount', $product->base_discount) }}"
                                    class="form-control" placeholder="Enter discount" tabindex="10">
                                @error('base_discount')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_stock">Stock <span class="text-danger">*</span></label>
                                <input type="number" id="base_stock" name="base_stock" min="0"
                                    value="{{ old('base_stock', $product->base_stock) }}" class="form-control"
                                    placeholder="Enter stock" tabindex="11">
                                @error('base_stock')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="base_sku">SKU <span class="text-danger">*</span></label>
                                <input type="text" id="base_sku" name="base_sku"
                                    value="{{ old('base_sku', $product->base_sku) }}" class="form-control"
                                    placeholder="Enter unique SKU" tabindex="12">
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
                                    <option value="default"
                                        {{ old('condition', $product->condition) == 'default' ? 'selected' : '' }}>Default
                                    </option>
                                    <option value="new"
                                        {{ old('condition', $product->condition) == 'new' ? 'selected' : '' }}>New</option>
                                    <option value="hot"
                                        {{ old('condition', $product->condition) == 'hot' ? 'selected' : '' }}>Hot</option>
                                </select>
                                @error('condition')
                                    <span class="invalid-feedback d-block">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label for="size">Sizes</label>
                                @php
                                    $rawSize = $product->size;
                                    $decodedSizes = [];
                                    if (is_array($rawSize)) {
                                        $decodedSizes = $rawSize;
                                    } elseif (is_string($rawSize) && trim($rawSize) !== '') {
                                        $trimmed = trim($rawSize);
                                        $jsonCandidate = null;
                                        if (preg_match('/^\[/', $trimmed)) {
                                            $jsonCandidate = json_decode($trimmed, true);
                                        }
                                        if (is_array($jsonCandidate)) {
                                            $decodedSizes = $jsonCandidate;
                                        } else {
                                            $decodedSizes = array_filter(array_map('trim', explode(',', $rawSize)));
                                        }
                                    }
                                    $selectedSizes = old('size', $decodedSizes);
                                    if (!is_array($selectedSizes)) {
                                        $selectedSizes = [];
                                    }
                                @endphp
                                <select name="size[]" id="size" class="form-control selectpicker" multiple
                                    data-live-search="true" tabindex="14">
                                    @foreach (['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
                                        <option value="{{ $key }}"
                                            {{ in_array($key, $selectedSizes, true) ? 'selected' : '' }}>
                                            {{ $label }}</option>
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
                                    class="form-check-input"
                                    {{ old('is_featured', $product->is_featured) ? 'checked' : '' }} tabindex="15">
                                <label for="is_featured" class="form-check-label">Featured Product</label>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Images Section (Non-Variant) -->
                <section class="mb-5" id="non-variant-images"
                    style="{{ $product->has_variants ? 'display: none;' : 'display: block;' }}">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Product Images</h6>
                    <div class="form-group">
                        <label for="photo">Photos <span class="text-danger">*</span> <small class="text-muted">(At
                                least 1 image required)</small></label>
                        <div class="input-group">
                            @php
                                // Store PATHS in input (not URLs) for backend processing
                                // Use image_path directly from DB
                                $existingPhotoValue =
                                    !$product->has_variants && $product->images->count()
                                        ? $product->images->pluck('image_path')->implode(',')
                                        : '';
                            @endphp
                            <input type="text" id="photo" name="photo" class="form-control"
                                value="{{ old('photo', $existingPhotoValue) }}" placeholder="Comma-separated image paths"
                                readonly tabindex="17">
                            <div class="input-group-append">
                                <a id="lfm" data-input="photo" data-preview="holder" class="btn btn-primary"><i
                                        class="fa fa-picture-o"></i> Choose Images</a>
                            </div>
                        </div>
                        @error('photo')
                            <span class="invalid-feedback d-block">{{ $message }}</span>
                        @enderror
                        <div id="image-preview-area" class="mt-3">
                            <div id="holder" class="d-flex flex-wrap" style="gap: 1rem;">
                                @if ($product->images->count() && !$product->has_variants)
                                    @foreach ($product->images as $index => $image)
                                        @php
                                            // Try multiple URL formats to ensure image loads
                                            $imageUrl = $image->url;
                                            $fallbackUrl = asset('storage/products/' . $image->image_path);
                                        @endphp
                                        <div class="image-container"
                                            data-image-id="{{ $product->images[$index]->id ?? '' }}"
                                            data-path="{{ $image->image_path }}">
                                            @if ($index === 0)
                                                <div class="primary-badge">
                                                    <span class="badge badge-success">Primary</span>
                                                </div>
                                            @endif
                                            <img src="{{ $imageUrl }}" data-fallback="{{ $fallbackUrl }}"
                                                class="image-preview" alt="Product Image {{ $index + 1 }}"
                                                data-path="{{ $image->image_path }}"
                                                onerror="if(this.dataset.fallback && this.src !== this.dataset.fallback) { this.src = this.dataset.fallback; } else { this.src='{{ asset('backend/img/avatar.webp') }}'; this.alt='Image Not Found'; }">
                                            @if (isset($product->images[$index]))
                                                <button type="button" class="btn btn-danger btn-sm delete-image-btn"
                                                    data-image-id="{{ $product->images[$index]->id }}"
                                                    data-product-id="{{ $product->id }}" title="Delete Image">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                                <div id="new-images" class="d-flex flex-wrap" style="gap: 1rem;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group form-check" id="alt-text-toggle"
                        style="{{ $product->images->count() && !$product->has_variants ? 'display: block;' : 'display: none;' }}">
                        <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1"
                            class="form-check-input" tabindex="18">
                        <label for="enable_alt_text" class="form-check-label">Enable Alt Text for Images <small
                                class="text-muted">(Improves SEO & Accessibility)</small></label>
                    </div>
                    <div id="current-alt-section" class="row mt-3" style="display: none;">
                        @if ($product->images && !$product->has_variants)
                            @foreach ($product->images as $index => $image)
                                <div class="col-md-6 alt-text-item" data-existing-id="{{ $index }}">
                                    <div class="d-flex align-items-start">
                                        <img src="{{ $image->url }}" class="alt-text-preview mr-3" alt="Preview">
                                        <div class="flex-fill">
                                            <label class="font-weight-bold">Alt Text for Image {{ $index + 1 }}
                                                @if ($image->is_primary)
                                                    <span class="badge badge-success badge-sm ml-1">Primary</span>
                                                @endif
                                            </label>
                                            <textarea name="existing_alt_text[{{ $image->id }}]" class="form-control alt-text-input"
                                                placeholder="Describe this image" data-index="existing-{{ $index }}" tabindex="{{ 31 + $index }}">{{ old('existing_alt_text.' . $image->id, $image->alt_text) }}</textarea>
                                            <small class="form-text text-muted">Max 125 characters. Include product name
                                                for SEO.</small>
                                            <div class="mt-2">
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-primary auto-generate-alt"
                                                    data-index="existing-{{ $index }}"
                                                    tabindex="{{ 32 + $index }}">Auto-generate</button>
                                                <span class="ml-2 char-count">0/125 characters</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    <div id="alt-text-section" class="row mt-3" style="display: none;"></div>
                </section>

                <!-- Variants Section -->
                <section class="mb-5">
                    <h6 class="mb-3 text-uppercase font-weight-bold">Product Variants</h6>
                    <div class="form-group form-check">
                        <input type="checkbox" name="has_variants" id="has_variants" value="1"
                            class="form-check-input" {{ old('has_variants', $product->has_variants) ? 'checked' : '' }}
                            tabindex="21">
                        <label for="has_variants" class="form-check-label">Enable Variants <small
                                class="text-muted">(e.g., Colors, Sizes)</small></label>
                    </div>
                    <div id="variants-panel" class="p-4 bg-light rounded"
                        style="{{ $product->has_variants ? 'display: block;' : 'display: none;' }}">
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold">Select Variant Types & Options</label>
                                <button type="button" id="load-types" class="btn btn-secondary btn-sm">Load Variant
                                    Types</button>
                            </div>
                            <div id="type-selections" class="d-flex flex-wrap gap-3">
                                @if ($product->has_variants)
                                    @foreach ($product->variantTypes as $type)
                                        <div class="variant-type-group">
                                            <label class="font-weight-bold">{{ $type->display_name }}</label>

                                            <select class="form-control type-select" data-type-id="{{ $type->id }}"
                                                multiple name="variant_options[{{ $type->id }}][]">

                                                @foreach ($type->options as $option)
                                                    <option value="{{ $option->id }}"
                                                        {{ $option->selected ? 'selected' : '' }}>
                                                        {{ $option->display_value }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="font-weight-bold">Existing Variants</label>
                                <button type="button" id="generate-preview" class="btn btn-info btn-sm">Generate New
                                    Variants</button>
                            </div>
                            <div id="variant-preview" class="table-responsive">
                                @if ($product->has_variants)
                                    <table class="table table-bordered table-hover">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Variant Name</th>
                                                <th>SKU <span class="text-danger">*</span></th>
                                                <th>Price (NRS) <span class="text-danger">*</span></th>
                                                <th>Discount (%)</th>
                                                <th>Stock <span class="text-danger">*</span></th>
                                                <th>Images <span class="text-danger">*</span></th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($product->variants as $index => $variant)
                                                <tr data-variant-id="{{ $variant->id }}">
                                                    <td style="max-width: 200px; word-wrap: break-word; white-space: normal;"
                                                        title="{{ $variant->display_name }}">
                                                        <strong>{{ $variant->display_name }}</strong>
                                                    </td>
                                                    <td>
                                                        <input type="hidden" name="variants[{{ $index }}][id]"
                                                            value="{{ $variant->id }}">
                                                        <input type="text" name="variants[{{ $index }}][sku]"
                                                            value="{{ old('variants.' . $index . '.sku', $variant->sku) }}"
                                                            class="form-control" required>
                                                    </td>
                                                    <td><input type="number" name="variants[{{ $index }}][price]"
                                                            step="0.01" min="0"
                                                            value="{{ old('variants.' . $index . '.price', $variant->price) }}"
                                                            class="form-control" required></td>
                                                    <td><input type="number"
                                                            name="variants[{{ $index }}][discount]" min="0"
                                                            max="100"
                                                            value="{{ old('variants.' . $index . '.discount', $variant->discount) }}"
                                                            class="form-control"></td>
                                                    <td><input type="number" name="variants[{{ $index }}][stock]"
                                                            min="0"
                                                            value="{{ old('variants.' . $index . '.stock', $variant->stock) }}"
                                                            class="form-control" required></td>
                                                    <td>
                                                        <div class="input-group">
                                                            <input type="text"
                                                                name="variants[{{ $index }}][images]"
                                                                id="variant-images-{{ $index }}"
                                                                class="form-control"
                                                                value="{{ old('variants.' . $index . '.images', $variant->images->pluck('image_path')->implode(',')) }}"
                                                                readonly required>
                                                            <div class="input-group-append">
                                                                <a class="btn btn-primary lfm-variant"
                                                                    data-input="variant-images-{{ $index }}"
                                                                    data-preview="variant-holder-{{ $index }}"><i
                                                                        class="fa fa-picture-o"></i> Choose</a>
                                                            </div>
                                                        </div>
                                                        <div id="variant-holder-{{ $index }}"
                                                            class="mt-2 d-flex flex-wrap gap-2">
                                                            @foreach ($variant->images as $imgIndex => $image)
                                                                <div class="image-container"
                                                                    data-image-id="{{ $image->id }}">
                                                                    <img src="{{ $image->url }}" class="image-preview"
                                                                        alt="{{ $image->alt_text ?? $variant->display_name . ' - Variant Image' }}"
                                                                        data-is-primary="{{ $image->is_primary ? 'true' : 'false' }}"
                                                                        data-fallback-text="{{ $image->alt_text ?? $variant->display_name . ' - Variant Image' }}"
                                                                        onerror="this.src='{{ asset('backend/img/avatar.webp') }}'; this.alt='Image Not Found';">
                                                                    <button type="button"
                                                                        class="btn btn-danger btn-sm delete-variant-image-btn"
                                                                        data-image-id="{{ $image->id }}"
                                                                        data-variant-id="{{ $variant->id }}"
                                                                        title="Delete Image"
                                                                        tabindex="{{ 22 + $index * 10 + $imgIndex }}">
                                                                        <i class="fa fa-trash"></i>
                                                                    </button>
                                                                    @if ($image->is_primary)
                                                                        <div class="primary-badge">
                                                                            <small
                                                                                class="badge badge-success">Primary</small>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <button type="button"
                                                            class="btn btn-danger btn-sm delete-variant-btn"
                                                            data-variant-id="{{ $variant->id }}"
                                                            tabindex="{{ 22 + $index * 10 + $variant->images->count() }}">Delete</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Submit Button -->
                <div class="form-group text-right">
                    <button type="submit" class="btn btn-success btn-lg" tabindex="30">Update Product</button>
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

    <!-- Delete Variant Image Modal -->
    <div class="modal fade" id="deleteVariantImageModal" tabindex="-1" aria-labelledby="deleteVariantImageModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteVariantImageModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this variant image? This action cannot be undone.
                    <input type="hidden" id="deleteVariantImageId" name="imageId">
                    <input type="hidden" id="deleteVariantId" name="variantId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteVariantImage">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Generic Confirmation Modal (re-used for all confirm actions) -->
    <div class="modal fade" id="genericConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Action</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Content filled dynamically -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="genericConfirmBtn">Confirm</button>
                </div>
            </div>
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

        .image-container.deleting {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
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

        /* Variant name column styling */
        .table td:first-child {
            min-width: 150px;
            max-width: 200px;
            word-wrap: break-word;
            white-space: normal;
            font-weight: 500;
            color: #333;
        }

        .table th:first-child {
            min-width: 150px;
            max-width: 200px;
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

            .table td:first-child,
            .table th:first-child {
                min-width: 120px;
                max-width: 150px;
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

            // Initialize File Manager with custom behavior to APPEND images
            // This handler works for BOTH product images AND variant images
            function initializeFileManagerButton(selector, isVariant = false) {
                $(document).off('click', selector).on('click', selector, function(e) {
                    e.preventDefault();
                    const $btn = $(this);
                    const inputId = $btn.data('input');
                    const previewId = $btn.data('preview');
                    const $input = $('#' + inputId);
                    const $preview = $('#' + previewId);

                    // Store the original value to append to it
                    const originalValue = $input.val().trim();

                    // Open file manager
                    window.open('/filemanager?type=image', 'FileManager', 'width=900,height=600');

                    // Helper function to normalize image URLs for backend storage
                    function normalizeImageUrl(url) {
                        // If it's already a relative path, return as-is
                        if (!url.includes('://')) {
                            return url;
                        }

                        // Extract the path after /storage/
                        // e.g., http://localhost:8000/storage/photos/1/Products/abc.webp -> photos/1/Products/abc.webp
                        const match = url.match(/\/storage\/(.+)$/);
                        if (match) {
                            return match[1];
                        }

                        // Fallback: return the original URL
                        return url;
                    }

                    // Custom SetUrl function that APPENDS instead of REPLACES
                    window.SetUrl = function(files) {
                        // Normalize URLs to relative paths for backend storage
                        const newUrls = files.map(f => normalizeImageUrl(f.url));

                        // Keep full URLs for preview display
                        const newDisplayUrls = files.map(f => f.url);

                        // Get existing URLs
                        const existingUrls = originalValue ? originalValue.split(',').map(u => u.trim())
                            .filter(u => u) : [];

                        // Combine existing and new URLs (avoid duplicates)
                        const allUrls = [...existingUrls];
                        newUrls.forEach(url => {
                            if (!allUrls.includes(url)) {
                                allUrls.push(url);
                            }
                        });

                        // Update input value with normalized paths and trigger change
                        $input.val(allUrls.join(',')).trigger('change');

                        // For NON-VARIANTS, trigger the updateImagePreview function
                        if (!isVariant) {
                            if (typeof updateImagePreview === 'function') {
                                updateImagePreview();
                            }
                        }
                        // For variants, update the preview holder by APPENDING new images only
                        else if (isVariant && $preview.length) {
                            // Get URLs of images already in the preview
                            const existingPreviewUrls = [];
                            $preview.find('img.image-preview').each(function() {
                                existingPreviewUrls.push($(this).attr('src'));
                            });

                            // Only add NEW images that aren't already in the preview
                            const imagesToAdd = newDisplayUrls.filter(url => !existingPreviewUrls
                                .includes(url));

                            imagesToAdd.forEach((displayUrl, idx) => {
                                const normalizedUrl = normalizeImageUrl(displayUrl);
                                const isPrimary = existingPreviewUrls.length === 0 && idx === 0;

                                // Create container matching existing structure
                                const $container = $('<div class="image-container"></div>');

                                // Create image with error handling (use display URL for src)
                                const $img = $('<img class="image-preview" />').attr('src',
                                        displayUrl)
                                    .attr('alt', 'Variant Image')
                                    .on('error', function() {
                                        $(this).attr('src',
                                            '{{ asset('backend/img/avatar.webp') }}');
                                        $(this).attr('alt', 'Image Not Found');
                                    });

                                // Add delete button (for new images, we'll use a class to handle client-side deletion)
                                const $deleteBtn = $(
                                        '<button type="button" class="btn btn-danger btn-sm remove-new-image-btn" title="Remove Image"></button>'
                                    )
                                    .html('<i class="fa fa-trash"></i>')
                                    .on('click', function() {
                                        // Remove from preview
                                        $container.remove();

                                        // Update input value using normalized URL
                                        const currentUrls = $input.val().split(',').map(u =>
                                            u.trim()).filter(u => u);
                                        const updatedUrls = currentUrls.filter(u => u !==
                                            normalizedUrl);
                                        $input.val(updatedUrls.join(','));
                                    });

                                // Add primary badge if needed
                                if (isPrimary) {
                                    const $primaryBadge = $('<div class="primary-badge"></div>')
                                        .html(
                                            '<small class="badge badge-success">Primary</small>'
                                        );
                                    $container.append($primaryBadge);
                                }

                                // Append elements in correct order
                                $container.append($img);
                                $container.append($deleteBtn);
                                $preview.append($container);
                            });
                        }
                    };

                    return false;
                });
            }

            // Initialize main product image file manager
            initializeFileManagerButton('#lfm', false);

            // Initialize existing variant file managers
            initializeFileManagerButton('.lfm-variant', true);

            // Re-initialize variant file managers when new variants are added
            $(document).on('DOMNodeInserted', '#variant-preview', function() {
                initializeFileManagerButton('.lfm-variant', true);
            });

            // Initialize Select2
            $('.type-select').each(function() {
                const typeId = $(this).data('type-id');
                $(this).select2({
                    placeholder: `Select ${$(this).prev().text()} options`,
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#variants-panel')
                });
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
                    if ($('#type-selections').children().length === 0) {
                        $('#load-types').click();
                    }
                } else {
                    $('#base_price, #base_stock, #base_sku, #photo').attr('required', true);
                }
            });

            if ($('#has_variants').is(':checked')) {
                $('#has_variants').trigger('change');
            }

            // Enhanced real-time field validation helpers
            function validateField($field, rules) {
                const value = $field.val();
                const fieldName = $field.attr('name') || $field.attr('id');
                let error = '';

                $field.removeClass('is-invalid custom-is-invalid is-valid');
                $field.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback').remove();

                if ($field.is(':hidden') || $field.is(':disabled')) {
                    return true;
                }

                if (rules.required && (!value || String(value).trim() === '')) {
                    error = `${rules.label || fieldName} is required.`;
                } else if (rules.email && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    error = `Please enter a valid email address.`;
                } else if (rules.number && value && (isNaN(value) || value === '')) {
                    error = `${rules.label || fieldName} must be a valid number.`;
                } else if (rules.integer && value && (!Number.isInteger(parseFloat(value)))) {
                    error = `${rules.label || fieldName} must be a whole number.`;
                } else if (rules.min !== undefined && value && parseFloat(value) < rules.min) {
                    error = `${rules.label || fieldName} must be at least ${rules.min}.`;
                } else if (rules.max !== undefined && value && parseFloat(value) > rules.max) {
                    error = `${rules.label || fieldName} cannot exceed ${rules.max}.`;
                } else if (rules.minLength !== undefined && value && String(value).length < rules.minLength) {
                    error = `${rules.label || fieldName} must be at least ${rules.minLength} characters.`;
                } else if (rules.maxLength !== undefined && value && String(value).length > rules.maxLength) {
                    error = `${rules.label || fieldName} must not exceed ${rules.maxLength} characters.`;
                } else if (rules.pattern && value && !rules.pattern.test(value)) {
                    error = rules.patternMessage || `${rules.label || fieldName} format is invalid.`;
                } else if (rules.url && value) {
                    try {
                        new URL(value);
                    } catch (_) {
                        error = `Please enter a valid URL.`;
                    }
                } else if (rules.custom && typeof rules.custom === 'function') {
                    const customResult = rules.custom(value, $field);
                    if (customResult !== true) {
                        error = customResult || 'Validation failed.';
                    }
                }

                if (error) {
                    $field.addClass('is-invalid custom-is-invalid');
                    $field.after(`<div class="invalid-feedback custom-invalid-feedback d-block">${error}</div>`);
                    return false;
                }

                if (Object.keys(rules).length > 0 && value && String(value).trim() !== '') {
                    $field.addClass('is-valid');
                    if (rules.showValidFeedback !== false) {
                        $field.after('<div class="valid-feedback d-block">✓ Looks good!</div>');
                    }
                }

                return true;
            }

            function validateSummernote($editor, rules) {
                const content = $editor.summernote('code').trim();
                const $container = $editor.next('.note-editor');
                const label = rules.label || 'This field';

                $container.removeClass('is-invalid custom-is-invalid is-valid');
                $container.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback').remove();

                let error = '';

                if (rules.required && (!content || content === '<p><br></p>' || content === '<p></p>')) {
                    error = `${label} is required.`;
                } else if (rules.minLength && content.replace(/<[^>]*>/g, '').length < rules.minLength) {
                    error = `${label} must be at least ${rules.minLength} characters.`;
                } else if (rules.maxLength && content.replace(/<[^>]*>/g, '').length > rules.maxLength) {
                    error = `${label} must not exceed ${rules.maxLength} characters.`;
                }

                if (error) {
                    $container.addClass('is-invalid custom-is-invalid');
                    $container.after(
                        `<div class="invalid-feedback custom-invalid-feedback d-block">${error}</div>`);
                    return false;
                }

                if (content && content !== '<p><br></p>' && content !== '<p></p>') {
                    $container.addClass('is-valid');
                }

                return true;
            }

            // Attach comprehensive real-time validation to fields
            $('#inputTitle').on('blur keyup', function() {
                validateField($(this), {
                    required: true,
                    minLength: 3,
                    maxLength: 255,
                    label: 'Product Title'
                });
            });

            $('#slug').on('blur keyup', function() {
                validateField($(this), {
                    required: true,
                    minLength: 3,
                    maxLength: 255,
                    pattern: /^[a-z0-9-]+$/,
                    patternMessage: 'Slug can only contain lowercase letters, numbers, and hyphens.'
                });
            });

            $('#summary').on('summernote.blur summernote.change', function() {
                validateSummernote($(this), {
                    required: true,
                    minLength: 10,
                    label: 'Summary'
                });
            });

            $('#description').on('summernote.blur summernote.change', function() {
                const content = $(this).summernote('code').trim();
                if (content && content !== '<p><br></p>' && content !== '<p></p>') {
                    validateSummernote($(this), {
                        minLength: 10,
                        label: 'Description'
                    });
                } else {
                    const $container = $(this).next('.note-editor');
                    $container.removeClass('is-invalid custom-is-invalid is-valid');
                    $container.siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback')
                        .remove();
                }
            });

            $('#cat_id').on('change blur', function() {
                validateField($(this), {
                    required: true,
                    label: 'Category'
                });
            });

            $('#child_cat_id').on('change blur', function() {
                if ($(this).children('option').length > 1) {
                    validateField($(this), {
                        label: 'Sub Category'
                    });
                }
            });

            $('#brand_id').on('change blur', function() {
                validateField($(this), {
                    label: 'Brand'
                });
            });

            $('#status').on('change blur', function() {
                validateField($(this), {
                    required: true,
                    label: 'Status'
                });
            });

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
                    $(this).removeClass('is-invalid custom-is-invalid is-valid');
                    $(this).siblings('.invalid-feedback, .custom-invalid-feedback, .valid-feedback')
                        .remove();
                }
            });

            $('#photo').on('change blur', function() {
                if (!$('#has_variants').is(':checked')) {
                    validateField($(this), {
                        required: true,
                        label: 'Product Images',
                        custom: function(value) {
                            if (!value || !value.trim()) {
                                return 'At least one image is required.';
                            }
                            const urls = value.split(',').filter(url => url.trim());
                            if (urls.length === 0) {
                                return 'At least one valid image URL is required.';
                            }
                            return true;
                        }
                    });
                }
            });

            // Enhanced variant field validation
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

            // Debounced real-time validation while typing variant data
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

            $('#product-form').submit(function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    showNotification('Please wait, form is being submitted...', 'info');
                    return false;
                }

                $('.custom-invalid-feedback, .valid-feedback').remove();
                $('.form-control, .note-editor').removeClass('custom-is-invalid is-invalid is-valid');

                let valid = true;
                const errors = [];
                const hasVariants = $('#has_variants').is(':checked');

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

                const descContent = $('#description').summernote('code').trim();
                const descText = descContent.replace(/<[^>]*>/g, '').trim();
                if (descContent && descContent !== '<p><br></p>' && descContent !== '<p></p>' && descText &&
                    descText.length < 10) {
                    valid = false;
                    errors.push('Description must be at least 10 characters if provided');
                    showFieldError('#description', 'Description must be at least 10 characters.');
                    $('#description').next('.note-editor').addClass('custom-is-invalid');
                }

                if (!$('#cat_id').val()) {
                    valid = false;
                    errors.push('Category is required');
                    showFieldError('#cat_id', 'Please select a category.');
                }

                if (!$('#status').val()) {
                    valid = false;
                    errors.push('Status is required');
                    showFieldError('#status', 'Please select a status.');
                }

                if (hasVariants) {
                    const variantRows = $('#variant-preview tbody tr');

                    if (variantRows.length === 0) {
                        valid = false;
                        errors.push('Please generate or keep at least one variant before submitting');
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
                    const basePrice = $('#base_price').val();
                    const baseStock = $('#base_stock').val();
                    const baseSku = $('#base_sku').val().trim();
                    const photo = $('#photo').val().trim();
                    const baseDiscount = $('#base_discount').val();

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

                    const existingImageCount = {{ $product->images->count() }};
                    if (!photo && existingImageCount === 0) {
                        valid = false;
                        errors.push('At least 1 product image is required');
                        showFieldError('#photo', 'At least 1 product image is required.');
                    } else if (photo) {
                        const urls = photo.split(',').filter(url => url.trim());
                        if (existingImageCount === 0 && urls.length < 1) {
                            valid = false;
                            errors.push('Select at least 1 product image.');
                            showFieldError('#photo', 'Select at least 1 image.');
                        }
                    }

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

                    if ($('#enable_alt_text').is(':checked')) {
                        let altTextValid = true;
                        $('textarea[name^="existing_alt_text["], textarea[name^="new_alt_text["], textarea[name^="alt_text["]')
                            .each(function() {
                                const $textarea = $(this);
                                const val = $textarea.val().trim();
                                $textarea.removeClass('custom-is-invalid is-invalid is-valid');
                                $textarea.siblings(
                                        '.custom-invalid-feedback, .invalid-feedback, .valid-feedback')
                                    .remove();

                                if (!val) {
                                    altTextValid = false;
                                    $textarea.addClass('custom-is-invalid');
                                    $textarea.after(
                                        '<span class="custom-invalid-feedback text-danger d-block">Alt text is required.</span>'
                                    );
                                } else if (val.length > 125) {
                                    altTextValid = false;
                                    $textarea.addClass('custom-is-invalid');
                                    $textarea.after(
                                        '<span class="custom-invalid-feedback text-danger d-block">Alt text must not exceed 125 characters.</span>'
                                    );
                                } else {
                                    $textarea.addClass('is-valid');
                                }
                            });
                        if (!altTextValid) {
                            valid = false;
                            errors.push(
                                'Alt text is required for all images (max 125 characters) when Alt Text is enabled'
                            );
                        }
                    }
                }

                if (!valid) {
                    e.preventDefault();
                    isSubmitting = false;

                    const errorCount = errors.length;
                    let errorListHtml =
                        `<p class="mb-2"><strong>Found ${errorCount} error${errorCount > 1 ? 's' : ''} that need${errorCount > 1 ? '' : 's'} to be fixed:</strong></p><ol class="mb-0 pl-3">`;
                    errors.forEach((error) => {
                        errorListHtml += `<li class="mb-1">${error}</li>`;
                    });
                    errorListHtml += '</ol>';

                    $('#validation-errors-list').html(errorListHtml);
                    $('#validation-summary').show().addClass('show');

                    const errorSummary =
                        `<div style="text-align:left;"><strong>⚠️ ${errorCount} Validation Error${errorCount > 1 ? 's' : ''}</strong><br><small>Scroll up to see the full list</small></div>`;
                    showNotification(errorSummary, 'error');

                    $('html, body').animate({
                        scrollTop: $('#validation-summary').offset().top - 20
                    }, 600, 'swing', function() {
                        setTimeout(function() {
                            const $firstError = $('.is-invalid, .custom-is-invalid')
                                .first();
                            if ($firstError.length) {
                                $('html, body').animate({
                                    scrollTop: $firstError.offset().top - 100
                                }, 400, 'swing', function() {
                                    $firstError.fadeOut(100).fadeIn(100).fadeOut(
                                        100).fadeIn(100);
                                });
                            }
                        }, 1000);
                    });

                    return false;
                }

                $('#validation-summary').hide().removeClass('show');

                isSubmitting = true;

                $('#summary').val($('#summary').summernote('code'));
                $('#description').val($('#description').summernote('code'));

                const submitBtn = $(this).find('button[type="submit"]');
                const originalBtnText = submitBtn.html();
                submitBtn.prop('disabled', true).html(
                    '<i class="fa fa-spinner fa-spin"></i> Saving Changes...');

                showNotification('✓ Validation passed! Submitting product update... Please wait.',
                    'success');

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
                $field.siblings('.custom-invalid-feedback').remove();
                $field.after(`<span class="custom-invalid-feedback text-danger d-block mt-1">${message}</span>`);
            }

            function showNotification(message, type = 'info') {
                $('.notification-toast').remove();
                const alertClass = type === 'success' ? 'alert-success' :
                    type === 'error' ? 'alert-danger' : 'alert-info';
                const iconClass = type === 'success' ? 'fa-check-circle' :
                    type === 'error' ? 'fa-exclamation-triangle' : 'fa-info-circle';

                const $notification = $(
                    `<div class="alert ${alertClass} notification-toast alert-dismissible fade show" role="alert" style="max-width: 500px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                            <span>&times;</span>
                                        </button>
                                        <div><i class="fa ${iconClass}" style="margin-right: 8px;"></i>${message}</div>
                                    </div>`
                );

                $('body').append($notification);

                const duration = type === 'error' ? 10000 : (type === 'success' ? 5000 : 4000);
                setTimeout(() => {
                    $notification.fadeOut(400, () => $notification.remove());
                }, duration);
            }

            $(document).on('input change', '.custom-is-invalid, .is-invalid', function() {
                $(this).removeClass('custom-is-invalid is-invalid');
                $(this).siblings('.custom-invalid-feedback, .invalid-feedback').remove();
            });

            $(document).on('input change', '.is-valid', function() {
                $(this).removeClass('is-valid');
                $(this).siblings('.valid-feedback').remove();
            });

            // Toggle variants panel
            $('#has_variants').change(function() {
                const isChecked = this.checked;
                $('#variants-panel').toggle(isChecked);
                $('#non-variant-section, #non-variant-images').toggle(!isChecked);
                if (isChecked) {
                    $('#base_price, #base_discount, #base_stock, #base_sku, #photo').removeAttr('required');
                    if ($('#type-selections').children().length === 0) {
                        $('#load-types').click();
                    }
                } else {
                    $('#base_price, #base_stock, #base_sku, #photo').attr('required', true);
                }
            });

            if ($('#has_variants').is(':checked')) {
                $('#has_variants').trigger('change');
            }

            // Load variant types
            $('#load-types').click(function() {
                const $btn = $(this).prop('disabled', true).html(
                    '<i class="fa fa-spinner fa-spin"></i> Loading...');

                $.get('{{ route('variant-type.api') }}')
                    .done(function(response) {
                        console.log('Variant types API response:', response);

                        // === SAFETY CHECKS ===
                        if (!response || typeof response !== 'object') {
                            showNotification('Invalid response from server.', 'error');
                            return;
                        }

                        let types = [];
                        if (Array.isArray(response)) {
                            types = response;
                        } else if (response.data && Array.isArray(response.data)) {
                            types = response.data;
                        } else if (response.types && Array.isArray(response.types)) {
                            types = response.types;
                        } else {
                            showNotification('No variant types found in response.', 'error');
                            console.warn('Unexpected response structure:', response);
                            return;
                        }

                        if (types.length === 0) {
                            showNotification('No variant types available.', 'info');
                            $('#type-selections').html(
                                '<p class="text-muted">No variant types defined yet.</p>');
                            return;
                        }

                        // === BUILD HTML ===
                        let html = '';
                        types.forEach(type => {
                            if (!type.id || !type.display_name) {
                                console.warn('Skipping invalid type:', type);
                                return;
                            }

                            html += `
                    <div class="variant-type-group">
                        <label class="font-weight-bold">${type.display_name}</label>
                        <select class="form-control type-select" data-type-id="${type.id}" multiple name="variant_options[${type.id}][]">
                            <option value="">Select Options</option>`;

                            (type.options || []).forEach(opt => {
                                if (opt.id && opt.display_value) {
                                    const selected = opt.selected ? 'selected' : '';
                                    html +=
                                        `<option value="${opt.id}" ${selected}>${opt.display_value}</option>`;
                                }
                            });

                            html += `</select>
                    </div>`;
                        });

                        $('#type-selections').html(html);

                        // === INIT SELECT2 ===
                        $('.type-select').each(function() {
                            $(this).select2({
                                placeholder: `Select ${$(this).prev().text()} options`,
                                allowClear: true,
                                width: '100%',
                                dropdownParent: $('#variants-panel')
                            });
                        });

                        showNotification(`Loaded ${types.length} variant type(s).`, 'success');
                    })
                    .fail(function(xhr) {
                        console.error('Failed to load variant types:', xhr.responseText);
                        const msg = xhr.responseJSON?.message || 'Failed to load variant types.';
                        showNotification(msg, 'error');
                    })
                    .always(function() {
                        $btn.prop('disabled', false).html('Load Variant Types');
                    });
            });

            // ============================================================
            // FULLY DYNAMIC VARIANT GENERATION - FIXED
            // Works with ANY variant types from database
            // Generates ALL possible combinations correctly
            // ============================================================
            $('#generate-preview').click(function() {
                console.log('%c🔄 GENERATE PREVIEW CLICKED',
                    'color:#2196F3;font-weight:bold;font-size:14px');

                // 1. Collect ALL variant type data dynamically
                const variantTypes = [];
                const allTypeOptions = new Map(); // typeId → all available options

                $('.type-select').each(function() {
                    const $select = $(this);
                    const typeId = $select.data('type-id');
                    const typeName = $select.prev('label').text().trim();
                    const selectedIds = $select.val() || [];

                    // Skip if nothing selected
                    if (selectedIds.length === 0) {
                        console.log(`%c⊘ Skipping "${typeName}" (no options selected)`,
                            'color:#9E9E9E;font-style:italic');
                        return;
                    }

                    // Collect ALL available options for this type
                    const allOptions = [];
                    $select.find('option').each(function() {
                        const optId = $(this).val();
                        const optText = $(this).text().trim().toLowerCase();

                        if (optId && optId !== 'Select Options' && optText) {
                            allOptions.push({
                                id: optId,
                                text: $(this).text().trim(), // Keep original case
                                textLower: optText,
                                selected: selectedIds.includes(optId)
                            });
                        }
                    });

                    allTypeOptions.set(typeId, allOptions);

                    variantTypes.push({
                        id: typeId,
                        name: typeName,
                        selectedIds: selectedIds,
                        allOptions: allOptions
                    });
                });

                if (variantTypes.length === 0) {
                    showNotification('Please select at least one variant type with options.', 'error');
                    return;
                }

                console.log(`%c📦 Active Variant Types: ${variantTypes.map(t => t.name).join(', ')}`,
                    'color:#9C27B0;font-weight:bold');

                // 2. Build option ID to text mapping
                const optionMap = new Map();
                allTypeOptions.forEach((options, typeId) => {
                    options.forEach(opt => {
                        optionMap.set(opt.id, opt.text);
                    });
                });

                console.log(`%c🔗 Total options collected: ${optionMap.size}`,
                    'color:#2196F3;font-weight:bold');

                // 3. CROSS-VALIDATION: REMOVED - This was incorrectly filtering out valid options
                // Variant options SHOULD be able to have same text in different types
                // e.g., Color:Blue + Size:Medium is a valid combination

                // 4. Use all selected options directly without filtering
                const validatedTypes = variantTypes.map(type => {
                    return {
                        ...type,
                        validIds: type.selectedIds, // Use ALL selected options
                        invalidIds: [] // No filtering needed
                    };
                });

                console.log(`%c✅ Using ALL selected options without cross-filtering`,
                    'color:#4CAF50;font-weight:bold');

                // 5. Build arrays for cartesian product
                const combinationArrays = [];
                const typeSequence = [];

                validatedTypes.forEach(type => {
                    if (type.validIds.length > 0) {
                        combinationArrays.push(type.validIds);
                        typeSequence.push(type.name);
                    }
                });

                if (combinationArrays.length === 0) {
                    showNotification('No valid variant options after filtering conflicts.', 'error');
                    return;
                }

                console.log(`%c🔗 Generation Sequence: ${typeSequence.join(' → ')}`,
                    'color:#2196F3;font-weight:bold;font-size:13px');

                // 6. Generate all combinations
                const allCombinations = cartesianProduct(combinationArrays);
                console.log(`%c🔢 Generated ${allCombinations.length} combinations`,
                    'color:#4CAF50;font-weight:bold');
                console.log(
                    `%c📊 Expected combinations: ${combinationArrays.map(arr => arr.length).join(' × ')} = ${combinationArrays.reduce((a, b) => a * b.length, 1)}`,
                    'color:#FF9800;font-weight:bold');

                // 7. Server-side validation - no client-side duplicate detection needed
                // This ensures accuracy by checking database directly via option combination IDs

                // 8. Don't filter combinations client-side - server will determine what's new
                // Client-side filtering can be inaccurate because it only checks displayed variants
                // Server checks database directly using option combination IDs for accuracy
                const newCombinations = allCombinations.map(combo => {
                    const displayValues = combo.map(optId => optionMap.get(optId));
                    const sortedDisplayValues = [...displayValues].sort();
                    const variantName = sortedDisplayValues.join(' / ');
                    const slugName = sortedDisplayValues
                        .map(v => v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))
                        .join('-');

                    return {
                        combo,
                        name: variantName,
                        slugName,
                        displayValues
                    };
                });

                // 9. (Skipped - no client-side filtering)

                console.log(`%c� Sending ${newCombinations.length} combinations to server`,
                    'color:#2196F3;font-weight:bold');

                // 10. Get product slug
                const productSlug = $('#slug').val().trim() || 'product';

                // 11. Build selections object for server
                const selectionsForServer = {};
                validatedTypes.forEach(type => {
                    if (type.validIds.length > 0) {
                        selectionsForServer[type.id] = type.validIds;
                    }
                });

                // 12. Send to server
                $.post('{{ route('product.preview-variants') }}', {
                        _token: '{{ csrf_token() }}',
                        selections: selectionsForServer,
                        product_id: '{{ $product->id }}'
                    })
                    .done(function(response) {
                        console.log('✅ Variant preview response:', response);

                        if (!response.variants || !Array.isArray(response.variants)) {
                            showNotification('Invalid server response.', 'error');
                            return;
                        }

                        // Get existing variant count to start indexing new variants
                        let newVariantIndex = $('#variant-preview tbody tr').length;

                        // Create table if needed
                        if ($('#variant-preview table').length === 0) {
                            $('#variant-preview').html(`
                        <table class="table table-bordered table-hover">
                            <thead class="thead-light">
                                <tr>
                                    <th>Variant Name</th>
                                    <th>SKU <span class="text-danger">*</span></th>
                                    <th>Price (NRS) <span class="text-danger">*</span></th>
                                    <th>Discount (%)</th>
                                    <th>Stock <span class="text-danger">*</span></th>
                                    <th>Images <span class="text-danger">*</span></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    `);
                        }

                        // Filter to get only new variants (not existing in database)
                        const newVariants = response.variants.filter(v => !v.existing);

                        console.log(`📦 Processing ${newVariants.length} new variants from server`);

                        // Add new rows directly from server response
                        newVariants.forEach((serverData, idx) => {
                            const displayName = serverData.name || 'Unnamed Variant';
                            const displaySku = serverData.sku || ''; // Use server-generated SKU

                            // Small helper to escape values inserted into HTML fragments
                            function escapeHtml(str) {
                                return String(str === undefined || str === null ? '' : str)
                                    .replace(/&/g, '&amp;')
                                    .replace(/</g, '&lt;')
                                    .replace(/>/g, '&gt;')
                                    .replace(/"/g, '&quot;')
                                    .replace(/'/g, '&#39;');
                            }

                            var rowHtml = '';
                            rowHtml += '<tr data-new-variant="' + escapeHtml(displayName) +
                                '">';
                            rowHtml +=
                                '    <td style="max-width: 200px; word-wrap: break-word; white-space: normal;" title="' +
                                escapeHtml(displayName) + '">';
                            rowHtml += '        <strong>' + escapeHtml(displayName) +
                                '</strong>';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml += '        <input type="text" name="new_variants[' +
                                newVariantIndex + '][sku]" value="' + escapeHtml(displaySku) +
                                '" class="form-control" required>';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml += '        <input type="number" name="new_variants[' +
                                newVariantIndex + '][price]" step="0.01" min="0" value="' +
                                escapeHtml(serverData.price || '') +
                                '" class="form-control" placeholder="0.00" required>';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml += '        <input type="number" name="new_variants[' +
                                newVariantIndex + '][discount]" min="0" max="100" value="' +
                                escapeHtml(serverData.discount || '') +
                                '" class="form-control" placeholder="0">';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml += '        <input type="number" name="new_variants[' +
                                newVariantIndex + '][stock]" min="0" value="' + escapeHtml(
                                    serverData.stock || '') +
                                '" class="form-control" placeholder="0" required>';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml += '        <div class="input-group">';
                            rowHtml += '            <input type="text" name="new_variants[' +
                                newVariantIndex + '][images]" id="variant-images-new-' +
                                newVariantIndex + '" class="form-control" readonly required>';
                            rowHtml += '            <div class="input-group-append">';
                            rowHtml +=
                                '                <a class="btn btn-primary lfm-variant" data-input="variant-images-new-' +
                                newVariantIndex + '" data-preview="variant-holder-new-' +
                                newVariantIndex + '">';
                            rowHtml +=
                                '                    <i class="fa fa-picture-o"></i> Choose';
                            rowHtml += '                </a>';
                            rowHtml += '            </div>';
                            rowHtml += '        </div>';
                            rowHtml += '        <div id="variant-holder-new-' +
                                newVariantIndex +
                                '" class="mt-2 d-flex flex-wrap gap-2"></div>';
                            rowHtml += '    </td>';
                            rowHtml += '    <td>';
                            rowHtml +=
                                '        <button type="button" class="btn btn-danger btn-sm remove-new-variant-btn">';
                            rowHtml += '            <i class="fa fa-trash"></i>';
                            rowHtml += '        </button>';
                            rowHtml += '    </td>';
                            rowHtml += '</tr>';

                            // Add hidden inputs for option_ids
                            if (serverData.option_ids && Array.isArray(serverData.option_ids)) {
                                serverData.option_ids.forEach(optionId => {
                                    rowHtml +=
                                        '<input type="hidden" name="new_variants[' +
                                        newVariantIndex + '][option_ids][]" value="' +
                                        optionId + '">';
                                });
                            }

                            $('#variant-preview tbody').append(rowHtml);
                            newVariantIndex++;
                        });

                        // Re-initialize file managers for new variant rows with append behavior
                        initializeFileManagerButton('.lfm-variant', true);

                        console.log(`%c✅ SUCCESS: ${newVariants.length} variants added!`,
                            'color:#4CAF50;font-weight:bold;font-size:14px');

                        const totalVariants = response.variants.length;
                        const existingCount = totalVariants - newVariants.length;

                        const summary = [
                            `${newVariants.length} new variant(s) created`,
                            existingCount > 0 ? `${existingCount} existing variant(s) preserved` :
                            null
                        ].filter(Boolean).join(', ');

                        showNotification(summary, 'success');
                    })
                    .fail(function(xhr) {
                        console.error('❌ AJAX Error:', xhr.responseJSON || xhr);
                        showNotification(
                            xhr.responseJSON?.message || 'Failed to generate variants.',
                            'error'
                        );
                    });
            });

            // Cartesian Product Helper
            function cartesianProduct(arrays) {
                if (arrays.length === 0) return [
                    []
                ];

                return arrays.reduce((acc, curr) => {
                    const result = [];
                    acc.forEach(a => {
                        curr.forEach(c => {
                            result.push([...a, c]);
                        });
                    });
                    return result;
                }, [
                    []
                ]);
            }

            console.log(
                '✅ FIXED: Fully dynamic variant generation loaded - generates ALL possible combinations correctly'
            );

            // Remove new variant
            /* --------------------------------------------------------------
             *  REMOVE NEW VARIANT (preview row) – MODAL
             * -------------------------------------------------------------- */
            $(document).on('click', '.remove-new-variant-btn', function() {
                const $row = $(this).closest('tr');
                const name = $row.find('td:first').text().trim();

                // Direct removal with fade-out animation
                $row.fadeOut(300, function() {
                    $(this).remove();
                    showNotification(`Variant "${name}" removed.`, 'info');
                });
            });

            // ============================================================
            // CARTESIAN PRODUCT HELPER (if not already defined)
            // ============================================================
            if (typeof cartesianProduct === 'undefined') {
                function cartesianProduct(arrays) {
                    if (arrays.length === 0) return [
                        []
                    ];

                    return arrays.reduce((acc, curr) => {
                        const result = [];
                        acc.forEach(a => {
                            curr.forEach(c => {
                                result.push([...a, c]);
                            });
                        });
                        return result;
                    }, [
                        []
                    ]);
                }
                console.log('cartesianProduct function defined');
            }

            // Delete variant
            /* --------------------------------------------------------------
             *  DELETE VARIANT – MODAL
             * -------------------------------------------------------------- */
            $(document).on('click', '.delete-variant-btn', function() {
                const variantId = $(this).data('variant-id');

                $('#genericConfirmModal .modal-title').text('Delete Variant');
                $('#genericConfirmModal .modal-body').html(
                    'Are you sure you want to delete this variant? This action cannot be undone.'
                );
                $('#genericConfirmModal').off('click', '#genericConfirmBtn')
                    .on('click', '#genericConfirmBtn', function() {
                        $.ajax({
                            url: `/admin/product/variant/${variantId}/delete`,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                $('#genericConfirmModal').modal('hide');
                                if (response.success) {
                                    $(`tr[data-variant-id="${variantId}"]`).fadeOut(300,
                                        function() {
                                            $(this).remove();
                                        });
                                    showNotification('Variant deleted successfully!',
                                        'success');
                                } else {
                                    showNotification(response.message ||
                                        'Failed to delete variant.', 'error');
                                }
                            },
                            error: function() {
                                showNotification('Failed to delete variant.', 'error');
                            }
                        });
                    });

                $('#genericConfirmModal').modal('show');
            });

            $(document).on('click', '.delete-variant-image-btn', function() {
                const imageId = $(this).data('image-id');
                const variantId = $(this).data('variant-id');
                const $container = $(this).closest('.image-container');

                // Set the image and variant IDs in the modal
                $('#deleteVariantImageId').val(imageId);
                $('#deleteVariantId').val(variantId);

                // Open the modal
                $('#deleteVariantImageModal').modal('show');
            });

            $('#confirmDeleteVariantImage').click(function() {
                const imageId = $('#deleteVariantImageId').val();
                const variantId = $('#deleteVariantId').val();
                const $container = $(`.image-container[data-image-id="${imageId}"]`);

                if (!imageId || !variantId) return;

                $container.addClass('deleting');
                $.ajax({
                    url: `/admin/product/variant/${variantId}/image/${imageId}/delete`,
                    type: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#deleteVariantImageModal').modal('hide'); // Close modal on success
                        if (response.success) {
                            $container.fadeOut(300, function() {
                                $(this).remove();
                            });
                            showNotification('Variant image deleted successfully!', 'success');
                        } else {
                            $container.removeClass('deleting');
                            showNotification(response.message ||
                                'Failed to delete variant image.', 'error');
                        }
                    },
                    error: function(xhr) {
                        $container.removeClass('deleting');
                        showNotification('Failed to delete variant image. ' + (xhr.responseJSON
                            ?.message || 'Server error.'), 'error');
                    }
                });
            });

            // Close modal and reset values when canceled
            $('#deleteVariantImageModal').on('hidden.bs.modal', function() {
                $('#deleteVariantImageId').val('');
                $('#deleteVariantId').val('');
            });

            // Delete product image
            /* --------------------------------------------------------------
             *  DELETE PRODUCT IMAGE – MODAL
             * -------------------------------------------------------------- */
            $(document).on('click', '.delete-image-btn', function() {
                const imageId = $(this).data('image-id');
                const productId = $(this).data('product-id');
                const $container = $(this).closest('.image-container');

                // Fill hidden fields in a **new** generic modal (re-use for all)
                $('#genericConfirmModal .modal-title').text('Delete Product Image');
                $('#genericConfirmModal .modal-body').html(
                    'Are you sure you want to delete this image? This action cannot be undone.'
                );
                $('#genericConfirmModal').off('click', '#genericConfirmBtn')
                    .on('click', '#genericConfirmBtn', function() {
                        $container.addClass('deleting');
                        $.ajax({
                            url: `/admin/product/${productId}/image/${imageId}/delete`,
                            type: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            success: function(response) {
                                $('#genericConfirmModal').modal('hide');
                                if (response.success) {
                                    // Capture the image URL before removal so we can update hidden input
                                    const deletedUrl = $container.find('img.image-preview')
                                        .attr('src');
                                    $container.fadeOut(300, function() {
                                        $(this).remove();
                                        // Update the hidden photo input to remove the deleted image URL
                                        const currentVal = $('#photo').val().trim();
                                        if (currentVal) {
                                            const updatedList = currentVal.split(
                                                    ',')
                                                .map(s => s.trim())
                                                .filter(s => s && s !== deletedUrl);
                                            $('#photo').val(updatedList.join(','));
                                        }
                                        // Remove matching alt-text block if alt text enabled
                                        $('#current-alt-section img.alt-text-preview')
                                            .each(function() {
                                                if ($(this).attr('src') ===
                                                    deletedUrl) {
                                                    $(this).closest(
                                                            '.alt-text-item')
                                                        .remove();
                                                }
                                            });
                                    });
                                    // Avoid calling updateImagePreview here to prevent duplicate rendering
                                    showNotification('Image deleted successfully!',
                                        'success');
                                } else {
                                    $container.removeClass('deleting');
                                    showNotification(response.message ||
                                        'Failed to delete image.', 'error');
                                }
                            },
                            error: function() {
                                $container.removeClass('deleting');
                                showNotification('Failed to delete image.', 'error');
                            }
                        });
                    });

                $('#genericConfirmModal').modal('show');
            });

            function updateImagePreview() {
                const imageInput = $('#photo').val().trim();
                const $holder = $('#image-preview-area');
                const $altToggle = $('#alt-text-toggle');
                const $altSection = $('#alt-text-section');
                const $altContainer = $('#alt-text-section'); // correct container id

                // Clear previously generated dynamic images & alt text (leave existing ones intact)
                $('#new-images').empty();
                $altContainer.empty();

                if (!imageInput) {
                    $('#new-images').hide();
                    $altToggle.hide();
                    $altSection.hide();
                    return;
                }

                const allPaths = imageInput.split(',').map(u => u.trim()).filter(u => u);

                // Collect paths already rendered (existing DB images) to avoid duplication
                // Use data-path attribute which contains the actual stored path
                const existingPaths = $('#holder .image-container:not([data-dynamic])').map(
                    function() {
                        return $(this).attr('data-path') || $(this).find('img').attr('data-path');
                    }).get().filter(p => p);

                console.log('Existing paths from DB:', existingPaths);
                console.log('All paths in input:', allPaths);

                const newPaths = allPaths.filter(p => existingPaths.indexOf(p) === -1);

                console.log('New paths to display:', newPaths);

                if (newPaths.length === 0) {
                    $('#new-images').hide();
                    return;
                }

                $('#new-images').show();
                $altToggle.show();

                newPaths.forEach((path, index) => {
                    // Convert stored path to display URL
                    const displayUrl = path.includes('://') ? path : '/storage/' + path;

                    const container = $('<div class="image-container" data-dynamic="true"></div>');
                    const img = $('<img />', {
                        src: displayUrl,
                        class: 'image-preview',
                        alt: `Product Image ${existingPaths.length + index + 1}`,
                        'data-index': index,
                        'data-path': path,
                        'data-is-primary': (index === 0 && existingPaths.length === 0),
                        'data-fallback-text': `Product Image ${existingPaths.length + index + 1}`
                    });

                    if (index === 0 && existingPaths.length === 0) {
                        // Only show a Primary badge if no existing images already mark one
                        container.append(
                            '<div class="primary-badge"><span class="badge badge-success">Primary</span></div>'
                        );
                    }

                    img.on('error', function() {
                        $(this).attr('src', '{{ asset('backend/img/avatar.webp') }}');
                        $(this).attr('alt', 'Image Not Found');
                    });

                    // Add delete button for new images
                    const deleteBtn = $(
                            '<button type="button" class="btn btn-danger btn-sm remove-new-image-btn" title="Remove Image"></button>'
                        )
                        .html('<i class="fa fa-trash"></i>')
                        .on('click', function() {
                            // Remove from preview
                            container.remove();
                            // Update input value using the stored path
                            const currentPaths = $('#photo').val().split(',').map(p => p.trim()).filter(
                                p => p);
                            const updatedPaths = currentPaths.filter(p => p !== path);
                            $('#photo').val(updatedPaths.join(',')).trigger('change');
                        });

                    container.append(img);
                    container.append(deleteBtn);
                    $('#new-images').append(container);

                    if ($('#enable_alt_text').is(':checked')) {
                        createAltTextInput(displayUrl, index, $altContainer);
                    }
                });

                // Show/hide alt text sections appropriately
                if ($('#enable_alt_text').is(':checked')) {
                    if ($altContainer.children().length) {
                        $altSection.show();
                    } else {
                        $altSection.hide();
                    }
                    $('#current-alt-section').show();
                } else {
                    $altSection.hide();
                }
            }

            function createAltTextInput(imageUrl, index, container) {
                const productTitle = $('#inputTitle').val() || 'Product';
                const suggestedAlt = `${productTitle} - ${index === 0 ? 'Main Image' : `Image ${index + 1}`}`;
                const isPrimary = index === 0;
                const tabIndex = 31 + index;

                const html = `
                <div class="col-md-6 alt-text-item" data-index="${index}">
                    <div class="d-flex align-items-start">
                        <img src="${imageUrl}" class="alt-text-preview mr-3" alt="Preview" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                        <div class="alt-text-fallback d-none"><i class="fa fa-image"></i><span>Image not available</span></div>
                        <div class="flex-fill">
                            <label class="font-weight-bold">Alt Text for Image ${index + 1} ${isPrimary ? '<span class="badge badge-success badge-sm ml-1">Primary</span>' : ''}</label>
                            <textarea name="new_alt_text[${index}]" class="form-control alt-text-input" placeholder="Describe this image (e.g., ${suggestedAlt})" data-index="${index}" tabindex="${tabIndex}">${suggestedAlt}</textarea>
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
                const $textarea = $(`textarea[data-index="${index}"], textarea[data-index="existing-${index}"]`);
                const $charCount = $textarea.closest('.alt-text-item').find('.char-count');
                const length = $textarea.val().length;
                $charCount.text(`${length}/125 characters`);
                $charCount.toggleClass('text-danger', length > 125).toggleClass('text-muted', length <= 125);
            }

            $('#enable_alt_text').change(function() {
                const isEnabled = this.checked;
                const $altSection = $('#alt-text-section');
                const $currentAltSection = $('#current-alt-section');
                if (isEnabled) {
                    updateImagePreview();
                    $altSection.show().addClass('alt-text-section-show');
                    $currentAltSection.show().addClass('alt-text-section-show');
                } else {
                    $altSection.hide().removeClass('alt-text-section-show');
                    $currentAltSection.hide().removeClass('alt-text-section-show');
                }
                $('.alt-text-item').each(function() {
                    const index = $(this).data('index') || $(this).data('existing-id');
                    updateCharCount(index);
                });
            });

            $('#photo').on('input change', updateImagePreview);

            // Image preview update is now triggered by the change event in the custom SetUrl function above

            $(document).on('click', '.auto-generate-alt', function() {
                const index = $(this).data('index');
                const productTitle = $('#inputTitle').val() || 'Product';
                const category = $('#cat_id option:selected').text();
                const brand = $('#brand_id option:selected').text();
                let autoAlt = productTitle;
                if (brand && brand !== 'Select Brand') autoAlt += ` by ${brand}`;
                if (category && category !== 'Select Category') autoAlt += ` - ${category}`;
                autoAlt += index.includes('existing') ? ` - Image ${index.split('-')[1]}` : (index === 0 ?
                    ' - Main Image' : ` - Image ${parseInt(index) + 1}`);
                const targetField = index.includes('existing') ?
                    `existing_alt_text[${index.split('-')[1]}]` : `new_alt_text[${index}]`;
                $(`textarea[name="${targetField}"]`).val(autoAlt.substring(0, 125));
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
                    $.get('{{ route('category.child', ':id') }}'.replace(':id', catId), function(
                        response) {
                        if (response.status && response.data.length) {
                            let options = '<option value="">Select Sub Category</option>';
                            response.data.forEach(child => {
                                options +=
                                    `<option value="${child.id}" ${child.id == '{{ old('child_cat_id', $product->child_cat_id) }}' ? 'selected' : ''}>${child.title}</option>`;
                            });
                            $('#child_cat_id').html(options);
                            $('#child_cat_div').removeClass('d-none');
                        }
                    }).fail(() => showNotification('Failed to load subcategories.', 'error'));
                }
            });

            if ($('#cat_id').val()) {
                $('#cat_id').trigger('change');
            }

            $('#addBrandBtn').click(function() {
                $('#new_brand_title').val(''); // Clear the input
                $('#addBrandModal').modal('show'); // Open the modal
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
                        $('#brand_id').selectpicker('refresh'); // Refresh Bootstrap Select
                        $('#addBrandModal').modal('hide'); // Close the modal
                        showNotification('Brand added successfully!', 'success');
                    } else {
                        showNotification(res.message || 'Error adding brand.', 'error');
                    }
                }).fail(() => showNotification('Failed to add brand.', 'error'));
            });

            // Initialize character counts for existing alt text
            $('.alt-text-item').each(function() {
                const index = $(this).data('index') || $(this).data('existing-id');
                updateCharCount(index);
            });
        });
    </script>
@endpush
