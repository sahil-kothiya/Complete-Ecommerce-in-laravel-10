<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\RedisCacheManager;

class CategoryObserver
{
    public function saved(Category $category): void
    {
        RedisCacheManager::forget('page', 'home');
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
        RedisCacheManager::flushByPrefix(['category', 'categories', 'global']);
    }
}
