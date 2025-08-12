<?php

namespace App\Providers;

use App\Services\RecentProductService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Share recent products with sidebar
        View::composer('frontend.partials.shop-sidebar', function ($view) {
            $recentProductService = app(RecentProductService::class);
            $recentProducts = $recentProductService->getRecentProductsForWidget(5);
            
            $view->with('recent_products', $recentProducts);
        });
    }
}
