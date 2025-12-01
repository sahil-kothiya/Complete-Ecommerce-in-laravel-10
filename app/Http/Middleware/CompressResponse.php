<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP Response Compression Middleware
 * Compresses responses using gzip to reduce transfer size and improve load times
 * Target: Reduce HTML/JSON/CSS/JS by 70-90%
 */
class CompressResponse
{
    /**
     * Handle an incoming request and compress the response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only compress if:
        // 1. Client accepts gzip encoding
        // 2. Response is compressible
        // 3. Not already compressed
        // 4. Response size is worth compressing (> 1KB)
        if (!$this->shouldCompress($request, $response)) {
            return $response;
        }

        $content = $response->getContent();
        
        if (empty($content)) {
            return $response;
        }

        // Compress using gzip level 6 (balance between speed and compression)
        $compressed = gzencode($content, 6);

        if ($compressed === false) {
            return $response;
        }

        // Only use compressed version if it's actually smaller
        if (strlen($compressed) < strlen($content)) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', strlen($compressed));
            $response->headers->remove('Transfer-Encoding');
        }

        return $response;
    }

    /**
     * Determine if the response should be compressed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldCompress(Request $request, Response $response): bool
    {
        // Check if client accepts gzip
        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (stripos($acceptEncoding, 'gzip') === false) {
            return false;
        }

        // Check if already compressed
        if ($response->headers->has('Content-Encoding')) {
            return false;
        }

        // Check content type is compressible
        $contentType = $response->headers->get('Content-Type', '');
        if (!$this->isCompressibleContentType($contentType)) {
            return false;
        }

        // Check response size (only compress if > 1KB)
        $content = $response->getContent();
        if (strlen($content) < 1024) {
            return false;
        }

        // Check status code (only compress successful responses)
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return false;
        }

        return true;
    }

    /**
     * Check if content type is compressible.
     *
     * @param  string  $contentType
     * @return bool
     */
    protected function isCompressibleContentType(string $contentType): bool
    {
        $compressibleTypes = [
            'text/',
            'application/json',
            'application/javascript',
            'application/xml',
            'application/xhtml+xml',
            'application/rss+xml',
            'application/atom+xml',
            'image/svg+xml',
        ];

        foreach ($compressibleTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }
}
