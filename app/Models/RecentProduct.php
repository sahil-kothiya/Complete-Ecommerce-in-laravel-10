<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class RecentProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'product_id',
        'viewed_at'
    ];

    protected $dates = ['viewed_at'];

    // Relationships
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add product to recent products
     */
    public static function addProduct($productId)
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        // Remove existing entry to avoid duplicates
        self::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->where('product_id', $productId)->delete();

        // Add new entry
        self::create([
            'user_id' => $userId,
            'session_id' => $userId ? null : $sessionId,
            'product_id' => $productId,
            'viewed_at' => now()
        ]);

        // Keep only last 20 recent products per user/session
        self::cleanupOldEntries($userId, $sessionId);
    }

    /**
     * Get recently viewed products
     *
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public static function getRecentProducts($limit = 10)
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        $query = self::with(['product.images' => function ($query) {
                $query->select(['id', 'image_path', 'product_id', 'is_primary'])
                    ->orderBy('is_primary', 'desc')
                    ->orderBy('sort_order', 'asc');
            }])
            ->where(function ($query) use ($userId, $sessionId) {
                if ($userId) {
                    $query->where('user_id', $userId);
                } else {
                    $query->where('session_id', $sessionId);
                }
            })
            ->whereHas('product', function ($query) {
                $query->where('status', 'active');
            })
            ->orderBy('viewed_at', 'desc')
            ->limit($limit)
            ->get();

        $products = $query->pluck('product');

        if (config('app.debug')) {
            Log::debug('getRecentProducts: Fetched recent products', [
                'user_id' => $userId,
                'session_id' => $sessionId,
                'limit' => $limit,
                'product_ids' => $products->pluck('id')->toArray(),
                'count' => $products->count()
            ]);
        }

        return $products;
    }

    /**
     * Clean up old entries to keep only recent ones
     */
    private static function cleanupOldEntries($userId, $sessionId, $keepCount = 20)
    {
        $query = self::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->orderBy('viewed_at', 'desc');

        $totalCount = $query->count();

        if ($totalCount > $keepCount) {
            $idsToDelete = $query->skip($keepCount)->pluck('id');
            self::whereIn('id', $idsToDelete)->delete();
        }
    }

    /**
     * Merge session data with user data when user logs in
     */
    public static function mergeSessionToUser($userId, $sessionId)
    {
        $sessionProducts = self::where('session_id', $sessionId)->get();

        foreach ($sessionProducts as $sessionProduct) {
            // Check if user already has this product in recent
            $existingUserProduct = self::where('user_id', $userId)
                ->where('product_id', $sessionProduct->product_id)
                ->first();

            if (!$existingUserProduct) {
                // Create new entry for user
                self::create([
                    'user_id' => $userId,
                    'product_id' => $sessionProduct->product_id,
                    'viewed_at' => $sessionProduct->viewed_at
                ]);
            } else {
                // Update viewed_at to the latest
                $existingUserProduct->update([
                    'viewed_at' => max($existingUserProduct->viewed_at, $sessionProduct->viewed_at)
                ]);
            }
        }

        // Delete session entries
        self::where('session_id', $sessionId)->delete();
    }
}