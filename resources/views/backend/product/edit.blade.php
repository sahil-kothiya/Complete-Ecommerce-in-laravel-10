@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow-sm border-0">
    <h5 class="card-header">Edit Product</h5>
    <div class="card-body p-4">
        <form method="POST" action="{{ route('product.update', $product->id) }}" enctype="multipart/form-data" id="product-form">
            @csrf
            @method('PATCH')
            
            <!-- Core Product Details Section -->
            <section class="mb-5">
                <h6 class="mb-3 text-uppercase font-weight-bold">Core Product Information</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="inputTitle">Title <span class="text-danger">*</span></label>
                            <input type="text" id="inputTitle" name="title" value="{{ old('title', $product->title) }}" class="form-control" placeholder="Enter product title" required tabindex="1">
                            @error('title')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="slug">Slug <span class="text-danger">*</span> <small class="text-muted">(Auto-generated)</small></label>
                            <input type="text" id="slug" name="slug" value="{{ old('slug', $product->slug) }}" class="form-control" placeholder="product-slug" readonly required tabindex="2">
                            @error('slug')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="summary">Summary <span class="text-danger">*</span></label>
                            <textarea id="summary" name="summary" class="form-control summernote" rows="3" tabindex="3">{{ old('summary', $product->summary) }}</textarea>
                            @error('summary')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" class="form-control summernote" rows="5" tabindex="4">{{ old('description', $product->description) }}</textarea>
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
                                <option value="{{ $cat->id }}" {{ old('cat_id', $product->cat_id) == $cat->id ? 'selected' : '' }}>{{ $cat->title }}</option>
                                @endforeach
                            </select>
                            @error('cat_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4 {{ $product->child_cat_id ? '' : 'd-none' }}" id="child_cat_div">
                        <div class="form-group">
                            <label for="child_cat_id">Sub Category</label>
                            <select name="child_cat_id" id="child_cat_id" class="form-control" tabindex="6">
                                <option value="">Select Sub Category</option>
                                @if($product->child_cat_id)
                                @foreach($subcategories as $subcat)
                                <option value="{{ $subcat->id }}" {{ old('child_cat_id', $product->child_cat_id) == $subcat->id ? 'selected' : '' }}>{{ $subcat->title }}</option>
                                @endforeach
                                @endif
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
                                    <option value="{{ $brand->id }}" {{ old('brand_id', $product->brand_id) == $brand->id ? 'selected' : '' }}>{{ $brand->title }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" id="addBrandBtn" class="btn btn-outline-primary" tabindex="8"><i class="fa fa-plus"></i></button>
                                </div>
                            </div>
                            @error('brand_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="status">Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required tabindex="16">
                                <option value="active" {{ old('status', $product->status) == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ old('status', $product->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                            @error('status')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
            </section>

            <!-- Product Attributes (Non-Variant) Section -->
            <section class="mb-5 {{ $product->has_variants ? 'd-none' : '' }}" id="non-variant-section">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Attributes (Non-Variant)</h6>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_price">Price (NRS) <span class="text-danger">*</span></label>
                            <input type="number" id="base_price" name="base_price" step="0.01" min="0" value="{{ old('base_price', $product->base_price) }}" class="form-control" placeholder="Enter price" {{ $product->has_variants ? '' : 'required' }} tabindex="9">
                            @error('base_price')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_discount">Discount (%)</label>
                            <input type="number" id="base_discount" name="base_discount" min="0" max="100" value="{{ old('base_discount', $product->base_discount) }}" class="form-control" placeholder="Enter discount" tabindex="10">
                            @error('base_discount')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_stock">Stock <span class="text-danger">*</span></label>
                            <input type="number" id="base_stock" name="base_stock" min="0" value="{{ old('base_stock', $product->base_stock) }}" class="form-control" placeholder="Enter stock" {{ $product->has_variants ? '' : 'required' }} tabindex="11">
                            @error('base_stock')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="base_sku">SKU <span class="text-danger">*</span></label>
                            <input type="text" id="base_sku" name="base_sku" value="{{ old('base_sku', $product->base_sku) }}" class="form-control" placeholder="Enter unique SKU" {{ $product->has_variants ? '' : 'required' }} tabindex="12">
                            @error('base_sku')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="condition">Condition</label>
                            <select name="condition" id="condition" class="form-control" tabindex="13">
                                <option value="default" {{ old('condition', $product->condition) == 'default' ? 'selected' : '' }}>Default</option>
                                <option value="new" {{ old('condition', $product->condition) == 'new' ? 'selected' : '' }}>New</option>
                                <option value="hot" {{ old('condition', $product->condition) == 'hot' ? 'selected' : '' }}>Hot</option>
                            </select>
                            @error('condition')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="size">Sizes</label>
                            @php $selectedSizes = old('size', explode(',', $product->size ?? 'M')); @endphp
                            <select name="size[]" id="size" class="form-control selectpicker" multiple data-live-search="true" tabindex="14">
                                @foreach(['S' => 'Small', 'M' => 'Medium', 'L' => 'Large', 'XL' => 'Extra Large'] as $key => $label)
                                <option value="{{ $key }}" {{ in_array($key, $selectedSizes) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('size')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group form-check mt-4">
                            <input type="checkbox" name="is_featured" id="is_featured" value="1" class="form-check-input" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }} tabindex="15">
                            <label for="is_featured" class="form-check-label">Featured Product</label>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Images Section (Non-Variant) -->
            <section class="mb-5 {{ $product->has_variants ? 'd-none' : '' }}" id="non-variant-images">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Images</h6>
                <div class="form-group">
                    <label for="photo">Photos <span class="text-danger">*</span> <small class="text-muted">(Select at least one image)</small></label>
                    <div class="input-group">
                        <input type="text" id="photo" name="photo" class="form-control" value="{{ old('photo', $product->images->pluck('image_path')->implode(',')) }}" placeholder="Comma-separated image URLs" readonly {{ $product->has_variants ? '' : 'required' }} tabindex="17">
                        <div class="input-group-append">
                            <a id="lfm" data-input="photo" data-preview="holder" class="btn btn-primary"><i class="fa fa-picture-o"></i> Choose Images</a>
                        </div>
                    </div>
                    @error('photo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    <div id="image-preview-area" class="mt-3 d-flex flex-wrap gap-3">
                        @if($product->images->count())
                        <div id="existing-images">
                            <label class="text-muted small">Current Images:</label>
                            <div id="existing-holder" class="d-flex flex-wrap gap-3">
                                @foreach($product->images as $index => $image)
                                <div class="image-container" data-image-id="{{ $image->id }}">
                                    <img src="{{ Storage::url($image->image_path) }}"
                                        class="image-preview"
                                        alt="{{ $image->alt_text ?? 'Product Image' }}"
                                        data-is-primary="{{ $image->is_primary ? 'true' : 'false' }}"
                                        data-fallback-text="{{ $image->alt_text ?? $product->title . ' - Product Image' }}">
                                    <button type="button" class="btn btn-danger btn-sm delete-image-btn"
                                        data-image-id="{{ $image->id }}"
                                        data-product-id="{{ $product->id }}"
                                        title="Delete Image"
                                        tabindex="{{ 18 + $index }}">
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
                        <div id="new-images" style="display: none;">
                            <label class="text-muted small">New Images Preview:</label>
                            <div id="holder" class="d-flex flex-wrap gap-3"></div>
                        </div>
                    </div>
                </div>
                <div class="form-group form-check" id="alt-text-toggle" {{ $product->images->isEmpty() ? 'style=display:none;' : '' }}>
                    <input type="checkbox" name="enable_alt_text" id="enable_alt_text" value="1" class="form-check-input" {{ old('enable_alt_text', $product->images->whereNotNull('alt_text')->count() > 0) ? 'checked' : '' }} tabindex="19">
                    <label for="enable_alt_text" class="form-check-label">Enable Alt Text for Images <small class="text-muted">(Improves SEO & Accessibility)</small></label>
                </div>
                <div id="current-alt-section" class="row mt-3" style="{{ old('enable_alt_text', $product->images->whereNotNull('alt_text')->count() > 0) ? 'display: block;' : 'display: none;' }}">
                    <div class="col-12">
                        <h6 class="text-primary">Current Image Alt Text</h6>
                        <p class="text-muted small">Update alt text for existing images to improve SEO and accessibility.</p>
                        <div id="current-alt-container" class="row">
                            @foreach($product->images as $index => $image)
                            <div class="col-md-6 alt-text-item" data-existing-id="{{ $image->id }}">
                                <div class="d-flex align-items-start">
                                    <img src="{{ Storage::url($image->image_path) }}"
                                        class="alt-text-preview mr-3"
                                        alt="{{ $image->alt_text ?? 'Product Image Preview' }}"
                                        >
                                    <div class="alt-text-fallback d-none"><i class="fa fa-image"></i><span>Image not available</span></div>
                                    <div class="flex-fill">
                                        <label class="font-weight-bold">Alt Text for Image {{ $index + 1 }} {{ $image->is_primary ? '<span class="badge badge-success badge-sm ml-1">Primary</span>' : '' }}</label>
                                        <textarea name="existing_alt_text[{{ $image->id }}]" class="form-control alt-text-input" placeholder="Describe this image" data-index="existing-{{ $image->id }}" tabindex="{{ 20 + $index }}">{{ old('existing_alt_text.' . $image->id, $image->alt_text) }}</textarea>
                                        <small class="form-text text-muted">Max 125 characters. Include product name for SEO.</small>
                                        <div class="mt-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary auto-generate-alt" data-index="existing-{{ $image->id }}" data-type="existing" tabindex="{{ 20 + $product->images->count() + $index }}">Auto-generate</button>
                                            <span class="ml-2 char-count">0/125 characters</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div id="alt-text-section" class="row mt-3" style="display: none;">
                    <div class="col-12">
                        <h6 class="text-primary">New Image Alt Text Configuration</h6>
                        <p class="text-muted small">Add descriptive alt text for new images to improve SEO and accessibility.</p>
                        <div id="alt-text-container" class="row"></div>
                    </div>
                </div>
            </section>

            <!-- Variants Section -->
            <section class="mb-5">
                <h6 class="mb-3 text-uppercase font-weight-bold">Product Variants</h6>
                <div class="form-group form-check">
                    <input type="checkbox" name="has_variants" id="has_variants" value="1" class="form-check-input" {{ old('has_variants', $product->has_variants) ? 'checked' : '' }} tabindex="21">
                    <label for="has_variants" class="form-check-label">Enable Variants <small class="text-muted">(e.g., Colors, Sizes)</small></label>
                </div>
                <div id="variants-panel" class="p-4 bg-light rounded" style="{{ $product->has_variants ? 'display: block;' : 'display: none;' }}">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="font-weight-bold">Select Variant Types & Options</label>
                            <button type="button" id="load-types" class="btn btn-secondary btn-sm">Load Variant Types</button>
                        </div>
                        <div id="type-selections" class="d-flex flex-wrap gap-3">
                            @if($product->has_variants)
                                @foreach($product->variantTypes as $type)
                                    <div class="variant-type-group">
                                        <label class="font-weight-bold">{{ $type->display_name }}</label>

                                        <select class="form-control type-select"
                                                data-type-id="{{ $type->id }}"
                                                multiple
                                                name="variant_options[{{ $type->id }}][]">

                                            @foreach($type->options as $option)
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
                            <button type="button" id="generate-preview" class="btn btn-info btn-sm">Generate New Variants</button>
                        </div>
                        <div id="variant-preview" class="table-responsive">
                            @if($product->has_variants)
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
                                    @foreach($product->variants as $index => $variant)
                                    <tr data-variant-id="{{ $variant->id }}">
                                        <td>{{ $variant->display_name }}</td>
                                        <td>
                                            <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $variant->id }}">
                                            <input type="text" name="variants[{{ $index }}][sku]" value="{{ old('variants.' . $index . '.sku', $variant->sku) }}" class="form-control" required>
                                        </td>
                                        <td><input type="number" name="variants[{{ $index }}][price]" step="0.01" min="0" value="{{ old('variants.' . $index . '.price', $variant->price) }}" class="form-control" required></td>
                                        <td><input type="number" name="variants[{{ $index }}][discount]" min="0" max="100" value="{{ old('variants.' . $index . '.discount', $variant->discount) }}" class="form-control"></td>
                                        <td><input type="number" name="variants[{{ $index }}][stock]" min="0" value="{{ old('variants.' . $index . '.stock', $variant->stock) }}" class="form-control" required></td>
                                        <td>
                                            <div class="input-group">
                                                <input type="text" name="variants[{{ $index }}][images]" id="variant-images-{{ $index }}" class="form-control" value="{{ old('variants.' . $index . '.images', $variant->images->pluck('image_path')->implode(',')) }}" readonly required>
                                                <div class="input-group-append">
                                                    <a class="btn btn-primary lfm-variant" data-input="variant-images-{{ $index }}" data-preview="variant-holder-{{ $index }}"><i class="fa fa-picture-o"></i> Choose</a>
                                                </div>
                                            </div>
                                            <div id="variant-holder-{{ $index }}" class="mt-2 d-flex flex-wrap gap-2">
                                                @foreach($variant->images as $imgIndex => $image)
                                                <div class="image-container" data-image-id="{{ $image->id }}">
                                                    <img src="{{ Storage::url($image->image_path) }}"
                                                        class="image-preview"
                                                        alt="{{ $image->alt_text ?? 'Variant Image' }}"
                                                        data-is-primary="{{ $image->is_primary ? 'true' : 'false' }}"
                                                        data-fallback-text="{{ $image->alt_text ?? $variant->display_name . ' - Variant Image' }}">
                                                    <button type="button" class="btn btn-danger btn-sm delete-variant-image-btn"
                                                        data-image-id="{{ $image->id }}"
                                                        data-variant-id="{{ $variant->id }}"
                                                        title="Delete Image"
                                                        tabindex="{{ 22 + $index * 10 + $imgIndex }}">
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
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm delete-variant-btn" data-variant-id="{{ $variant->id }}" tabindex="{{ 22 + $index * 10 + $variant->images->count() }}">Delete</button>
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

<!-- Delete Variant Image Modal -->
<div class="modal fade" id="deleteVariantImageModal" tabindex="-1" aria-labelledby="deleteVariantImageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVariantImageModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
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
    .card { border-radius: 0.5rem; overflow: hidden; }
    .form-control, .btn { border-radius: 0.25rem; }
    .form-control.is-invalid { border-color: #dc3545; }
    .invalid-feedback { font-size: 0.875rem; color: #dc3545; }
    #image-preview-area { display: flex; flex-wrap: wrap; gap: 1rem; }
    .image-container { position: relative; border: 1px solid #dee2e6; border-radius: 0.25rem; padding: 0.5rem; background: #fff; }
    .image-preview { width: 120px; height: 120px; object-fit: cover; border-radius: 0.25rem; }
    .image-not-found { width: 120px; height: 120px; display: flex; align-items: center; justify-content: center; border: 2px dashed #dee2e6; border-radius: 0.25rem; background: #f8f9fa; color: #6c757d; font-size: 0.875rem; }
    .primary-badge { position: absolute; top: 0.25rem; left: 0.25rem; }
    .alt-text-item { background: #f8f9fa; padding: 1rem; border: 1px solid #dee2e6; border-radius: 0.25rem; margin-bottom: 1rem; }
    .alt-text-preview { width: 80px; height: 80px; object-fit: cover; border-radius: 0.25rem; margin-right: 1rem; }
    .alt-text-fallback { width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border: 2px dashed #dee2e6; border-radius: 0.25rem; background: #f8f9fa; color: #6c757d; font-size: 0.75rem; text-align: center; }
    .char-count { font-size: 0.875rem; }
    .variant-type-group { flex: 1 1 200px; padding: 1rem; border: 1px solid #dee2e6; border-radius: 0.25rem; background: #fff; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); transition: all 0.2s; }
    .variant-type-group:hover { box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
    .table th, .table td { vertical-align: middle; }
    .btn-success, .btn-primary, .btn-info, .btn-secondary { transition: all 0.2s; }
    .btn-success:hover, .btn-primary:hover, .btn-info:hover, .btn-secondary:hover { transform: translateY(-2px); }
    .notification-toast { position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); }
    .select2-container .select2-selection--multiple { min-height: 38px; border: 1px solid #ced4da; border-radius: 0.25rem; }
    .select2-container--default .select2-selection--multiple .select2-selection__choice { background-color: #007bff; border-color: #007bff; color: #fff; }
    .image-container.deleting { opacity: 0.6; pointer-events: none; position: relative; }
    .image-container.deleting::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(255, 255, 255, 0.9); border-radius: 8px; z-index: 15; display: flex; align-items: center; justify-content: center; }
    .image-container.deleting::after { content: '🗑️ Deleting...'; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 8px 12px; border-radius: 6px; font-size: 12px; color: #dc3545; font-weight: bold; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3); z-index: 16; white-space: nowrap; border: 1px solid #dee2e6; }
    @media (max-width: 768px) {
        #type-selections { flex-direction: column; }
        .variant-type-group { width: 100%; }
        .image-preview, .image-not-found { width: 100px; height: 100px; }
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

        // Initialize File Manager
        $('#lfm').filemanager('image');
        $('.lfm-variant').each(function() { $(this).filemanager('image'); });

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

        // Load variant types
$('#load-types').click(function() {
    const $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Loading...');

    $.get('{{ route("variant-type.api") }}')
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
                $('#type-selections').html('<p class="text-muted">No variant types defined yet.</p>');
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
                        html += `<option value="${opt.id}" ${selected}>${opt.display_value}</option>`;
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
        // FULLY DYNAMIC VARIANT GENERATION
        // Works with ANY variant types from database
        // Validates cross-contamination automatically
        // ============================================================
        $('#generate-preview').click(function () {
            console.log('%c🔄 GENERATE PREVIEW CLICKED', 'color:#2196F3;font-weight:bold;font-size:14px');

            // 1. Collect ALL variant type data dynamically
            const variantTypes = [];
            const allTypeOptions = new Map(); // typeId → all available options
            const optionToTypes = new Map(); // optionText → [typeIds that have this option]
            
            $('.type-select').each(function () {
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
                        
                        // Track which types have this option (for cross-validation)
                        if (!optionToTypes.has(optText)) {
                            optionToTypes.set(optText, []);
                        }
                        optionToTypes.get(optText).push({
                            typeId: typeId,
                            typeName: typeName
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

            // 3. CROSS-VALIDATION: Remove options that appear in multiple variant types
            console.groupCollapsed('%c🔍 Cross-Validation Check', 'color:#FF9800;font-weight:bold');
            
            const validatedTypes = variantTypes.map(type => {
                const validIds = [];
                const invalidIds = [];
                
                type.selectedIds.forEach(optId => {
                    const optText = optionMap.get(optId).toLowerCase();
                    const appearingInTypes = optionToTypes.get(optText) || [];
                    
                    // Check if this option appears in OTHER variant types
                    const otherTypes = appearingInTypes.filter(t => t.typeId !== type.id);
                    
                    if (otherTypes.length > 0) {
                        invalidIds.push({
                            id: optId,
                            text: optionMap.get(optId),
                            conflictsWith: otherTypes.map(t => t.typeName).join(', ')
                        });
                        
                        console.warn(
                            `⚠️ "${type.name}" option "${optionMap.get(optId)}" ` +
                            `also exists in: ${otherTypes.map(t => t.typeName).join(', ')}`
                        );
                    } else {
                        validIds.push(optId);
                    }
                });
                
                return {
                    ...type,
                    validIds: validIds,
                    invalidIds: invalidIds
                };
            });
            
            console.groupEnd();

            // 4. Show validation summary
            const totalInvalid = validatedTypes.reduce((sum, t) => sum + t.invalidIds.length, 0);
            if (totalInvalid > 0) {
                console.log(`%c🚫 Filtered ${totalInvalid} conflicting option(s)`, 
                        'color:#F44336;font-weight:bold');
                validatedTypes.forEach(type => {
                    if (type.invalidIds.length > 0) {
                        console.log(
                            `   ${type.name}: ${type.invalidIds.map(i => i.text).join(', ')} ` +
                            `(conflicts with other types)`
                        );
                    }
                });
            }

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

            // 7. Get existing variant names (saved + new)
            const existingVariantNames = new Set();
            
            $('#variant-preview tbody tr[data-variant-id]').each(function () {
                const name = $(this).find('td:first').text().trim();
                if (name) existingVariantNames.add(name);
            });
            
            $('#variant-preview tbody tr[data-new-variant]').each(function () {
                const name = $(this).attr('data-new-variant');
                if (name) existingVariantNames.add(name);
            });

            console.log(`%c📋 Existing variants: ${existingVariantNames.size}`, 
                        'color:#FF9800;font-weight:bold');

            // 8. Process combinations
            const newCombinations = [];
            const ignoredCombinations = [];

            allCombinations.forEach(combo => {
                // Map option IDs to display text in EXACT selection order
                const displayValues = combo.map(optId => optionMap.get(optId));
                
                const variantName = displayValues.join(', ');
                const slugName = displayValues
                    .map(v => v.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''))
                    .join('-');

                if (existingVariantNames.has(variantName)) {
                    ignoredCombinations.push({
                        name: variantName,
                        reason: 'Already exists',
                        combo: displayValues
                    });
                } else {
                    newCombinations.push({
                        combo,
                        name: variantName,
                        slugName,
                        displayValues
                    });
                    existingVariantNames.add(variantName);
                }
            });

            // 9. Log ignored variants
            if (ignoredCombinations.length > 0) {
                console.groupCollapsed(`%c🚫 IGNORED (${ignoredCombinations.length})`, 
                                    'color:#F44336;font-weight:bold');
                ignoredCombinations.forEach((item, i) => {
                    console.log(`${i + 1}. "${item.name}" → ${item.reason}`);
                });
                console.groupEnd();
            }

            if (newCombinations.length === 0) {
                showNotification('All combinations already exist or were filtered.', 'info');
                return;
            }

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
            $.post('{{ route("product.preview-variants") }}', {
                _token: '{{ csrf_token() }}',
                selections: selectionsForServer,
                product_id: '{{ $product->id }}'
            })
            .done(function (response) {
                if (!response.variants || !Array.isArray(response.variants)) {
                    showNotification('Invalid server response.', 'error');
                    return;
                }

                const serverVariantMap = {};
                response.variants.forEach(v => { 
                    if (v.name) serverVariantMap[v.name] = v; 
                });

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

                // Add new rows
                newCombinations.forEach(item => {
                    const serverData = serverVariantMap[item.name] || {};
                    const autoSku = serverData.sku || `${productSlug}-${item.slugName}`.toUpperCase();

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
                    rowHtml += '<tr data-new-variant="' + escapeHtml(item.name) + '">';
                    rowHtml += '    <td><strong>' + escapeHtml(item.name) + '</strong></td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <input type="text" name="new_variants[' + newVariantIndex + '][sku]" value="' + escapeHtml(autoSku) + '" class="form-control" required>';
                    rowHtml += '    </td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <input type="number" name="new_variants[' + newVariantIndex + '][price]" step="0.01" min="0" value="' + escapeHtml(serverData.price || '') + '" class="form-control" placeholder="0.00" required>';
                    rowHtml += '    </td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <input type="number" name="new_variants[' + newVariantIndex + '][discount]" min="0" max="100" value="' + escapeHtml(serverData.discount || '') + '" class="form-control" placeholder="0">';
                    rowHtml += '    </td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <input type="number" name="new_variants[' + newVariantIndex + '][stock]" min="0" value="' + escapeHtml(serverData.stock || '') + '" class="form-control" placeholder="0" required>';
                    rowHtml += '    </td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <div class="input-group">';
                    rowHtml += '            <input type="text" name="new_variants[' + newVariantIndex + '][images]" id="variant-images-new-' + newVariantIndex + '" class="form-control" readonly required>';
                    rowHtml += '            <div class="input-group-append">';
                    rowHtml += '                <a class="btn btn-primary lfm-variant" data-input="variant-images-new-' + newVariantIndex + '" data-preview="variant-holder-new-' + newVariantIndex + '">';
                    rowHtml += '                    <i class="fa fa-picture-o"></i> Choose';
                    rowHtml += '                </a>';
                    rowHtml += '            </div>';
                    rowHtml += '        </div>';
                    rowHtml += '        <div id="variant-holder-new-' + newVariantIndex + '" class="mt-2 d-flex flex-wrap gap-2"></div>';
                    rowHtml += '    </td>';
                    rowHtml += '    <td>';
                    rowHtml += '        <button type="button" class="btn btn-danger btn-sm remove-new-variant-btn">';
                    rowHtml += '            <i class="fa fa-trash"></i>';
                    rowHtml += '        </button>';
                    rowHtml += '    </td>';
                    rowHtml += '</tr>';
                    
                    $('#variant-preview tbody').append(rowHtml);
                    newVariantIndex++;
                });

                $('.lfm-variant').filemanager('image');

                console.log(`%c✅ SUCCESS: ${newCombinations.length} variants added!`, 
                        'color:#4CAF50;font-weight:bold;font-size:14px');
                
                const summary = [
                    `${newCombinations.length} variant(s) created`,
                    totalInvalid > 0 ? `${totalInvalid} conflict(s) filtered` : null
                ].filter(Boolean).join(', ');
                
                showNotification(summary, 'success');
            })
            .fail(function (xhr) {
                console.error('❌ AJAX Error:', xhr.responseJSON || xhr);
                showNotification(
                    xhr.responseJSON?.message || 'Failed to generate variants.', 
                    'error'
                );
            });
        });

        // Cartesian Product Helper
        function cartesianProduct(arrays) {
            if (arrays.length === 0) return [[]];
            
            return arrays.reduce((acc, curr) => {
                const result = [];
                acc.forEach(a => {
                    curr.forEach(c => {
                        result.push([...a, c]);
                    });
                });
                return result;
            }, [[]]);
        }

        console.log('✅ Fully dynamic variant generation loaded (works with ANY variant types)');

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
                if (arrays.length === 0) return [[]];
                
                return arrays.reduce((acc, curr) => {
                    const result = [];
                    acc.forEach(a => {
                        curr.forEach(c => {
                            result.push([...a, c]);
                        });
                    });
                    return result;
                }, [[]]);
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
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        $('#genericConfirmModal').modal('hide');
                        if (response.success) {
                            $(`tr[data-variant-id="${variantId}"]`).fadeOut(300, function() { $(this).remove(); });
                            showNotification('Variant deleted successfully!', 'success');
                        } else {
                            showNotification(response.message || 'Failed to delete variant.', 'error');
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
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    $('#deleteVariantImageModal').modal('hide'); // Close modal on success
                    if (response.success) {
                        $container.fadeOut(300, function() { $(this).remove(); });
                        showNotification('Variant image deleted successfully!', 'success');
                    } else {
                        $container.removeClass('deleting');
                        showNotification(response.message || 'Failed to delete variant image.', 'error');
                    }
                },
                error: function(xhr) {
                    $container.removeClass('deleting');
                    showNotification('Failed to delete variant image. ' + (xhr.responseJSON?.message || 'Server error.'), 'error');
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
            const imageId   = $(this).data('image-id');
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
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        $('#genericConfirmModal').modal('hide');
                        if (response.success) {
                            $container.fadeOut(300, function() { $(this).remove(); });
                            updateImagePreview();
                            showNotification('Image deleted successfully!', 'success');
                        } else {
                            $container.removeClass('deleting');
                            showNotification(response.message || 'Failed to delete image.', 'error');
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

        // Form validation
        $('#product-form').submit(function(e) {
            $('.custom-invalid-feedback').remove();
            $('.form-control').removeClass('custom-is-invalid');

            let valid = true;
            const hasVariants = $('#has_variants').is(':checked');

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

            if (hasVariants) {
                const variantRows = $('#variant-preview tbody tr');
                if (variantRows.length === 0) {
                    valid = false;
                    showNotification('Please generate or keep at least one variant before submitting.', 'error');
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

                if ($('#enable_alt_text').is(':checked')) {
                    let altTextValid = true;
                    $('textarea[name^="existing_alt_text["], textarea[name="new_alt_text[]"]').each(function() {
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
                    if (!altTextValid) valid = false;
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

            $('#summary').val($('#summary').summernote('code'));
            $('#description').val($('#description').summernote('code'));

            const submitBtn = $(this).find('button[type="submit"]');
            submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        });

        function showFieldError(selector, message) {
            const $field = $(selector);
            $field.addClass('custom-is-invalid');
            $field.siblings('.custom-invalid-feedback').remove();
            $field.after(`<span class="custom-invalid-feedback text-danger d-block mt-1">${message}</span>`);
        }

        function showNotification(message, type = 'info') {
            $('.notification-toast').remove();
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
            const $notification = $(`
                <div class="alert ${alertClass} notification-toast alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span>&times;</span>
                    </button>
                    <strong>${type.charAt(0).toUpperCase() + type.slice(1)}!</strong> ${message}
                </div>
            `);
            $('body').append($notification);
            setTimeout(() => $notification.fadeOut(400, () => $notification.remove()), 5000);
        }

        $(document).on('input change', '.custom-is-invalid', function() {
            $(this).removeClass('custom-is-invalid');
            $(this).siblings('.custom-invalid-feedback').remove();
        });

        $(document).on('blur', '#variant-preview input[required]', function() {
            const $input = $(this);
            const val = $input.val().trim();
            const name = $input.attr('name');
            $input.removeClass('custom-is-invalid');
            if (!val) {
                $input.addClass('custom-is-invalid');
            } else if (name.includes('discount')) {
                const discount = parseFloat(val);
                if (discount < 0 || discount > 100) $input.addClass('custom-is-invalid');
            } else if (name.includes('price') || name.includes('stock')) {
                const num = parseFloat(val);
                if (num < 0) $input.addClass('custom-is-invalid');
            }
        });

        function updateImagePreview() {
            const imageInput = $('#photo').val().trim();
            const $holder = $('#image-preview-area');
            const $altToggle = $('#alt-text-toggle');
            const $altSection = $('#alt-text-section');
            const $altContainer = $('#alt-text-container');

            $holder.find('#new-images').empty();
            $altContainer.empty();

            if (!imageInput) {
                $holder.find('#new-images').hide();
                $altToggle.hide();
                $altSection.hide();
                return;
            }

            const images = imageInput.split(',').map(url => url.trim()).filter(url => url);
            $holder.find('#new-images').show();
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
                $holder.find('#holder').append(container);

                if ($('#enable_alt_text').is(':checked')) {
                    createAltTextInput(url, index, $altContainer);
                }
            });

            if ($('#enable_alt_text').is(':checked')) {
                $altSection.show();
                $('#current-alt-section').show();
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
            autoAlt += index.includes('existing') ? ` - Image ${index.split('-')[1]}` : (index === 0 ? ' - Main Image' : ` - Image ${parseInt(index) + 1}`);
            const targetField = index.includes('existing') ? `existing_alt_text[${index.split('-')[1]}]` : `new_alt_text[${index}]`;
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
                $.get('{{ route("category.child", ":id") }}'.replace(':id', catId), function(response) {
                    if (response.status && response.data.length) {
                        let options = '<option value="">Select Sub Category</option>';
                        response.data.forEach(child => {
                            options += `<option value="${child.id}" ${child.id == '{{ old('child_cat_id', $product->child_cat_id) }}' ? 'selected' : ''}>${child.title}</option>`;
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
            $.post('{{ route("brand.store.ajax") }}', {
                _token: '{{ csrf_token() }}',
                title: brandName
            }, function(res) {
                if (res.status === 'success') {
                    $('#brand_id').append(`<option value="${res.data.id}" selected>${res.data.title}</option>`);
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