<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use App\Models\Category;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any view-related services.
     */
    public function boot(): void
    {
        $ttl = Config::get('cache_keys.ttl');
        $prefix = Config::get('cache_keys.home_prefix');

        View::composer(['frontend.layouts.header', 'frontend.layouts.footer'], function ($view) use ($ttl, $prefix) {
            $cacheKey = $prefix . 'header_categories';

            $categories = Cache::tags(['global', 'categories'])->remember(
                $cacheKey,
                $ttl['header_categories'],
                function () {
                    return Category::select('id', 'title', 'slug', 'parent_id')
                        ->active()
                        ->where('is_parent', 1)
                        ->with([
                            'children' => fn($query) =>
                            $query->active()->select('id', 'title', 'slug', 'parent_id')
                        ])
                        ->orderBy('title')
                        ->get();
                }
            );

            $view->with('categories', $categories);
            $view->with('categoryBanners', $categories->filter(fn($cat) => $cat->is_parent == 1 && !empty($cat->photo)));
        });
    }
}
