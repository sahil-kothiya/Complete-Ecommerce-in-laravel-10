# ⚡ Ultra-Fast Filter Page Setup Script
# Run this after reading FILTER_PAGE_OPTIMIZATION_GUIDE.md

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "FILTER PAGE OPTIMIZATION - QUICK SETUP" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Set-Location D:\wamp64\www\Enterprice-Ecommerce

Write-Host "Step 1: Checking system status..." -ForegroundColor Yellow
Write-Host ""
php check-systems.php
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SETUP INSTRUCTIONS:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$redisIndexes = & php -r "require 'vendor/autoload.php'; `$app = require 'bootstrap/app.php'; `$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap(); echo count(Redis::keys('index:*'));"

if ($redisIndexes -eq "0") {
    Write-Host "[ACTION NEEDED] Build Redis indexes (5-10 minutes):" -ForegroundColor Red
    Write-Host "  php artisan indexes:manage build" -ForegroundColor White
    Write-Host ""
} else {
    Write-Host "[OK] Redis indexes built ($redisIndexes keys)" -ForegroundColor Green
    Write-Host ""
}

# Check if Elasticsearch is running
try {
    $esCheck = Invoke-WebRequest -Uri "http://localhost:9200" -UseBasicParsing -TimeoutSec 2 -ErrorAction Stop
    Write-Host "[OK] Elasticsearch is running" -ForegroundColor Green
    Write-Host ""

    # Check if products are indexed
    Write-Host "[ACTION NEEDED] Index products in Elasticsearch:" -ForegroundColor Yellow
    Write-Host "  php artisan elasticsearch:index-all --chunk=2000" -ForegroundColor White
    Write-Host "  (This takes 10-20 min for 100K products, one-time only)" -ForegroundColor Gray
    Write-Host ""
} catch {
    Write-Host "[ACTION NEEDED] Start Elasticsearch:" -ForegroundColor Red
    Write-Host "  1. Open NEW PowerShell window" -ForegroundColor White
    Write-Host "  2. Run: cd D:\elasticsearch-9.0.2\bin" -ForegroundColor White
    Write-Host "  3. Run: .\elasticsearch.bat" -ForegroundColor White
    Write-Host "  4. Wait for 'started' message" -ForegroundColor White
    Write-Host ""
}

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "AFTER COMPLETING SETUP:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Test your filter page - it should load in < 2 seconds!" -ForegroundColor Green
Write-Host ""
Write-Host "Check logs to see the optimization in action:" -ForegroundColor Yellow
Write-Host "  Get-Content storage\logs\laravel.log -Tail 20 | Select-String 'HybridFilter|FastFilter'" -ForegroundColor White
Write-Host ""
Write-Host "You should see lines like:" -ForegroundColor Yellow
Write-Host '  [2025-11-24] HybridFilter: Result {method: "redis_only", time_ms: 156}' -ForegroundColor Gray
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Read full guide: FILTER_PAGE_OPTIMIZATION_GUIDE.md" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
