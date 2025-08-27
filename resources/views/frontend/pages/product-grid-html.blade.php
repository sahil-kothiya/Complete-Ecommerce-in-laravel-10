 <!-- Products Grid -->
<div class="row product-grid-container">

    @forelse($products as $product)
    @include('frontend.partials.product-card', ['product' => $product])
    @include('frontend.partials.product-modal', ['product' => $product])
    @empty
    <div class="col-12">
        <h4 class="text-warning text-center py-5">No products found.</h4>
    </div>
    @endforelse
</div>