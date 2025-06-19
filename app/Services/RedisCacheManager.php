<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class RedisCacheManager
{
    /**
     * Store a model in cache with a human-readable key.
     */
    public static function put($type, $id, $data, $ttl = 3600)
    {
        $key = self::makeKey($type, $id);
        Cache::store('redis')->put($key, $data, $ttl);
    }

    /**
     * Get a model from cache.
     */
    public static function get($type, $id)
    {
        $key = self::makeKey($type, $id);
        return Cache::store('redis')->get($key);
    }

    /**
     * Remove a model from cache.
     */
    public static function forget($type, $id)
    {
        $key = self::makeKey($type, $id);
        Cache::store('redis')->forget($key);
    }

    /**
     * Bulk load models from cache (for index pages).
     */
    public static function many($type, $ids)
    {
        $result = [];
        foreach ($ids as $id) {
            $result[$id] = self::get($type, $id);
        }
        return $result;
    }

    /**
     * Generate a human-readable cache key.
     */
    public static function makeKey($type, $id)
    {
        return strtolower($type) . ':' . $id;
    }

    public static function flushByPrefix(array $prefixes): void
    {
        foreach ($prefixes as $prefix) {
            $cursor = null;
            do {
                [$cursor, $keys] = Redis::scan($cursor, 'MATCH', strtolower($prefix) . ':*', 'COUNT', 1000);
                if (!empty($keys)) {
                    Redis::del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== null);
        }
    }
}
