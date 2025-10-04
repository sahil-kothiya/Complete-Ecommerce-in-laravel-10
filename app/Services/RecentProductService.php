<?php

namespace App\Services;

use App\Models\RecentProduct;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RecentProductService
{
    /**
     * Track product view
     */
    public function trackProductView($productId)
    {
        // Don't track if product doesn't exist or is inactive
        $product = Product::where('id', $productId)
            ->where('status', 'active')
            ->first();

        if (!$product) {
            return false;
        }

        // Add to database
        RecentProduct::addProduct($productId);

        return true;
    }

    /**
     * Get recent products
     */
    public function getRecentProducts($limit = 10)
    {
        return RecentProduct::getRecentProducts($limit);
    }

    /**
     * Get recent products for sidebar widget
     */
    public function getRecentProductsForWidget($limit = 5)
    {
        $products = $this->getRecentProducts($limit);
        
        return $products->map(function ($product) {
            return [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => $product->price,
                'discount' => $product->discount,
                'images' => $product->images,
                'stock' => $product->stock,
                'discounted_price' => $product->price - ($product->price * $product->discount / 100),
                'image_url' => $product->images->first() ? 
                    asset($product->images->first()->image_path) : 
                    asset('frontend/img/default-product.png')
            ];
        });
    }

    /**
     * Handle user login - merge session data
     */
    public function handleUserLogin($userId)
    {
        $sessionId = Session::getId();
        RecentProduct::mergeSessionToUser($userId, $sessionId);
    }

    /**
     * Clear recent products
     */
    public function clearRecentProducts()
    {
        $userId = Auth::id();
        $sessionId = Session::getId();

        RecentProduct::where(function ($query) use ($userId, $sessionId) {
            if ($userId) {
                $query->where('user_id', $userId);
            } else {
                $query->where('session_id', $sessionId);
            }
        })->delete();
    }
}