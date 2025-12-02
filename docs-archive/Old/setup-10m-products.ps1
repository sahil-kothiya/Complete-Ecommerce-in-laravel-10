Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "AUTOMATED SETUP FOR 10M PRODUCTS" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan

Set-Location D:\wamp64\www\Enterprice-Ecommerce

Write-Host "Step 1: Fixing Redis configuration..." -ForegroundColor Yellow
php fix-redis-config.php
Write-Host ""

Write-Host "Step 2: Clearing old Redis indexes..." -ForegroundColor Yellow
php -d memory_limit=3G artisan indexes:manage clean
Write-Host ""

Write-Host "Step 3: Building Redis indexes for 10M products..." -ForegroundColor Yellow
Write-Host "This will take 5-15 minutes. Please be patient...`n" -ForegroundColor Gray

# Auto-answer yes to rebuild prompt
$process = Start-Process -FilePath "php" -ArgumentList "-d","memory_limit=3G","artisan","indexes:manage","build","--force" -NoNewWindow -PassThru -Wait

if ($process.ExitCode -eq 0) {
    Write-Host "`n✅ Redis indexes built successfully!" -ForegroundColor Green
    Write-Host ""

    Write-Host "========================================" -ForegroundColor Cyan
    Write-Host "NEXT: ELASTICSEARCH INDEXING" -ForegroundColor Cyan
    Write-Host "========================================`n" -ForegroundColor Cyan

    Write-Host "Choose your strategy:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Option A: Quick Test (100K products, 15-20 min)" -ForegroundColor White
    Write-Host "  php -d memory_limit=2G artisan elasticsearch:index-all --chunk=2000 --limit=100000`n" -ForegroundColor Gray

    Write-Host "Option B: Full Index (10M products, 15-25 HOURS)" -ForegroundColor White
    Write-Host "  php -d memory_limit=4G artisan elasticsearch:index-all --chunk=5000`n" -ForegroundColor Gray

    Write-Host "Option C: Background Job (Recommended for full index)" -ForegroundColor White
    Write-Host "  Start-Job -ScriptBlock { Set-Location D:\wamp64\www\Enterprice-Ecommerce; php -d memory_limit=4G artisan elasticsearch:index-all --chunk=5000 }`n" -ForegroundColor Gray

    Write-Host "Checking current status..." -ForegroundColor Yellow
    php check-systems.php

} else {
    Write-Host "`n❌ Redis index build failed!" -ForegroundColor Red
    Write-Host "Check the error messages above" -ForegroundColor Yellow
}

Write-Host "`n========================================" -ForegroundColor Cyan
Write-Host "Read: 10M_PRODUCTS_SETUP_GUIDE.md for details" -ForegroundColor Cyan
Write-Host "========================================`n" -ForegroundColor Cyan
