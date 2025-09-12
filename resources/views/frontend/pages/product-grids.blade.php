@extends('frontend.layouts.master')

@section('title', 'E-SHOP || PRODUCT PAGE')

@section('main-content')
<!-- Breadcrumbs Section -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{ route('home') }}" tabindex="1">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0)" tabindex="2">Shop Grid</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Filter Form -->
<form id="productFilterForm" action="{{ url()->current() }}" method="GET">
    <section class="product-area shop-sidebar shop section">
        <div class="container">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 col-md-4 col-12">
                    <div class="shop-sidebar-container">
                        @include('frontend.partials.shop-sidebar')
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9 col-md-8 col-12">
                    <!-- Shop Controls -->
                    <div class="shop-top">
                        <div class="shop-shorter">
                            <select name="sortBy" id="sortBy" tabindex="3">
                                <option value="latest" {{ $applied_filters['sortBy'] == 'latest' ? 'selected' : '' }}>Latest</option>
                                <option value="price_low_high" {{ $applied_filters['sortBy'] == 'price_low_high' ? 'selected' : '' }}>Price: Low → High</option>
                                <option value="price_high_low" {{ $applied_filters['sortBy'] == 'price_high_low' ? 'selected' : '' }}>Price: High → Low</option>
                            </select>
                            <select name="show" id="show" tabindex="4">
                                <option value="12" {{ $applied_filters['show'] == '12' ? 'selected' : '' }}>Show 12</option>
                                <option value="18" {{ $applied_filters['show'] == '18' ? 'selected' : '' }}>Show 18</option>
                            </select>
                        </div>
                    </div>
                    
                    <div id="product-grids">
                        @include('frontend.pages.product-grid-html')
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ADDED: Hidden inputs to preserve filter state -->
    @if(!empty($applied_filters['price_range']))
        <input type="hidden" id="price_range" name="price_range" value="{{ $applied_filters['price_range'] }}">
    @endif
</form>

<!-- Product Modals -->
@include('frontend.partials.product-modals', compact('products'))

@endsection

@push('styles')
<style>
    /* Pagination Styling */
    .pagination {
        display: inline-flex;
    }

    /* Checkbox Styling */
    .single-widget.rating input[type="checkbox"],
    .single-widget.discount input[type="checkbox"],
    .single-widget.mainCategory input[type="checkbox"],
    .single-widget.rating-filter input[type="checkbox"],
    .single-widget.category input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        vertical-align: middle;
        accent-color: #f7941d;
    }

    /* Label Styling */
    .single-widget.rating label,
    .single-widget.discount label,
    .single-widget.active-filters label,
    .single-widget.category label {
        font-size: 14px;
        line-height: 1.8;
        color: #212121;
    }

    /* Shop Top Bar */
    .shop-top {
        padding: 15px 0;
        background: #f9f9f9;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Filter Status Indicator */
    .filter-status {
        display: flex;
        align-items: center;
    }

    .filter-status .badge {
        background-color: #17a2b8;
        color: white;
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 12px;
    }

    /* Select Dropdown Styling */
    .shop-shorter select {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-right: 10px;
        background: #fff;
        font-size: 14px;
    }

    /* Product Card Styling */
    .single-post {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        padding: 15px;
        transition: box-shadow 0.3s ease;
        text-align: center;
    }

    .single-post:hover {
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    }

    /* Product Grid Container */
    .product-grid-container {
        margin-bottom: 20px;
    }

    /* Card Body */
    .card-body {
        padding: 0;
    }

    /* Product Image */
    .image img {
        width: 100%;
        height: auto;
        border-radius: 5px 5px 0 0;
    }

    /* Product Title */
    .product-title {
        font-size: 16px;
        color: #333;
        margin: 10px 0;
    }

    /* Price Styling */
    .price {
        font-size: 16px;
        color: #f7941d;
        margin-bottom: 10px;
    }

    .price del {
        color: #999;
        margin-right: 5px;
    }

    /* Add to Cart Button */
    .add-to-cart {
        background: #000;
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        width: 100%;
        transition: background 0.3s;
    }

    .add-to-cart:hover {
        background: #333;
    }

    /* Product Actions */
    .product-actions a {
        color: #6c757d;
        margin: 0 5px;
    }

    .product-actions a:hover {
        color: #f7941d;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .shop-top {
            padding: 10px;
            flex-direction: column;
            gap: 10px;
        }

        .shop-shorter select {
            width: 100%;
            margin-bottom: 10px;
        }

        .single-post {
            padding: 10px;
        }

        .product-title {
            font-size: 14px;
        }

        .price {
            font-size: 14px;
        }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    
    // ADDED: Auto-apply filters on page load if filters exist in URL
    function initializeFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);
        let hasUrlFilters = false;

        // Check brand filters
        const brandParam = urlParams.get('brand');
        if (brandParam) {
            const brands = brandParam.split(',');
            brands.forEach(brand => {
                const checkbox = document.querySelector(`input[name="brand[]"][value="${brand}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    hasUrlFilters = true;
                }
            });
        }

        // Check rating filters
        const ratingParam = urlParams.get('min_rating');
        if (ratingParam) {
            const ratings = ratingParam.split(',');
            ratings.forEach(rating => {
                const checkbox = document.querySelector(`input[name="min_rating[]"][value="${rating}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    hasUrlFilters = true;
                }
            });
        }

        // Check discount filters
        const discountParam = urlParams.get('min_discount');
        if (discountParam) {
            const discounts = discountParam.split(',');
            discounts.forEach(discount => {
                const checkbox = document.querySelector(`input[name="min_discount[]"][value="${discount}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                    hasUrlFilters = true;
                }
            });
        }

        // Check price range
        const priceRangeParam = urlParams.get('price_range');
        if (priceRangeParam && priceRangeParam !== '0-' + window.maxPrice) {
            document.getElementById('price_range').value = priceRangeParam;
            hasUrlFilters = true;
        }

        // Check sorting
        const sortByParam = urlParams.get('sortBy');
        if (sortByParam && sortByParam !== 'latest') {
            document.getElementById('sortBy').value = sortByParam;
            hasUrlFilters = true;
        }

        // Check show parameter
        const showParam = urlParams.get('show');
        if (showParam && showParam !== '12') {
            document.getElementById('show').value = showParam;
            hasUrlFilters = true;
        }

        console.log('URL filters initialized:', hasUrlFilters);
        return hasUrlFilters;
    }

    // Initialize filters from URL on page load
    const hasUrlFilters = initializeFiltersFromURL();

    // Initialize price range slider
    if ($("#slider-range").length > 0) {
        const maxValue = window.maxPrice || 5000;
        const minValue = 0;
        const currency = $("#slider-range").data('currency') || '$';
        
        // FIXED: Get initial price range from URL or use defaults
        let priceRange = minValue + '-' + maxValue;
        const urlPriceRange = new URLSearchParams(window.location.search).get('price_range');
        
        if (urlPriceRange) {
            priceRange = urlPriceRange;
        } else if ($("#price_range").val()) {
            priceRange = $("#price_range").val().trim();
        }

        const price = priceRange.split('-').map(p => parseInt(p));

        $("#slider-range").slider({
            range: true,
            min: minValue,
            max: maxValue,
            values: price,
            slide: function(event, ui) {
                $("#amount").val(currency + ui.values[0] + " - " + currency + ui.values[1]);
                $("#price_range").val(ui.values[0] + "-" + ui.values[1]);
            },
            stop: function(event, ui) {
                applyFilters();
            }
        });

        $("#amount").val(currency + $("#slider-range").slider("values", 0) +
            " - " + currency + $("#slider-range").slider("values", 1));
    }

    // Get category slug from current URL
    function getCategorySlug() {
        const pathSegments = window.location.pathname.split('/');
        const productCatIndex = pathSegments.indexOf('product-cat');
        
        if (productCatIndex !== -1 && pathSegments.length > productCatIndex + 1) {
            return pathSegments.slice(productCatIndex + 1).join('/');
        }
        
        return '';
    }

    // Apply filters function
    let timeoutId;
    function applyFilters(page = 1) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            const form = document.getElementById('productFilterForm');
            if (!form) {
                console.error('Form not found');
                return;
            }

            // Show loading state
            const productGrids = document.getElementById('product-grids');
            if (productGrids) {
                productGrids.innerHTML = '<div class="text-center py-4"><i class="fa fa-spinner fa-spin fa-2x"></i><p>Loading...</p></div>';
            }

            // Collect form data properly
            const params = new URLSearchParams();

            // Handle checkboxes and selects properly
            const checkboxGroups = {};
            
            // Group checkbox values
            form.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
                const name = checkbox.name.replace('[]', ''); // Remove [] from name
                const value = checkbox.value;
                
                if (!checkboxGroups[name]) {
                    checkboxGroups[name] = [];
                }
                checkboxGroups[name].push(value);
            });

            // Add grouped checkbox values
            for (const [name, values] of Object.entries(checkboxGroups)) {
                if (values.length > 0) {
                    params.append(name, values.join(','));
                }
            }

            // Add other form elements
            form.querySelectorAll('select, input[type="text"], input[type="hidden"]').forEach(element => {
                if (element.name && element.value && element.name !== 'price_range') {
                    params.set(element.name, element.value);
                }
            });

            // Add price range if it's different from default
            const priceRange = document.getElementById('price_range');
            if (priceRange && priceRange.value && priceRange.value !== `0-${window.maxPrice}`) {
                params.set('price_range', priceRange.value);
            }

            // Add page and category_slug
            params.set('page', page);
            const categorySlug = getCategorySlug();
            if (categorySlug) {
                params.set('category_slug', categorySlug);
            }

            // Use the correct route
            const filterUrl = '{{ route("apply.filters") }}';
            
            fetch(`${filterUrl}?${params.toString()}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        console.error('Server Response:', text);
                        throw new Error(`Server returned ${response.status}: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Filter response:', data);
                
                if (data.success) {
                    const productGrids = document.getElementById('product-grids');
                    if (productGrids && data.html) {
                        productGrids.innerHTML = data.html;
                        attachPaginationListeners();
                    }

                    // Update browser URL without reload
                    const currentUrl = new URL(window.location);
                    const newParams = new URLSearchParams();
                    
                    params.forEach((value, key) => {
                        if (key !== 'page' || (key === 'page' && value !== '1')) {
                            newParams.set(key, value);
                        }
                    });

                    const newUrl = `${currentUrl.pathname}?${newParams.toString()}`;
                    history.replaceState({ path: newUrl }, '', newUrl);

                    // Show message if no products found
                    if (data.message) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Filter Results',
                            text: data.message,
                            timer: 3000,
                            showConfirmButton: false
                        });
                    }
                } else {
                    console.error('Filter failed:', data);
                    
                    // Restore original content or show error
                    const productGrids = document.getElementById('product-grids');
                    if (productGrids) {
                        productGrids.innerHTML = `
                            <div class="alert alert-warning text-center">
                                <i class="fa fa-exclamation-triangle"></i>
                                <p>${data.message || 'Failed to apply filters. Please try again.'}</p>
                                <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Filter error:', error);
                
                const productGrids = document.getElementById('product-grids');
                if (productGrids) {
                    productGrids.innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fa fa-times-circle"></i>
                            <p>Failed to apply filters: ${error.message}</p>
                            <button class="btn btn-primary" onclick="location.reload()">Refresh Page</button>
                        </div>
                    `;
                }
            });
        }, 300);
    }

    // Attach pagination listeners
    function attachPaginationListeners() {
        document.querySelectorAll('#product-grids .pagination a').forEach(link => {
            link.setAttribute('tabindex', '5');
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                try {
                    const url = new URL(this.href);
                    const page = url.searchParams.get('page') || 1;
                    applyFilters(parseInt(page));
                } catch (error) {
                    console.error('Pagination error:', error);
                }
            });
        });
    }

    // Initial attachment for pagination
    attachPaginationListeners();

    // Form change event handlers
    const form = document.getElementById('productFilterForm');
    if (form) {
        // Handle select changes
        form.addEventListener('change', function(e) {
            if (e.target.matches('select[name="sortBy"], select[name="show"]')) {
                applyFilters(1); // Reset to page 1 when changing display options
            } else if (e.target.matches('input[type="checkbox"]')) {
                applyFilters(1); // Reset to page 1 when applying filters
            }
        });

        // Handle filter button click (if exists)
        const filterButton = form.querySelector('.filter_button, button[type="submit"]');
        if (filterButton) {
            filterButton.setAttribute('tabindex', '6');
            filterButton.addEventListener('click', function(e) {
                e.preventDefault();
                applyFilters(1);
            });
        }
    }

    // Handle browser back/forward
    window.addEventListener('popstate', function(e) {
        if (e.state && e.state.path) {
            location.reload();
        }
    });

    // Handle price range input changes
    const priceRangeInput = document.getElementById('price_range');
    if (priceRangeInput) {
        priceRangeInput.addEventListener('change', function() {
            applyFilters(1);
        });
    }

    // Debug logging
    console.log('Filter form initialized');
    console.log('Category slug:', getCategorySlug());
    console.log('Current URL params:', new URLSearchParams(window.location.search));
    console.log('Applied filters from PHP:', window.appliedFilters);
    console.log('Has filters:', window.hasFilters);
});
</script>
@endpush