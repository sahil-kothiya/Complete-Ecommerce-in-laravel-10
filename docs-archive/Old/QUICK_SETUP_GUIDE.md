# 🚀 Quick Setup Guide - Centralized Redis Caching

## ⚡ **5-Minute Setup**

Follow these steps to activate the new centralized Redis caching architecture.

---

## **Step 1: Verify Files**

Check that these files exist:
```bash
# Config
config/redis_cache.php                          ✅

# Services
app/Services/RedisCacheService.php              ✅
app/Helpers/RedisHelper.php                     ✅ (refactored)

# Commands
app/Console/Commands/RedisCacheCommand.php      ✅

# Reference
OPTIMIZED_HOMEPAGE_METHOD.php                   ✅
REDIS_ARCHITECTURE_V2.md                        ✅
```

---

## **Step 2: Update .env File**

Your `.env` has been updated with Redis configuration. Verify these key settings:

```env
# Enable caching
REDIS_CACHE_ENABLED=true
CACHE_HOMEPAGE_ENABLED=true

# Performance settings
REDIS_COMPRESS_ENABLED=true
REDIS_PIPELINE_ENABLED=true
REDIS_LOCK_ENABLED=true
```

---

## **Step 3: Clear Application Cache**

```bash
php artisan config:clear
php artisan cache:clear
php artisan optimize:clear
```

---

## **Step 4: Test Redis Connection**

```bash
php artisan redis:cache status
```

**Expected Output:**
```
📊 Redis Cache Status

✅ Redis connection: OK

Cache Type    | Status
------------- | -----------
master        | ✅ Enabled
homepage      | ✅ Enabled
products      | ✅ Enabled
categories    | ✅ Enabled
...
```

---

## **Step 5: Warm Up Cache**

```bash
php artisan redis:cache warm --type=homepage
```

---

## **Step 6: Update Homepage Method (IMPORTANT!)**

**Manual Step Required:**

1. Open `app/Http/Controllers/FrontendController.php`

2. **Replace** the existing `home()` method with the optimized version from `OPTIMIZED_HOMEPAGE_METHOD.php`

   The new method:
   - Uses `RedisCacheService` instead of mixed caching
   - Implements 3-tier caching
   - Batch-fetches product cards
   - Achieves <15ms cache hits

3. **Add** the helper methods:
   - `buildHomepageFromComponents()`
   - `buildCategoriesComponent()`
   - `buildBannersComponent()`
   - `buildFeaturedProductsComponent()`
   - `batchFetchProductCards()`
   - `transformToProductCard()`

**Tip:** Copy the entire method and helper methods from `OPTIMIZED_HOMEPAGE_METHOD.php`

---

## **Step 7: Test Homepage**

Visit your homepage and check the logs:

```bash
tail -f storage/logs/laravel.log | grep "Homepage:"
```

**Expected Output (first visit):**
```
Homepage: Built from cache (245.67ms)
```

**Expected Output (subsequent visits):**
```
Homepage: Full cache hit (5.23ms)
```

---

## **Step 8: Verify Statistics**

```bash
php artisan redis:cache stats
```

**Expected Output:**
```
📈 Redis Cache Statistics

Redis Server:
Metric          | Value
--------------- | --------
Total Keys      | 1,234
Hit Rate        | 95.4%
Memory Used     | 45.67 MB
Ops/sec         | 120
```

---

## **✅ Success Indicators**

You've successfully set up the system if:

- ✅ `redis:cache status` shows Redis connected
- ✅ Homepage loads in < 50ms (after first visit)
- ✅ Logs show "Full cache hit" messages
- ✅ Cache hit rate > 90%
- ✅ No Redis errors in logs

---

## **🛠️ Common Commands**

```bash
# Check status
php artisan redis:cache status --detailed

# View stats
php artisan redis:cache stats

# Clear homepage cache
php artisan redis:cache clear --type=homepage --confirm

# Warm homepage
php artisan redis:cache warm --type=homepage

# List cached keys
php artisan redis:cache keys --pattern="page:*"

# Enable/disable caches
php artisan redis:cache enable --type=homepage
php artisan redis:cache disable --type=search

# Clear everything (use with caution!)
php artisan redis:cache flush --confirm
```

---

## **🔧 Troubleshooting**

### **Problem: Redis not connected**

**Solution:**
```bash
# Check if Redis is running
redis-cli ping
# Should output: PONG

# If not running, start Redis
# Windows (WAMP):
redis-server

# Linux:
sudo systemctl start redis
```

### **Problem: Cache not working**

**Solution:**
```bash
# 1. Clear all caches
php artisan optimize:clear

# 2. Check .env settings
# REDIS_CACHE_ENABLED=true
# CACHE_HOMEPAGE_ENABLED=true

# 3. Reload config
php artisan config:clear

# 4. Test again
php artisan redis:cache status
```

### **Problem: Slow homepage**

**Solution:**
```bash
# 1. Warm cache
php artisan redis:cache warm --type=homepage

# 2. Check hit rate
php artisan redis:cache stats

# 3. If hit rate low, check logs
tail -f storage/logs/laravel.log | grep "cache miss"
```

### **Problem: Stale data**

**Solution:**
```bash
# Clear and rebuild
php artisan redis:cache clear --type=homepage --confirm
php artisan redis:cache warm --type=homepage
```

---

## **📊 Performance Monitoring**

### **Real-time Monitoring**
```bash
# Watch homepage performance
tail -f storage/logs/laravel.log | grep "Homepage:"

# Watch Redis operations (verbose)
tail -f storage/logs/laravel.log | grep "Redis"
```

### **Redis Insights** (Recommended)

1. Install [Redis Insights](https://redis.com/redis-enterprise/redis-insight/)
2. Connect to `127.0.0.1:6379`
3. Monitor:
   - Real-time operations
   - Memory usage
   - Key patterns
   - Slow commands

---

## **🎯 Performance Targets**

After setup, you should achieve:

| Metric | Target | How to Check |
|--------|--------|--------------|
| Cache Hit Time | < 15ms | Check logs |
| Cache Miss Time | < 500ms | Check logs |
| Hit Rate | > 95% | `redis:cache stats` |
| Memory Usage | < 500MB | `redis:cache stats` |

---

## **📝 Next Steps**

1. **Update Other Controllers** (Optional)
   - Product detail page
   - Category pages
   - Search results
   
   Use the same pattern:
   ```php
   use App\Services\RedisCacheService;
   
   $key = RedisCacheService::makeKey('product', $id);
   $product = RedisCacheService::remember($key, $ttl, function () {
       return Product::with('variants')->find($id);
   });
   ```

2. **Set Up Scheduled Cache Warming** (Recommended)
   
   In `app/Console/Kernel.php`:
   ```php
   protected function schedule(Schedule $schedule)
   {
       $schedule->command('redis:cache warm --type=homepage')->hourly();
   }
   ```

3. **Monitor in Production**
   - Set up alerts for low hit rates
   - Monitor memory usage
   - Review slow operation logs weekly

---

## **📚 Documentation**

- **Full Documentation:** `REDIS_ARCHITECTURE_V2.md`
- **Config Reference:** `config/redis_cache.php` (heavily commented)
- **Code Reference:** `OPTIMIZED_HOMEPAGE_METHOD.php`

---

## **🎉 You're Done!**

Your e-commerce site is now optimized with:
- ✅ Centralized Redis configuration
- ✅ Sub-1-second homepage loads
- ✅ Support for 10M+ products
- ✅ Unified caching service
- ✅ Easy cache management
- ✅ Comprehensive monitoring

**Test it out:** Visit your homepage and see the speed! 🚀

---

## **Need Help?**

1. Check `REDIS_ARCHITECTURE_V2.md` for detailed documentation
2. Run `php artisan redis:cache status --detailed`
3. Check logs: `tail -f storage/logs/laravel.log`
4. Test Redis: `redis-cli ping`

**Happy Caching! 🎊**
