<?php
/**
 * Fix Redis MISCONF Error - Disable stop-writes-on-bgsave-error
 * This script disables the Redis configuration that prevents writes when background save fails
 */

try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    
    echo "Connected to Redis\n";
    
    // Disable stop-writes-on-bgsave-error to allow writes even if background save fails
    $result = $redis->config('SET', 'stop-writes-on-bgsave-error', 'no');
    
    if ($result) {
        echo "✓ Successfully disabled stop-writes-on-bgsave-error\n";
    } else {
        echo "✗ Failed to disable stop-writes-on-bgsave-error\n";
    }
    
    // Verify the change
    $config = $redis->config('GET', 'stop-writes-on-bgsave-error');
    echo "Current value: " . ($config['stop-writes-on-bgsave-error'] ?? 'unknown') . "\n";
    
    // Optional: Clear any corrupt data
    echo "\nClearing session and cache data...\n";
    $redis->flushDb();
    echo "✓ Redis database cleared\n";
    
    echo "\n✓ Redis is now ready to accept writes\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
