<!-- Updated product-grid-html.blade.php for better grid structure -->
<!-- Products Grid -->
<div class="product-grid-container">
    <div class="product-grid-row">
        @forelse($products as $product)
        @include('frontend.partials.product-card-grid', ['product' => $product])
        @empty
        <div class="col-12">
            <div class="no-products-message">
                <i class="fa fa-search"></i>
                <h4>No products found.</h4>
                <p>Try adjusting your filters or search terms.</p>
                <a href="{{ route('home') }}" class="btn btn-primary">Browse All Products</a>
            </div>
        </div>
        @endforelse
    </div>
</div>

<!-- Pagination -->
@if($products->hasPages())
<div class="row mt-4">
    <div class="col-12 d-flex justify-content-center">
        <div class="pagination-wrapper">
            {{ $products->appends(request()->query())->links('vendor.pagination.bootstrap-4') }}
        </div>
    </div>
</div>
@endif
