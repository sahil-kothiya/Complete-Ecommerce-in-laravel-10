<?php

// Fix Redis write error
try {
    $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);

    // Disable stop-writes-on-bgsave-error
    $result = $redis->config('SET', 'stop-writes-on-bgsave-error', 'no');
    echo 'stop-writes-on-bgsave-error set to no: '.($result ? 'SUCCESS' : 'FAILED')."\n";

    // Optional: Disable RDB persistence entirely for development
    $result2 = $redis->config('SET', 'save', '');
    echo 'RDB persistence disabled: '.($result2 ? 'SUCCESS' : 'FAILED')."\n";

    echo "\nRedis configuration updated successfully!\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
}
