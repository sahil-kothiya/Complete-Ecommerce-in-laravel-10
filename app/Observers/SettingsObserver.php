<?php

namespace App\Observers;

use App\Models\Settings;
use App\Services\RedisCacheManager;

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
        RedisCacheManager::flushByPrefix(['settings', 'homepage']);
    }
}
