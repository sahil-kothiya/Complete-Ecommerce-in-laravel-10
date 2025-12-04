<?php

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Helpers\UrlEncryptor;

// Test encrypted path from URL
$encryptedPath = 'ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9';

echo "=== URL Decryption Test ===\n\n";
echo "Encrypted Path: $encryptedPath\n\n";

try {
    $decrypted = UrlEncryptor::decodePath($encryptedPath);
    echo "Decrypted Path: $decrypted\n\n";

    // Find category
    $category = \App\Models\Category::where('slug', $decrypted)->first();

    if (! $category && is_numeric($decrypted)) {
        $category = \App\Models\Category::find($decrypted);
    }

    if ($category) {
        echo "✅ Category Found:\n";
        echo "  ID: {$category->id}\n";
        echo "  Slug: {$category->slug}\n";
        echo "  Title: {$category->title}\n\n";

        // Check Redis index for this category
        $indexKey = \App\Services\RedisKeyManager::indexCategory($category->id);
        echo "Redis Index Key: $indexKey\n";

        $count = \Illuminate\Support\Facades\Redis::connection()->scard($indexKey);
        echo "Product Count in Index: $count\n";

        if ($count > 0) {
            echo "\n✅ Index has products! Let's sample 5:\n";
            $sample = \Illuminate\Support\Facades\Redis::connection()->srandmember($indexKey, 5);
            print_r($sample);
        } else {
            echo "\n❌ Index is empty! Run: php artisan indexes:manage build\n";
        }
    } else {
        echo "❌ Category not found for: $decrypted\n";

        echo "\nAll categories in database:\n";
        $categories = \App\Models\Category::select('id', 'slug', 'title')->get();
        foreach ($categories as $cat) {
            echo "  - ID: {$cat->id}, Slug: {$cat->slug}, Title: {$cat->title}\n";
        }
    }
} catch (\Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}
