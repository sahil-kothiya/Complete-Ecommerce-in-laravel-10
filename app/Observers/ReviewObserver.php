<?php

namespace App\Observers;

use App\Models\ProductReview;
use App\Services\RedisCacheManager;

class ReviewObserver
{
    public function saved(ProductReview $review): void
    {
        $this->clearRatingCache($review->product_id);
    }

    public function deleted(ProductReview $review): void
    {
        $this->clearRatingCache($review->product_id);
    }

    public function forceDeleted(ProductReview $review): void
    {
        $this->clearRatingCache($review->product_id);
    }

    public function restored(ProductReview $review): void
    {
        $this->clearRatingCache($review->product_id);
    }

    protected function clearRatingCache(int|string $productId): void
    {
        RedisCacheManager::forget('product:ratings', $productId);
    }
}
