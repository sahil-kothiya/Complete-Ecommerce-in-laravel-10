<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\RedisCacheManager;

class ProductObserver
{
    public function saved(Product $product): void
    {
        RedisCacheManager::forget('page', 'home');
        RedisCacheManager::put('product', $product->id, $product->toArray());
    }

    public function deleted(Product $product): void
    {
        $this->clearProductCache($product->id);
    }

    public function forceDeleted(Product $product): void
    {
        $this->clearProductCache($product->id);
    }

    public function restored(Product $product): void
    {
        RedisCacheManager::put('product', $product->id, $product->toArray());
    }

    protected function clearProductCache(int|string $id): void
    {
        RedisCacheManager::forget('product', $id);
    }
}
