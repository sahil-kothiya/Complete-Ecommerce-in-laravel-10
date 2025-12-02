# Filter System - Robust Implementation Guide

## 🎯 Overview

This document describes the robust filter system implementation that handles Redis index failures gracefully and ensures continuous operation even when indexes are missing.

## 🏗️ Architecture

### Components

1. **IndexHealthService** - Monitors and maintains index health
2. **UltraFastFilterController** - Smart filtering with automatic fallback
3. **ProductIndexService** - Builds and maintains Redis indexes
4. **RebuildProductIndexesJob** - Background index rebuilding
5. **IncrementalIndexSyncJob** - Keeps indexes up-to-date
6. **CheckFilterIndexHealth Middleware** - Proactive health checks

### How It Works

```
Request → Middleware → Health Check → Controller → Index Check → Query
                                                        ↓
                                              [Redis Available?]
                                                   ↙        ↘
                                          [Yes: Fast]  [No: Fallback]
                                                   ↓            ↓
                                           Redis Query   Database Query
                                                   ↓            ↓
                                           < 100ms       1-3 seconds
```

## 🚀 Quick Start

### 1. Initial Setup (First Time)

```bash
# Build indexes for the first time
php -d memory_limit=2G artisan indexes:manage build --force

# This will take 5-10 minutes for 10M products
# Progress is shown in console and logged
```

### 2. Check Index Health

```bash
# Quick health check
php artisan indexes:health

# Detailed JSON output
php artisan indexes:health --json

# Check and auto-rebuild if unhealthy
php artisan indexes:health --rebuild
```

### 3. Configure Queue for Background Jobs

```bash
# In .env, ensure queue is configured
QUEUE_CONNECTION=redis

# Run queue worker
php artisan queue:work --queue=indexes

# Or use supervisor (recommended for production)
```

### 4. Schedule Maintenance (Production)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Full rebuild daily at 2 AM
    $schedule->command('indexes:manage build --force')
             ->dailyAt('02:00')
             ->withoutOverlapping();
    
    // Incremental sync every 10 minutes
    $schedule->job(new IncrementalIndexSyncJob(15))
             ->everyTenMinutes()
             ->withoutOverlapping();
    
    // Health check every hour
    $schedule->command('indexes:health --rebuild')
             ->hourly();
    
    // Clean temp keys daily
    $schedule->command('indexes:manage clean')
             ->daily();
}
```

## 🛡️ Failure Handling

### Scenario 1: Redis Not Available

**Detection:**
- `IndexHealthService::isHealthy()` returns `false`
- Redis connection fails

**Response:**
1. Controller automatically uses database fallback
2. Query optimized to prevent timeout (< 120 seconds)
3. Warning logged for monitoring
4. Filters still work, just slower (1-3 seconds vs 100ms)

**Recovery:**
- Restart Redis service
- Indexes auto-rebuild on next health check

### Scenario 2: Indexes Missing

**Detection:**
- `IndexHealthService::getMissingIndexesForFilters()` identifies gaps
- Specific brand/category indexes don't exist

**Response:**
1. **On-Demand Build**: Missing indexes built immediately (1-5 seconds)
2. **Background Rebuild**: Full rebuild triggered asynchronously
3. **Database Fallback**: Query proceeds with database if build fails

**Recovery:**
- On-demand builds fill gaps instantly
- Background job rebuilds all indexes
- No user-facing errors

### Scenario 3: Indexes Stale

**Detection:**
- TTL checks show expired indexes
- Product changes not reflected

**Response:**
1. Incremental sync updates changed products
2. Full rebuild scheduled if many stale
3. Filters use slightly outdated data temporarily

**Recovery:**
- Incremental sync runs every 10 minutes
- Full rebuild runs daily at 2 AM
- Max staleness: 10-60 minutes

### Scenario 4: Query Timeout

**Detection:**
- Query exceeds 120 seconds
- Fatal error caught

**Response:**
1. Transaction rolled back safely
2. Error logged with full context
3. User sees generic error message
4. Health check triggered

**Recovery:**
- Query optimization applied
- Indexes rebuilt
- Retry automatically works

## 📊 Monitoring

### Health Check Dashboard

```bash
php artisan indexes:health
```

Output:
```
✅ Overall Status: HEALTHY

✅ Redis Available: Yes
📊 Total Indexes: 156

🔑 Critical Indexes:
  ✅  index:category:1    Yes    12,543
  ✅  index:brand:42      Yes    8,921
  ✅  index:price:0-100   Yes    45,231

🔄 Rebuild Status: Not Needed
⏱️  Last Rebuild: 2025-11-26 02:00:15
```

### Metrics to Monitor

1. **Index Count**: Should be > 100
2. **Critical Index Health**: All should exist
3. **Last Rebuild Time**: Within 24 hours
4. **Query Performance**: < 500ms average

### Logs to Watch

```bash
# Real-time monitoring
tail -f storage/logs/laravel.log | grep -E "Index|Filter"

# Look for:
# - "Index Health: ..." warnings
# - "Missing indexes detected"
# - "Building single index"
# - "Background rebuild triggered"
```

## 🔧 Maintenance Commands

### Daily Operations

```bash
# Morning health check
php artisan indexes:health

# View statistics
php artisan indexes:manage stats

# Test filter performance
php artisan indexes:manage test
```

### Troubleshooting

```bash
# Force complete rebuild
php -d memory_limit=2G artisan indexes:manage build --force

# Clean up temporary keys
php artisan indexes:manage clean

# Check specific index
redis-cli SCARD "index:brand:42"

# View all index keys
redis-cli KEYS "index:*" | head -20
```

### Emergency Recovery

If filters completely break:

```bash
# 1. Check Redis is running
redis-cli ping

# 2. Clear all caches
php artisan cache:clear
php artisan config:clear

# 3. Rebuild indexes with high memory
php -d memory_limit=4G artisan indexes:manage build --force

# 4. Verify health
php artisan indexes:health

# 5. Test filters
curl "http://localhost:8000/api/filters?brands=hp"
```

## ⚙️ Configuration

### Performance Tuning

In `UltraFastFilterController.php`:

```php
// Adjust timeouts
private const MAX_EXECUTION_TIME = 120; // Increase if needed

// Adjust cache TTL
private const CACHE_TTL = 300; // 5 minutes (increase for less load)
```

In `ProductIndexService.php`:

```php
// Adjust batch sizes
private const CHUNK_SIZE = 5000;  // Lower if memory issues
private const BATCH_SIZE = 10000; // Lower if Redis connection issues
```

### Redis Configuration

In `config/database.php`:

```php
'redis' => [
    'client' => 'phpredis',
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
        'read_timeout' => 60,      // Increase for large sets
        'timeout' => 5,
        'persistent' => true,      // Reuse connections
    ],
],
```

## 🧪 Testing

### Manual Testing

```bash
# Test 1: Healthy indexes
php artisan indexes:manage test

# Test 2: Simulate missing index
redis-cli DEL "index:brand:42"
curl "http://localhost:8000/api/filters?brands=hp"
# Should build on-demand and work

# Test 3: Simulate Redis down
sudo service redis stop
curl "http://localhost:8000/api/filters?brands=hp"
# Should fall back to database
sudo service redis start

# Test 4: Load test
ab -n 100 -c 10 "http://localhost:8000/api/filters?brands=hp"
```

### Automated Testing

Create `tests/Feature/FilterRobustnessTest.php`:

```php
public function test_filters_work_without_redis()
{
    // Disable Redis
    Config::set('database.redis.client', 'invalid');
    
    // Test filter request
    $response = $this->getJson('/api/filters?brands=hp');
    
    // Should still work (database fallback)
    $response->assertOk();
    $response->assertJsonStructure([
        'ok',
        'f',
        'p',
        'pg',
        'm'
    ]);
}
```

## 📈 Performance Expectations

### With Healthy Indexes (Redis)

- **Simple Filter** (1 category): 50-100ms
- **Brand Filter** (HP): 80-150ms
- **Complex Filter** (category + brand + price): 100-300ms
- **Heavy Load** (100 concurrent): < 500ms avg

### Database Fallback Mode

- **Simple Filter**: 500-1000ms
- **Brand Filter**: 1-2 seconds
- **Complex Filter**: 2-3 seconds
- **Heavy Load**: 3-5 seconds avg

### Index Rebuild Times

- **Full Rebuild**: 5-10 minutes (10M products)
- **Single Index**: 1-30 seconds
- **Incremental Sync**: 5-15 seconds (100 products)

## 🎛️ Advanced Features

### On-Demand Index Building

The system automatically builds missing indexes when needed:

```php
// In controller, before query:
$this->ensureIndexesExist($category, $filters);

// This:
// 1. Detects missing indexes
// 2. Builds them on-the-fly (1-5 seconds)
// 3. Triggers background full rebuild
// 4. Proceeds with query
```

### Smart Database Fallback

Database queries are optimized to avoid subqueries:

```php
// Instead of slow EXISTS subqueries:
// $query->whereExists(...)

// We use fast BETWEEN on indexed columns:
$query->whereBetween('base_price', [$min, $max]);

// And LEFT JOINs for ratings:
$query->leftJoin('product_ratings_cache', ...)
      ->where('average_rating', '>=', $minRating);
```

### Incremental Sync

Only changed products are re-indexed:

```php
// Runs every 10 minutes via cron
dispatch(new IncrementalIndexSyncJob(15)); // Last 15 minutes

// Updates only:
// - New products (created_at)
// - Modified products (updated_at)
// - Takes 5-15 seconds vs 10 minutes for full rebuild
```

## 🚨 Common Issues & Solutions

### Issue: "Maximum execution time exceeded"

**Cause:** Query timeout due to missing indexes or complex filters

**Solution:**
```bash
# 1. Rebuild indexes
php artisan indexes:manage build --force

# 2. Check health
php artisan indexes:health

# 3. If still happens, increase timeout in php.ini
max_execution_time = 300
```

### Issue: "Redis connection refused"

**Cause:** Redis service not running

**Solution:**
```bash
# Windows (WAMP)
# Start Redis manually or check service

# Linux
sudo service redis start

# Verify
redis-cli ping
```

### Issue: "Filters showing old data"

**Cause:** Indexes stale, incremental sync not running

**Solution:**
```bash
# Force full rebuild
php artisan indexes:manage build --force

# Set up cron for incremental sync
* */10 * * * php artisan queue:work --once
```

### Issue: "Out of memory during index build"

**Cause:** Insufficient PHP memory limit

**Solution:**
```bash
# Increase memory limit
php -d memory_limit=4G artisan indexes:manage build --force

# Or in php.ini:
memory_limit = 4096M
```

## 📝 Best Practices

1. **Always run queue worker** in production for background jobs
2. **Schedule daily rebuilds** at low-traffic times (2-4 AM)
3. **Monitor health daily** with `indexes:health`
4. **Test filter performance** weekly with `indexes:manage test`
5. **Keep Redis persistent** with proper backup
6. **Log monitoring** for warnings and errors
7. **Set up alerts** for index health failures

## 🎯 Success Criteria

Your filter system is healthy when:

- ✅ `php artisan indexes:health` shows "HEALTHY"
- ✅ All critical indexes exist with data
- ✅ Last rebuild within 24 hours
- ✅ Filter API responds < 500ms
- ✅ No timeout errors in logs
- ✅ Queue worker processing jobs
- ✅ Incremental sync running every 10 minutes

## 📞 Support

If issues persist:

1. Check logs: `storage/logs/laravel.log`
2. Verify Redis: `redis-cli info`
3. Check database indexes: `php artisan indexes:manage stats`
4. Review health report: `php artisan indexes:health --json`
5. Test manually: `curl localhost:8000/api/filters?brands=hp`

---

**Last Updated:** November 26, 2025
**Version:** 2.0 (Robust Implementation)
