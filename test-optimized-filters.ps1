# Test Optimized Filter System
Write-Host "🔧 Testing Optimized Filter Cache System" -ForegroundColor Cyan
Write-Host ""

# Clear old caches
Write-Host "1. Clearing old caches..." -ForegroundColor Yellow
php artisan cache:clear
redis-cli FLUSHDB

Write-Host ""
Write-Host "2. Building optimized filter cache..." -ForegroundColor Yellow
php artisan filters:optimize --clear

Write-Host ""
Write-Host "3. Testing filter API response time..." -ForegroundColor Yellow

# Test 1: Category filter
Write-Host "   → Category filter test..."
$url1 = "http://127.0.0.1:8000/api/filters/electronics"
$response1 = Measure-Command { Invoke-WebRequest -Uri $url1 -Method GET }
Write-Host "      Time: $($response1.TotalMilliseconds)ms" -ForegroundColor Green

# Test 2: Price filter
Write-Host "   → Price filter test..."
$url2 = "http://127.0.0.1:8000/api/filters/electronics?price_range=100-1000"
$response2 = Measure-Command { Invoke-WebRequest -Uri $url2 -Method GET }
Write-Host "      Time: $($response2.TotalMilliseconds)ms" -ForegroundColor Green

# Test 3: Combined filters
Write-Host "   → Combined filters test..."
$url3 = "http://127.0.0.1:8000/api/filters/electronics?price_range=100-1000&sortBy=price_high_low"
$response3 = Measure-Command { Invoke-WebRequest -Uri $url3 -Method GET }
Write-Host "      Time: $($response3.TotalMilliseconds)ms" -ForegroundColor Green

Write-Host ""
Write-Host "4. Checking Redis keys..." -ForegroundColor Yellow
redis-cli KEYS "ecommerce:v1:filter:*" | Measure-Object -Line | Select-Object -ExpandProperty Lines | ForEach-Object {
    Write-Host "   → Found $_ optimized filter keys" -ForegroundColor Green
}

Write-Host ""
Write-Host "✅ Test complete!" -ForegroundColor Green
Write-Host ""
Write-Host "Expected improvements:" -ForegroundColor Cyan
Write-Host "  • Filter metadata: < 10ms (was 200ms+)" -ForegroundColor White
Write-Host "  • Price filtering: < 50ms (was slow)" -ForegroundColor White
Write-Host "  • Combined filters: < 100ms" -ForegroundColor White
