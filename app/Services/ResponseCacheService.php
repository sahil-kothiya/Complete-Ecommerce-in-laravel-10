<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ResponseCacheService
{
    private $cachePrefix = 'response_cache:';
    private $ttl = 3600; // 1 hour

    public function getCachedResponse(string $key): ?array
    {
        $cacheKey = $this->cachePrefix . md5($key);
        
        try {
            $cached = Redis::get($cacheKey);
            return $cached ? json_decode($cached, true) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function cacheResponse(string $key, string $content, array $headers = []): void
    {
        $cacheKey = $this->cachePrefix . md5($key);
        
        // Compress the content
        $compressed = gzencode($content, 6);
        
        $cacheData = [
            'content' => base64_encode($compressed),
            'headers' => $headers,
            'compressed' => true,
            'created_at' => now()->timestamp
        ];

        try {
            Redis::setex($cacheKey, $this->ttl, json_encode($cacheData));
        } catch (\Exception $e) {
            // Log the error or handle it as needed
        }
    }

    public function getDecompressedContent(array $cachedData): string
    {
        if (!$cachedData['compressed']) {
            return $cachedData['content'];
        }

        $compressed = base64_decode($cachedData['content']);
        return gzdecode($compressed);
    }

    public function generateCacheKey(\Illuminate\Http\Request $request): string
    {
        return $request->fullUrl() . '|' . $request->header('Accept-Encoding', '');
    }
}