<!-- Price Filter Widget -->
@if(!isset($category) || $category->filters->where('name', 'price')->count())
<div class="single-widget range">
    <h3 class="title">Shop by Price</h3>
    <div class="price-filter">
        <div class="price-filter-inner">
            <div id="slider-range" data-min="0" data-max="{{ $max_price ?? 1000 }}"></div>
            <div class="product_filter">
                <button type="button" class="filter_button" onclick="applyFilters()">Filter</button>
                <div class="label-input">
                    <span>Range:</span>
                    <input type="text" id="amount" readonly />
                    <input type="hidden" name="price_range" id="price_range" value="{{ request('price') }}" />
                </div>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Recent Products Widget -->
<div class="single-widget">
    <div class="">
        <h3 class="title">Recently Viewed</h3>
    </div>

    <div class="recent-products-container">
        @if(isset($recent_products) && count($recent_products) > 0)
        @foreach($recent_products as $product)
        <div class="single-product card" data-product-id="{{ $product['id'] }}">
            <div class="card-body">
                <div class="image mb-2">
                    <a href="{{ route('product-detail', $product['slug']) }}">
                        <img src="{{ $product['image_url'] }}" alt="{{ $product['title'] }}" loading="lazy" class="img-fluid">
                    </a>
                </div>
                <div class="content">
                    <h5 class="product-title">
                        <a href="{{ route('product-detail', $product['slug']) }}">
                            {{ Str::limit($product['title'], 40) }}
                        </a>
                    </h5>
                    <p class="price">
                        @if($product['discount'] > 0)
                        <del class="text-muted">${{ number_format($product['price'], 2) }}</del>
                        @endif
                        <span class="current-price">${{ number_format($product['discounted_price'], 2) }}</span>
                    </p>
                    <div class="product-actions mt-2 d-flex">
                        <a href="{{ route('add-to-cart', $product['slug']) }}"
                            class="text-dark mr-2 {{ $product['stock'] <= 0 ? 'disabled' : '' }}"
                            title="{{ $product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart' }}">
                            <i class="ti-shopping-cart"></i>
                        </a>
                        <a href="#"
                            class="text-secondary"
                            title="Quick View"
                            onclick="event.preventDefault(); $('#productModal{{ $product['id'] }}').modal('show');">
                            <i class="ti-eye"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
        @else
        <div class="no-recent-products">
            <div class="empty-state">
                <i class="fa fa-clock-o"></i>
                <p>No recently viewed products</p>
                <small>Products you view will appear here</small>
            </div>
        </div>
        @endif
    </div>

    @if(isset($recent_products) && count($recent_products) >= 5)
    <div class="view-all-recent">
        <a href="{{ route('recent-products') }}" class="view-all-btn">
            View All Recent Products <i class="fa fa-arrow-right"></i>
        </a>
    </div>
    @endif
</div>

<!-- Brands Widget -->
@if(!isset($category) || $category->filters->where('name', 'brand')->count())
<div class="single-widget category">
    <h3 class="title">Brands</h3>
    @php
        $brands = isset($category) 
            ? $category->brands->where('status', 'active')->sortBy('title') 
            : App\Models\Brand::where('status', 'active')->orderBy('title')->get();
    @endphp
    @if($brands->count() > 0)
        <ul class="categor-list">
            @foreach($brands as $brand)
                <li>
                    <label>
                        @php
                            $selectedBrands = is_string(request('brand')) ? explode(',', request('brand')) : (request('brand', []) ?: []);
                        @endphp
                        <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" {{ in_array($brand->slug, $selectedBrands) ? 'checked' : '' }}>
                        {{ $brand->title }}
                    </label>
                </li>
            @endforeach
        </ul>
    @else
        <div class="no-brands">
            <div class="empty-state text-center p-3">
                <i class="fa fa-tags mb-2" style="font-size: 20px;"></i>
                <p>No brands available</p>
                <small>Active brands will appear here</small>
            </div>
        </div>
    @endif
</div>
@endif

<!-- Customer Ratings Widget -->
@if(!isset($category) || $category->filters->where('name', 'rating')->count())
<div class="single-widget rating">
    <h3 class="title">Customer Ratings</h3>
    <ul class="categor-list">
        @foreach([4, 3, 2, 1] as $rating)
        <li>
            <label>
                @php
                    $minRatings = is_string(request('min_rating')) ? explode(',', request('min_rating')) : (request('min_rating', []) ?: []);
                @endphp
                <input type="checkbox" name="min_rating[]" value="{{ $rating }}" {{ in_array((string)$rating, $minRatings) ? 'checked' : '' }}>
                {{ $rating }} ★ & above
            </label>
        </li>
        @endforeach
    </ul>
</div>
@endif

<!-- Discounts Widget -->
@if(!isset($category) || $category->filters->where('name', 'discount')->count())
<div class="single-widget discount">
    <h3 class="title">Discounts</h3>
    <ul class="categor-list">
        @foreach([50, 30, 20, 10, 5] as $discount)
        <li>
            <label>
                @php
                    $minDiscounts = is_string(request('min_discount')) ? explode(',', request('min_discount')) : (request('min_discount', []) ?: []);
                @endphp
                <input type="checkbox" name="min_discount[]" value="{{ $discount }}" {{ in_array((string)$discount, $minDiscounts) ? 'checked' : '' }}>
                {{ $discount }}% & above
            </label>
        </li>
        @endforeach
    </ul>
</div>
@endif

@push('styles')
<style>
    
</style>
@endpush

@push('scripts')
<script>
let timeoutId;

function applyFilters(page = 1) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
        const form = document.querySelector('form[action="{{ route('shop.filter') }}"]');
        if (!form) {
            console.error('Form not found');
            return;
        }

        const formData = new FormData(form);
        const data = {
            show: formData.get('show') || 12,
            sortBy: formData.get('sortBy') || '',
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
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error(`Server returned ${response.status}: ${text}`);
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Success:', data);
                const productsContent = document.querySelector('#products-content');
                if (productsContent) {
                    productsContent.innerHTML = data.html;
                }
            } else {
                console.error('Filter error:', data.message);
            }
        })
        .catch(error => console.error('Error applying filters:', error));
    }, 300);
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="{{ route('shop.filter') }}"]');
    if (form) {
        form.addEventListener('change', function(e) {
            if (e.target.matches('input[type="checkbox"]')) {
                applyFilters();
            }
        });
    }

    // Handle pagination clicks via AJAX
    document.addEventListener('click', function(e) {
        const link = e.target.closest('.pagination a');
        if (link) {
            e.preventDefault();
            const url = new URL(link.href);
            const page = url.searchParams.get('page') || 1;
            applyFilters(page);
        }
    });
});
</script>

<script>
    $(document).ready(function() {
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
                }
            });

            $("#amount").val(currency + $("#slider-range").slider("values", 0) +
                " - " + currency + $("#slider-range").slider("values", 1));
        }
    });
</script>
@endpush