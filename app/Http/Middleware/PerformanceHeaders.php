<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PerformanceHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Get the content type
        $contentType = $response->headers->get('Content-Type');

        // Cache-Control headers for static assets
        if ($this->isStaticAsset($request)) {
            // 1 year for immutable assets (with versioning)
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
            
            // Add ETag for validation
            if ($request->getMethod() === 'GET') {
                $etag = md5($response->getContent());
                $response->headers->set('ETag', $etag);
                
                // Check if client has valid cache
                $ifNoneMatch = $request->header('If-None-Match');
                if ($ifNoneMatch === $etag) {
                    return response('', 304)->withHeaders($response->headers->all());
                }
            }
        } elseif ($this->isImage($request)) {
            // 30 days for images
            $response->headers->set('Cache-Control', 'public, max-age=2592000, stale-while-revalidate=86400');
        } elseif ($this->isFont($request)) {
            // 1 year for fonts (they rarely change)
            $response->headers->set('Cache-Control', 'public, max-age=31536000, immutable');
        } elseif ($contentType && str_contains($contentType, 'text/html')) {
            // No cache for HTML (or very short cache with revalidation)
            $response->headers->set('Cache-Control', 'public, max-age=0, must-revalidate');
        }

        // Security headers
        $this->addSecurityHeaders($response);

        // Performance hints
        $this->addPerformanceHints($response);

        // Compression hint
        if (!$response->headers->has('Content-Encoding')) {
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }

    /**
     * Check if the request is for a static asset
     */
    private function isStaticAsset(Request $request): bool
    {
        $path = $request->path();
        $staticExtensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'otf'];
        
        foreach ($staticExtensions as $ext) {
            if (str_ends_with($path, '.' . $ext)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if the request is for an image
     */
    private function isImage(Request $request): bool
    {
        $path = $request->path();
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico'];
        
        foreach ($imageExtensions as $ext) {
            if (str_ends_with($path, '.' . $ext)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if the request is for a font
     */
    private function isFont(Request $request): bool
    {
        $path = $request->path();
        $fontExtensions = ['woff', 'woff2', 'ttf', 'eot', 'otf'];
        
        foreach ($fontExtensions as $ext) {
            if (str_ends_with($path, '.' . $ext)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Add security headers
     */
    private function addSecurityHeaders($response): void
    {
        // Content Security Policy
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://code.jquery.com https://cdnjs.cloudflare.com https://stackpath.bootstrapcdn.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:",
            "img-src 'self' data: https: http:",
            "connect-src 'self' https://stackpath.bootstrapcdn.com https://cdnjs.cloudflare.com https://code.jquery.com https://cdn.jsdelivr.net",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));

        // X-Content-Type-Options
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-Frame-Options
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // X-XSS-Protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy (formerly Feature-Policy)
        $permissions = [
            'accelerometer=()',
            'camera=()',
            'geolocation=()',
            'gyroscope=()',
            'magnetometer=()',
            'microphone=()',
            'payment=(self)',
            'usb=()',
        ];
        $response->headers->set('Permissions-Policy', implode(', ', $permissions));

        // HSTS (HTTP Strict Transport Security) - only if on HTTPS
        if (request()->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Add performance hints
     */
    private function addPerformanceHints($response): void
    {
        // Timing-Allow-Origin for performance monitoring
        $response->headers->set('Timing-Allow-Origin', '*');
    }
}
