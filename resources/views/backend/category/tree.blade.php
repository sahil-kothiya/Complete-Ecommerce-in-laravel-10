@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Category Tree Manager</h6>
        <a href="{{ route('category.index') }}" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Back to Category List"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
    </div>
    <div class="card-body">
        <div class="controls-panel mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1"><i class="fas fa-sitemap me-2"></i>Category Hierarchy</h2>
                    <p class="text-muted mb-0">Drag and drop to reorganize categories or use actions to edit/delete.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="stats-card">
                        <div class="stats-number" id="totalCategories">0</div>
                        <div>Total Categories</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tree-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fas fa-tree me-2"></i>Category Structure</h4>
                <div>
                    <button class="btn btn-success btn-sm me-2" id="saveChangesBtn" data-bs-toggle="tooltip" data-bs-placement="top" title="Save category hierarchy changes">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                    <button class="btn btn-secondary btn-sm me-2" id="expandAllBtn" data-bs-toggle="tooltip" data-bs-placement="top" title="Expand all categories">
                        <i class="fas fa-expand-arrows-alt me-1"></i> Expand All
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="collapseAllBtn" data-bs-toggle="tooltip" data-bs-placement="top" title="Collapse all categories">
                        <i class="fas fa-compress-arrows-alt me-1"></i> Collapse All
                    </button>
                </div>
            </div>
            <div id="loadingSpinner" class="text-center d-none">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <ul class="category-tree sortable" id="categoryTree">
                <!-- Categories will be loaded here dynamically -->
            </ul>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<style>
    .tree-container {
        background: #ffffff;
        border-radius: 12px;
        padding: 25px;
        margin: 20px 0;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .category-tree {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .category-item {
        background: #f8f9fa;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        margin: 10px 0;
        padding: 15px;
        position: relative;
        cursor: move;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }

    .category-item:hover {
        border-color: #3b82f6;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.12);
        transform: translateY(-3px);
    }

    .category-item.dragging {
        opacity: 0.7;
        transform: scale(1.02);
        background: #e6f3ff;
    }

    .category-item.drag-over {
        border-color: #22c55e;
        background: #f0fdf4;
    }

    .category-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
    }

    .category-info {
        display: flex;
        align-items: center;
        flex: 1;
        gap: 15px;
    }

    .category-icon {
        width: 44px;
        height: 44px;
        background: linear-gradient(135deg, #3b82f6, #7c3aed);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 20px;
        flex-shrink: 0;
    }

    .category-details h6 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
    }

    .category-details small {
        color: #64748b;
        font-size: 0.85rem;
    }

    .category-level {
        font-size: 0.75rem;
        background: #e2e8f0;
        padding: 4px 10px;
        border-radius: 12px;
        margin-left: 10px;
        color: #475569;
    }

    .category-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-action:hover {
        transform: scale(1.15);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .children-container {
        margin-left: 50px;
        margin-top: 15px;
        border-left: 3px solid #e2e8f0;
        padding-left: 25px;
        position: relative;
    }

    .children-container::before {
        content: '';
        position: absolute;
        left: -3px;
        top: 0;
        bottom: 0;
        width: 3px;
        background: linear-gradient(180deg, #3b82f6, #7c3aed);
        border-radius: 3px;
    }

    .level-0 .category-icon {
        background: linear-gradient(135deg, #ef4444, #f97316);
    }

    .level-1 .category-icon {
        background: linear-gradient(135deg, #3b82f6, #7c3aed);
    }

    .level-2 .category-icon {
        background: linear-gradient(135deg, #22c55e, #10b981);
    }

    .level-3 .category-icon {
        background: linear-gradient(135deg, #f59e0b, #f97316);
    }

    .drop-zone {
        min-height: 60px;
        border: 2px dashed #d1d5db;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        margin: 12px 0;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    .drop-zone.drag-over {
        border-color: #22c55e;
        background: #f0fdf4;
        color: #15803d;
    }

    .status-badge {
        font-size: 0.8rem;
        padding: 5px 10px;
        border-radius: 14px;
        font-weight: 500;
    }

    .badge-success {
        background: #22c55e;
        color: #ffffff;
    }

    .badge-warning {
        background: #f59e0b;
        color: #ffffff;
    }

    .controls-panel {
        background: #ffffff;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .stats-card {
        background: linear-gradient(135deg, #6366f1, #a855f7);
        color: #ffffff;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        transition: transform 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-3px);
    }

    .stats-number {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 8px;
    }

    @media (max-width: 768px) {
        .children-container {
            margin-left: 25px;
            padding-left: 15px;
        }

        .category-content {
            flex-direction: column;
            align-items: flex-start;
            gap: 12px;
        }

        .category-actions {
            justify-content: flex-end;
            width: 100%;
        }

        .stats-card {
            margin-top: 15px;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js" integrity="sha512-X/YkDZyjTf4wyc2Vy16YvO3Wv4xWV7YdYzkf1rJ8r/4B5BlH2ZTsW35A4r3Z86V+OxWvG3qU3g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js" integrity="sha512-EPPsgA/B5LEj66AdVvhFS4h1w1D2k5j+EP3AJv9y2dtaUquN+2tF3K5q3nMgd5/f3cCnfZxD92BbCmkfZ1QRo3A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js" integrity="sha512-f5f1JhV7j9p1ZQ6jQdLMWnWOJ2tQkV+U1gFrqTab9TXCaLkqhyP2v6A7Q2jGrO3vJGTCPj5Ntwz2Mr0O4M5s3Og==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script>
    let categoryData = [];
    let categoryDataOriginal = [];
    let hasChanges = false;
    const csrfToken = '{{ csrf_token() }}';

    function initializePage() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });
        fetchCategoryData();
    }

    function fetchCategoryData() {
        console.log('Fetching category data...');
        $('#loadingSpinner').removeClass('d-none');
        $.ajax({
            url: '{{ route("category.get-tree-data") }}',
            method: 'GET',
            success: function(response) {
                console.log('Category data received:', response);
                $('#loadingSpinner').addClass('d-none');
                if (response.success) {
                    categoryData = response.data;
                    categoryDataOriginal = JSON.parse(JSON.stringify(response.data));
                    renderCategoryTree();
                    updateStats();
                    initializeSortable();
                    initializeTooltips();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to load category data',
                        confirmButtonColor: '#3085d6'
                    });
                }
            },
            error: function(xhr) {
                console.error('Error fetching category data:', xhr);
                $('#loadingSpinner').addClass('d-none');
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to fetch category data',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
    }

    function buildCategoryHtml(category) {
        const hasChildren = category.children && category.children.length > 0;
        const statusClass = category.status === 'active' ? 'badge-success' : 'badge-warning';
        const featuredIcon = category.is_featured ? '<i class="fas fa-star text-warning ms-2"></i>' : '';

        let html = `
            <li class="category-item level-${category.level}" data-id="${category.id}">
                <div class="category-content">
                    <div class="category-info">
                        <div class="category-icon"><i class="fas fa-folder"></i></div>
                        <div class="category-details">
                            <h6>${category.title}${featuredIcon}</h6>
                            <small>Slug: ${category.slug} | Children: ${category.children_count}</small>
                            <span class="category-level">Level ${category.level}</span>
                        </div>
                    </div>
                    <div class="category-actions">
                        <span class="status-badge badge ${statusClass}">${category.status}</span>
                        ${hasChildren ? `<button class="btn-action btn-primary toggle-children" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Toggle children"><i class="fas fa-minus"></i></button>` : ''}
                        <button class="btn-action btn-info" onclick="editCategory(${category.id})" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit category"><i class="fas fa-edit"></i></button>
                        <button class="btn-action btn-danger" onclick="deleteCategory(${category.id})" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete category"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="children-container ${hasChildren ? '' : 'd-none'}" data-parent="${category.id}">
                    <ul class="children-tree sortable">
        `;

        if (hasChildren) {
            category.children.forEach(child => {
                html += buildCategoryHtml(child);
            });
        }

        html += `
                    </ul>
                </div>
            </li>
        `;

        return html;
    }

    function renderCategoryTree() {
        console.log('Rendering category tree:', categoryData);
        let treeHtml = '';
        categoryData.forEach(category => {
            treeHtml += buildCategoryHtml(category);
        });
        $('#categoryTree').html(treeHtml);
        $('.toggle-children').off('click').on('click', toggleChildren);
    }

    function initializeSortable() {
        console.log('Initializing Sortable.js');
        const sortables = document.querySelectorAll('.sortable');
        sortables.forEach(el => {
            new Sortable(el, {
                group: 'nested',
                animation: 200,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                handle: '.category-item',
                onStart: function(evt) {
                    evt.item.classList.add('dragging');
                    console.log('Drag started for category:', evt.item.dataset.id);
                },
                onEnd: function(evt) {
                    evt.item.classList.remove('dragging');
                    hasChanges = true;
                    console.log('Drag ended, updating category data');
                    updateCategoryDataFromDOM();
                    showSaveButton();
                }
            });
        });
    }

    function initializeTooltips() {
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }

    function updateCategoryDataFromDOM() {
        console.log('Updating category data from DOM');
        categoryData = [];
        const rootItems = $('#categoryTree > li');

        function processItem(item, parentId = null, level = 0, order = 0) {
            const id = parseInt($(item).data('id'));
            const original = findCategoryById(categoryDataOriginal, id) || findCategoryById(categoryData, id);
            if (!original) {
                console.warn('Original category not found for ID:', id);
                return null;
            }

            const newCategory = {
                id: id,
                parent_id: parentId,
                level: level,
                sort_order: order + 1,
                title: original.title,
                slug: original.slug,
                status: original.status,
                is_featured: original.is_featured,
                children_count: original.children_count,
                children: []
            };

            const childrenItems = $(item).find('> .children-container > ul > li');
            childrenItems.each((childOrder, childItem) => {
                const child = processItem(childItem, id, level + 1, childOrder);
                if (child) {
                    newCategory.children.push(child);
                }
            });

            return newCategory;
        }

        rootItems.each((order, item) => {
            const processed = processItem(item, null, 0, order);
            if (processed) {
                categoryData.push(processed);
            }
        });

        console.log('Updated category data:', categoryData);
        updateStats();
    }

    function findCategoryById(categories, id) {
        for (const cat of categories) {
            if (cat.id === id) return cat;
            if (cat.children) {
                const found = findCategoryById(cat.children, id);
                if (found) return found;
            }
        }
        return null;
    }

    function toggleChildren(evt) {
        const btn = $(evt.target).closest('.toggle-children');
        const childrenContainer = btn.closest('.category-item').find('> .children-container');
        const icon = btn.find('i');

        childrenContainer.toggleClass('d-none');
        icon.toggleClass('fa-minus fa-plus');
        console.log('Toggled children for category:', btn.data('id'));
    }

    function expandAll() {
        $('.children-container').removeClass('d-none');
        $('.toggle-children i').removeClass('fa-plus').addClass('fa-minus');
        console.log('Expanded all categories');
    }

    function collapseAll() {
        $('.children-container').addClass('d-none');
        $('.toggle-children i').removeClass('fa-minus').addClass('fa-plus');
        console.log('Collapsed all categories');
    }

    function updateStats() {
        function countCategories(cats) {
            let count = cats.length;
            cats.forEach(cat => count += cat.children ? countCategories(cat.children) : 0);
            return count;
        }
        $('#totalCategories').text(countCategories(categoryData));
        console.log('Updated stats, total categories:', countCategories(categoryData));
    }

    function showSaveButton() {
        const saveBtn = $('#saveChangesBtn');
        saveBtn.addClass('btn-warning').removeClass('btn-success');
        saveBtn.html('<i class="fas fa-exclamation-triangle me-1"></i>Save Changes');
        console.log('Showing save button');
    }

    function saveChanges() {
        if (!hasChanges) {
            Swal.fire({
                icon: 'info',
                title: 'No Changes',
                text: 'No changes to save!',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        const saveBtn = $('#saveChangesBtn');
        const originalHtml = saveBtn.html();
        saveBtn.html('<i class="fas fa-spinner fa-spin me-1"></i>Saving...').prop('disabled', true);

        const flattened = [];

        function flattenTree(cats, parentId = null) {
            cats.forEach((cat, index) => {
                flattened.push({
                    id: cat.id,
                    parent_id: parentId,
                    sort_order: index + 1,
                    level: cat.level
                });
                if (cat.children) flattenTree(cat.children, cat.id);
            });
        }
        flattenTree(categoryData);

        console.log('Saving category data:', flattened);

        $.ajax({
            url: '{{ route("category.update-tree") }}',
            method: 'POST',
            data: {
                _token: csrfToken,
                categories: flattened
            },
            success: function(response) {
                console.log('Save response:', response);
                if (response.success) {
                    hasChanges = false;
                    categoryDataOriginal = JSON.parse(JSON.stringify(categoryData));
                    saveBtn.html('<i class="fas fa-check me-1"></i>Saved').addClass('btn-success').removeClass('btn-warning');
                    setTimeout(() => saveBtn.html(originalHtml).prop('disabled', false), 2000);
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Category hierarchy saved!',
                        confirmButtonColor: '#3085d6'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Failed to save changes',
                        confirmButtonColor: '#3085d6'
                    });
                    saveBtn.html(originalHtml).prop('disabled', false);
                }
            },
            error: function(xhr) {
                console.error('Error saving category data:', xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to save changes',
                    confirmButtonColor: '#3085d6'
                });
                saveBtn.html(originalHtml).prop('disabled', false);
            }
        });
    }

    function editCategory(id) {
        console.log('Editing category:', id);
        window.location.href = '{{ route("category.edit", ":id") }}'.replace(':id', id);
    }

    function deleteCategory(id) {
        console.log('Deleting category:', id);
        if (typeof Swal === 'undefined') {
            console.error('SweetAlert2 is not loaded');
            alert('Error: SweetAlert2 is not loaded. Please check your internet connection or contact support.');
            return;
        }
        Swal.fire({
            title: 'Are you sure?',
            text: "This will delete the category and its subcategories!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '{{ route("category.destroy", ":id") }}'.replace(':id', id),
                    method: 'DELETE',
                    data: {
                        _token: csrfToken
                    },
                    success: function(response) {
                        console.log('Delete response:', response);
                        fetchCategoryData();
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: 'Category has been deleted.',
                            confirmButtonColor: '#3085d6'
                        });
                    },
                    error: function(xhr) {
                        console.error('Error deleting category:', xhr);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Failed to delete category',
                            confirmButtonColor: '#3085d6'
                        });
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        console.log('Document ready, initializing page');
        initializePage();

        $('#saveChangesBtn').on('click', saveChanges);
        $('#expandAllBtn').on('click', expandAll);
        $('#collapseAllBtn').on('click', collapseAll);

        window.addEventListener('beforeunload', (e) => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    });
</script>
@endpush