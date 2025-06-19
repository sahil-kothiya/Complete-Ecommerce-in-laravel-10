<?php

namespace App\Observers;

use App\Models\Post;
use App\Services\RedisCacheManager;

class PostObserver
{
    public function saved(Post $post): void
    {
        RedisCacheManager::forget('page', 'home');
        RedisCacheManager::put('post', $post->id, $post->toArray());
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
        RedisCacheManager::put('post', $post->id, $post->toArray());
    }

    protected function clearPostCache(int|string $id): void
    {
        RedisCacheManager::forget('post', $id);
    }
}
