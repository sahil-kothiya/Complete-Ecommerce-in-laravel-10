<?php

namespace App\Observers;

use App\Models\Banner;
use App\Services\RedisCacheService;

class BannerObserver
{
    public function saved(Banner $banner): void
    {
        RedisCacheService::put(RedisCacheService::makeKey('banner', $banner->id), $banner->toArray());
        RedisCacheService::forget(RedisCacheService::makeKey('page', 'home'));
        $this->clearBannerCache();
    }

    public function deleted(Banner $banner): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('banner', $banner->id));
        $this->clearBannerCache();
    }

    public function forceDeleted(Banner $banner): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('banner', $banner->id));
        $this->clearBannerCache();
    }

    public function restored(Banner $banner): void
    {
        RedisCacheService::put(RedisCacheService::makeKey('banner', $banner->id), $banner->toArray());
        $this->clearBannerCache();
    }

    protected function clearBannerCache(): void
    {
        foreach (['banner', 'global', 'banners'] as $prefix) {
            RedisCacheService::forgetPattern("{$prefix}:*");
        }
    }
}
