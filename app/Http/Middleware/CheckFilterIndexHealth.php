<?php

namespace App\Http\Middleware;

use App\Services\IndexHealthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Check Filter Index Health Middleware
 * 
 * Ensures Redis indexes are healthy before processing filter requests.
 * Triggers on-demand rebuilds if needed to prevent filter failures.
 */
class CheckFilterIndexHealth
{
    private IndexHealthService $healthService;
    
    public function __construct(IndexHealthService $healthService)
    {
        $this->healthService = $healthService;
    }
    
    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Only check on filter/product listing requests
        if ($this->shouldCheckIndexHealth($request)) {
            $this->ensureIndexesHealthy();
        }
        
        return $next($request);
    }
    
    /**
     * Determine if we should check index health for this request
     */
    private function shouldCheckIndexHealth(Request $request): bool
    {
        $path = $request->path();
        
        // Check if it's a filter API request
        return str_contains($path, 'api/filters') || 
               str_contains($path, 'api/products');
    }
    
    /**
     * Ensure indexes are healthy, trigger rebuild if needed
     */
    private function ensureIndexesHealthy(): void
    {
        // Throttle health checks (check at most once per minute per user)
        $cacheKey = 'index_health_checked_' . request()->ip();
        
        if (Cache::has($cacheKey)) {
            return; // Already checked recently
        }
        
        try {
            // Quick health check
            if (!$this->healthService->isHealthy()) {
                Log::warning('Filter indexes unhealthy, triggering rebuild');
                
                // Trigger background rebuild (non-blocking)
                $this->healthService->triggerRebuildIfNeeded();
            }
            
            // Cache that we've checked (1 minute)
            Cache::put($cacheKey, true, 60);
            
        } catch (\Exception $e) {
            Log::error('Index health check failed in middleware', [
                'error' => $e->getMessage()
            ]);
            // Don't block request on error
        }
    }
}
