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
            $ttl = Redis::ttl($key);
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
    private static function deserializeData(string $redisData): mixed
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

            $results = Redis::mget($keys);
            $data = [];

            foreach ($keys as $index => $key) {
                $redisData = $results[$index] ?? null;

                if ($redisData === null) {
                    $data[$key] = null;
                    continue;
                }

                // Handle chunked data
                if (str_starts_with($redisData, self::CHUNKED_PREFIX)) {
                    $data[$key] = self::getChunkedData($key);
                } else {
                    $data[$key] = self::deserializeData($redisData);
                }
            }

            return $data;
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

            // Execute callback and cache result
            $value = $callback();
            self::put($key, $value, $ttl);

            return $value;
        } catch (\Exception $e) {
            Log::error("Redis REMEMBER error for key {$key}: " . $e->getMessage());
            // If Redis fails, just execute the callback
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

            foreach ($keys as $key) {
                $redisData = Redis::get($key);
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

            return Redis::del($allKeysToDelete) > 0;
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
            foreach ($data as $key => $value) {
                if (!self::put($key, $value, $ttl)) {
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
            return Redis::exists($key) > 0;
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
}
