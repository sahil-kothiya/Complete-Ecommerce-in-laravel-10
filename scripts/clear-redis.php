<?php

$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

echo "Clearing ALL Redis cache...\n";
echo str_repeat('=', 80) . "\n\n";

// Get database info before flush
$info = $redis->info();
echo "Redis Database Info:\n";
echo "  Keys: " . ($redis->dbSize() ?? 'unknown') . "\n\n";

// Flush all keys in current database
$result = $redis->flushDB();

if ($result) {
    echo "✅ All Redis keys cleared!\n";
    echo "  Keys after flush: " . $redis->dbSize() . "\n";
} else {
    echo "❌ Failed to flush Redis\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "✅ Redis cache completely cleared!\n";
