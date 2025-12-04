<?php
/**
 * Clear Response Cache
 * 
 * Clears all cached HTTP responses from Redis
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Redis;

echo "🧹 Clearing Response Cache...\n";

try {
    // Get all response cache keys
    $pattern = 'response_cache:*';
    $keys = Redis::keys($pattern);
    
    if (empty($keys)) {
        echo "✓ No response cache keys found\n";
    } else {
        $count = count($keys);
        echo "Found {$count} cached responses\n";
        
        // Delete all response cache keys
        foreach ($keys as $key) {
            Redis::del($key);
        }
        
        echo "✓ Deleted {$count} cached responses\n";
    }
    
    echo "\n✓ Response cache cleared successfully!\n";
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
