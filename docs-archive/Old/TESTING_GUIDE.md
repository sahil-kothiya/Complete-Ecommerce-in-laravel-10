# Testing & Verification Guide - Index-Based Filtering

## 🔍 Quick Check Commands

### 1. Check if Commands are Available
```powershell
php artisan list | Select-String "indexes"
```

**Expected output:**
```
indexes:manage    Manage product filter indexes for fast filtering
```

---

## 📦 Step-by-Step Testing

### **Step 1: Build Indexes**

```powershell
php artisan indexes:manage build
```

**What this does:**
- Creates Redis SET indexes for categories, brands, prices, ratings, discounts
- Takes 5-10 minutes for 10M products (much faster for smaller datasets)
- Stores ~2,000 index keys in Redis

**Expected output:**
```
Building product indexes...
This may take 5-10 minutes for 10M+ products.

✅ Product indexes built successfully!

+-------------------------+--------+
| Metric                  | Value  |
+-------------------------+--------+
| Categories indexed      | 45     |
| Brands indexed          | 78     |
| Price ranges           | 6      |
| Rating levels          | 5      |
| Discount levels        | 4      |
| Total products         | 12,345 |
| Build time             | 23.45s |
+-------------------------+--------+
```

### **Step 2: View Statistics**

```powershell
php artisan indexes:manage stats
```

**What this shows:**
- Number of indexes created
- Products per index
- Memory usage
- Top categories and brands

**Expected output:**
```
=== Index Overview ===
+---------------+-------+----------------+
| Index Type    | Count | Total Products |
+---------------+-------+----------------+
| Categories    | 45    | 150,234       |
| Brands        | 78    | 145,678       |
| Price Ranges  | 6     | 125,456       |
| Ratings       | 5     | 98,234        |
| Discounts     | 4     | 45,678        |
+---------------+-------+----------------+

=== Memory Usage ===
Total index keys: 138
Temp filter keys: 0
Est. memory usage: 234.56 MB

=== Top 5 Categories by Products ===
+------------------------+----------+
| Key                    | Products |
+------------------------+----------+
| index:category:1       | 25,678   |
| index:category:5       | 18,234   |
...
```

### **Step 3: Test Performance**

```powershell
php artisan indexes:manage test
```

**What this does:**
- Tests single filter (category only)
- Tests multi-filter (category + brand)
- Tests complex filter (4+ filters)
- Shows response times

**Expected output:**
```
Running filter performance tests...

Test 1: Single category filter
  Results: 15,234 products
  Time: 45.23ms

Test 2: Category + Brand filter
  Results: 1,234 products
  Time: 78.45ms

Test 3: Complex multi-filter
  Results: 456 products
  Time: 125.67ms

Test 4: Estimation accuracy
  Estimated: 500
  Actual: 456
  Accuracy: 109.65%

Performance tests completed!
```

---

## 🔍 Manual Redis Verification

### Check if indexes exist in Redis:

```powershell
# Using redis-cli (if installed)
redis-cli KEYS "index:*" | Measure-Object -Line

# Or use PHP artisan tinker
php artisan tinker
```

In tinker:
```php
use Illuminate\Support\Facades\Redis;

// Count total index keys
count(Redis::keys('index:*'))

// View specific index
Redis::smembers('index:category:1')

// Check index size
Redis::scard('index:category:1')

// See all category indexes
Redis::keys('index:category:*')
```

**Expected:**
- 100+ index keys (categories + brands + price ranges + ratings + discounts)
- Each index contains array of product IDs
- Memory usage: 200-500MB for 10M products

---

## 🧪 Test Filtering in Your Application

### Option 1: Direct Service Test

```powershell
php artisan tinker
```

Then test:
```php
use App\Services\FastFilterService;

$service = app(FastFilterService::class);

// Test single category filter
$results = $service->getFilteredProductIds(['category_id' => 1]);
echo "Found: " . count($results) . " products\n";

// Test multi-filter
$results = $service->getFilteredProductIds([
    'category_id' => 1,
    'brands' => [1, 2, 3],
    'price_range' => '100-500'
]);
echo "Found: " . count($results) . " products\n";

// Check if indexes can handle filters
$canUse = $service->canUseIndexes(['category_id' => 1, 'brands' => [1]]);
echo "Can use indexes: " . ($canUse ? 'Yes' : 'No') . "\n";
```

### Option 2: Test via Browser

1. **Visit a category page:**
   ```
   http://127.0.0.1:8000/product-cat/ZXlKcGRpSTZJ...
   ```

2. **Apply filters** (brand, price, rating)

3. **Check Laravel log:**
   ```powershell
   Get-Content storage\logs\laravel.log -Tail 20
   ```

   Look for:
   ```
   FastFilter: Multi-set intersection
   {"filters":{"category_id":1,"brands":[1,2]},"sets_count":3,"result_count":456,"time_ms":78.45}
   ```

---

## 📊 Performance Benchmarks

### What to Expect:

| Scenario | Response Time | Status |
|----------|---------------|--------|
| Single filter | 30-100ms | ✅ Good |
| 2 filters | 50-150ms | ✅ Good |
| 3-4 filters | 100-300ms | ✅ Acceptable |
| 5+ filters | 200-500ms | ⚠️ Complex |

### If Response Times are Slow:

1. **Check Redis connection:**
   ```php
   php artisan tinker
   Redis::ping() // Should return 'PONG'
   ```

2. **Verify indexes exist:**
   ```php
   count(Redis::keys('index:*')) // Should be 100+
   ```

3. **Rebuild indexes:**
   ```powershell
   php artisan indexes:manage build --force
   ```

---

## 🔄 Maintenance Commands

### Daily Automated Tasks (Add to Scheduler)

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Rebuild indexes daily at 3 AM
    $schedule->command('indexes:manage build --force')
        ->dailyAt('03:00');
    
    // Clean temp keys every hour
    $schedule->command('indexes:manage clean')
        ->hourly();
    
    // Check stats daily
    $schedule->command('indexes:manage stats')
        ->dailyAt('09:00')
        ->appendOutputTo(storage_path('logs/index-stats.log'));
}
```

### Manual Maintenance

```powershell
# Clean up temporary filter keys
php artisan indexes:manage clean

# Force rebuild (if data changed significantly)
php artisan indexes:manage build --force

# Quick stats check
php artisan indexes:manage stats
```

---

## 🐛 Troubleshooting

### Problem: "No index keys found"

**Solution:**
```powershell
# Build indexes first
php artisan indexes:manage build
```

### Problem: "Slow filter responses"

**Checks:**
1. Verify indexes exist: `php artisan indexes:manage stats`
2. Check Redis memory: `redis-cli INFO memory`
3. Rebuild indexes: `php artisan indexes:manage build --force`

### Problem: "Empty results for valid filters"

**Checks:**
1. Ensure products have `status = 'active'`
2. Check category/brand IDs are correct
3. Verify indexes have data:
   ```php
   Redis::scard('index:category:1') // Should be > 0
   ```

### Problem: "Memory issues"

**Solutions:**
1. Increase Redis max memory in `redis.conf`
2. Set eviction policy: `maxmemory-policy allkeys-lru`
3. Monitor: `redis-cli INFO memory`

---

## 📈 Monitoring in Production

### Check Cache Hit Rates

```powershell
php artisan cache:structure --stats
```

### Monitor Redis Memory

```powershell
# If redis-cli is available
redis-cli INFO memory | Select-String "used_memory"
```

### Log Analysis

```powershell
# Filter performance logs
Get-Content storage\logs\laravel.log | Select-String "FastFilter"

# Count cache hits vs misses
Get-Content storage\logs\laravel.log | Select-String "Cache HIT"
Get-Content storage\logs\laravel.log | Select-String "Cache MISS"
```

---

## ✅ Success Criteria

Your implementation is working correctly if:

- ✅ `php artisan indexes:manage stats` shows 100+ index keys
- ✅ Memory usage is 200-800MB (not GB or TB)
- ✅ Test command shows response times under 300ms
- ✅ Filter pages load in under 1 second
- ✅ Laravel logs show "FastFilter" entries
- ✅ No Redis connection errors

---

## 🚀 Next Steps

Once indexes are working:

1. **Integrate into productSubCat** (see INDEXING_QUICK_START.md)
2. **Enable hot combo caching** (SmartFilterCacheService)
3. **Add pagination pre-fetching**
4. **Monitor and optimize**

---

## 📞 Quick Reference

```powershell
# Build
php artisan indexes:manage build

# Stats
php artisan indexes:manage stats

# Test
php artisan indexes:manage test

# Clean
php artisan indexes:manage clean

# Check logs
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "FastFilter"
```

---

**Current Status:** Run `php artisan indexes:manage stats` to see your setup! 🎯
