<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

/**
 * Centralized Redis Cache Service
 *
 * THE SINGLE SOURCE OF TRUTH for all Redis caching operations.
 * Optimized for 10M+ products with sub-1-second page loads.
 *
 * Features:
 * - Centralized configuration management
 * - Multiple encoding methods (serialize, JSON, MessagePack, Igbinary)
 * - Automatic compression with configurable threshold
 * - Cache stampede prevention with distributed locking
 * - Chunking for large datasets (>64MB)
 * - Pipeline operations for batch processing
 * - Version-based cache invalidation
 * - Comprehensive monitoring and logging
 * - Graceful fallback handling
 *
 * @package App\Services
 */
class RedisCacheService
{
    private static ?array $config = null;
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
    ];

    /**
     * Get configuration value
     */
    private static function config(string $key, mixed $default = null): mixed
    {
        if (self::$config === null) {
            self::$config = Config::get('redis_cache');
        }

        return data_get(self::$config, $key, $default);
    }

    /**
     * Check if master cache is enabled
     */
    public static function isEnabled(string $type = 'master'): bool
    {
        return self::config("enabled.{$type}", false);
    }

    /**
     * Get TTL for specific cache type
     */
    public static function getTtl(string $type): int
    {
        return self::config("ttl.{$type}", 3600);
    }

    /**
     * Generate cache key with prefix
     */
    public static function makeKey(string $type, ...$identifiers): string
    {
        $prefix = self::config("prefixes.{$type}", $type);
        $key = $prefix;

        foreach ($identifiers as $id) {
            $key .= ':' . $id;
        }

        return $key;
    }

    /**
     * Check if cache key exists
     */
    public static function has(string $key): bool
    {
        try {
            if (!self::isEnabled()) {
                return false;
            }

            return Redis::exists($key) > 0;
        } catch (\Throwable $e) {
            self::handleError('has', $key, $e);
            return false;
        }
    }

    /**
     * Get data from cache
     */
    public static function get(string $key): mixed
    {
        try {
            if (!self::isEnabled()) {
                return null;
            }

            $start = microtime(true);
            $data = Redis::get($key);

            if ($data === null) {
                self::$stats['misses']++;
                self::logMiss($key);
                return null;
            }

            // Handle chunked data
            if (str_starts_with($data, 'CHUNKED:')) {
                $result = self::getChunked($key);
            } else {
                $result = self::decode($data);
            }

            self::$stats['hits']++;
            self::logPerformance('get', $key, $start);

            return $result;
        } catch (\Throwable $e) {
            self::handleError('get', $key, $e);
            return null;
        }
    }

    /**
     * Store data in cache
     */
    public static function put(string $key, mixed $data, ?int $ttl = null): bool
    {
        try {
            if (!self::isEnabled()) {
                return false;
            }

            $start = microtime(true);
            $encoded = self::encode($data);
            $size = strlen($encoded);

            // Check if chunking is needed
            $maxSize = self::config('performance.max_data_size', 67108864);
            if ($size > $maxSize && self::config('performance.enable_chunking', true)) {
                $result = self::putChunked($key, $encoded, $ttl);
            } else {
                // Compress if threshold exceeded
                $threshold = self::config('encoding.compress_threshold', 1024);
                if (self::config('encoding.compress', true) && $size > $threshold) {
                    $encoded = self::compress($encoded);
                }

                $ttl = $ttl ?? 3600;
                $result = Redis::setex($key, $ttl, $encoded);
            }

            self::$stats['writes']++;
            self::logPerformance('put', $key, $start, $size);

            return $result !== false;
        } catch (\Throwable $e) {
            self::handleError('put', $key, $e);
            return false;
        }
    }

    /**
     * Remember pattern - get from cache or execute callback
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        try {
            // Try cache first
            $value = self::get($key);

            if ($value !== null) {
                return $value;
            }

            // Use locking if enabled
            if (self::config('performance.lock_enabled', true)) {
                return self::rememberWithLock($key, $ttl, $callback);
            }

            // Execute callback and cache
            $value = $callback();
            self::put($key, $value, $ttl);

            return $value;
        } catch (\Throwable $e) {
            self::handleError('remember', $key, $e);
            return $callback();
        }
    }

    /**
     * Remember with distributed locking to prevent cache stampede
     */
    public static function rememberWithLock(string $key, int $ttl, callable $callback): mixed
    {
        // Check cache again
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }

        // Try to acquire lock
        $lockTtl = self::config('performance.lock_timeout', 10);
        if (!self::lock($key, $lockTtl)) {
            // Lock not acquired, wait and retry
            $delay = self::config('performance.lock_retry_delay', 100);
            usleep($delay * 1000); // Convert to microseconds

            $value = self::get($key);
            if ($value !== null) {
                return $value;
            }

            // Still no cache, execute without lock
            Log::warning("Cache stampede detected for key: {$key}");
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
    }

    /**
     * Get multiple keys at once
     */
    public static function mget(array $keys): array
    {
        try {
            if (!self::isEnabled() || empty($keys)) {
                return array_fill_keys($keys, null);
            }

            $start = microtime(true);
            $values = Redis::mget($keys);
            $result = [];

            foreach ($keys as $index => $key) {
                $data = $values[$index] ?? null;
                $result[$key] = $data ? self::decode($data) : null;

                if ($data === null) {
                    self::$stats['misses']++;
                } else {
                    self::$stats['hits']++;
                }
            }

            self::logPerformance('mget', implode(',', array_slice($keys, 0, 3)), $start);

            return $result;
        } catch (\Throwable $e) {
            self::handleError('mget', 'multiple', $e);
            return array_fill_keys($keys, null);
        }
    }

    /**
     * Set multiple key-value pairs
     */
    public static function mset(array $data, int $ttl = 3600): bool
    {
        try {
            if (!self::isEnabled() || empty($data)) {
                return false;
            }

            $start = microtime(true);

            if (self::config('performance.pipeline_enabled', true)) {
                return self::msetPipeline($data, $ttl);
            }

            // Fallback to individual sets
            $success = true;
            foreach ($data as $key => $value) {
                if (!self::put($key, $value, $ttl)) {
                    $success = false;
                }
            }

            self::logPerformance('mset', count($data) . ' keys', $start);

            return $success;
        } catch (\Throwable $e) {
            self::handleError('mset', 'multiple', $e);
            return false;
        }
    }

    /**
     * MSET using pipeline for better performance
     */
    private static function msetPipeline(array $data, int $ttl): bool
    {
        $encoded = [];
        foreach ($data as $key => $value) {
            $encoded[$key] = self::encode($value);
        }

        Redis::pipeline(function ($pipe) use ($encoded, $ttl) {
            foreach ($encoded as $key => $value) {
                $pipe->setex($key, $ttl, $value);
            }
        });

        return true;
    }

    /**
     * Delete single key
     */
    public static function forget(string $key): bool
    {
        try {
            if (!self::isEnabled()) {
                return false;
            }

            // Check if chunked
            $data = Redis::get($key);
            if ($data && str_starts_with($data, 'CHUNKED:')) {
                return self::forgetChunked($key);
            }

            $result = Redis::del($key) > 0;
            self::$stats['deletes']++;

            return $result;
        } catch (\Throwable $e) {
            self::handleError('forget', $key, $e);
            return false;
        }
    }

    /**
     * Delete multiple keys
     */
    public static function forgetMany(array $keys): bool
    {
        try {
            if (!self::isEnabled() || empty($keys)) {
                return false;
            }

            $allKeys = [];

            // Check for chunked data
            $values = Redis::mget($keys);
            foreach ($keys as $index => $key) {
                $data = $values[$index] ?? null;
                if ($data && str_starts_with($data, 'CHUNKED:')) {
                    $metadata = json_decode(substr($data, 8), true);
                    if (isset($metadata['chunk_count'])) {
                        $allKeys[] = $key;
                        for ($i = 0; $i < $metadata['chunk_count']; $i++) {
                            $allKeys[] = "{$key}:chunk:{$i}";
                        }
                    }
                } else {
                    $allKeys[] = $key;
                }
            }

            if (!empty($allKeys)) {
                Redis::del(...$allKeys);
                self::$stats['deletes'] += count($allKeys);
            }

            return true;
        } catch (\Throwable $e) {
            self::handleError('forgetMany', 'multiple', $e);
            return false;
        }
    }

    /**
     * Delete keys matching pattern using SCAN
     */
    public static function forgetPattern(string $pattern): int
    {
        try {
            if (!self::isEnabled()) {
                return 0;
            }

            $deleted = 0;
            $cursor = null;
            $scanCount = self::config('performance.scan_count', 1000);

            do {
                $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', $scanCount);
                $cursor = $result[0] ?? null;
                $keys = $result[1] ?? [];

                if (!empty($keys)) {
                    $deleted += Redis::del(...$keys);
                }
            } while ($cursor !== 0 && $cursor !== null);

            self::$stats['deletes'] += $deleted;
            Log::info("Deleted {$deleted} keys matching pattern: {$pattern}");

            return $deleted;
        } catch (\Throwable $e) {
            self::handleError('forgetPattern', $pattern, $e);
            return 0;
        }
    }

    /**
     * Increment counter
     */
    public static function increment(string $key, int $value = 1, int $ttl = 3600): int
    {
        try {
            if (!self::isEnabled()) {
                return 0;
            }

            $results = Redis::pipeline(function ($pipe) use ($key, $value, $ttl) {
                $pipe->incrby($key, $value);
                $pipe->expire($key, $ttl);
            });

            return $results[0] ?? 0;
        } catch (\Throwable $e) {
            self::handleError('increment', $key, $e);
            return 0;
        }
    }

    /**
     * Decrement counter
     */
    public static function decrement(string $key, int $value = 1, int $ttl = 3600): int
    {
        try {
            if (!self::isEnabled()) {
                return 0;
            }

            $results = Redis::pipeline(function ($pipe) use ($key, $value, $ttl) {
                $pipe->decrby($key, $value);
                $pipe->expire($key, $ttl);
            });

            return $results[0] ?? 0;
        } catch (\Throwable $e) {
            self::handleError('decrement', $key, $e);
            return 0;
        }
    }

    /**
     * Acquire distributed lock
     */
    public static function lock(string $key, int $ttl = 10): bool
    {
        try {
            $lockKey = self::makeKey('lock', $key);
            $result = Redis::set($lockKey, 1, 'NX', 'EX', $ttl);
            return $result !== false && $result !== null;
        } catch (\Throwable $e) {
            self::handleError('lock', $key, $e);
            return false;
        }
    }

    /**
     * Release distributed lock
     */
    public static function unlock(string $key): bool
    {
        try {
            $lockKey = self::makeKey('lock', $key);
            return Redis::del($lockKey) > 0;
        } catch (\Throwable $e) {
            self::handleError('unlock', $key, $e);
            return false;
        }
    }

    /**
     * Get or increment cache version (for mass invalidation)
     */
    public static function getVersion(): int
    {
        try {
            $versionKey = self::config('invalidation.version_key', 'meta:cache:version');
            $version = self::get($versionKey);

            if ($version === null) {
                $version = 1;
                self::put($versionKey, $version, 86400);
            }

            return (int) $version;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    /**
     * Increment cache version to invalidate all version-based caches
     */
    public static function incrementVersion(): int
    {
        try {
            $versionKey = self::config('invalidation.version_key', 'meta:cache:version');
            $newVersion = self::increment($versionKey, 1, 86400);

            Log::info("Cache version incremented to: {$newVersion}");

            return $newVersion;
        } catch (\Throwable $e) {
            self::handleError('incrementVersion', 'version', $e);
            return 1;
        }
    }

    /**
     * Invalidate caches based on entity type
     */
    public static function invalidate(string $entityType, array $identifiers = []): void
    {
        try {
            if (!self::config('invalidation.auto_invalidate', true)) {
                return;
            }

            $dependencies = self::config("invalidation.dependencies.{$entityType}", []);

            foreach ($dependencies as $pattern) {
                // Replace placeholders
                foreach ($identifiers as $key => $value) {
                    $pattern = str_replace("{{$key}}", $value, $pattern);
                }

                // Delete matching keys
                if (str_contains($pattern, '*')) {
                    self::forgetPattern($pattern);
                } else {
                    self::forget($pattern);
                }
            }

            Log::debug("Invalidated caches for: {$entityType}", $identifiers);
        } catch (\Throwable $e) {
            self::handleError('invalidate', $entityType, $e);
        }
    }

    /**
     * Get cache statistics
     */
    public static function getStats(): array
    {
        try {
            $info = Redis::info('memory');
            $stats = Redis::info('stats');

            $hits = $stats['keyspace_hits'] ?? 0;
            $misses = $stats['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            $hitRate = $total > 0 ? round(($hits / $total) * 100, 2) : 0;

            return [
                // Session stats
                'session' => self::$stats,

                // Redis stats
                'redis' => [
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate' => $hitRate,
                    'total_keys' => Redis::dbSize(),
                    'used_memory' => self::formatBytes($info['used_memory'] ?? 0),
                    'used_memory_peak' => self::formatBytes($info['used_memory_peak'] ?? 0),
                    'memory_fragmentation_ratio' => $info['mem_fragmentation_ratio'] ?? 0,
                    'ops_per_sec' => $stats['instantaneous_ops_per_sec'] ?? 0,
                ],

                // Configuration
                'config' => [
                    'enabled' => self::isEnabled(),
                    'encoding' => self::config('encoding.method', 'serialize'),
                    'compression' => self::config('encoding.compress', true),
                    'chunking' => self::config('performance.enable_chunking', true),
                    'locking' => self::config('performance.lock_enabled', true),
                ],
            ];
        } catch (\Throwable $e) {
            self::handleError('getStats', 'stats', $e);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get keys matching pattern
     */
    public static function keys(string $pattern, int $limit = 1000): array
    {
        try {
            if (!self::config('performance.use_scan', true)) {
                return Redis::keys($pattern) ?? [];
            }

            return self::scanKeys($pattern, $limit);
        } catch (\Throwable $e) {
            self::handleError('keys', $pattern, $e);
            return [];
        }
    }

    /**
     * Scan keys matching pattern (safer for large datasets)
     */
    private static function scanKeys(string $pattern, int $limit): array
    {
        $keys = [];
        $cursor = null;
        $scanCount = self::config('performance.scan_count', 1000);

        do {
            $result = Redis::scan($cursor, 'MATCH', $pattern, 'COUNT', $scanCount);
            $cursor = $result[0] ?? null;
            $foundKeys = $result[1] ?? [];

            $keys = array_merge($keys, $foundKeys);

            if (count($keys) >= $limit) {
                break;
            }
        } while ($cursor !== 0 && $cursor !== null);

        return array_slice($keys, 0, $limit);
    }

    /**
     * Clear all caches (use with caution!)
     */
    public static function flush(): bool
    {
        try {
            if (!self::isEnabled()) {
                return false;
            }

            Redis::flushDb();
            Log::warning("Redis cache flushed completely");

            return true;
        } catch (\Throwable $e) {
            self::handleError('flush', 'all', $e);
            return false;
        }
    }

    /**
     * Ping Redis connection
     */
    public static function ping(): bool
    {
        try {
            return Redis::ping() !== false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Encode data based on configuration
     */
    private static function encode(mixed $data): string
    {
        $method = self::config('encoding.method', 'serialize');

        return match ($method) {
            'json' => json_encode($data),
            'msgpack' => extension_loaded('msgpack') ? msgpack_pack($data) : serialize($data),
            'igbinary' => extension_loaded('igbinary') ? igbinary_serialize($data) : serialize($data),
            default => serialize($data),
        };
    }

    /**
     * Decode data based on prefix or configuration
     */
    private static function decode(string $data): mixed
    {
        // Check for compression
        if (str_starts_with($data, 'GZIP:')) {
            $data = self::decompress($data);
        }

        $method = self::config('encoding.method', 'serialize');

        try {
            return match ($method) {
                'json' => json_decode($data, true),
                'msgpack' => extension_loaded('msgpack') ? msgpack_unpack($data) : unserialize($data),
                'igbinary' => extension_loaded('igbinary') ? igbinary_unserialize($data) : unserialize($data),
                default => unserialize($data),
            };
        } catch (\Throwable $e) {
            Log::error("Failed to decode data: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compress data
     */
    private static function compress(string $data): string
    {
        $level = self::config('encoding.compress_level', 6);
        $method = self::config('encoding.compress_method', 'gzip');

        try {
            $compressed = match ($method) {
                'lz4' => extension_loaded('lz4') ? lz4_compress($data, $level) : gzcompress($data, $level),
                'zstd' => extension_loaded('zstd') ? zstd_compress($data, $level) : gzcompress($data, $level),
                default => gzcompress($data, $level),
            };

            return $compressed !== false ? 'GZIP:' . base64_encode($compressed) : $data;
        } catch (\Throwable $e) {
            Log::warning("Compression failed: " . $e->getMessage());
            return $data;
        }
    }

    /**
     * Decompress data
     */
    private static function decompress(string $data): string
    {
        try {
            $compressed = base64_decode(substr($data, 5));
            $decompressed = @gzuncompress($compressed);

            return $decompressed !== false ? $decompressed : $data;
        } catch (\Throwable $e) {
            Log::warning("Decompression failed: " . $e->getMessage());
            return $data;
        }
    }

    /**
     * Store chunked data
     */
    private static function putChunked(string $key, string $data, ?int $ttl): bool
    {
        $chunkSize = self::config('performance.chunk_size', 16777216);
        $chunks = str_split($data, $chunkSize);
        $chunkCount = count($chunks);

        $metadata = json_encode([
            'chunk_count' => $chunkCount,
            'size' => strlen($data),
            'created' => time(),
        ]);

        $ttl = $ttl ?? 3600;

        // Store metadata
        Redis::setex($key, $ttl, 'CHUNKED:' . $metadata);

        // Store chunks
        Redis::pipeline(function ($pipe) use ($key, $chunks, $ttl) {
            foreach ($chunks as $index => $chunk) {
                $pipe->setex("{$key}:chunk:{$index}", $ttl, $chunk);
            }
        });

        Log::info("Stored chunked data: {$key} ({$chunkCount} chunks)");

        return true;
    }

    /**
     * Get chunked data
     */
    private static function getChunked(string $key): mixed
    {
        $metadataRaw = Redis::get($key);
        if (!$metadataRaw) {
            return null;
        }

        $metadata = json_decode(substr($metadataRaw, 8), true);
        if (!isset($metadata['chunk_count'])) {
            return null;
        }

        $chunkKeys = [];
        for ($i = 0; $i < $metadata['chunk_count']; $i++) {
            $chunkKeys[] = "{$key}:chunk:{$i}";
        }

        $chunks = Redis::mget($chunkKeys);
        $data = implode('', array_filter($chunks));

        return self::decode($data);
    }

    /**
     * Forget chunked data
     */
    private static function forgetChunked(string $key): bool
    {
        $metadataRaw = Redis::get($key);
        if (!$metadataRaw) {
            return true;
        }

        $metadata = json_decode(substr($metadataRaw, 8), true);
        if (!isset($metadata['chunk_count'])) {
            return Redis::del($key) > 0;
        }

        $keys = [$key];
        for ($i = 0; $i < $metadata['chunk_count']; $i++) {
            $keys[] = "{$key}:chunk:{$i}";
        }

        return Redis::del(...$keys) > 0;
    }

    /**
     * Format bytes to human-readable
     */
    private static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Log cache miss
     */
    private static function logMiss(string $key): void
    {
        if (self::config('monitoring.log_misses', false)) {
            Log::debug("Cache miss: {$key}");
        }
    }

    /**
     * Log performance metrics
     */
    private static function logPerformance(string $operation, string $key, float $start, int $size = 0): void
    {
        $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
        $threshold = self::config('monitoring.slow_threshold_ms', 100);

        if ($duration > $threshold && self::config('monitoring.log_slow_operations', true)) {
            $sizeStr = $size > 0 ? ", size: " . self::formatBytes($size) : '';
            Log::warning("Slow Redis operation: {$operation} on {$key} took {$duration}ms{$sizeStr}");
        }
    }

    /**
     * Handle errors with logging and fallback
     */
    private static function handleError(string $operation, string $key, \Throwable $e): void
    {
        if (self::config('monitoring.alert_on_failure', true)) {
            Log::error("Redis {$operation} failed for key: {$key}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        if (self::config('fallback.log_fallbacks', true)) {
            Log::warning("Redis operation failed, using fallback for: {$key}");
        }
    }
}
