<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GzipMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Check if the client accepts gzip encoding
        $acceptEncoding = $request->header('Accept-Encoding', '');
        
        if (strpos($acceptEncoding, 'gzip') !== false) {
            // Get the response content
            $content = $response->getContent();
            
            // Only compress if content is larger than 1KB and is compressible
            if (strlen($content) > 1024 && $this->isCompressible($response)) {
                // Compress the content
                $compressed = gzencode($content, 6); // Compression level 6 (balance between speed and size)
                
                if ($compressed !== false) {
                    $response->setContent($compressed);
                    $response->headers->set('Content-Encoding', 'gzip');
                    $response->headers->set('Content-Length', strlen($compressed));
                    $response->headers->set('Vary', 'Accept-Encoding');
                }
            }
        }

        return $response;
    }

    private function isCompressible($response)
    {
        $contentType = $response->headers->get('Content-Type', '');
        
        $compressibleTypes = [
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'application/xml',
            'text/xml',
            'text/plain',
        ];

        foreach ($compressibleTypes as $type) {
            if (strpos($contentType, $type) !== false) {
                return true;
            }
        }

        return false;
    }
}