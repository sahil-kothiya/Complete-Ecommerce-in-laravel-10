<?php

/**
 * Test Filter API with various combinations
 * Tests the fixed UltraFastFilterController
 */

$baseUrl = 'http://127.0.0.1:8000/api/filters';

// Test cases
$tests = [
    [
        'name' => 'Test 1: Category Only (Electronics)',
        'url' => '/ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9',
        'params' => ['page' => 1, 'show' => 12],
    ],
    [
        'name' => 'Test 2: Category + Brand (HP)',
        'url' => '/ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9',
        'params' => ['brands' => 'hp', 'page' => 1, 'show' => 12],
    ],
    [
        'name' => 'Test 3: Category + Brand + Price Range (0-500)',
        'url' => '/ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9',
        'params' => ['brands' => 'hp', 'price_range' => '0-500', 'page' => 1, 'show' => 12],
    ],
    [
        'name' => 'Test 4: Category + Brand + Price + Sort (Price High to Low)',
        'url' => '/ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9',
        'params' => ['brands' => 'hp', 'price_range' => '0-500', 'sortBy' => 'price_high_low', 'page' => 1, 'show' => 12],
    ],
    [
        'name' => 'Test 5: Category + Sort (Latest)',
        'url' => '/ZXlKcGRpSTZJbVpJTWtjMWRXRk9ObFJHT0RWakswOTJTR0Z3WVZFOVBTSXNJblpoYkhWbElqb2lNbmgwV2xSM2JIbERjbGd5WmxwcVpUZ3pkR1E0Wldkd1pHSjFUV2tyWTFsQlpVNW5VMlJMV2pSWlNUMGlMQ0p0WVdNaU9pSTFNakZrTXpjeU1XVTFPR1JpTkRaaE1qQXpOek0zWmpnM09EazNNek13TURWbU56VmpObUV6TWpRMU4yUTRZMlkwTmpJMU5XSTRNRGMwT0dSbE1tWmtJaXdpZEdGbklqb2lJbjA9',
        'params' => ['sortBy' => 'latest', 'page' => 1, 'show' => 12],
    ],
];

echo "=== FILTER API PERFORMANCE TEST ===\n\n";
echo "Server: $baseUrl\n";
echo "Total Tests: " . count($tests) . "\n\n";

$results = [];

foreach ($tests as $i => $test) {
    echo str_repeat('=', 70) . "\n";
    echo "TEST " . ($i + 1) . ": " . $test['name'] . "\n";
    echo str_repeat('=', 70) . "\n";
    
    $url = $baseUrl . $test['url'];
    if (!empty($test['params'])) {
        $url .= '?' . http_build_query($test['params']);
    }
    
    echo "URL: $url\n\n";
    
    $start = microtime(true);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 130); // Allow 130s timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    $elapsed = round((microtime(true) - $start) * 1000, 2);
    
    if ($curlError) {
        echo "❌ CURL ERROR: $curlError\n";
        $results[] = ['test' => $test['name'], 'status' => 'FAILED', 'time_ms' => $elapsed, 'error' => $curlError];
        continue;
    }
    
    if ($httpCode !== 200) {
        echo "❌ HTTP ERROR: $httpCode\n";
        echo "Response: " . substr($response, 0, 500) . "\n";
        $results[] = ['test' => $test['name'], 'status' => 'FAILED', 'time_ms' => $elapsed, 'http_code' => $httpCode];
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "❌ JSON DECODE ERROR: " . json_last_error_msg() . "\n";
        echo "Raw response: " . substr($response, 0, 500) . "\n";
        $results[] = ['test' => $test['name'], 'status' => 'FAILED', 'time_ms' => $elapsed, 'error' => 'JSON decode failed'];
        continue;
    }
    
    echo "✅ SUCCESS\n";
    echo "Response Time: {$elapsed} ms\n";
    echo "Total Products: " . ($data['total'] ?? 0) . "\n";
    echo "Products Returned: " . count($data['products'] ?? []) . "\n";
    echo "Source: " . ($data['source'] ?? 'unknown') . "\n";
    echo "Cached: " . (($data['cached'] ?? false) ? 'YES' : 'NO') . "\n";
    
    if (isset($data['performance'])) {
        echo "\nPerformance Breakdown:\n";
        echo "  - Redis: " . ($data['performance']['redis_ms'] ?? 'N/A') . " ms\n";
        echo "  - Sort: " . ($data['performance']['sort_ms'] ?? 'N/A') . " ms\n";
        echo "  - Total: " . ($data['performance']['total_ms'] ?? 'N/A') . " ms\n";
    }
    
    $results[] = [
        'test' => $test['name'],
        'status' => 'SUCCESS',
        'time_ms' => $elapsed,
        'total' => $data['total'] ?? 0,
        'returned' => count($data['products'] ?? []),
        'source' => $data['source'] ?? 'unknown',
    ];
    
    echo "\n";
}

// Summary
echo "\n" . str_repeat('=', 70) . "\n";
echo "SUMMARY\n";
echo str_repeat('=', 70) . "\n\n";

$passed = count(array_filter($results, fn($r) => $r['status'] === 'SUCCESS'));
$failed = count($results) - $passed;
$avgTime = $passed > 0 ? round(array_sum(array_column(array_filter($results, fn($r) => $r['status'] === 'SUCCESS'), 'time_ms')) / $passed, 2) : 0;

echo "Total Tests: " . count($results) . "\n";
echo "Passed: $passed ✅\n";
echo "Failed: $failed " . ($failed > 0 ? '❌' : '✅') . "\n";
echo "Average Response Time: {$avgTime} ms\n\n";

if ($passed > 0) {
    echo "Individual Results:\n";
    foreach ($results as $i => $result) {
        $status = $result['status'] === 'SUCCESS' ? '✅' : '❌';
        echo ($i + 1) . ". $status {$result['test']} - {$result['time_ms']} ms\n";
    }
}

echo "\n";

if ($failed === 0 && $avgTime < 200) {
    echo "🎉 ALL TESTS PASSED! System is optimized and working as expected.\n";
} elseif ($failed === 0) {
    echo "⚠️ All tests passed but average response time is {$avgTime} ms (target: <200ms)\n";
} else {
    echo "❌ Some tests failed. Check errors above.\n";
}
