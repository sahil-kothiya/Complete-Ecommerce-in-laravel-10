<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\RedisCacheService;

class PostObserver
{
    public function saved(Post $post): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('page', 'home'));
        RedisCacheService::put(RedisCacheService::makeKey('post', $post->id), $post->toArray());
    }

    public function deleted(Post $post): void
    {
        $this->clearPostCache($post->id);
    }

    public function forceDeleted(Post $post): void
    {
        $this->clearPostCache($post->id);
    }

    public function restored(Post $post): void
    {
        RedisCacheService::put(RedisCacheService::makeKey('post', $post->id), $post->toArray());
    }

    protected function clearPostCache(int|string $id): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('post', $id));
    }
}
