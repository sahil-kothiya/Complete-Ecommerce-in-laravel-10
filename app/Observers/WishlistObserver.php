<?php

namespace App\Observers;

use App\Models\Wishlist;
use App\Services\RedisCacheService;

class WishlistObserver
{
    public function saved(Wishlist $wishlist): void
    {
        $this->clearWishlistCache($wishlist->user_id);
    }

    public function deleted(Wishlist $wishlist): void
    {
        $this->clearWishlistCache($wishlist->user_id);
    }

    public function restored(Wishlist $wishlist): void
    {
        $this->clearWishlistCache($wishlist->user_id);
    }

    public function forceDeleted(Wishlist $wishlist): void
    {
        $this->clearWishlistCache($wishlist->user_id);
    }

    protected function clearWishlistCache(int|string $userId): void
    {
        RedisCacheService::forget(RedisCacheService::makeKey('wishlist', 'user', $userId));
    }
}
