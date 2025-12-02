# ========================================
# Filter System Test Script
# ========================================
# 
# Tests the robust filter system end-to-end
#
# ========================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Filter System Test Suite" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$baseUrl = "http://127.0.0.1:8000"
$testsPassed = 0
$testsFailed = 0

function Test-FilterEndpoint {
    param(
        [string]$testName,
        [string]$url,
        [int]$maxTimeMs = 5000
    )
    
    Write-Host "Testing: $testName" -ForegroundColor Yellow
    Write-Host "  URL: $url" -ForegroundColor Gray
    
    try {
        $sw = [System.Diagnostics.Stopwatch]::StartNew()
        $response = Invoke-WebRequest -Uri $url -Method GET -TimeoutSec 30 -ErrorAction Stop
        $sw.Stop()
        
        $timeMs = [math]::Round($sw.Elapsed.TotalMilliseconds, 2)
        $statusCode = $response.StatusCode
        
        if ($statusCode -eq 200) {
            $json = $response.Content | ConvertFrom-Json
            
            if ($json.ok -eq $true) {
                $productCount = $json.p.Count
                $totalProducts = $json.m.tot
                
                Write-Host "  ✅ PASS" -ForegroundColor Green
                Write-Host "     Status: $statusCode" -ForegroundColor Gray
                Write-Host "     Time: ${timeMs}ms" -ForegroundColor Gray
                Write-Host "     Products: $productCount (Total: $totalProducts)" -ForegroundColor Gray
                Write-Host "     Source: $($json.m.src)" -ForegroundColor Gray
                
                if ($timeMs -gt $maxTimeMs) {
                    Write-Host "     ⚠️  Warning: Response time exceeds ${maxTimeMs}ms threshold" -ForegroundColor Yellow
                }
                
                return $true
            } else {
                Write-Host "  ❌ FAIL: Response ok=false" -ForegroundColor Red
                Write-Host "     $($json | ConvertTo-Json -Depth 3)" -ForegroundColor Gray
                return $false
            }
        } else {
            Write-Host "  ❌ FAIL: HTTP $statusCode" -ForegroundColor Red
            return $false
        }
    }
    catch {
        Write-Host "  ❌ FAIL: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
    finally {
        Write-Host ""
    }
}

# Test 1: Health Check
Write-Host "[Test 1] Index Health Check" -ForegroundColor Cyan
Write-Host ""
php artisan indexes:health
Write-Host ""

# Test 2: No filters (recent products)
if (Test-FilterEndpoint `
    -testName "No filters (recent products)" `
    -url "$baseUrl/api/filters" `
    -maxTimeMs 1000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 3: Brand filter (HP)
if (Test-FilterEndpoint `
    -testName "Brand filter: HP" `
    -url "$baseUrl/api/filters?brands=hp" `
    -maxTimeMs 2000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 4: Multiple brands
if (Test-FilterEndpoint `
    -testName "Multiple brands: HP + Dell" `
    -url "$baseUrl/api/filters?brands=hp,dell" `
    -maxTimeMs 2000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 5: Price range filter
if (Test-FilterEndpoint `
    -testName "Price range: 100-500" `
    -url "$baseUrl/api/filters?price_range=100-500" `
    -maxTimeMs 2000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 6: Combined filters
if (Test-FilterEndpoint `
    -testName "Combined: Brand + Price" `
    -url "$baseUrl/api/filters?brands=hp&price_range=100-500" `
    -maxTimeMs 3000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 7: Sorting - Price Low to High
if (Test-FilterEndpoint `
    -testName "Sort: Price Low to High" `
    -url "$baseUrl/api/filters?brands=hp&sortBy=price_low_high" `
    -maxTimeMs 3000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 8: Sorting - Rating High to Low
if (Test-FilterEndpoint `
    -testName "Sort: Rating High to Low" `
    -url "$baseUrl/api/filters?brands=hp&sortBy=rating_high_low" `
    -maxTimeMs 3000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 9: Pagination
if (Test-FilterEndpoint `
    -testName "Pagination: Page 2, 24 items" `
    -url "$baseUrl/api/filters?brands=hp&page=2&show=24" `
    -maxTimeMs 3000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 10: Discount filter
if (Test-FilterEndpoint `
    -testName "Discount: 25%+ off" `
    -url "$baseUrl/api/filters?discounts=25" `
    -maxTimeMs 3000) {
    $testsPassed++
} else {
    $testsFailed++
}

# Test 11: Simulate missing index (if Redis available)
Write-Host "[Test 11] Testing resilience (missing index)" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Checking if we can simulate index removal..." -ForegroundColor Yellow

try {
    # Try to delete a brand index temporarily
    $redisCheck = php artisan tinker --execute="Redis::ping() ? exit(0) : exit(1);" 2>$null
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  Redis is available. Testing missing index scenario..." -ForegroundColor Gray
        
        # Delete HP brand index temporarily
        php artisan tinker --execute="Redis::del('index:brand:42'); exit(0);" 2>$null
        
        # Test HP filter (should auto-rebuild)
        if (Test-FilterEndpoint `
            -testName "HP filter with missing index (auto-rebuild)" `
            -url "$baseUrl/api/filters?brands=hp" `
            -maxTimeMs 10000) {
            $testsPassed++
            Write-Host "  ✅ System auto-recovered from missing index!" -ForegroundColor Green
        } else {
            $testsFailed++
            Write-Host "  ❌ Failed to recover from missing index" -ForegroundColor Red
        }
    } else {
        Write-Host "  ⏭️  Redis not available, skipping resilience test" -ForegroundColor Yellow
        Write-Host ""
    }
}
catch {
    Write-Host "  ⚠️  Could not perform resilience test: $($_.Exception.Message)" -ForegroundColor Yellow
    Write-Host ""
}

# Summary
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Test Results" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$totalTests = $testsPassed + $testsFailed
$passRate = if ($totalTests -gt 0) { [math]::Round(($testsPassed / $totalTests) * 100, 2) } else { 0 }

Write-Host "Total Tests: $totalTests" -ForegroundColor White
Write-Host "Passed: $testsPassed" -ForegroundColor Green
Write-Host "Failed: $testsFailed" -ForegroundColor Red
Write-Host "Pass Rate: ${passRate}%" -ForegroundColor $(if ($passRate -ge 80) { "Green" } else { "Yellow" })
Write-Host ""

if ($testsFailed -eq 0) {
    Write-Host "🎉 All tests passed! Filter system is working correctly." -ForegroundColor Green
} elseif ($testsPassed -gt 0) {
    Write-Host "⚠️  Some tests failed. Review failures above." -ForegroundColor Yellow
} else {
    Write-Host "❌ All tests failed. Check system configuration." -ForegroundColor Red
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Recommendations" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($testsFailed -gt 0) {
    Write-Host "If tests failed, try:" -ForegroundColor Yellow
    Write-Host "  1. Check index health: php artisan indexes:health" -ForegroundColor Gray
    Write-Host "  2. Rebuild indexes: php -d memory_limit=2G artisan indexes:manage build --force" -ForegroundColor Gray
    Write-Host "  3. Check Laravel logs: Get-Content storage\logs\laravel.log -Tail 100" -ForegroundColor Gray
    Write-Host "  4. Verify Redis: redis-cli ping" -ForegroundColor Gray
    Write-Host ""
}

Write-Host "Performance Guidelines:" -ForegroundColor Cyan
Write-Host "  • With Redis indexes: < 500ms" -ForegroundColor Gray
Write-Host "  • Database fallback: 1-3 seconds" -ForegroundColor Gray
Write-Host "  • Complex filters: < 3 seconds" -ForegroundColor Gray
Write-Host ""

Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "  • Test in production environment" -ForegroundColor Gray
Write-Host "  • Monitor performance with real traffic" -ForegroundColor Gray
Write-Host "  • Set up scheduled index maintenance" -ForegroundColor Gray
Write-Host "  • Configure monitoring alerts" -ForegroundColor Gray
Write-Host ""

exit $testsFailed
