<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performance Optimization Middleware
 * Adds resource hints, HTTP/2 server push, and optimal caching headers
 * Target: Reduce initial load time by 40-60%
 */
class OptimizePerformance
{
    /**
     * Critical assets to preload
     */
    protected array $criticalAssets = [
        // Commented out - these are not critical assets for frontend
        // '/css/app.css' => ['as' => 'style'],
        // '/js/app.js' => ['as' => 'script', 'crossorigin' => true],
    ];

    /**
     * DNS prefetch domains
     */
    protected array $dnsPrefetchDomains = [
        'https://fonts.googleapis.com',
        'https://fonts.gstatic.com',
        'https://cdn.jsdelivr.net',
    ];

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

        // Only optimize HTML responses
        if (!$this->shouldOptimize($response)) {
            return $response;
        }

        // Add performance headers
        $this->addResourceHints($response);
        $this->addCacheHeaders($response);
        $this->addSecurityHeaders($response);
        $this->optimizeHeaders($response);

        return $response;
    }

    /**
     * Determine if response should be optimized.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldOptimize(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');
        return stripos($contentType, 'text/html') !== false;
    }

    /**
     * Add resource hints (preload, dns-prefetch, preconnect).
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function addResourceHints(Response $response): void
    {
        $links = [];

        // DNS Prefetch
        foreach ($this->dnsPrefetchDomains as $domain) {
            $links[] = "<{$domain}>; rel=dns-prefetch";
            $links[] = "<{$domain}>; rel=preconnect; crossorigin";
        }

        // Preload critical assets
        foreach ($this->criticalAssets as $asset => $attrs) {
            $resolved = $this->resolveAssetPath($asset);
            $linkValue = "<{$resolved}>; rel=preload; as={$attrs['as']}";
            
            if (isset($attrs['crossorigin'])) {
                $linkValue .= '; crossorigin';
            }
            
            if (isset($attrs['type'])) {
                $linkValue .= "; type={$attrs['type']}";
            }

            $links[] = $linkValue;
        }

        // Add all Link headers at once
        if (!empty($links)) {
            $response->headers->set('Link', implode(', ', $links), false);
        }
    }

    protected function resolveAssetPath(string $asset): string
    {
        try {
            return mix($asset);
        } catch (\Throwable $e) {
            return asset(ltrim($asset, '/'));
        }
    }

    /**
     * Add optimal cache headers.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function addCacheHeaders(Response $response): void
    {
        // For HTML pages: use stale-while-revalidate for better UX
        if (!$response->headers->has('Cache-Control')) {
            $response->headers->set(
                'Cache-Control',
                'public, max-age=300, stale-while-revalidate=600, stale-if-error=86400'
            );
        }

        // Add timing headers
        if (!$response->headers->has('X-Content-Type-Options')) {
            $response->headers->set('X-Content-Type-Options', 'nosniff');
        }
    }

    /**
     * Add security headers.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function addSecurityHeaders(Response $response): void
    {
        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];

        foreach ($headers as $key => $value) {
            if (!$response->headers->has($key)) {
                $response->headers->set($key, $value);
            }
        }
    }

    /**
     * Optimize response headers for performance.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function optimizeHeaders(Response $response): void
    {
        // Remove unnecessary headers
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // Add Keep-Alive
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Keep-Alive', 'timeout=5, max=100');

        // Add timing allow origin for performance monitoring
        $response->headers->set('Timing-Allow-Origin', '*');
    }
}
