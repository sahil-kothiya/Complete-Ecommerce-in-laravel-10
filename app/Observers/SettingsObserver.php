<?php

namespace App\Observers;

use App\Models\Settings;
use App\Services\RedisCacheService;

class SettingsObserver
{
    public function saved(Settings $settings): void
    {
        $this->clearSettingsCache();
    }

    public function deleted(Settings $settings): void
    {
        $this->clearSettingsCache();
    }

    protected function clearSettingsCache(): void
    {
        foreach (['settings', 'homepage'] as $prefix) {
            RedisCacheService::forgetPattern("{$prefix}:*");
        }
    }
}
