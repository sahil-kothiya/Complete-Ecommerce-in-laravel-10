<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RedisException;

/**
 * RedisFailureHandler Middleware
 * 
 * Handles Redis failures gracefully without breaking the application.
 * For 10M+ product scale, this ensures the site stays operational
 * even when Redis has persistence or connection issues.
 */
class RedisFailureHandler
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
        try {
            return $next($request);
        } catch (RedisException $e) {
            // Log the Redis error with context
            Log::error('Redis operation failed', [
                'message' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => auth()->id() ?? 'guest',
            ]);

            // Check if this is a write error
            if ($this->isWriteError($e)) {
                // Attempt to disable persistence temporarily
                $this->attemptRedisRecovery();
            }

            // For API requests, return JSON error
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Service temporarily degraded',
                    'message' => 'Our caching system is experiencing issues. Your data is safe, but some features may be slower.',
                    'retry_after' => 30,
                ], 503);
            }

            // For web requests, show user-friendly error page
            return response()->view('errors.redis-unavailable', [
                'message' => 'Our system is experiencing high load. Please try again in a moment.',
            ], 503);
        }
    }

    /**
     * Check if this is a Redis write error
     */
    private function isWriteError(RedisException $e): bool
    {
        $message = $e->getMessage();
        return str_contains($message, 'MISCONF') ||
               str_contains($message, 'stop-writes-on-bgsave-error') ||
               str_contains($message, 'unable to persist') ||
               str_contains($message, 'RDB');
    }

    /**
     * Attempt to recover Redis by disabling problematic persistence settings
     */
    private function attemptRedisRecovery(): void
    {
        try {
            $redis = app('redis')->connection();
            
            // Try to disable stop-writes-on-bgsave-error
            $redis->config('SET', 'stop-writes-on-bgsave-error', 'no');
            
            Log::info('Redis recovery attempted: Disabled stop-writes-on-bgsave-error');
        } catch (\Exception $e) {
            Log::warning('Redis recovery failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
