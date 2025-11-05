@extends('frontend.layouts.master')
@section('title', 'Order Success')

@section('main-content')
<div class="container py-5 text-center">
    <div class="card mx-auto" style="max-width: 500px;">
        <div class="card-body">
            <h1 class="text-success mb-3">Order Placed!</h1>
            <p><strong>Order #:</strong> {{ $order->order_number }}</p>
            <p><strong>Total:</strong> {{ $order->getFormattedTotalAttribute() }}</p>
            <p><strong>Shipping:</strong> {{ $order->shipping?->type ?? 'Free' }} - ${{ number_format($order->shipping_cost, 2) }}</p>
            <a href="{{ route('home') }}" class="btn btn-primary mt-3">Continue Shopping</a>
        </div>
    </div>
</div>
@endsection