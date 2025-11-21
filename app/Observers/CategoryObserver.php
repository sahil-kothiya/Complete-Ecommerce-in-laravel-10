<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\RedisCacheService;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('page', 'home'));
        $this->clearCategoryCache();
    }

    public function deleted(Category $category): void
    {
        $this->clearCategoryCache();
    }

    public function forceDeleted(Category $category): void
    {
        $this->clearCategoryCache();
    }

    public function restored(Category $category): void
    {
        $this->clearCategoryCache();
    }

    protected function clearCategoryCache(): void
    {
        foreach (['category', 'categories', 'global'] as $prefix) {
            RedisCacheService::forgetPattern("{$prefix}:*");
        }
    }
}
