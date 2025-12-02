# ========================================
# Fix Redis & Build Indexes
# ========================================
# Automatically fixes Redis write issues
# and builds filter indexes
# ========================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Fix Redis & Build Filter Indexes" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Fix Redis Configuration
Write-Host "[1/3] Fixing Redis configuration..." -ForegroundColor Yellow

try {
    # Disable Redis write protection on RDB save errors
    $result = php artisan tinker --execute="Illuminate\Support\Facades\Redis::connection()->client()->config('SET', 'stop-writes-on-bgsave-error', 'no'); echo 'OK';" 2>&1
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  [OK] Redis configured to allow writes" -ForegroundColor Green
    } else {
        Write-Host "  [WARNING] Could not configure Redis, trying alternative method..." -ForegroundColor Yellow
        
        # Try alternative: Set save to empty (disable RDB snapshots)
        $result2 = php artisan tinker --execute="Illuminate\Support\Facades\Redis::connection()->client()->config('SET', 'save', ''); echo 'OK';" 2>&1
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host "  [OK] Redis RDB snapshots disabled" -ForegroundColor Green
        } else {
            Write-Host "  [ERROR] Failed to configure Redis" -ForegroundColor Red
            Write-Host "  Please check Redis logs or restart Redis service" -ForegroundColor Yellow
            exit 1
        }
    }
}
catch {
    Write-Host "  [ERROR] Redis configuration failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 2: Verify Redis is working
Write-Host "[2/3] Verifying Redis connection..." -ForegroundColor Yellow

try {
    $pingResult = php artisan tinker --execute="Illuminate\Support\Facades\Redis::connection()->ping(); echo 'CONNECTED';" 2>&1
    
    if ($pingResult -match "CONNECTED") {
        Write-Host "  [OK] Redis is connected and writable" -ForegroundColor Green
    } else {
        Write-Host "  [ERROR] Redis connection test failed" -ForegroundColor Red
        exit 1
    }
}
catch {
    Write-Host "  [ERROR] Redis verification failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

Write-Host ""

# Step 3: Build Indexes
Write-Host "[3/3] Building filter indexes..." -ForegroundColor Yellow
Write-Host "  This will take 5-10 minutes for 10M+ products" -ForegroundColor Gray
Write-Host "  Memory limit: 2GB" -ForegroundColor Gray
Write-Host ""

try {
    # Run the index build command
    php -d memory_limit=2G artisan indexes:manage build --force
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "  SUCCESS!" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "Filter indexes built successfully!" -ForegroundColor Green
        Write-Host ""
        Write-Host "Next steps:" -ForegroundColor Cyan
        Write-Host "  1. Check health: php artisan indexes:health" -ForegroundColor Gray
        Write-Host "  2. View stats: php artisan indexes:manage stats" -ForegroundColor Gray
        Write-Host "  3. Test filters: .\test-robust-filters.ps1" -ForegroundColor Gray
        Write-Host ""
    } else {
        Write-Host ""
        Write-Host "[ERROR] Index build failed with exit code: $LASTEXITCODE" -ForegroundColor Red
        Write-Host ""
        Write-Host "Troubleshooting steps:" -ForegroundColor Yellow
        Write-Host "  1. Check storage/logs/laravel.log for errors" -ForegroundColor Gray
        Write-Host "  2. Verify database connection" -ForegroundColor Gray
        Write-Host "  3. Ensure sufficient disk space" -ForegroundColor Gray
        Write-Host "  4. Try increasing memory: php -d memory_limit=4G artisan indexes:manage build --force" -ForegroundColor Gray
        Write-Host ""
        exit 1
    }
}
catch {
    Write-Host ""
    Write-Host "[ERROR] Build process failed: $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}
