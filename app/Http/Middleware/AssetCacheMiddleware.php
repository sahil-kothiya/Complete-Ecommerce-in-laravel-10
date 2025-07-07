<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Response;

class AssetCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->is('css/*') && !$request->is('js/*')) {
            return $next($request);
        }

        $cacheKey = 'asset:' . md5($request->getPathInfo());
        
        // Check Redis cache
        if (Redis::exists($cacheKey)) {
            $cachedData = json_decode(Redis::get($cacheKey), true);
            
            return Response::make($cachedData['content'], 200, [
                'Content-Type' => $cachedData['mime_type'],
                'Cache-Control' => 'public, max-age=31536000',
                'Expires' => gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000),
                'Content-Encoding' => 'gzip'
            ]);
        }

        $response = $next($request);
        
        // Cache the response in Redis
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();
            $compressedContent = gzencode($content, 9);
            
            Redis::setex($cacheKey, 3600, json_encode([
                'content' => $compressedContent,
                'mime_type' => $response->headers->get('Content-Type')
            ]));
        }

        return $response;
    }
}