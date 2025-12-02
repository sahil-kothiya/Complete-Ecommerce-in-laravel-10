# 🚀 Filter System - Quick Reference

## Quick Commands

### Setup (First Time)
```bash
# Run automated setup
.\setup-robust-filters.ps1

# Or manual setup
php -d memory_limit=2G artisan indexes:manage build --force
php artisan indexes:health
```

### Daily Operations
```bash
# Check health
php artisan indexes:health

# View statistics
php artisan indexes:manage stats

# Test performance
php artisan indexes:manage test

# Run tests
.\test-robust-filters.ps1
```

### Troubleshooting
```bash
# Force rebuild
php -d memory_limit=2G artisan indexes:manage build --force

# Check specific index
redis-cli SCARD "index:brand:42"

# View logs
Get-Content storage\logs\laravel.log -Tail 50 -Wait

# Clear caches
php artisan cache:clear
php artisan config:clear
```

## Architecture Summary

```
Request Flow:
1. Middleware checks index health (once/min/user)
2. Controller checks for missing indexes
3. Builds missing indexes on-demand (1-5s)
4. Queries Redis or falls back to database
5. Returns results (50ms-3s depending on mode)

Background Jobs:
- Full rebuild: Daily at 2 AM (5-10 min)
- Incremental sync: Every 10 min (5-15 sec)
- Health check: Hourly with auto-recovery
- Temp cleanup: Daily
```

## Response Times

| Scenario | Expected Time |
|----------|---------------|
| Simple filter (Redis) | 50-100ms |
| Brand filter (Redis) | 80-150ms |
| Complex filter (Redis) | 100-300ms |
| Database fallback | 1-3 seconds |
| On-demand index build | 1-5 seconds |
| Full index rebuild | 5-10 minutes |

## Common Issues

### ❌ "Maximum execution time exceeded"
```bash
# Solution 1: Rebuild indexes
php artisan indexes:manage build --force

# Solution 2: Check health and auto-fix
php artisan indexes:health --rebuild

# Solution 3: Increase PHP timeout
# In php.ini: max_execution_time = 300
```

### ❌ "Redis connection refused"
```bash
# Windows: Start Redis service
# OR check .env for correct REDIS_HOST and REDIS_PORT

# Verify connection
redis-cli ping
# Should return: PONG
```

### ❌ "Filters showing old data"
```bash
# Trigger incremental sync
php artisan queue:work --once

# Or force full rebuild
php artisan indexes:manage build --force
```

### ❌ "Out of memory"
```bash
# Use higher memory limit
php -d memory_limit=4G artisan indexes:manage build --force

# Or adjust batch sizes in ProductIndexService.php
# CHUNK_SIZE = 5000 → 2500
# BATCH_SIZE = 10000 → 5000
```

## Health Check Interpretation

```
✅ Overall Status: HEALTHY
   → Everything working, no action needed

❌ Overall Status: UNHEALTHY
   → Run: php artisan indexes:health --rebuild

📊 Total Indexes: 156
   → Should be > 100 for full catalog

⚠️  Missing Indexes: 3
   → Will auto-build on next filter request
   → Or run: php artisan indexes:manage build --force

🔄 Rebuild Status: In Progress
   → Check logs: tail -f storage/logs/laravel.log

⏱️  Last Rebuild: 2025-11-26 02:00:15
   → Should be within 24 hours
```

## Production Checklist

- [ ] Redis is running and persistent
- [ ] Queue worker is running (`php artisan queue:work`)
- [ ] Cron jobs are scheduled (see Kernel.php)
- [ ] `QUEUE_CONNECTION=redis` in .env
- [ ] `php artisan indexes:health` shows HEALTHY
- [ ] Filter API responds < 500ms
- [ ] Logs monitored for warnings/errors
- [ ] Daily backups configured
- [ ] Monitoring/alerts set up

## API Testing

```powershell
# Basic filter
Invoke-WebRequest "http://localhost:8000/api/filters"

# Brand filter
Invoke-WebRequest "http://localhost:8000/api/filters?brands=hp"

# Multiple filters
Invoke-WebRequest "http://localhost:8000/api/filters?brands=hp,dell&price_range=100-500&sortBy=price_low_high"

# With pagination
Invoke-WebRequest "http://localhost:8000/api/filters?brands=hp&page=2&show=24"
```

## Key Files

| File | Purpose |
|------|---------|
| `UltraFastFilterController.php` | Main filter controller |
| `IndexHealthService.php` | Health monitoring & recovery |
| `ProductIndexService.php` | Index building service |
| `FastFilterService.php` | Redis query service |
| `RebuildProductIndexesJob.php` | Background rebuild job |
| `IncrementalIndexSyncJob.php` | Incremental sync job |
| `CheckFilterIndexHealth.php` | Request middleware |
| `CheckIndexHealth.php` | Health check command |
| `ManageProductIndexes.php` | Index management command |

## Monitoring Metrics

Monitor these in production:

1. **Index Health**: `php artisan indexes:health --json`
2. **Query Performance**: Check `m.ms` in API responses
3. **Error Rate**: Count 500 errors in logs
4. **Index Count**: Should be > 100
5. **Last Rebuild**: Within 24 hours
6. **Queue Lag**: Jobs processed within 1 minute

## Support Commands

```bash
# Get health report as JSON
php artisan indexes:health --json

# Get detailed statistics
php artisan indexes:manage stats

# Clean temporary keys
php artisan indexes:manage clean

# Test filter performance
php artisan indexes:manage test

# Run comprehensive tests
.\test-robust-filters.ps1

# Check queue status
php artisan queue:work --queue=indexes --once

# Monitor logs in real-time
Get-Content storage\logs\laravel.log -Tail 50 -Wait | Select-String "Index|Filter"
```

## Emergency Recovery

If everything breaks:

```bash
# 1. Check basics
redis-cli ping
php artisan config:clear
php artisan cache:clear

# 2. Verify database connection
php artisan tinker --execute="DB::connection()->getPdo();"

# 3. Force complete rebuild
php -d memory_limit=4G artisan indexes:manage build --force

# 4. Verify health
php artisan indexes:health

# 5. Test filters
.\test-robust-filters.ps1

# 6. Check logs for errors
Get-Content storage\logs\laravel.log -Tail 100
```

## Performance Tuning

### For Large Datasets (10M+ products)

In `ProductIndexService.php`:
```php
private const CHUNK_SIZE = 5000;  // Lower if memory issues
private const BATCH_SIZE = 10000; // Lower if Redis timeout
```

In `UltraFastFilterController.php`:
```php
private const MAX_EXECUTION_TIME = 120; // Increase if needed
private const CACHE_TTL = 300;          // Increase to reduce load
```

### For High Traffic

- Enable Redis persistence (AOF or RDB)
- Use Redis Cluster for scalability
- Increase PHP-FPM workers
- Enable opcache and apcu
- Use CDN for static assets
- Consider read replicas for database

## Automation

### Windows Task Scheduler
```powershell
# Daily full rebuild at 2 AM
schtasks /create /tn "IndexRebuild" /tr "php d:\path\to\artisan indexes:manage build --force" /sc daily /st 02:00

# Hourly health check
schtasks /create /tn "IndexHealth" /tr "php d:\path\to\artisan indexes:health --rebuild" /sc hourly
```

### Linux Cron
```bash
# Add to crontab
0 2 * * * php /path/to/artisan indexes:manage build --force
0 * * * * php /path/to/artisan indexes:health --rebuild
*/10 * * * * php /path/to/artisan queue:work --queue=indexes --once
```

---

**Quick Links:**
- Full Guide: [FILTER_SYSTEM_ROBUST_GUIDE.md](FILTER_SYSTEM_ROBUST_GUIDE.md)
- Setup Script: `setup-robust-filters.ps1`
- Test Script: `test-robust-filters.ps1`
- Health Check: `php artisan indexes:health`
- Statistics: `php artisan indexes:manage stats`

**Version:** 2.0 (Robust Implementation)  
**Last Updated:** November 26, 2025
