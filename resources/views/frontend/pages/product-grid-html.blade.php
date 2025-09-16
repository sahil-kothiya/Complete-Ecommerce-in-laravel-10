<!-- Products Grid -->
<div class="product-grid-container">
    <div class="product-grid-row">
        @forelse($products as $product)
        @include('frontend.partials.product-card-grid', ['product' => $product])
        @empty
            <div class="col-12 text-center py-5">
                <i class="fa fa-star fa-3x text-warning mb-3"></i> <!-- Rating-themed icon -->
                <h4>No products match your rating filter.</h4>
                <p>Try a broader rating range or clear filters.</p>
                <button type="button" class="btn btn-outline-primary" onclick="clearFilter('rating')">Clear Rating Filter</button>
            </div>
        @endforelse
    </div>
</div>

<!-- Always render pagination wrapper -->
<div class="row mt-4">
    <div class="col-12 d-flex justify-content-center">
        <div class="pagination-wrapper">
            @if($products->hasPages())
                {{ $products->appends(request()->query())->links('vendor.pagination.bootstrap-4') }}
            @else
                <p class="text-muted text-center">End of results.</p>
            @endif
        </div>
    </div>
</div>