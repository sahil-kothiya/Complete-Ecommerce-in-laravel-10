# Fix Redis MISCONF Error
Write-Host "Fixing Redis write error..." -ForegroundColor Cyan

# Run the PHP script to fix Redis configuration
php fix-redis-write-error.php

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n✓ Redis fixed successfully!" -ForegroundColor Green
    Write-Host "You can now access the application." -ForegroundColor Green
} else {
    Write-Host "`n✗ Failed to fix Redis" -ForegroundColor Red
    Write-Host "Please check if Redis is running and try again." -ForegroundColor Yellow
}
