<?php

namespace App\Observers;

use App\Models\Banner;
use App\Services\RedisCacheManager;

class BannerObserver
{
    public function saved(Banner $banner): void
    {
        RedisCacheManager::put('banner', $banner->id, $banner->toArray());
        RedisCacheManager::forget('page', 'home');
        $this->clearBannerCache();
    }

    public function deleted(Banner $banner): void
    {
        RedisCacheManager::forget('banner', $banner->id);
        $this->clearBannerCache();
    }

    public function forceDeleted(Banner $banner): void
    {
        RedisCacheManager::forget('banner', $banner->id);
        $this->clearBannerCache();
    }

    public function restored(Banner $banner): void
    {
        RedisCacheManager::put('banner', $banner->id, $banner->toArray());
        $this->clearBannerCache();
    }

    protected function clearBannerCache(): void
    {
        RedisCacheManager::flushByPrefix(['banner', 'global', 'banners']);
    }
}
