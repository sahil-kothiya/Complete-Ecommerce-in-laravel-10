<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class RedisHelper
{
    /**
     * Compression threshold (compress data larger than 1KB)
     */
    private const COMPRESSION_THRESHOLD = 1024;

    /**
     * Maximum data size for Redis storage (64MB - Redis string limit)
     */
    private const MAX_DATA_SIZE = 64 * 1024 * 1024;

    /**
     * Chunk size for large data (16MB chunks)
     */
    private const CHUNK_SIZE = 16 * 1024 * 1024;

    /**
     * Compression prefix to identify compressed data
     */
    private const COMPRESSION_PREFIX = 'GZIP:';

    /**
     * Chunked data prefix
     */
    private const CHUNKED_PREFIX = 'CHUNKED:';

    public static function has(string $key): bool
    {
        try {
            return Redis::exists($key) > 0;
        } catch (\Throwable $e) {
            Log::error("RedisHelper::has() failed for key: {$key}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get data from Redis with automatic decompression and chunk handling
     */
    public static function get(string $key): mixed
    {
        try {
            $redisData = Redis::get($key);

            if ($redisData === null) {
                return null;
            }

            // Handle chunked data
            if (str_starts_with($redisData, self::CHUNKED_PREFIX)) {
                return self::getChunkedData($key);
            }

            return self::deserializeData($redisData);
        } catch (\Exception $e) {
            Log::error("Redis get error for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store data in Redis with automatic compression and chunking for very large data
     */
    public static function put(string $key, mixed $data, int $ttl = 3600): bool
    {
        try {
            $serialized = serialize($data);
            $dataSize = strlen($serialized);

            // Log data size for debugging
            Log::info("Storing Redis key: {$key}, Size: " . self::formatBytes($dataSize));

            // If data is too large, use chunking
            if ($dataSize > self::MAX_DATA_SIZE) {
                return self::putChunkedData($key, $serialized, $ttl);
            }

            // Compress if data is larger than threshold
            if ($dataSize > self::COMPRESSION_THRESHOLD) {
                $compressed = gzcompress($serialized, 6);
                if ($compressed === false) {
                    Log::warning("Failed to compress data for key: {$key}");
                    return false;
                }
                $finalData = self::COMPRESSION_PREFIX . base64_encode($compressed);
            } else {
                $finalData = $serialized;
            }

            $result = Redis::set($key, $finalData, 'EX', $ttl);

            if (!$result) {
                Log::warning("Failed to store data in Redis for key: {$key}");
            }

            return $result;
        } catch (\Exception $e) {
            Log::error("Redis put error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    public static function getMemoryUsage(): array
    {
        try {
            $info = Redis::info('memory');

            return [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'used_memory_rss' => $info['used_memory_rss'] ?? 0,
                'used_memory_rss_human' => $info['used_memory_rss_human'] ?? 'N/A',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'N/A',
                'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            Log::error("Redis MEMORY_USAGE error: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get keys matching a pattern
     */
    public static function keys(string $pattern): array
    {
        try {
            return Redis::keys($pattern) ?: [];
        } catch (\Exception $e) {
            Log::error("Redis KEYS error for pattern {$pattern}: " . $e->getMessage());
            return [];
        }
    }


    /**
     * Get TTL for a key
     */
    public static function ttl(string $key): ?int
    {
        try {
            $result = Redis::pipeline(function ($pipe) use ($key) {
                $pipe->ttl($key);
            });
            $ttl = $result[0] ?? -1;
            return $ttl >= 0 ? $ttl : null;
        } catch (\Exception $e) {
            Log::error("Redis TTL error for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store large data in chunks
     */
    private static function putChunkedData(string $key, string $serializedData, int $ttl): bool
    {
        try {
            $chunks = str_split($serializedData, self::CHUNK_SIZE);
            $chunkCount = count($chunks);

            Log::info("Chunking data for key: {$key}, Chunks: {$chunkCount}");

            // Use pipeline for better performance
            $pipeline = Redis::pipeline();

            // Store chunk count and metadata
            $metadata = [
                'chunk_count' => $chunkCount,
                'original_size' => strlen($serializedData),
                'created_at' => time()
            ];

            $pipeline->set($key, self::CHUNKED_PREFIX . json_encode($metadata), 'EX', $ttl);

            // Store each chunk
            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkKey = "{$key}:chunk:{$i}";
                $chunkData = $chunks[$i];

                // Compress chunk if beneficial
                if (strlen($chunkData) > self::COMPRESSION_THRESHOLD) {
                    $compressed = gzcompress($chunkData, 6);
                    if ($compressed !== false && strlen($compressed) < strlen($chunkData)) {
                        $chunkData = self::COMPRESSION_PREFIX . base64_encode($compressed);
                    }
                }

                $pipeline->set($chunkKey, $chunkData, 'EX', $ttl);
            }

            $results = $pipeline->exec();

            // Check if all operations succeeded
            foreach ($results as $result) {
                if (!$result) {
                    Log::error("Failed to store chunked data for key: {$key}");
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Redis putChunkedData error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve chunked data
     */
    private static function getChunkedData(string $key): mixed
    {
        try {
            $metadataRaw = Redis::get($key);
            if (!$metadataRaw) {
                return null;
            }

            $metadataJson = substr($metadataRaw, strlen(self::CHUNKED_PREFIX));
            $metadata = json_decode($metadataJson, true);

            if (!$metadata || !isset($metadata['chunk_count'])) {
                Log::warning("Invalid chunk metadata for key: {$key}");
                return null;
            }

            $chunkCount = $metadata['chunk_count'];
            $chunkKeys = [];

            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkKeys[] = "{$key}:chunk:{$i}";
            }

            // Get all chunks at once
            $chunks = Redis::mget($chunkKeys);
            $serializedData = '';

            for ($i = 0; $i < $chunkCount; $i++) {
                $chunkData = $chunks[$i];
                if ($chunkData === null) {
                    Log::warning("Missing chunk {$i} for key: {$key}");
                    return null;
                }

                // Decompress chunk if needed
                if (str_starts_with($chunkData, self::COMPRESSION_PREFIX)) {
                    $compressedData = substr($chunkData, strlen(self::COMPRESSION_PREFIX));
                    $decompressed = @gzuncompress(base64_decode($compressedData));
                    if ($decompressed === false) {
                        Log::warning("Failed to decompress chunk {$i} for key: {$key}");
                        return null;
                    }
                    $chunkData = $decompressed;
                }

                $serializedData .= $chunkData;
            }

            return @unserialize($serializedData);
        } catch (\Exception $e) {
            Log::error("Redis getChunkedData error for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Deserialize data with compression handling
     */
    public static function deserializeData(string $redisData): mixed
    {
        // Check if data is compressed
        if (str_starts_with($redisData, self::COMPRESSION_PREFIX)) {
            $compressedData = substr($redisData, strlen(self::COMPRESSION_PREFIX));
            $decompressed = @gzuncompress(base64_decode($compressedData));

            if ($decompressed === false) {
                Log::warning("Failed to decompress Redis data");
                return null;
            }

            $data = @unserialize($decompressed);
        } else {
            $data = @unserialize($redisData);
        }

        return $data === false ? null : $data;
    }

    /**
     * Get multiple keys at once using pipeline with chunk support
     */
    public static function mget(array $keys): array
    {
        try {
            if (empty($keys)) {
                return [];
            }

            $results = [];
            $responses = Redis::pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $key) {
                    $pipe->get($key);
                }
            });

            Log::debug('RedisHelper::mget raw response', [
                'keys' => $keys,
                'responses' => $responses
            ]);

            foreach ($keys as $index => $key) {
                $redisData = $responses[$index] ?? null;
                $results[$key] = $redisData ? self::deserializeData($redisData) : null;
            }

            return $results;
        } catch (\Exception $e) {
            Log::error("Redis mget error: " . $e->getMessage());
            return array_fill_keys($keys, null);
        }
    }

    /**
     * Delete single key including chunks
     */
    public static function forget(string $key): bool
    {
        try {
            // Check if it's chunked data
            $redisData = Redis::get($key);
            if ($redisData && str_starts_with($redisData, self::CHUNKED_PREFIX)) {
                return self::forgetChunkedData($key);
            }

            return Redis::del($key) > 0;
        } catch (\Exception $e) {
            Log::error("Redis forget error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete chunked data
     */
    private static function forgetChunkedData(string $key): bool
    {
        try {
            $metadataRaw = Redis::get($key);
            if (!$metadataRaw) {
                return true;
            }

            $metadataJson = substr($metadataRaw, strlen(self::CHUNKED_PREFIX));
            $metadata = json_decode($metadataJson, true);

            if ($metadata && isset($metadata['chunk_count'])) {
                $keysToDelete = [$key];

                for ($i = 0; $i < $metadata['chunk_count']; $i++) {
                    $keysToDelete[] = "{$key}:chunk:{$i}";
                }

                return Redis::del($keysToDelete) > 0;
            }

            return Redis::del($key) > 0;
        } catch (\Exception $e) {
            Log::error("Redis forgetChunkedData error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remember pattern - get from cache or execute callback and cache result
     */
    public static function remember(string $key, int $ttl, callable $callback)
    {
        try {
            // Try to get from cache first
            $cached = self::get($key);

            if ($cached !== null) {
                return $cached;
            }

            $value = $callback();
            self::put($key, $value, $ttl);

            return $value;
        } catch (\Exception $e) {
            Log::error("Redis REMEMBER error for key {$key}: " . $e->getMessage());
            return $callback();
        }
    }

    /**
     * Delete multiple keys using pipeline with chunk support
     */
    public static function forgetMany(array $keys): bool
    {
        try {
            if (empty($keys)) {
                return true;
            }

            $allKeysToDelete = [];
            $responses = Redis::pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $key) {
                    $pipe->get($key);
                }
            });

            foreach ($keys as $index => $key) {
                $redisData = $responses[$index] ?? null;
                if ($redisData && str_starts_with($redisData, self::CHUNKED_PREFIX)) {
                    $metadataJson = substr($redisData, strlen(self::CHUNKED_PREFIX));
                    $metadata = json_decode($metadataJson, true);
                    if ($metadata && isset($metadata['chunk_count'])) {
                        $allKeysToDelete[] = $key;
                        for ($i = 0; $i < $metadata['chunk_count']; $i++) {
                            $allKeysToDelete[] = "{$key}:chunk:{$i}";
                        }
                    } else {
                        $allKeysToDelete[] = $key;
                    }
                } else {
                    $allKeysToDelete[] = $key;
                }
            }

            if (!empty($allKeysToDelete)) {
                return Redis::pipeline(function ($pipe) use ($allKeysToDelete) {
                    $pipe->del($allKeysToDelete);
                })[0] > 0;
            }

            return true;
        } catch (\Exception $e) {
            Log::error("Redis forgetMany error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple key-value pairs using pipeline with chunking support
     */
    public static function mset(array $data, int $ttl = 3600): bool
    {
        try {
            if (empty($data)) {
                return true;
            }

            $success = true;
            $chunkedKeys = [];
            Redis::pipeline(function ($pipe) use ($data, $ttl, &$success, &$chunkedKeys) {
                foreach ($data as $key => $value) {
                    try {
                        $serialized = serialize($value);
                        $dataSize = strlen($serialized);

                        if ($dataSize > self::MAX_DATA_SIZE) {
                            $chunkedKeys[] = $key;
                            continue; // Handle chunked data separately
                        }

                        if ($dataSize > self::COMPRESSION_THRESHOLD) {
                            $compressed = gzcompress($serialized, 6);
                            if ($compressed === false) {
                                Log::warning("Failed to compress data for key: {$key}");
                                $success = false;
                                continue;
                            }
                            $finalData = self::COMPRESSION_PREFIX . base64_encode($compressed);
                        } else {
                            $finalData = $serialized;
                        }

                        $pipe->set($key, $finalData, 'EX', $ttl);
                    } catch (\Exception $e) {
                        Log::error("Redis mset put error for key {$key}: " . $e->getMessage());
                        $success = false;
                    }
                }
            });

            // Handle chunked data separately
            foreach ($chunkedKeys as $key) {
                if (!self::putChunkedData($key, serialize($data[$key]), $ttl)) {
                    $success = false;
                }
            }

            return $success;
        } catch (\Exception $e) {
            Log::error("Redis mset error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if key exists
     */
    public static function exists(string $key): bool
    {
        try {
            $result = Redis::pipeline(function ($pipe) use ($key) {
                $pipe->exists($key);
            });
            return ($result[0] ?? 0) > 0;
        } catch (\Exception $e) {
            Log::error("Redis exists error for key {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Increment counter with expiration
     */
    public static function increment(string $key, int $value = 1, int $ttl = 3600): int
    {
        try {
            $pipeline = Redis::pipeline();
            $pipeline->incrby($key, $value);
            $pipeline->expire($key, $ttl);
            $results = $pipeline->exec();

            return $results[0] ?? 0;
        } catch (\Exception $e) {
            Log::error("Redis increment error for key {$key}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get cache statistics for debugging
     */
    public static function getCacheStats(): array
    {
        try {
            $info = Redis::info('memory');
            return [
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'N/A',
                'used_memory_peak' => $info['used_memory_peak'] ?? 0,
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            Log::error("Redis getCacheStats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Ping Redis to check connection
     */
    public static function ping(): bool
    {
        try {
            return Redis::ping() !== false;
        } catch (\Exception $e) {
            Log::error("Redis ping failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database size (total keys)
     */
    public static function dbSize(): int
    {
        try {
            return Redis::dbSize();
        } catch (\Exception $e) {
            Log::error("Failed to get Redis DB size: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Set multiple key-value pairs atomically using MSET
     * More efficient than multiple SET operations
     */
    public static function msetAtomic(array $data, int $ttl = 3600): bool
    {
        try {
            // Prepare data for MSET
            $msetData = [];
            foreach ($data as $key => $value) {
                $serialized = serialize($value);

                // Compress if needed
                if (strlen($serialized) > self::COMPRESSION_THRESHOLD) {
                    $compressed = gzcompress($serialized, 6);
                    $serialized = self::COMPRESSION_PREFIX . $compressed;
                }

                $msetData[$key] = $serialized;
            }

            // Execute MSET atomically
            Redis::mset($msetData);

            // Set TTL for each key using pipeline
            Redis::pipeline(function ($pipe) use ($data, $ttl) {
                foreach (array_keys($data) as $key) {
                    $pipe->expire($key, $ttl);
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error("Redis msetAtomic failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete keys matching a pattern (use with caution)
     * Uses SCAN to avoid blocking Redis
     */
    public static function deletePattern(string $pattern): int
    {
        try {
            $deleted = 0;
            $cursor = null;

            do {
                $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', 1000);
                $cursor = $result[0] ?? null;
                $keys = $result[1] ?? [];

                if (!empty($keys)) {
                    $deleted += Redis::del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== null);

            Log::debug("Deleted {$deleted} keys matching pattern: {$pattern}");
            return $deleted;
        } catch (\Exception $e) {
            Log::error("Failed to delete pattern {$pattern}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get keys matching a pattern with count limit
     * More efficient than keys() for large datasets
     */
    public static function scanKeys(string $pattern, int $limit = 1000): array
    {
        try {
            $keys = [];
            $cursor = null;
            $scanned = 0;

            do {
                $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', 100);
                $cursor = $result[0] ?? null;
                $foundKeys = $result[1] ?? [];

                $keys = array_merge($keys, $foundKeys);
                $scanned += count($foundKeys);

                if ($scanned >= $limit) {
                    break;
                }
            } while ($cursor !== 0 && $cursor !== null);

            return array_slice($keys, 0, $limit);
        } catch (\Exception $e) {
            Log::error("Failed to scan keys with pattern {$pattern}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Batch remember pattern - get from cache or execute callbacks and cache results
     * Optimized for multiple cache checks in one operation
     */
    public static function rememberMany(array $items, int $ttl, callable $callback): array
    {
        try {
            // Extract keys
            $keys = array_keys($items);

            // Fetch all cached values
            $cached = self::mget($keys);

            // Identify missing keys
            $missing = [];
            foreach ($keys as $key) {
                if ($cached[$key] === null) {
                    $missing[] = $key;
                }
            }

            // Execute callback for missing items
            if (!empty($missing)) {
                $fresh = $callback($missing);

                // Cache fresh data
                $toCache = [];
                foreach ($fresh as $key => $value) {
                    $toCache[$key] = $value;
                    $cached[$key] = $value;
                }

                if (!empty($toCache)) {
                    self::mset($toCache, $ttl);
                }
            }

            return $cached;
        } catch (\Exception $e) {
            Log::error("Redis rememberMany failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lock mechanism for preventing cache stampede
     * Returns true if lock acquired, false otherwise
     */
    public static function lock(string $key, int $ttl = 10): bool
    {
        try {
            $lockKey = "lock:{$key}";
            $result = Redis::set($lockKey, 1, 'NX', 'EX', $ttl);
            return $result !== false && $result !== null;
        } catch (\Exception $e) {
            Log::error("Failed to acquire lock for {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release lock
     */
    public static function unlock(string $key): bool
    {
        try {
            $lockKey = "lock:{$key}";
            return Redis::del($lockKey) > 0;
        } catch (\Exception $e) {
            Log::error("Failed to release lock for {$key}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remember with lock to prevent cache stampede
     */
    public static function rememberWithLock(string $key, int $ttl, callable $callback, int $lockTtl = 10)
    {
        try {
            // Try to get from cache first
            $value = self::get($key);
            if ($value !== null) {
                return $value;
            }

            // Try to acquire lock
            if (!self::lock($key, $lockTtl)) {
                // Lock not acquired, wait briefly and try cache again
                usleep(100000); // 100ms
                $value = self::get($key);
                if ($value !== null) {
                    return $value;
                }

                // Still no cache, execute callback without lock
                Log::warning("Cache miss without lock for key: {$key}");
                return $callback();
            }

            // Lock acquired, execute callback
            try {
                $value = $callback();
                self::put($key, $value, $ttl);
                return $value;
            } finally {
                self::unlock($key);
            }
        } catch (\Exception $e) {
            Log::error("Redis rememberWithLock failed for {$key}: " . $e->getMessage());
            self::unlock($key);
            throw $e;
        }
    }

    /**
     * Increment cache version for mass invalidation
     */
    public static function incrementVersion(string $versionKey = 'meta:cache:version'): int
    {
        try {
            return self::increment($versionKey, 1, 86400);
        } catch (\Exception $e) {
            Log::error("Failed to increment version: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get current cache version
     */
    public static function getVersion(string $versionKey = 'meta:cache:version'): int
    {
        try {
            $version = self::get($versionKey);
            return $version ?? 1;
        } catch (\Exception $e) {
            Log::error("Failed to get cache version: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Get detailed Redis info
     */
    public static function getRedisInfo(): array
    {
        try {
            $info = Redis::info();

            return [
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime_days' => isset($info['uptime_in_seconds']) ? round($info['uptime_in_seconds'] / 86400, 2) : 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => isset($info['used_memory']) ? self::formatBytes($info['used_memory']) : '0',
                'used_memory_peak' => isset($info['used_memory_peak']) ? self::formatBytes($info['used_memory_peak']) : '0',
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'instantaneous_ops_per_sec' => $info['instantaneous_ops_per_sec'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to get Redis info: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Calculate cache hit rate
     */
    private static function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        if ($total === 0) {
            return 0.0;
        }

        return round(($hits / $total) * 100, 2);
    }

    /**
     * Flush specific database
     */
    public static function flushDb(): bool
    {
        try {
            Redis::flushDb();
            Log::info("Redis database flushed");
            return true;
        } catch (\Exception $e) {
            Log::error("Failed to flush Redis database: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get memory info for specific keys
     */
    public static function getKeyMemory(string $key): ?int
    {
        try {
            return Redis::memory('USAGE', $key);
        } catch (\Exception $e) {
            Log::error("Failed to get memory usage for key {$key}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Batch operation wrapper for pipeline
     */
    public static function pipeline(callable $callback): array
    {
        try {
            return Redis::pipeline(function ($pipe) use ($callback) {
                return $callback($pipe);
            });
        } catch (\Exception $e) {
            Log::error("Redis pipeline failed: " . $e->getMessage());
            return [];
        }
    }
}
