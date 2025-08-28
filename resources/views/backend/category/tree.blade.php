@extends('backend.layouts.master')

@section('main-content')
<div class="card shadow mb-4">
    <div class="row">
        <div class="col-md-12">
            @include('backend.layouts.notification')
        </div>
    </div>
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Category Tree Manager - Infinite Levels</h6>
        <a href="{{ route('category.index') }}" class="btn btn-primary btn-sm" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Back to Category List"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
    </div>
    <div class="card-body">
        <div class="controls-panel mb-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2 class="mb-1"><i class="fas fa-sitemap me-2"></i>Category Hierarchy - Infinite Depth</h2>
                    <p class="text-muted mb-0">Drag and drop any category to any level. Create unlimited subcategory levels.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="stats-card">
                        <div class="stats-number" id="totalCategories">0</div>
                        <div>Total Categories</div>
                        <div class="mt-2">
                            <small>Max Depth: <span id="maxDepth">0</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="tree-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4><i class="fas fa-tree me-2"></i>Category Structure</h4>
                <div>
                    <!-- <button class="btn btn-primary btn-sm me-2" id="addRootBtn" data-bs-toggle="tooltip" data-bs-placement="top" title="Add root category">
                        <i class="fas fa-plus me-1"></i> Add Root
                    </button> -->
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
            <div class="drop-zone-root" id="rootDropZone">
                <div class="drop-zone-hint">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <span>Drop here to make root category</span>
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
        position: relative;
    }

    .category-tree {
        list-style: none;
        padding: 0;
        margin: 0;
        min-height: 100px;
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
        opacity: 0.8;
        transform: scale(1.02);
        background: #e6f3ff;
        border-color: #3b82f6;
        z-index: 1000;
    }

    .category-item.drag-over {
        border-color: #22c55e !important;
        background: #f0fdf4 !important;
        box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.2) !important;
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
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #ffffff;
        font-size: 20px;
        flex-shrink: 0;
        position: relative;
    }

    .category-details h6 {
        margin: 0;
        font-size: 1.1rem;
        font-weight: 600;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .category-details small {
        color: #64748b;
        font-size: 0.85rem;
        display: block;
        margin-top: 4px;
    }

    .category-level {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 12px;
        margin-left: 10px;
        color: #ffffff;
        font-weight: 500;
        display: inline-block;
    }

    .category-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
    }

    .btn-action {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-action:hover {
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .children-container {
        margin-left: 20px;
        margin-top: 15px;
        border-left: 3px solid #e2e8f0;
        padding-left: 20px;
        position: relative;
    }

    .children-container::before {
        content: '';
        position: absolute;
        left: -3px;
        top: 0;
        bottom: 0;
        width: 3px;
        border-radius: 3px;
        opacity: 0.7;
    }

    .children-tree {
        list-style: none;
        padding: 0;
        margin: 0;
        min-height: 60px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .children-tree:empty {
        border: 2px dashed #d1d5db;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #6b7280;
        font-size: 0.9rem;
        background: #f9fafb;
    }

    .children-tree:empty::after {
        content: 'Drop subcategories here or click + to add';
    }

    .children-tree.drag-over-empty {
        border-color: #22c55e !important;
        background: #f0fdf4 !important;
        color: #16a34a !important;
    }

    .children-tree.drag-over-empty::after {
        content: 'Release to add as subcategory';
    }

    /* Dynamic level colors */
    .level-0 .category-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .level-0 .category-level { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .level-0 .children-container::before { background: linear-gradient(180deg, #ef4444, #dc2626); }

    .level-1 .category-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .level-1 .category-level { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .level-1 .children-container::before { background: linear-gradient(180deg, #3b82f6, #2563eb); }

    .level-2 .category-icon { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .level-2 .category-level { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .level-2 .children-container::before { background: linear-gradient(180deg, #22c55e, #16a34a); }

    .level-3 .category-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .level-3 .category-level { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .level-3 .children-container::before { background: linear-gradient(180deg, #f59e0b, #d97706); }

    .level-4 .category-icon { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .level-4 .category-level { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .level-4 .children-container::before { background: linear-gradient(180deg, #8b5cf6, #7c3aed); }

    .level-5 .category-icon { background: linear-gradient(135deg, #ec4899, #db2777); }
    .level-5 .category-level { background: linear-gradient(135deg, #ec4899, #db2777); }
    .level-5 .children-container::before { background: linear-gradient(180deg, #ec4899, #db2777); }

    .level-6 .category-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    .level-6 .category-level { background: linear-gradient(135deg, #06b6d4, #0891b2); }
    .level-6 .children-container::before { background: linear-gradient(180deg, #06b6d4, #0891b2); }

    /* For levels beyond 6, cycle through colors */
    .category-item[class*="level-"]:nth-child(7n+1) .category-icon { background: linear-gradient(135deg, #84cc16, #65a30d); }
    .category-item[class*="level-"]:nth-child(7n+1) .category-level { background: linear-gradient(135deg, #84cc16, #65a30d); }

    .status-badge {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 12px;
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

    .drop-zone-root {
        min-height: 60px;
        border: 2px dashed #d1d5db;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
        transition: all 0.3s ease;
        background: #f9fafb;
    }

    .drop-zone-root.drag-over {
        border-color: #22c55e !important;
        background: #f0fdf4 !important;
    }

    .drop-zone-hint {
        color: #6b7280;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .drop-zone-root.drag-over .drop-zone-hint {
        color: #16a34a;
    }

    .path-indicator {
        font-size: 0.75rem;
        color: #64748b;
        background: #f1f5f9;
        padding: 2px 8px;
        border-radius: 8px;
        margin-left: 8px;
    }

    .depth-indicator {
        position: absolute;
        top: 5px;
        right: 5px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 10px;
        font-weight: bold;
    }

    @media (max-width: 768px) {
        .children-container {
            margin-left: 15px;
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

    /* Enhanced sortable indicators */
    .sortable-ghost {
        opacity: 0.5;
    }

    .sortable-chosen {
        background: #e6f3ff !important;
    }

    .sortable-drag {
        background: #ffffff !important;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
        transform: rotate(5deg) !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert2/11.7.3/sweetalert2.all.min.js"></script>
<script>
    let categoryData = [];
    let categoryDataOriginal = [];
    let hasChanges = false;
    let sortableInstances = [];
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
                    initializeAllSortables();
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

    function buildCategoryPath(category, allData) {
        let path = [];
        let current = category;
        
        while (current && current.parent_id) {
            let parent = findCategoryById(allData, current.parent_id);
            if (parent) {
                path.unshift(parent.title);
                current = parent;
            } else {
                break;
            }
        }
        
        return path.length > 0 ? path.join(' → ') : 'Root Level';
    }

    function buildCategoryHtml(category, allData = null) {
        allData = allData || categoryData;
        const hasChildren = category.children && category.children.length > 0;
        const statusClass = category.status === 'active' ? 'badge-success' : 'badge-warning';
        const featuredIcon = category.is_featured ? '<i class="fas fa-star text-warning" title="Featured"></i>' : '';
        const toggleIcon = hasChildren ? 'minus' : 'plus';
        const containerClass = hasChildren ? '' : 'd-none';
        const categoryPath = buildCategoryPath(category, allData);
        const maxLevelClass = category.level > 6 ? `level-${category.level % 7}` : '';

        let html = `
            <li class="category-item level-${category.level} ${maxLevelClass}" data-id="${category.id}" data-level="${category.level}">
                <div class="depth-indicator">L${category.level}</div>
                <div class="category-content">
                    <div class="category-info">
                        <div class="category-icon">
                            <i class="fas fa-${category.level === 0 ? 'home' : 'folder'}"></i>
                        </div>
                        <div class="category-details">
                            <h6>
                                ${category.title}
                                ${featuredIcon}
                                <span class="category-level">Level ${category.level}</span>
                            </h6>
                            <small>
                                ID: ${category.id} | Slug: ${category.slug} | Children: ${category.children_count}
                                <div class="path-indicator" title="Category Path">${categoryPath}</div>
                            </small>
                        </div>
                    </div>
                    <div class="category-actions">
                        <span class="status-badge badge ${statusClass}">${category.status}</span>
                        <button class="btn-action btn-success add-sub" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Add subcategory">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn-action btn-primary toggle-children" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Toggle children">
                            <i class="fas fa-${toggleIcon}"></i>
                        </button>
                        <button class="btn-action btn-info edit-cat" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit category">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-action btn-warning move-cat" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Quick move category">
                            <i class="fas fa-arrows-alt"></i>
                        </button>
                        <button class="btn-action btn-danger delete-cat" data-id="${category.id}" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete category">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="children-container ${containerClass}" data-parent="${category.id}">
                    <ul class="children-tree sortable" data-parent="${category.id}">
        `;

        if (hasChildren) {
            category.children.forEach(child => {
                html += buildCategoryHtml(child, allData);
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
        
        // Rebind all event handlers
        bindCategoryEvents();
    }

    function bindCategoryEvents() {
        $('.toggle-children').off('click').on('click', toggleChildren);
        $('.add-sub').off('click').on('click', function() {
            addSubcategory($(this).data('id'));
        });
        $('.edit-cat').off('click').on('click', function() {
            editCategory($(this).data('id'));
        });
        $('.move-cat').off('click').on('click', function() {
            quickMoveCategory($(this).data('id'));
        });
        $('.delete-cat').off('click').on('click', function() {
            deleteCategory($(this).data('id'));
        });
    }

    function getExpandedIds() {
        return $('.children-container:not(.d-none)').map(function() {
            return $(this).data('parent');
        }).get();
    }

    function setExpanded(ids) {
        ids.forEach(id => {
            const btn = $(`.toggle-children[data-id="${id}"]`);
            btn.find('i').removeClass('fa-plus').addClass('fa-minus');
            $(`.children-container[data-parent="${id}"]`).removeClass('d-none');
        });
    }

    function destroyAllSortables() {
        sortableInstances.forEach(instance => {
            if (instance && typeof instance.destroy === 'function') {
                instance.destroy();
            }
        });
        sortableInstances = [];
    }

    function initializeAllSortables() {
        console.log('Initializing all sortables...');
        destroyAllSortables();

        // Initialize root level sortable
        initializeRootDropZone();

        // Initialize all sortable containers
        const sortableContainers = document.querySelectorAll('.sortable');
        sortableContainers.forEach(container => {
            const sortableInstance = new Sortable(container, {
                group: 'nested-categories',
                animation: 200,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                handle: '.category-item',
                
                onStart: function(evt) {
                    console.log('Drag started:', evt.item.dataset.id);
                    evt.item.classList.add('dragging');
                    
                    // Highlight all possible drop zones
                    document.querySelectorAll('.children-tree').forEach(zone => {
                        if (!zone.contains(evt.item)) {
                            zone.classList.add('drop-zone-active');
                        }
                    });
                    document.getElementById('rootDropZone').classList.add('drop-zone-active');
                },

                onEnd: function(evt) {
                    console.log('Drag ended');
                    evt.item.classList.remove('dragging');
                    
                    // Remove drop zone highlights
                    document.querySelectorAll('.children-tree, #rootDropZone').forEach(zone => {
                        zone.classList.remove('drop-zone-active', 'drag-over-empty');
                    });

                    hasChanges = true;
                    const expanded = getExpandedIds();
                    updateCategoryDataFromDOM();
                    renderCategoryTree();
                    setExpanded(expanded);
                    initializeAllSortables();
                    initializeTooltips();
                    updateStats();
                    showSaveButton();
                },

                onMove: function(evt, originalEvent) {
                    // Prevent dropping item into itself or its descendants
                    const draggedId = parseInt(evt.dragged.dataset.id);
                    const targetContainer = evt.to;
                    
                    if (targetContainer.dataset.parent) {
                        const targetParentId = parseInt(targetContainer.dataset.parent);
                        if (isDescendantOf(targetParentId, draggedId)) {
                            return false; // Prevent the move
                        }
                    }
                    
                    return true;
                }
            });
            
            sortableInstances.push(sortableInstance);
        });
    }

    function initializeRootDropZone() {
        const rootDropZone = document.getElementById('rootDropZone');
        const rootSortable = new Sortable(rootDropZone, {
            group: 'nested-categories',
            animation: 200,
            
            onAdd: function(evt) {
                // Move the item to the main tree
                document.getElementById('categoryTree').appendChild(evt.item);
                
                // Trigger the regular update process
                hasChanges = true;
                const expanded = getExpandedIds();
                updateCategoryDataFromDOM();
                renderCategoryTree();
                setExpanded(expanded);
                initializeAllSortables();
                initializeTooltips();
                updateStats();
                showSaveButton();
            }
        });
        
        sortableInstances.push(rootSortable);
    }

    function isDescendantOf(parentId, childId) {
        const category = findCategoryById(categoryData, parentId);
        if (!category) return false;
        
        if (category.id === childId) return true;
        
        if (category.children) {
            for (let child of category.children) {
                if (isDescendantOf(child.id, childId)) {
                    return true;
                }
            }
        }
        
        return false;
    }

    function initializeTooltips() {
        // Destroy existing tooltips first
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            const tooltip = bootstrap.Tooltip.getInstance(el);
            if (tooltip) {
                tooltip.dispose();
            }
        });
        
        // Initialize new tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggerList.forEach(tooltipTriggerEl => {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function updateCategoryDataFromDOM() {
        console.log('Updating category data from DOM');
        categoryData = [];
        const rootItems = document.querySelectorAll('#categoryTree > li');

        function processItem(item, parentId = null, level = 0, order = 0) {
            const id = parseInt(item.dataset.id);
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
                children: [],
                children_count: 0
            };

            // Find direct children
            const childrenContainer = item.querySelector(':scope > .children-container > .children-tree');
            if (childrenContainer) {
                const childItems = childrenContainer.querySelectorAll(':scope > li');
                childItems.forEach((childItem, childOrder) => {
                    const child = processItem(childItem, id, level + 1, childOrder);
                    if (child) {
                        newCategory.children.push(child);
                    }
                });
            }
            
            newCategory.children_count = newCategory.children.length;
            return newCategory;
        }

        rootItems.forEach((item, order) => {
            const processed = processItem(item, null, 0, order);
            if (processed) {
                categoryData.push(processed);
            }
        });

        console.log('Updated category data:', categoryData);
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

    function calculateMaxDepth(categories, currentDepth = 0) {
        if (!categories || categories.length === 0) return currentDepth;
        
        let maxDepth = currentDepth;
        categories.forEach(category => {
            const categoryDepth = category.level || currentDepth;
            maxDepth = Math.max(maxDepth, categoryDepth);
            
            if (category.children && category.children.length > 0) {
                const childMaxDepth = calculateMaxDepth(category.children, categoryDepth + 1);
                maxDepth = Math.max(maxDepth, childMaxDepth);
            }
        });
        
        return maxDepth;
    }

    function updateStats() {
        function countCategories(cats) {
            let count = cats.length;
            cats.forEach(cat => {
                if (cat.children) {
                    count += countCategories(cat.children);
                }
            });
            return count;
        }
        
        const totalCount = countCategories(categoryData);
        const maxDepth = calculateMaxDepth(categoryData);
        
        $('#totalCategories').text(totalCount);
        $('#maxDepth').text(maxDepth);
        console.log('Updated stats - Total:', totalCount, 'Max Depth:', maxDepth);
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
                if (cat.children && cat.children.length > 0) {
                    flattenTree(cat.children, cat.id);
                }
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
                    setTimeout(() => {
                        saveBtn.html(originalHtml).prop('disabled', false);
                    }, 2000);
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Category hierarchy saved successfully!',
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

    function quickMoveCategory(categoryId) {
        const allCategories = [];
        
        function collectCategories(cats, level = 0, path = '') {
            cats.forEach(cat => {
                if (cat.id !== categoryId && !isDescendantOf(cat.id, categoryId)) {
                    const indent = '&nbsp;'.repeat(level * 4);
                    const currentPath = path ? `${path} → ${cat.title}` : cat.title;
                    allCategories.push({
                        id: cat.id,
                        title: cat.title,
                        level: level,
                        path: currentPath,
                        html: `${indent}<i class="fas fa-folder"></i> ${cat.title} <small class="text-muted">(Level ${cat.level})</small>`
                    });
                    
                    if (cat.children && cat.children.length > 0) {
                        collectCategories(cat.children, level + 1, currentPath);
                    }
                }
            });
        }
        
        collectCategories(categoryData);
        
        let optionsHtml = '<option value="">-- Root Level --</option>';
        allCategories.forEach(cat => {
            optionsHtml += `<option value="${cat.id}">${cat.html}</option>`;
        });
        
        const currentCategory = findCategoryById(categoryData, categoryId);
        
        Swal.fire({
            title: `Move "${currentCategory.title}"`,
            html: `
                <p class="text-muted mb-3">Select new parent category:</p>
                <select id="parentSelect" class="form-control">
                    ${optionsHtml}
                </select>
                <div class="mt-3">
                    <small class="text-info">
                        <i class="fas fa-info-circle"></i> 
                        Current: Level ${currentCategory.level} 
                        ${currentCategory.parent_id ? '(Has Parent)' : '(Root Level)'}
                    </small>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Move Category',
            cancelButtonText: 'Cancel',
            preConfirm: () => {
                const selectedParent = document.getElementById('parentSelect').value;
                return selectedParent || null;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                moveCategory(categoryId, result.value);
            }
        });
    }

    function moveCategory(categoryId, newParentId) {
        hasChanges = true;
        
        // Find and remove category from current location
        let categoryToMove = null;
        
        function removeFromTree(cats) {
            for (let i = 0; i < cats.length; i++) {
                if (cats[i].id === categoryId) {
                    categoryToMove = cats.splice(i, 1)[0];
                    return true;
                }
                if (cats[i].children && removeFromTree(cats[i].children)) {
                    cats[i].children_count = cats[i].children.length;
                    return true;
                }
            }
            return false;
        }
        
        removeFromTree(categoryData);
        
        if (!categoryToMove) {
            Swal.fire('Error', 'Category not found!', 'error');
            return;
        }
        
        // Update category data
        function updateLevels(cat, newLevel) {
            cat.level = newLevel;
            if (cat.children) {
                cat.children.forEach(child => updateLevels(child, newLevel + 1));
            }
        }
        
        if (newParentId) {
            // Add to new parent
            const newParent = findCategoryById(categoryData, parseInt(newParentId));
            if (newParent) {
                if (!newParent.children) newParent.children = [];
                categoryToMove.parent_id = parseInt(newParentId);
                updateLevels(categoryToMove, newParent.level + 1);
                newParent.children.push(categoryToMove);
                newParent.children_count = newParent.children.length;
            }
        } else {
            // Move to root level
            categoryToMove.parent_id = null;
            updateLevels(categoryToMove, 0);
            categoryData.push(categoryToMove);
        }
        
        // Re-render and update
        const expanded = getExpandedIds();
        renderCategoryTree();
        setExpanded(expanded);
        initializeAllSortables();
        initializeTooltips();
        updateStats();
        showSaveButton();
        
        Swal.fire({
            icon: 'success',
            title: 'Category Moved',
            text: `"${categoryToMove.title}" has been moved successfully!`,
            timer: 2000,
            showConfirmButton: false
        });
    }

    function addSubcategory(parentId) {
        Swal.fire({
            title: parentId ? 'Add Subcategory' : 'Add Root Category',
            html: `
                <input id="swal-input1" class="swal2-input" placeholder="Category Title" required>
                <input id="swal-input2" class="swal2-input" placeholder="Category Slug" required>
                <select id="swal-input3" class="swal2-select">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <div class="form-check mt-2">
                    <input type="checkbox" id="swal-input4" class="form-check-input">
                    <label for="swal-input4" class="form-check-label">Featured Category</label>
                </div>
            `,
            focusConfirm: false,
            preConfirm: () => {
                const title = document.getElementById('swal-input1').value;
                const slug = document.getElementById('swal-input2').value;
                const status = document.getElementById('swal-input3').value;
                const is_featured = document.getElementById('swal-input4').checked;
                
                if (!title || !slug) {
                    Swal.showValidationMessage('Please enter both title and slug');
                    return false;
                }
                
                return { title, slug, status, is_featured };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const { title, slug, status, is_featured } = result.value;
                
                $.ajax({
                    url: '{{ route("category.store") }}',
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        title: title,
                        slug: slug,
                        status: status,
                        is_featured: is_featured ? 1 : 0,
                        parent_id: parentId || null
                    },
                    success: function(response) {
                        if (response.success) {
                            fetchCategoryData();
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Category added successfully!',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to add category', 'error');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error adding category:', xhr);
                        Swal.fire('Error', 'Failed to add category', 'error');
                    }
                });
            }
        });
    }

    function editCategory(id) {
        console.log('Editing category:', id);
        window.location.href = '{{ route("category.edit", ":id") }}'.replace(':id', id);
    }

    function deleteCategory(id) {
        const category = findCategoryById(categoryData, id);
        if (!category) {
            Swal.fire('Error', 'Category not found!', 'error');
            return;
        }
        
        const hasChildren = category.children && category.children.length > 0;
        const warningText = hasChildren 
            ? `This will delete "${category.title}" and all its ${category.children_count} subcategories!`
            : `This will delete "${category.title}".`;
        
        Swal.fire({
            title: 'Are you sure?',
            text: warningText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, delete it!',
            cancelButtonText: 'Cancel'
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
                        if (response.success) {
                            fetchCategoryData();
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'Category has been deleted successfully.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire('Error', response.message || 'Failed to delete category', 'error');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error deleting category:', xhr);
                        Swal.fire('Error', 'Failed to delete category', 'error');
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        console.log('Document ready, initializing enhanced category tree manager');
        initializePage();

        $('#addRootBtn').on('click', function() {
            addSubcategory(null);
        });
        
        $('#saveChangesBtn').on('click', saveChanges);
        $('#expandAllBtn').on('click', expandAll);
        $('#collapseAllBtn').on('click', collapseAll);

        // Warn user before leaving with unsaved changes
        window.addEventListener('beforeunload', (e) => {
            if (hasChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });

        console.log('Enhanced category tree manager initialized successfully!');
    });
</script>
@endpush