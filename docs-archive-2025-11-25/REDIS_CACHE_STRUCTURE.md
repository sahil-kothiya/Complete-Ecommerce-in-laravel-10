# Redis Cache Structure Reference

## 🎯 Overview

This document describes the organized Redis cache structure for the e-commerce filter system.

---

## 📁 Cache Namespace Hierarchy

```
ecommerce:v1:                          # Root namespace
├── filters:                           # Filter results cache
│   └── products:                      # Product filter results
│       └── {hash}:                    # Filter combination hash
│           └── page:{n}:size:{m}     # Paginated results
│
├── indexes:                           # Single-dimension indexes
│   ├── category_id:{id}:page:{n}     # By category
│   ├── brand:{name}:page:{n}         # By brand
│   ├── price_range:{min-max}:page:{n}# By price
│   └── rating:{n}:page:{n}           # By rating
│
├── metrics:                           # Performance metrics
│   ├── cache_hit:tier1               # Tier 1 hit counter
│   ├── cache_hit:tier2               # Tier 2 hit counter
│   └── cache_miss                    # Miss counter
│
├── engagement:                        # User engagement tracking
│   └── views:{hash}                  # Page view counter per filter combo
│
└── analytics:                         # Analytics data
    └── hot_combos                    # Sorted set of popular combinations
```

---

## 🔑 Cache Key Formats

### Filter Results
**Format:** `ecommerce:v1:filters:products:{hash}:page:{page}:size:{perPage}`

**Examples:**
```
ecommerce:v1:filters:products:19f7c59267724930c452a355d50c401:page:1:size:12
ecommerce:v1:filters:products:abc123def456:page:2:size:24
ecommerce:v1:filters:products:xyz789:page:1:size:12
```

**TTL:** 
- Tier 1 (hot): 3600s (1 hour)
- Tier 2 (warm): 21600s (6 hours)
- Tier 3 (cold): 900s (15 minutes)

---

### Single-Dimension Indexes
**Format:** `ecommerce:v1:indexes:{dimension}:{value}:page:{page}`

**Examples:**
```
ecommerce:v1:indexes:category_id:5:page:1
ecommerce:v1:indexes:brand:Nike:page:1
ecommerce:v1:indexes:price_range:0-100:page:1
ecommerce:v1:indexes:rating:4:page:1
```

**TTL:** 21600s (6 hours)

---

### Metrics
**Format:** `ecommerce:v1:metrics:{metric_name}`

**Keys:**
```
ecommerce:v1:metrics:cache_hit:tier1    # INTEGER counter
ecommerce:v1:metrics:cache_hit:tier2    # INTEGER counter
ecommerce:v1:metrics:cache_miss         # INTEGER counter
```

**TTL:** No expiration (counters reset manually)

---

### Engagement Tracking
**Format:** `ecommerce:v1:engagement:views:{hash}`

**Examples:**
```
ecommerce:v1:engagement:views:19f7c59267724930c452a355d50c401
ecommerce:v1:engagement:views:abc123def456
```

**TTL:** 300s (5 minutes)

---

### Analytics (Hot Combos)
**Format:** `ecommerce:v1:analytics:hot_combos`

**Type:** Redis Sorted Set (ZSET)

**Structure:**
- **Members:** Filter cache keys
- **Scores:** Access count

**Example:**
```redis
ZREVRANGE ecommerce:v1:analytics:hot_combos 0 9 WITHSCORES

1) "ecommerce:v1:filters:products:19f7c59267724930c452a355d50c401:page:1:size:12"
2) "1250"
3) "ecommerce:v1:filters:products:abc123:page:1:size:12"
4) "980"
...
```

**Retention:** Top 500 combinations only

---

## 🔧 Management Commands

### View Cache Structure
```bash
php artisan cache:structure --show
```

**Output:**
```
📁 Cache Namespace Structure:

Component  | Prefix
-----------|----------------------------------------
Namespace  | ecommerce
Version    | v1
Filters    | ecommerce:v1:filters
Indexes    | ecommerce:v1:indexes
Metrics    | ecommerce:v1:metrics
Engagement | ecommerce:v1:engagement
Analytics  | ecommerce:v1:analytics:hot_combos
```

---

### View Statistics
```bash
php artisan cache:structure --stats
```

**Output:**
```
📊 Cache Statistics:

Metric              | Value
--------------------|----------
Tier 1 Hits         | 12,450
Tier 2 Hits         | 2,180
Cache Misses        | 850
Total Requests      | 15,480
Hit Rate            | 94.51%
Hot Combos Tracked  | 385
```

---

### Analyze Key Distribution
```bash
php artisan cache:structure --analyze
```

**Output:**
```
🔍 Analyzing Redis key distribution...

Category    | Count | Percentage
------------|-------|------------
filters     | 1,245 | 76%
indexes     | 280   | 17%
metrics     | 3     | 0.2%
engagement  | 85    | 5.2%
analytics   | 1     | 0.1%
other       | 20    | 1.5%

📦 Storage Info:
  Total Keys: 1,634
  Estimated Size: 8.45 MB
  Redis Memory Used: 12.3 MB
  Redis Memory Peak: 15.7 MB
```

---

### Clear All Filter Cache
```bash
php artisan cache:structure --clear
```

**Clears:** All keys matching `ecommerce:v1:*`

---

### Clear Product Cache Only
```bash
php artisan cache:structure --clear-products
```

**Clears:** Only `ecommerce:v1:filters:*` (keeps metrics/analytics)

---

## 📊 Cache Tiers

### Tier 1: Hot Path (High Priority)
- **Keys:** Popular filter combinations (top 10%)
- **TTL:** 1 hour
- **Expected Hit Rate:** 70-80%
- **Storage:** ~2-5 GB

### Tier 2: Indexed (Medium Priority)
- **Keys:** Single-dimension indexes
- **TTL:** 6 hours
- **Expected Hit Rate:** 15-20%
- **Storage:** ~1-2 GB

### Tier 3: Cold (Low Priority)
- **Keys:** Rare/complex filter combinations
- **TTL:** 15 minutes
- **Expected Hit Rate:** 5-10%
- **Storage:** ~500 MB - 1 GB

---

## 🎯 Filter Hash Generation

Filters are hashed to create consistent, short keys:

```php
// Input filters
$filters = [
    'category_id' => [5],
    'brand' => ['Nike', 'Adidas'],
    'price_range' => '0-100',
    'min_rating' => [4]
];

// Normalized (sorted for consistency)
$normalized = [
    'brand' => ['Adidas', 'Nike'],        // sorted alphabetically
    'category_id' => [5],
    'min_rating' => [4],
    'price_range' => '0-100'
];

// Hash
$hash = md5(json_encode($normalized));
// Result: "19f7c59267724930c452a355d50c401"

// Final key
$key = "ecommerce:v1:filters:products:19f7c59267724930c452a355d50c401:page:1:size:12";
```

---

## 🔍 Redis Queries

### Get All Filter Keys
```bash
redis-cli KEYS "ecommerce:v1:filters:*"
```

### Get Specific Filter Result
```bash
redis-cli GET "ecommerce:v1:filters:products:19f7c59267724930c452a355d50c401:page:1:size:12"
```

### Get Top 10 Hot Combos
```bash
redis-cli ZREVRANGE "ecommerce:v1:analytics:hot_combos" 0 9 WITHSCORES
```

### Get Cache Hit Statistics
```bash
redis-cli MGET \
  "ecommerce:v1:metrics:cache_hit:tier1" \
  "ecommerce:v1:metrics:cache_hit:tier2" \
  "ecommerce:v1:metrics:cache_miss"
```

### Count All Ecommerce Keys
```bash
redis-cli --scan --pattern "ecommerce:v1:*" | wc -l
```

### Check Memory Usage by Pattern
```bash
redis-cli --bigkeys --pattern "ecommerce:v1:*"
```

---

## 🛠️ Maintenance

### Daily Tasks (Automated)
```bash
# Scheduled at 2 AM
php artisan cache:warm-filters --auto --limit=200
```

### Weekly Tasks
```bash
# Clear old analytics (Sunday 3 AM)
redis-cli DEL "ecommerce:v1:analytics:hot_combos"

# Re-index products (Sunday 4 AM)
php artisan scout:import "App\Models\Product"
```

### Manual Cleanup
```bash
# Remove expired keys
redis-cli --scan --pattern "ecommerce:v1:*" | xargs redis-cli DEL

# Or use the command
php artisan cache:structure --clear
```

---

## 🎨 Benefits of This Structure

### ✅ Organized
- Clear hierarchy
- Easy to navigate
- Predictable patterns

### ✅ Versionable
- `v1` allows for future schema changes
- Can run multiple versions simultaneously
- Easy rollback

### ✅ Scalable
- Namespace isolation
- Easy to partition across Redis instances
- Can set different TTLs per category

### ✅ Debuggable
- Human-readable keys
- Easy to query specific patterns
- Clear metrics tracking

### ✅ Maintainable
- Selective cache clearing
- Easy monitoring
- Clear separation of concerns

---

## 🔄 Migration from Old Structure

### Old Keys (Messy)
```
e_shop_database_product_count_19f7c59267724930c452a355d50c401_max_price
filter:products:abc123:p1:pp12
hot_filter_combos
metrics:cache_hit:tier1
```

### New Keys (Clean)
```
ecommerce:v1:filters:products:19f7c59267724930c452a355d50c401:page:1:size:12
ecommerce:v1:analytics:hot_combos
ecommerce:v1:metrics:cache_hit:tier1
```

### Migration Steps
```bash
# 1. Clear old cache
php artisan cache:clear

# 2. Clear Redis
redis-cli FLUSHDB

# 3. Warm up new structure
php artisan cache:warm-filters --auto --limit=100
```

---

## 📈 Performance Monitoring

### Track Hit Rates
```php
$stats = app(\App\Services\SmartFilterCacheService::class)->getStats();

echo "Hit Rate: " . $stats['hit_rate'] . "%\n";
```

### Expected Performance
- **Tier 1 Hit:** 10-30ms
- **Tier 2 Hit:** 30-80ms
- **Cache Miss:** 50-200ms (search engine)

### Alerts
Set up monitoring for:
- Hit rate < 85%
- Average response > 100ms
- Redis memory > 80% capacity

---

## 🎓 Best Practices

1. **Never hardcode keys** - use the service methods
2. **Always use namespaces** - for isolation
3. **Monitor hit rates** - optimize popular combos
4. **Set appropriate TTLs** - balance freshness vs. performance
5. **Regular cleanup** - remove unused keys
6. **Version your cache** - plan for schema changes
7. **Track metrics** - make data-driven decisions

---

## 📞 Quick Reference

| Task | Command |
|------|---------|
| Show structure | `php artisan cache:structure --show` |
| View stats | `php artisan cache:structure --stats` |
| Analyze keys | `php artisan cache:structure --analyze` |
| Clear all | `php artisan cache:structure --clear` |
| Clear products | `php artisan cache:structure --clear-products` |
| Warm cache | `php artisan cache:warm-filters --auto` |
| View hot combos | `php artisan cache:warm-filters --analyze` |

---

**Last Updated:** November 21, 2025  
**Cache Version:** v1  
**Namespace:** ecommerce
