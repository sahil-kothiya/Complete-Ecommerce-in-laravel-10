# 🚀 Quick Reference - Smart Filter Cache Commands

## 📋 Essential Commands

### View Cache Structure
```bash
php artisan cache:structure --show
```
Shows the organized namespace hierarchy and example keys.

### Analyze Redis Keys
```bash
php artisan cache:structure --analyze
```
See how keys are distributed across categories and memory usage.

### View Cache Statistics
```bash
php artisan cache:structure --stats
```
Check hit rates, cache performance, and popular filter combinations.

### Clear All Filter Cache
```bash
php artisan cache:structure --clear
```
⚠️ Removes ALL cache including metrics and analytics.

### Clear Product Cache Only
```bash
php artisan cache:structure --clear-products
```
Clears product filter results but keeps metrics/analytics.

### Warm Cache Automatically
```bash
php artisan cache:warm-filters --auto --limit=200
```
Analyzes popular combos and warms cache for top 200.

### Analyze Popular Combinations
```bash
php artisan cache:warm-filters --analyze
```
See which filter combinations are most popular.

---

## 🎯 Cache Key Structure

```
ecommerce:v1:{category}:{details}

Examples:
✅ ecommerce:v1:filters:products:19f7c59267:page:1:size:12
✅ ecommerce:v1:indexes:category_id:5:page:1
✅ ecommerce:v1:metrics:cache_hit:tier1
✅ ecommerce:v1:engagement:views:abc123
✅ ecommerce:v1:analytics:hot_combos
```

---

## 📊 Redis CLI Commands

### View All Filter Keys
```bash
redis-cli KEYS "ecommerce:v1:filters:*"
```

### View Top 10 Popular Combos
```bash
redis-cli ZREVRANGE "ecommerce:v1:analytics:hot_combos" 0 9 WITHSCORES
```

### Get Cache Hit Metrics
```bash
redis-cli MGET \
  "ecommerce:v1:metrics:cache_hit:tier1" \
  "ecommerce:v1:metrics:cache_hit:tier2" \
  "ecommerce:v1:metrics:cache_miss"
```

### Count All Cache Keys
```bash
redis-cli --scan --pattern "ecommerce:v1:*" | wc -l
```

### Check Memory Usage
```bash
redis-cli INFO memory
```

---

## 🔄 Maintenance Schedule

### Daily (2 AM)
```bash
php artisan cache:warm-filters --auto --limit=200
```

### Weekly (Sunday 3 AM)
```bash
# Clear old analytics
redis-cli DEL "ecommerce:v1:analytics:hot_combos"

# Re-index products (Sunday 4 AM)
php artisan scout:import "App\Models\Product"
```

### As Needed
```bash
# After product updates
php artisan cache:structure --clear-products

# After deployment
php artisan cache:structure --clear
php artisan cache:warm-filters --auto --limit=100
```

---

## 📈 Expected Performance

| Scenario | Response Time | Hit Rate |
|----------|---------------|----------|
| Tier 1 Hit | 10-30ms | 70-80% |
| Tier 2 Hit | 30-80ms | 15-20% |
| Cache Miss | 50-200ms | 5-10% |
| **Overall** | **20-50ms** | **90-95%** |

---

## 🎓 Usage Examples

### Scenario 1: After Product Import
```bash
# Clear product cache
php artisan cache:structure --clear-products

# Warm popular combos
php artisan cache:warm-filters --auto --limit=100
```

### Scenario 2: Check Performance
```bash
# View stats
php artisan cache:structure --stats

# Analyze if hit rate < 85%
php artisan cache:warm-filters --analyze

# Warm more combos
php artisan cache:warm-filters --warm --limit=500
```

### Scenario 3: Debugging
```bash
# Show structure
php artisan cache:structure --show

# Analyze distribution
php artisan cache:structure --analyze

# Check specific key
redis-cli GET "ecommerce:v1:filters:products:abc123:page:1:size:12"
```

---

## 🛠️ Troubleshooting

### Low Hit Rate (< 85%)
```bash
# 1. Check popular combos
php artisan cache:warm-filters --analyze

# 2. Warm more combinations
php artisan cache:warm-filters --warm --limit=500

# 3. Check stats again
php artisan cache:structure --stats
```

### High Memory Usage
```bash
# 1. Analyze distribution
php artisan cache:structure --analyze

# 2. Clear old cache
php artisan cache:structure --clear-products

# 3. Reduce warm limit
php artisan cache:warm-filters --warm --limit=100
```

### Slow Response Times
```bash
# 1. Check cache stats
php artisan cache:structure --stats

# 2. Ensure Redis is running
redis-cli PING

# 3. Check search engine
# Meilisearch: http://127.0.0.1:7700/health
```

---

## 📱 Save This!

**Print or bookmark this file for quick reference during development and maintenance.**

---

**Last Updated:** November 21, 2025  
**Version:** 1.0  
**Cache Namespace:** ecommerce:v1
