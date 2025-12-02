# PERFORMANCE TEST SCRIPT
# Run this after implementing optimizations

Write-Host "HOMEPAGE PERFORMANCE TEST SUITE" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host ""

# 1. Clear all caches
Write-Host "1. Clearing all caches..." -ForegroundColor Yellow
php artisan optimize:clear
Write-Host "Caches cleared" -ForegroundColor Green
Write-Host ""

# 2. Check middleware files
Write-Host "2. Checking middleware files..." -ForegroundColor Yellow
$middlewareFiles = @(
    ".\app\Http\Middleware\CompressResponse.php",
    ".\app\Http\Middleware\OptimizePerformance.php"
)
foreach ($file in $middlewareFiles) {
    if (Test-Path $file) {
        Write-Host "Found: $(Split-Path $file -Leaf)" -ForegroundColor Green
    } else {
        Write-Host "Missing: $(Split-Path $file -Leaf)" -ForegroundColor Red
    }
}
Write-Host ""

# 3. Test homepage response
Write-Host "3. Testing homepage..." -ForegroundColor Yellow
$siteUrl = "http://localhost/Enterprice-Ecommerce/public"
Write-Host "URL: $siteUrl" -ForegroundColor Cyan

try {
    $response = Invoke-WebRequest -Uri $siteUrl -Headers @{"Accept-Encoding"="gzip,deflate"} -TimeoutSec 10
    
    Write-Host "Site accessible - Status: $($response.StatusCode)" -ForegroundColor Green
    Write-Host "Content Length: $($response.RawContentLength) bytes" -ForegroundColor Cyan
    
    if ($response.Headers["Content-Encoding"]) {
        Write-Host "Compression ENABLED: $($response.Headers['Content-Encoding'])" -ForegroundColor Green
    } else {
        Write-Host "Compression not detected (normal for localhost)" -ForegroundColor Yellow
    }
    
} catch {
    Write-Host "Failed to connect. Make sure WAMP is running." -ForegroundColor Red
}
Write-Host ""

Write-Host "NEXT STEPS:" -ForegroundColor Cyan
Write-Host "1. Restart WAMP server" -ForegroundColor White
Write-Host "2. Clear browser cache" -ForegroundColor White
Write-Host "3. Run Lighthouse test in Chrome" -ForegroundColor White
Write-Host "4. Target Score: 95-100" -ForegroundColor White
Write-Host ""
Write-Host "Test complete!" -ForegroundColor Green
