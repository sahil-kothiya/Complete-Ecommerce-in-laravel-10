<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\RedisCacheManager;

class OrderObserver
{
    public function created(Order $order): void
    {
        $this->clearOrderCache();
    }

    protected function clearOrderCache(): void
    {
        RedisCacheManager::flushByPrefix(['order', 'orders', 'admin']);
    }
}
