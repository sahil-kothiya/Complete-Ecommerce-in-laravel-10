<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
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
            $cached = Redis::get($cacheKey);

            if ($cached) {
                $categories = unserialize($cached);
            } else {
                $categories = Category::select('id', 'title', 'slug', 'parent_id', 'photo')
                    ->active()
                    ->where('is_parent', 1)
                    ->with([
                        'children' => fn($query) =>
                        $query->active()->select('id', 'title', 'slug', 'parent_id')
                    ])
                    ->orderBy('title')
                    ->get();
                Redis::set($cacheKey, serialize($categories), 'EX', $ttl['header_categories']);
            }

            $view->with('categories', $categories);
            $view->with('categoryBanners', $categories->filter(fn($cat) => $cat->is_parent == 1 && !empty($cat->photo)));
        });
    }
}
