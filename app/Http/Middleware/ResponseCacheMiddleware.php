<?php

namespace App\Http\Middleware;

use App\Services\ResponseCacheService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResponseCacheMiddleware
{
    private $cacheService;

    public function __construct(ResponseCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Skip caching for certain conditions
        if ($this->shouldSkipCache($request)) {
            return $next($request);
        }

        $cacheKey = $this->cacheService->generateCacheKey($request);
        
        // Try to get cached response
        $cachedData = $this->cacheService->getCachedResponse($cacheKey);
        
        if ($cachedData) {
            return $this->createResponseFromCache($cachedData, $request);
        }

        // Process the request
        $response = $next($request);

        // Cache the response if it's cacheable
        if ($this->shouldCache($response)) {
            $this->cacheService->cacheResponse(
                $cacheKey,
                $response->getContent(),
                $this->getHeadersToCache($response)
            );
        }

        return $this->addCompressionHeaders($response, $request);
    }

    private function shouldSkipCache(Request $request): bool
    {
        return $request->isMethod('POST') || 
               $request->isMethod('PUT') || 
               $request->isMethod('DELETE') ||
               $request->hasSession() && $request->session()->has('errors');
    }

    private function shouldCache(Response $response): bool
    {
        return $response->getStatusCode() === 200 && 
               strlen($response->getContent()) > 1024;
    }

    private function createResponseFromCache(array $cachedData, Request $request): Response
    {
        $content = $this->cacheService->getDecompressedContent($cachedData);
        
        $response = new Response($content, 200, $cachedData['headers']);
        
        return $this->addCompressionHeaders($response, $request);
    }

    private function addCompressionHeaders(Response $response, Request $request): Response
    {
        $acceptEncoding = $request->header('Accept-Encoding', '');
        
        if (strpos($acceptEncoding, 'gzip') !== false && $this->isCompressible($response)) {
            $content = $response->getContent();
            
            if (strlen($content) > 1024) {
                $compressed = gzencode($content, 6);
                
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Content-Length', strlen($compressed));
                    $response->headers->set('Vary', 'Accept-Encoding');
                }
            }
        }

        // Add cache control headers
        $response->headers->set('Cache-Control', 'public, max-age=3600');
        $response->headers->set('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 3600));

        return $response;
    }

    private function isCompressible(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        
        $compressibleTypes = [
            'text/html', 'text/css', 'text/javascript',
            'application/javascript', 'application/json',
            'application/xml', 'text/xml', 'text/plain'
        ];

        foreach ($compressibleTypes as $type) {
            if (strpos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getHeadersToCache(Response $response): array
    {
        return [
            'Content-Type' => $response->headers->get('Content-Type'),
            'Cache-Control' => 'public, max-age=3600',
        ];
    }
}