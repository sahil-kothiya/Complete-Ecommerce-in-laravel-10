# ========================================
# PERFORMANCE TEST SCRIPT
# Run this after implementing optimizations
# ========================================

Write-Host "🚀 HOMEPAGE PERFORMANCE TEST SUITE" -ForegroundColor Cyan
Write-Host "===================================" -ForegroundColor Cyan
Write-Host ""

# 1. Clear all caches
Write-Host "1️⃣  Clearing all caches..." -ForegroundColor Yellow
php artisan optimize:clear
php artisan cache:clear
php artisan view:clear
php artisan config:clear
php artisan route:clear
Write-Host "✅ Caches cleared" -ForegroundColor Green
Write-Host ""

# 2. Check Redis connection
Write-Host "2️⃣  Checking Redis connection..." -ForegroundColor Yellow
try {
    $redisTest = redis-cli ping 2>&1
    if ($redisTest -match "PONG") {
        Write-Host "✅ Redis is running" -ForegroundColor Green
    } else {
        Write-Host "❌ Redis is not responding" -ForegroundColor Red
        Write-Host "   Start Redis with: redis-server" -ForegroundColor Yellow
    }
} catch {
    Write-Host "⚠️  Redis CLI not found (optional)" -ForegroundColor Yellow
}
Write-Host ""

# 3. Check Apache modules
Write-Host "3️⃣  Verifying Apache configuration..." -ForegroundColor Yellow
$htaccessPath = ".\public\.htaccess"
if (Test-Path $htaccessPath) {
    Write-Host "✅ .htaccess found" -ForegroundColor Green
} else {
    Write-Host "❌ .htaccess not found!" -ForegroundColor Red
}
Write-Host ""

# 4. Check middleware files
Write-Host "4️⃣  Checking middleware files..." -ForegroundColor Yellow
$middlewareFiles = @(
    ".\app\Http\Middleware\CompressResponse.php",
    ".\app\Http\Middleware\OptimizePerformance.php"
)
foreach ($file in $middlewareFiles) {
    if (Test-Path $file) {
        Write-Host "✅ $(Split-Path $file -Leaf) exists" -ForegroundColor Green
    } else {
        Write-Host "❌ $(Split-Path $file -Leaf) missing!" -ForegroundColor Red
    }
}
Write-Host ""

# 5. Test homepage response
Write-Host "5️⃣  Testing homepage response..." -ForegroundColor Yellow
$siteUrl = "http://localhost/Enterprice-Ecommerce/public"
Write-Host "   URL: $siteUrl" -ForegroundColor Cyan

try {
    $response = Invoke-WebRequest -Uri $siteUrl -Headers @{"Accept-Encoding"="gzip,deflate"} -TimeoutSec 10
    
    Write-Host "✅ Site is accessible" -ForegroundColor Green
    Write-Host "   Status Code: $($response.StatusCode)" -ForegroundColor Cyan
    Write-Host "   Content Length: $($response.RawContentLength) bytes" -ForegroundColor Cyan
    
    # Check for compression
    if ($response.Headers["Content-Encoding"]) {
        Write-Host "✅ Compression ENABLED: $($response.Headers['Content-Encoding'])" -ForegroundColor Green
    } else {
        Write-Host "⚠️  Compression NOT detected" -ForegroundColor Yellow
        Write-Host "   This might be normal for localhost. Test on production." -ForegroundColor Gray
    }
    
    # Check cache headers
    if ($response.Headers["Cache-Control"]) {
        Write-Host "✅ Cache headers present: $($response.Headers['Cache-Control'])" -ForegroundColor Green
    }
    
} catch {
    Write-Host "❌ Failed to connect: $_" -ForegroundColor Red
    Write-Host "   Make sure WAMP is running and the URL is correct" -ForegroundColor Yellow
}
Write-Host ""

# 6. Warm up cache
Write-Host "6️⃣  Warming up homepage cache..." -ForegroundColor Yellow
try {
    $warmup1 = Invoke-WebRequest -Uri $siteUrl -TimeoutSec 10
    Start-Sleep -Seconds 1
    $warmup2 = Invoke-WebRequest -Uri $siteUrl -TimeoutSec 10
    Write-Host "✅ Cache warmed up (2 requests made)" -ForegroundColor Green
} catch {
    Write-Host "⚠️  Cache warmup failed: $_" -ForegroundColor Yellow
}
Write-Host ""

# 7. Performance summary
Write-Host "📊 PERFORMANCE TEST SUMMARY" -ForegroundColor Cyan
Write-Host "============================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Yellow
Write-Host "1. Restart WAMP server (stop then start)" -ForegroundColor White
Write-Host "2. Clear browser cache (Ctrl+Shift+Delete)" -ForegroundColor White
Write-Host "3. Run Lighthouse test in Chrome DevTools" -ForegroundColor White
Write-Host "4. Target Score: 95-100" -ForegroundColor White
Write-Host ""
Write-Host "Compression Test:" -ForegroundColor Yellow
Write-Host "curl -I -H `"Accept-Encoding: gzip`" $siteUrl" -ForegroundColor Gray
Write-Host ""
Write-Host "Expected Lighthouse Improvements:" -ForegroundColor Yellow
Write-Host "- First Contentful Paint: under 1.0s" -ForegroundColor White
Write-Host "- Largest Contentful Paint: under 1.5s" -ForegroundColor White
Write-Host "- Total Blocking Time: under 100ms" -ForegroundColor White
Write-Host "- Cumulative Layout Shift: under 0.1" -ForegroundColor White
Write-Host "- Performance Score: 95-100" -ForegroundColor Green
Write-Host ""
Write-Host "Test complete!" -ForegroundColor Green
