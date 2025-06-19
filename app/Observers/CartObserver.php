<?php

namespace App\Observers;

use App\Models\Cart;
use App\Services\RedisCacheManager;

class CartObserver
{
    public function saved(Cart $cart): void
    {
        $this->clearUserCartCache($cart->user_id);
    }

    public function deleted(Cart $cart): void
    {
        $this->clearUserCartCache($cart->user_id);
    }

    protected function clearUserCartCache(int|string $userId): void
    {
        RedisCacheManager::forget('cart:user', $userId);
    }
}
