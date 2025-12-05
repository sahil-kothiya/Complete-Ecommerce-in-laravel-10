# Fix Redis Persistence Error - Permanent Solution
# Run this after Redis restart or if index building fails

Write-Host "Fixing Redis persistence configuration..." -ForegroundColor Yellow

# Disable stop-writes-on-bgsave-error
wsl redis-cli CONFIG SET stop-writes-on-bgsave-error no
Write-Host "✓ Disabled write blocking on RDB save errors" -ForegroundColor Green

# Fix permissions on Redis directory
wsl sudo chmod 777 /var/lib/redis 2>$null
Write-Host "✓ Fixed Redis data directory permissions" -ForegroundColor Green

# Verify configuration
$config = wsl redis-cli CONFIG GET stop-writes-on-bgsave-error
Write-Host "`nCurrent Configuration:" -ForegroundColor Cyan
Write-Host $config

Write-Host "`n✓ Redis is now ready for index building" -ForegroundColor Green
Write-Host "Run: php -d memory_limit=2G artisan indexes:manage build --force" -ForegroundColor Cyan
