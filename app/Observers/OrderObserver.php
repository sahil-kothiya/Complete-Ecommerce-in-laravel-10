<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\RedisCacheService;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->clearOrderCache();
    }

    protected function clearOrderCache(): void
    {
        foreach (['order', 'orders', 'admin'] as $prefix) {
            RedisCacheService::forgetPattern("{$prefix}:*");
        }
    }
}
