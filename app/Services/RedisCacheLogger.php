<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class RedisCacheLogger
{
    private static $logFile = 'storage/logs/redis-cache-operations.log';

    /**
     * Log cache PUT operation
     */
    public static function logPut(string $key, $data, int $ttl, string $context = '')
    {
        $logEntry = [
            'timestamp' => now()->toIso8601String(),
            'operation' => 'PUT',
            'context' => $context,
            'key' => $key,
            'ttl' => $ttl,
            'data_type' => gettype($data),
            'data_structure' => self::analyzeDataStructure($data),
            'serialized_size' => strlen(serialize($data)),
        ];

        self::writeLog($logEntry);

        return $logEntry;
    }

    /**
     * Log cache GET operation
     */
    public static function logGet(string $key, $data, bool $found, string $context = '')
    {
        $logEntry = [
            'timestamp' => now()->toIso8601String(),
            'operation' => 'GET',
            'context' => $context,
            'key' => $key,
            'found' => $found,
            'data_type' => $found ? gettype($data) : null,
            'data_structure' => $found ? self::analyzeDataStructure($data) : null,
        ];

        self::writeLog($logEntry);

        return $logEntry;
    }

    /**
     * Log product transformation
     */
    public static function logProductTransform(int $productId, $product, $transformed, string $stage = 'warmup')
    {
        $logEntry = [
            'timestamp' => now()->toIso8601String(),
            'operation' => 'TRANSFORM',
            'stage' => $stage,
            'product_id' => $productId,
            'has_variants' => $product->has_variants,
            'product_images_count' => $product->images->count(),
            'variants_count' => $product->variants->count(),
            'transformed_images_count' => count($transformed->images),
            'transformed_images' => $transformed->images,
            'first_image_path' => $transformed->images[0]['image_path'] ?? 'none',
            'is_fallback' => isset($transformed->images[0]['image_path']) &&
                           strpos($transformed->images[0]['image_path'], 'no-image.png') !== false,
        ];

        self::writeLog($logEntry);

        return $logEntry;
    }

    /**
     * Analyze data structure
     */
    private static function analyzeDataStructure($data)
    {
        if (is_array($data)) {
            return [
                'type' => 'array',
                'count' => count($data),
                'first_item_type' => !empty($data) ? gettype($data[0]) : null,
                'sample' => !empty($data) ? self::getSample($data[0]) : null,
            ];
        }

        if (is_object($data)) {
            $props = get_object_vars($data);
            return [
                'type' => 'object',
                'class' => get_class($data),
                'properties' => array_keys($props),
                'sample' => self::getSample($data),
            ];
        }

        return ['type' => gettype($data), 'value' => $data];
    }

    /**
     * Get sample data for logging
     */
    private static function getSample($data)
    {
        if (is_object($data)) {
            $sample = [];
            $props = get_object_vars($data);
            foreach (array_slice(array_keys($props), 0, 5) as $key) {
                $value = $props[$key];
                if (is_array($value) || is_object($value)) {
                    $sample[$key] = gettype($value) . '(' . (is_countable($value) ? count($value) : 0) . ')';
                } else {
                    $sample[$key] = $value;
                }
            }
            return $sample;
        }

        return $data;
    }

    /**
     * Write log entry to file
     */
    private static function writeLog(array $entry)
    {
        $logPath = base_path(self::$logFile);
        $logLine = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n" . str_repeat('-', 80) . "\n";

        File::append($logPath, $logLine);
    }

    /**
     * Clear log file
     */
    public static function clearLog()
    {
        $logPath = base_path(self::$logFile);
        if (File::exists($logPath)) {
            File::delete($logPath);
        }

        $header = "Redis Cache Operations Log\n";
        $header .= "Started: " . now()->toDateTimeString() . "\n";
        $header .= str_repeat('=', 80) . "\n\n";

        File::put($logPath, $header);
    }

    /**
     * Analyze log for issues
     */
    public static function analyzeLog()
    {
        $logPath = base_path(self::$logFile);

        if (!File::exists($logPath)) {
            return ['error' => 'Log file not found'];
        }

        $content = File::get($logPath);
        $entries = explode(str_repeat('-', 80), $content);

        $analysis = [
            'total_operations' => 0,
            'put_operations' => 0,
            'get_operations' => 0,
            'transform_operations' => 0,
            'fallback_images' => [],
            'issues' => [],
        ];

        foreach ($entries as $entry) {
            $entry = trim($entry);
            if (empty($entry) || strpos($entry, 'Redis Cache Operations Log') !== false) {
                continue;
            }

            $data = json_decode($entry, true);
            if (!$data || !isset($data['operation'])) {
                continue;
            }

            $analysis['total_operations']++;

            switch ($data['operation']) {
                case 'PUT':
                    $analysis['put_operations']++;
                    break;
                case 'GET':
                    $analysis['get_operations']++;
                    break;
                case 'TRANSFORM':
                    $analysis['transform_operations']++;
                    if ($data['is_fallback'] ?? false) {
                        $analysis['fallback_images'][] = [
                            'product_id' => $data['product_id'],
                            'stage' => $data['stage'],
                            'has_variants' => $data['has_variants'],
                            'product_images_count' => $data['product_images_count'],
                            'variants_count' => $data['variants_count'],
                        ];
                    }
                    break;
            }
        }

        return $analysis;
    }
}
