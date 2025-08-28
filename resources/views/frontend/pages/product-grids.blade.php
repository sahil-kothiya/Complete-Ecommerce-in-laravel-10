@extends('frontend.layouts.master')

@section('title', 'E-SHOP || PRODUCT PAGE')

@section('main-content')
<!-- Breadcrumbs -->
<div class="breadcrumbs">
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="bread-inner">
                    <ul class="bread-list">
                        <li><a href="{{ route('home') }}">Home<i class="ti-arrow-right"></i></a></li>
                        <li class="active"><a href="javascript:void(0)">Shop Grid</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Product Area -->
<form id="productFilterForm" action="{{ route('shop.filter') }}" method="POST">
    @csrf
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
                            <select name="sortBy" id="sortBy">
                                <option value="latest" {{ request('sortBy') == 'latest' ? 'selected' : '' }}>Latest</option>
                                <option value="price_low_high" {{ request('sortBy') == 'price_low_high' ? 'selected' : '' }}>Price: Low → High</option>
                                <option value="price_high_low" {{ request('sortBy') == 'price_high_low' ? 'selected' : '' }}>Price: High → Low</option>
                            </select>
                            <select name="show" id="show">
                                <option value="9" {{ request('show') == '9' ? 'selected' : '' }}>Show 9</option>
                                <option value="18" {{ request('show') == '18' ? 'selected' : '' }}>Show 18</option>
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
</form>

<!-- Product Modals -->
@include('frontend.partials.product-modals', compact('products'))

@endsection

@push('styles')
<style>
    .pagination {
        display: inline-flex; /* or inline-block, unset, revert */
    }
    .single-widget.rating input[type="checkbox"],
    .single-widget.discount input[type="checkbox"],
    .single-widget.category input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        vertical-align: middle;
        accent-color: #f7941d;
    }

    .single-widget.rating label,
    .single-widget.discount label,
    .single-widget.category label {
        font-size: 14px;
        line-height: 1.8;
        color: #212121;
    }

    .shop-top {
        padding: 15px 0;
        background: #f9f9f9;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .shop-shorter select {
        padding: 8px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        margin-right: 10px;
        background: #fff;
        font-size: 14px;
    }

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

    .product-grid-container {
        margin-bottom: 20px;
    }

    .card-body {
        padding: 0;
    }

    .image img {
        width: 100%;
        height: auto;
        border-radius: 5px 5px 0 0;
    }

    .product-title {
        font-size: 16px;
        color: #333;
        margin: 10px 0;
    }

    .price {
        font-size: 16px;
        color: #f7941d;
        margin-bottom: 10px;
    }

    .price del {
        color: #999;
        margin-right: 5px;
    }

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

    .product-actions a {
        color: #6c757d;
        margin: 0 5px;
    }

    .product-actions a:hover {
        color: #f7941d;
    }

    @media (max-width: 768px) {
        .shop-top {
            padding: 10px;
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
<script src="https://cdnjs.cloudflare.com/ajax/libs/sweetalert/2.1.2/sweetalert.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize price range slider
    if ($("#slider-range").length > 0) {
        const maxValue = parseInt($("#slider-range").data('max')) || 1000;
        const minValue = parseInt($("#slider-range").data('min')) || 0;
        const currency = $("#slider-range").data('currency') || '$';
        let priceRange = minValue + '-' + maxValue;

        if ($("#price_range").val()) {
            priceRange = $("#price_range").val().trim();
        }

        const price = priceRange.split('-');

        $("#slider-range").slider({
            range: true,
            min: minValue,
            max: maxValue,
            values: price.map(p => parseInt(p)),
            slide: function(event, ui) {
                $("#amount").val(currency + ui.values[0] + " - " + currency + ui.values[1]);
                $("#price_range").val(ui.values[0] + "-" + ui.values[1]);
                applyFilters();
            }
        });

        $("#amount").val(currency + $("#slider-range").slider("values", 0) +
            " - " + currency + $("#slider-range").slider("values", 1));
    }

    // Apply filters on sortBy, show, or checkbox changes
    let timeoutId;
    function applyFilters(page = 1) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            const form = document.getElementById('productFilterForm');
            if (!form) {
                console.error('Form not found');
                return;
            }

            const formData = new FormData(form);
            const data = {
                show: formData.get('show') || 9,
                sortBy: formData.get('sortBy') || 'latest',
                query: formData.get('query') || '',
                category: formData.getAll('category[]'),
                brand: formData.getAll('brand[]'),
                price_range: formData.get('price_range') || '',
                min_rating: formData.getAll('min_rating[]'),
                min_discount: formData.getAll('min_discount[]'),
                page: page,
                category_slug: '{{ request()->route("slug") ?? "" }}'
            };

            console.log('Sending AJAX request with data:', data);

            fetch('{{ route('apply.filters') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                },
                body: JSON.stringify(data),
            })
            .then(response => {
                console.log('Raw response:', response);
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`Server returned ${response.status}: ${text}`);
                    });
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const productGrids = document.getElementById('product-grids');
                    if (productGrids) {
                        productGrids.innerHTML = data.html;
                        // Re-bind pagination links
                        document.querySelectorAll('#product-grids .pagination a').forEach(link => {
                            link.addEventListener('click', function(e) {
                                e.preventDefault();
                                const url = new URL(this.href);
                                const page = url.searchParams.get('page') || 1;
                                applyFilters(page);
                            });
                        });
                        if (data.message) {
                            Swal.fire({
                                icon: 'info',
                                title: 'No Results',
                                text: data.message,
                            });
                        }
                    }
                } else {
                    console.error('Filter error:', data.message);
                    Swal.fire({
                        icon: 'error',
                        title: 'Filter Error',
                        text: data.message || 'Something went wrong while applying filters.',
                    });
                }
            })
            .catch(error => {
                console.error('Error applying filters:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to apply filters: ' + error.message,
                });
            });
        }, 300);
    }

    // Bind change events to form inputs
    const form = document.getElementById('productFilterForm');
    if (form) {
        form.addEventListener('change', function(e) {
            if (e.target.matches('select, input[type="checkbox"]')) {
                applyFilters();
            }
        });

        // Handle filter button click
        const filterButton = form.querySelector('.filter_button');
        if (filterButton) {
            filterButton.addEventListener('click', function(e) {
                e.preventDefault();
                applyFilters();
            });
        }
    }

    // Handle pagination clicks
    document.addEventListener('click', function(e) {
        const link = e.target.closest('#product-grids .pagination a');
        if (link) {
            e.preventDefault();
            const url = new URL(link.href);
            const page = url.searchParams.get('page') || 1;
            applyFilters(page);
        }
    });
});
</script>
@endpush