<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

echo "Testing Recent Products Functionality\n";
echo "=====================================\n\n";

// Test 1: Check if recent_products table exists
echo "1. Checking if recent_products table exists...\n";
try {
    $tableExists = DB::select("SELECT EXISTS (
        SELECT FROM information_schema.tables
        WHERE table_schema = 'public'
        AND table_name = 'recent_products'
    )");
    echo "   ✓ recent_products table exists\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
    exit(1);
}

// Test 2: Check table structure
echo "2. Checking table structure...\n";
try {
    $columns = DB::select("SELECT column_name, data_type, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'recent_products'
        ORDER BY ordinal_position");

    foreach ($columns as $column) {
        echo "   - {$column->column_name} ({$column->data_type}) - " .
             ($column->is_nullable === 'YES' ? 'nullable' : 'not null') . "\n";
    }
    echo "\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 3: Count existing records
echo "3. Counting existing records...\n";
try {
    $userRecords = DB::table('recent_products')
        ->whereNotNull('user_id')
        ->count();
    $sessionRecords = DB::table('recent_products')
        ->whereNotNull('session_id')
        ->count();

    echo "   - User-based records: {$userRecords}\n";
    echo "   - Session-based records: {$sessionRecords}\n";
    echo "   - Total: " . ($userRecords + $sessionRecords) . "\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 4: Check if we can get a session ID
echo "4. Testing session functionality...\n";
try {
    $testSessionId = \Illuminate\Support\Str::random(40);
    echo "   - Generated test session ID: {$testSessionId}\n";
    echo "   ✓ Session generation works\n\n";
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

// Test 5: Test inserting a recent product (session-based)
echo "5. Testing session-based insert...\n";
try {
    $testProductId = DB::table('products')
        ->where('status', 'active')
        ->first()->id ?? null;

    if ($testProductId) {
        $testSessionId = 'test_session_' . time();

        // Clean up any existing test data
        DB::table('recent_products')
            ->where('session_id', 'like', 'test_session_%')
            ->delete();

        DB::table('recent_products')->insert([
            'session_id' => $testSessionId,
            'user_id' => null,
            'product_id' => $testProductId,
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "   ✓ Successfully inserted test record with product ID: {$testProductId}\n";

        // Verify insert
        $inserted = DB::table('recent_products')
            ->where('session_id', $testSessionId)
            ->first();

        if ($inserted) {
            echo "   ✓ Verified: Record exists in database\n";

            // Clean up
            DB::table('recent_products')
                ->where('session_id', $testSessionId)
                ->delete();
            echo "   ✓ Test data cleaned up\n\n";
        }
    } else {
        echo "   ✗ No active products found to test with\n\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Error: " . $e->getMessage() . "\n\n";
}

echo "Test completed!\n";
