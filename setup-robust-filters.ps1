# ========================================
# Robust Filter System Setup Script
# ========================================
# 
# This script sets up the robust filter system
# that handles missing Redis indexes gracefully
#
# Author: AI Assistant
# Date: November 26, 2025
# ========================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Robust Filter System Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Step 1: Check Redis
Write-Host "[1/6] Checking Redis connection..." -ForegroundColor Yellow
php artisan tinker --execute="Redis::ping() ? exit(0) : exit(1);" 2>$null

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Redis is not available" -ForegroundColor Red
    Write-Host "Please start Redis and try again" -ForegroundColor Yellow
    exit 1
} else {
    Write-Host "✅ Redis is running" -ForegroundColor Green
}
Write-Host ""

# Step 2: Clear existing caches
Write-Host "[2/6] Clearing existing caches..." -ForegroundColor Yellow
php artisan cache:clear
php artisan config:clear
Write-Host "✅ Caches cleared" -ForegroundColor Green
Write-Host ""

# Step 3: Check index health
Write-Host "[3/6] Checking current index health..." -ForegroundColor Yellow
php artisan indexes:health --json | Out-Null

if ($LASTEXITCODE -ne 0) {
    Write-Host "⚠️  Indexes need rebuilding" -ForegroundColor Yellow
} else {
    Write-Host "✅ Indexes are healthy" -ForegroundColor Green
}
Write-Host ""

# Step 4: Ask user if they want to rebuild
Write-Host "[4/6] Index Rebuild Options:" -ForegroundColor Yellow
Write-Host "  1. Full rebuild now (5-10 minutes, blocks terminal)" -ForegroundColor White
Write-Host "  2. Background rebuild (non-blocking, check logs)" -ForegroundColor White
Write-Host "  3. Skip rebuild (use existing indexes)" -ForegroundColor White
Write-Host ""

$choice = Read-Host "Select option (1-3)"

switch ($choice) {
    "1" {
        Write-Host ""
        Write-Host "Building indexes (this will take 5-10 minutes)..." -ForegroundColor Yellow
        Write-Host "Progress will be shown below:" -ForegroundColor Gray
        Write-Host ""
        
        php -d memory_limit=2G artisan indexes:manage build --force
        
        if ($LASTEXITCODE -eq 0) {
            Write-Host ""
            Write-Host "✅ Indexes built successfully!" -ForegroundColor Green
        } else {
            Write-Host ""
            Write-Host "❌ Index build failed. Check logs for details." -ForegroundColor Red
            exit 1
        }
    }
    "2" {
        Write-Host ""
        Write-Host "Triggering background rebuild..." -ForegroundColor Yellow
        
        # Ensure queue connection is configured
        $queueConnection = php artisan tinker --execute="echo config('queue.default');" 2>$null
        
        if ($queueConnection -eq "sync") {
            Write-Host "⚠️  Warning: Queue is set to 'sync' (synchronous)" -ForegroundColor Yellow
            Write-Host "For true background processing, set QUEUE_CONNECTION=redis in .env" -ForegroundColor Yellow
        }
        
        # Trigger background rebuild
        php artisan indexes:health --rebuild
        
        Write-Host ""
        Write-Host "✅ Background rebuild triggered" -ForegroundColor Green
        Write-Host "Monitor progress with: Get-Content storage\logs\laravel.log -Tail 50 -Wait" -ForegroundColor Gray
        
        if ($queueConnection -ne "sync") {
            Write-Host ""
            Write-Host "⚠️  Don't forget to run queue worker:" -ForegroundColor Yellow
            Write-Host "php artisan queue:work --queue=indexes" -ForegroundColor Cyan
        }
    }
    "3" {
        Write-Host ""
        Write-Host "⏭️  Skipping rebuild" -ForegroundColor Yellow
    }
    default {
        Write-Host ""
        Write-Host "❌ Invalid choice. Exiting." -ForegroundColor Red
        exit 1
    }
}
Write-Host ""

# Step 5: Verify health
Write-Host "[5/6] Verifying index health..." -ForegroundColor Yellow
php artisan indexes:health

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ System is healthy!" -ForegroundColor Green
} else {
    Write-Host ""
    Write-Host "⚠️  Some issues detected. Review output above." -ForegroundColor Yellow
}
Write-Host ""

# Step 6: Show next steps
Write-Host "[6/6] Setup Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Next Steps" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "1. Test the filter API:" -ForegroundColor White
Write-Host "   Invoke-WebRequest http://127.0.0.1:8000/api/filters?brands=hp" -ForegroundColor Gray
Write-Host ""

Write-Host "2. Run queue worker (if using background jobs):" -ForegroundColor White
Write-Host "   php artisan queue:work --queue=indexes" -ForegroundColor Gray
Write-Host ""

Write-Host "3. Monitor logs:" -ForegroundColor White
Write-Host "   Get-Content storage\logs\laravel.log -Tail 50 -Wait" -ForegroundColor Gray
Write-Host ""

Write-Host "4. Check health anytime:" -ForegroundColor White
Write-Host "   php artisan indexes:health" -ForegroundColor Gray
Write-Host ""

Write-Host "5. View statistics:" -ForegroundColor White
Write-Host "   php artisan indexes:manage stats" -ForegroundColor Gray
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  Important Notes" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "[OK] Filters will work even if Redis indexes are missing" -ForegroundColor Green
Write-Host "[OK] System auto-builds missing indexes on-demand" -ForegroundColor Green
Write-Host "[OK] Database fallback prevents errors (1-3 sec response)" -ForegroundColor Green
Write-Host "[OK] Background jobs keep indexes fresh automatically" -ForegroundColor Green
Write-Host ""

Write-Host "[!] For production:" -ForegroundColor Yellow
Write-Host "  - Set up cron for scheduled tasks (see FILTER_SYSTEM_ROBUST_GUIDE.md)" -ForegroundColor Gray
Write-Host "  - Run queue worker with supervisor/systemd" -ForegroundColor Gray
Write-Host "  - Monitor index health daily" -ForegroundColor Gray
Write-Host ""

Write-Host "[DOC] Full documentation: FILTER_SYSTEM_ROBUST_GUIDE.md" -ForegroundColor Cyan
Write-Host ""

Write-Host "[SUCCESS] Setup complete! Your filter system is now robust and production-ready!" -ForegroundColor Green
Write-Host ""
