<?php

// Fix Redis configuration for large dataset operations

try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    echo "Fixing Redis configuration for 10M products...\n\n";

    // Disable stop-writes-on-bgsave-error
    $redis->config('SET', 'stop-writes-on-bgsave-error', 'no');
    echo "✅ stop-writes-on-bgsave-error = no\n";

    // Increase max memory (optional, adjust based on your system)
    try {
        $redis->config('SET', 'maxmemory', '2gb');
        echo "✅ maxmemory = 2gb\n";
    } catch (Exception $e) {
        echo "⚠️  Could not set maxmemory (may require redis.conf edit)\n";
    }

    // Set eviction policy for when memory is full
    try {
        $redis->config('SET', 'maxmemory-policy', 'allkeys-lru');
        echo "✅ maxmemory-policy = allkeys-lru\n";
    } catch (Exception $e) {
        echo "⚠️  Could not set eviction policy\n";
    }

    echo "\n✅ Redis is now configured for large datasets!\n";
    echo "You can now run: php -d memory_limit=2G artisan indexes:manage build\n";

} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
    echo "\nAlternative solution:\n";
    echo "1. Find redis.conf file (usually in Redis installation directory)\n";
    echo "2. Edit: stop-writes-on-bgsave-error no\n";
    echo "3. Restart Redis service\n";
}
