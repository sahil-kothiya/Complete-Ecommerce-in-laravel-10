<?php
// Test Redis write operations after fix
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);

echo "Testing Redis...\n";
echo "Ping: " . $redis->ping() . "\n";

// Test write
$redis->set('test_key', 'test_value', 10);
$value = $redis->get('test_key');

if ($value === 'test_value') {
    echo "✓ Write test: SUCCESS\n";
    echo "✓ Redis is working properly!\n";
} else {
    echo "✗ Write test: FAILED\n";
}
