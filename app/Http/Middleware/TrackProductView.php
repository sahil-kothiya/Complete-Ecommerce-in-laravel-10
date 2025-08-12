<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\RecentProductService;

class TrackProductView
{
    protected $recentProductService;

    public function __construct(RecentProductService $recentProductService)
    {
        $this->recentProductService = $recentProductService;
    }

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Track product view if this is a product detail page
        if ($request->route() && $request->route()->getName() === 'product-detail') {
            $slug = $request->route('slug');
            
            // Get product ID from slug
            $product = \App\Models\Product::where('slug', $slug)
                ->where('status', 'active')
                ->first();

            if ($product) {
                $this->recentProductService->trackProductView($product->id);
            }
        }

        return $response;
    }
}