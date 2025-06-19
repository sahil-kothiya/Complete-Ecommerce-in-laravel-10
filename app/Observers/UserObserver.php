<?php

namespace App\Observers;

use App\User;
use App\Services\RedisCacheManager;

class UserObserver
{
    public function updated(User $user): void
    {
        $this->clearUserProfileCache($user->id);
    }

    public function deleted(User $user): void
    {
        $this->clearUserProfileCache($user->id);
    }

    public function forceDeleted(User $user): void
    {
        $this->clearUserProfileCache($user->id);
    }

    public function restored(User $user): void
    {
        $this->clearUserProfileCache($user->id);
    }

    protected function clearUserProfileCache(int|string $userId): void
    {
        RedisCacheManager::forget('user:profile', $userId);
    }
}
