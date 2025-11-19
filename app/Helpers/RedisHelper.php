<?php

namespace App\Helpers;

use App\Services\RedisCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * RedisHelper - Backward Compatible Facade
 *
 * This class maintains backward compatibility while delegating
 * all operations to the centralized RedisCacheService.
 *
 * @deprecated Use RedisCacheService directly for new code
 */
class RedisHelper
{
    public static function has(string $key): bool
    {
        return RedisCacheService::has($key);
    }

    public static function get(string $key): mixed
    {
        return RedisCacheService::get($key);
    }

    public static function put(string $key, mixed $data, int $ttl = 3600): bool
    {
        return RedisCacheService::put($key, $data, $ttl);
    }

    public static function getMemoryUsage(): array
    {
        $stats = RedisCacheService::getStats();
        return $stats['redis'] ?? [];
    }

    public static function keys(string $pattern): array
    {
        return RedisCacheService::keys($pattern);
    }

    public static function ttl(string $key): ?int
    {
        try {
            return Redis::ttl($key);
        } catch (\Exception $e) {
            Log::error("Redis TTL error for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    public static function deserializeData(string $redisData): mixed
    {
        return @unserialize($redisData);
    }

    public static function mget(array $keys): array
    {
        return RedisCacheService::mget($keys);
    }

    public static function forget(string $key): bool
    {
        return RedisCacheService::forget($key);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return RedisCacheService::remember($key, $ttl, $callback);
    }

    public static function forgetMany(array $keys): bool
    {
        return RedisCacheService::forgetMany($keys);
    }

    public static function mset(array $data, int $ttl = 3600): bool
    {
        return RedisCacheService::mset($data, $ttl);
    }

    public static function exists(string $key): bool
    {
        return RedisCacheService::has($key);
    }

    public static function increment(string $key, int $value = 1, int $ttl = 3600): int
    {
        return RedisCacheService::increment($key, $value, $ttl);
    }

    public static function getCacheStats(): array
    {
        $stats = RedisCacheService::getStats();
        return $stats['redis'] ?? [];
    }

    public static function ping(): bool
    {
        return RedisCacheService::ping();
    }

    public static function dbSize(): int
    {
        try {
            return Redis::dbSize();
        } catch (\Exception $e) {
            Log::error("Failed to get Redis DB size: " . $e->getMessage());
            return 0;
        }
    }

    public static function msetAtomic(array $data, int $ttl = 3600): bool
    {
        return RedisCacheService::mset($data, $ttl);
    }

    public static function deletePattern(string $pattern): int
    {
        return RedisCacheService::forgetPattern($pattern);
    }

    public static function scanKeys(string $pattern, int $limit = 1000): array
    {
        return RedisCacheService::keys($pattern, $limit);
    }

    public static function rememberMany(array $items, int $ttl, callable $callback): array
    {
        $keys = array_keys($items);
        $cached = RedisCacheService::mget($keys);

        $missing = [];
        foreach ($keys as $key) {
            if ($cached[$key] === null) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            $fresh = $callback($missing);
            if (!empty($fresh)) {
                RedisCacheService::mset($fresh, $ttl);
                $cached = array_merge($cached, $fresh);
            }
        }

        return $cached;
    }

    public static function lock(string $key, int $ttl = 10): bool
    {
        return RedisCacheService::lock($key, $ttl);
    }

    public static function unlock(string $key): bool
    {
        return RedisCacheService::unlock($key);
    }

    public static function rememberWithLock(string $key, int $ttl, callable $callback, int $lockTtl = 10): mixed
    {
        return RedisCacheService::rememberWithLock($key, $ttl, $callback);
    }

    public static function incrementVersion(string $versionKey = 'meta:cache:version'): int
    {
        return RedisCacheService::incrementVersion();
    }

    public static function getVersion(string $versionKey = 'meta:cache:version'): int
    {
        return RedisCacheService::getVersion();
    }

    public static function getRedisInfo(): array
    {
        $stats = RedisCacheService::getStats();
        return $stats['redis'] ?? [];
    }

    public static function flushDb(): bool
    {
        return RedisCacheService::flush();
    }

    public static function getKeyMemory(string $key): ?int
    {
        try {
            return Redis::memory('USAGE', $key);
        } catch (\Exception $e) {
            Log::error("Failed to get memory usage for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    public static function pipeline(callable $callback): array
    {
        try {
            return Redis::pipeline($callback);
        } catch (\Exception $e) {
            Log::error("Redis pipeline failed: " . $e->getMessage());
            return [];
        }
    }

    public static function decrement(string $key, int $value = 1, int $ttl = 3600): int
    {
        return RedisCacheService::decrement($key, $value, $ttl);
    }
}
