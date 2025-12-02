<!-- Active Filters Display -->
<div id="active-filters-section" class="single-widget active-filters">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h3 class="title mb-0">Filters</h3>
        <button id="clear-all-filters" class="btn btn-sm btn-outline-secondary clear-all-btn" style="display: none;">
            <i class="fa fa-times-circle mr-1"></i> Clear All
        </button>
    </div>
    <div id="active-filters-list" class="active-filters-container">
        <!-- Active filters will be dynamically populated here -->
    </div>
</div>

<!-- Price Filter Widget -->
@if (!isset($mainCategory) || $mainCategory->filters->where('name', 'price')->count())
    <div class="single-widget range">
        <h3 class="title">Shop by Price</h3>
        <div class="price-filter">
            @php
                $priceRanges = [
                    '0-100' => 'Under $100',
                    '100-500' => '$100 - $500',
                    '500-1000' => '$500 - $1,000',
                ];
                $selectedRange = request('price_range', '');
                $selectedMin = '0';
                $selectedMax = '1000';

                if ($selectedRange && str_contains($selectedRange, '-')) {
                    [$rangeMin, $rangeMax] = explode('-', $selectedRange);
                    $selectedMin = (string) $rangeMin;
                    $selectedMax = (string) str_replace('+', '', $rangeMax);
                }

                $effectiveRange = $selectedRange ?: $selectedMin . '-' . $selectedMax;
            @endphp
            <div class="row">
                <div class="col-6">
                    <label for="price_min">Min</label>
                    <select name="price_min" id="price_min" class="form-control filter-select" data-filter-type="price">
                        <option value="">Min</option>
                        <option value="0" {{ $selectedMin === '0' ? 'selected' : '' }}>$0</option>
                        <option value="100" {{ $selectedMin === '100' ? 'selected' : '' }}>$100</option>
                        <option value="500" {{ $selectedMin === '500' ? 'selected' : '' }}>$500</option>
                    </select>
                </div>
                <div class="col-6">
                    <label for="price_max">Max</label>
                    <select name="price_max" id="price_max" class="form-control filter-select" data-filter-type="price">
                        <option value="">Max</option>
                        <option value="100" {{ $selectedMax === '100' ? 'selected' : '' }}>$100</option>
                        <option value="500" {{ $selectedMax === '500' ? 'selected' : '' }}>$500</option>
                        <option value="1000" {{ $selectedMax === '1000' ? 'selected' : '' }}>$1000+</option>
                    </select>
                </div>
            </div>
            <input type="hidden" name="price_range" id="price_range" value="{{ $effectiveRange }}">
        </div>
    </div>
@endif

@if (!isset($mainCategory) || $mainCategory->filters->where('name', 'brand')->count())
    <div class="single-widget mainCategory">
        <h3 class="title">Brands</h3>
        @php
            $brands = isset($mainCategory)
                ? $mainCategory->brands->where('status', 'active')->sortBy('title')
                : App\Models\Brand::where('status', 'active')->orderBy('title')->get();
        @endphp
        @if ($brands->count() > 0)
            <ul class="categor-list">
                @foreach ($brands as $index => $brand)
                    <li>
                        <label>
                            @php
                                $selectedBrands = is_array(request('brand'))
                                    ? request('brand')
                                    : (request('brand')
                                        ? explode(',', request('brand'))
                                        : []);
                            @endphp
                            <input type="checkbox" name="brand[]" value="{{ $brand->slug }}" data-filter-type="brand"
                                data-filter-label="{{ $brand->title }}"
                                {{ in_array($brand->slug, $selectedBrands) ? 'checked' : '' }}
                                tabindex="{{ 13 + $index }}" class="filter-checkbox">
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
@if (!isset($mainCategory) || $mainCategory->filters->where('name', 'rating')->count())
    <div class="single-widget rating">
        <h3 class="title">Customer Ratings</h3>
        <ul class="categor-list">
            @foreach ([4, 3, 2, 1] as $index => $rating)
                <li>
                    <label>
                        @php
                            $minRatings = is_array(request('min_rating'))
                                ? request('min_rating')
                                : (request('min_rating')
                                    ? explode(',', request('min_rating'))
                                    : []);
                        @endphp
                        <input type="checkbox" name="min_rating[]" value="{{ $rating }}"
                            data-filter-type="rating" data-filter-label="{{ $rating }} ★ & above"
                            {{ in_array((string) $rating, $minRatings) ? 'checked' : '' }}
                            tabindex="{{ 20 + $index }}" class="filter-checkbox">
                        {{ $rating }} ★ & above
                    </label>
                </li>
            @endforeach
        </ul>
    </div>
@endif
<!-- Discounts Widget -->
@if (!isset($mainCategory) || $mainCategory->filters->where('name', 'discount')->count())
    <div class="single-widget discount">
        <h3 class="title">Discounts</h3>
        <ul class="categor-list">
            @foreach ([50, 30, 20, 10, 5] as $index => $discount)
                <li>
                    <label>
                        @php
                            $minDiscounts = is_array(request('min_discount'))
                                ? request('min_discount')
                                : (request('min_discount')
                                    ? explode(',', request('min_discount'))
                                    : []);
                        @endphp
                        <input type="checkbox" name="min_discount[]" value="{{ $discount }}"
                            data-filter-type="discount" data-filter-label="{{ $discount }}% & above"
                            {{ in_array((string) $discount, $minDiscounts) ? 'checked' : '' }}
                            tabindex="{{ 24 + $index }}" class="filter-checkbox">
                        {{ $discount }}% & above
                    </label>
                </li>
            @endforeach
        </ul>
    </div>
@endif

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/shop-system.css') }}">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="{{ asset('js/shop-system.js') }}"></script>
@endpush
