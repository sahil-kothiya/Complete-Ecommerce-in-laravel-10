<!-- Products Grid -->
<div class="product-grid-container">
    <div class="product-grid-row">
        @foreach($products as $product)
        @include('frontend.partials.product-card-grid', ['product' => $product])
        @endforeach
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