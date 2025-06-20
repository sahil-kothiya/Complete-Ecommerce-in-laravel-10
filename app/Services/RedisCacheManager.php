<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class RedisCacheManager
{
    /**
     * Store a model in Redis with manual TTL and serialization.
     */
    public static function put($type, $id, $data, $ttl = 3600)
    {
        $key = self::makeKey($type, $id);
        Redis::setex($key, $ttl, serialize($data));
    }

    /**
     * Get a model from Redis with deserialization.
     */
    public static function get($type, $id)
    {
        $key = self::makeKey($type, $id);
        $raw = Redis::get($key);
        return $raw ? unserialize($raw) : null;
    }

    /**
     * Remove a model from Redis.
     */
    public static function forget($type, $id)
    {
        $key = self::makeKey($type, $id);
        Redis::del($key);
    }

    /**
     * Bulk load models from Redis.
     */
    public static function many($type, $ids)
    {
        $keys = array_map(fn($id) => self::makeKey($type, $id), $ids);
        $values = Redis::mget($keys);

        $result = [];
        foreach ($ids as $index => $id) {
            $result[$id] = $values[$index] ? unserialize($values[$index]) : null;
        }
        return $result;
    }

    /**
     * Generate a Redis-friendly human-readable key.
     */
    public static function makeKey($type, $id)
    {
        return strtolower($type) . ':' . $id;
    }

    /**
     * Flush all keys matching given prefixes using SCAN + DEL.
     */
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
