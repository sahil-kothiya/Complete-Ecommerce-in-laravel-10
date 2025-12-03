# Redis Quick Reference Guide
## TL;DR - What You Need to Know

---

## 🚀 Getting Started

### Import the Manager

```php
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;
```

### Basic Pattern

```php
// 1. Generate key
$key = RedisKeyManager::productCard($id);

// 2. Try cache
$data = RedisCacheService::get($key);

// 3. Fallback to DB if miss
if (!$data) {
    $data = Product::find($id);
    RedisCacheService::put($key, $data, 3600);
}

// 4. Use data
return $data;
```

---

## 📖 Common Keys Cheat Sheet

### Products
```php
RedisKeyManager::productCard(123)           // ec:p:123:card
RedisKeyManager::productFull(123)           // ec:p:123:full
RedisKeyManager::productMeta(123)           // ec:p:123:meta
RedisKeyManager::productBySlug('phone')     // ec:p:slug:phone
```

### Variants
```php
RedisKeyManager::variantCard(456)           // ec:v:456:card
RedisKeyManager::variantFull(456)           // ec:v:456:full
RedisKeyManager::variantsForProduct(123)    // ec:v:p:123:all
RedisKeyManager::variantsActive(123)        // ec:v:p:123:active
RedisKeyManager::variantBySku('SKU-123')    // ec:v:sku:SKU-123
```

### Categories & Brands
```php
RedisKeyManager::categoryFull(10)           // ec:cat:10:full
RedisKeyManager::categoryProducts(10)       // ec:cat:10:prods
RedisKeyManager::brandFull(5)               // ec:br:5:full
RedisKeyManager::brandProducts(5)           // ec:br:5:prods
```

### Filters & Search
```php
RedisKeyManager::filterMeta('electronics')  // ec:flt:meta:electronics
RedisKeyManager::filterResults($hash)       // ec:flt:res:{hash}
RedisKeyManager::searchQuery('phone', 1)    // ec:srch:q:{hash}:p1
RedisKeyManager::searchSuggestions('ph')    // ec:srch:sug:{hash}
```

### Pages
```php
$v = RedisCacheService::getVersion();
RedisKeyManager::pageHome($v)               // ec:pg:home:v1
RedisKeyManager::pageProduct(123)           // ec:pg:prod:123
RedisKeyManager::pageCategory(10, 2)        // ec:pg:cat:10:p2
```

### Components
```php
RedisKeyManager::componentNav()             // ec:cmp:nav
RedisKeyManager::componentBanners()         // ec:cmp:banner
RedisKeyManager::componentFeatured()        // ec:cmp:feat
```

### User Data
```php
RedisKeyManager::userCart(100)              // ec:usr:100:cart
RedisKeyManager::userWishlist(100)          // ec:usr:100:wish
RedisKeyManager::userRecent(100)            // ec:usr:100:recent
```

### Indexes (Fast Filtering)
```php
RedisKeyManager::indexCategory(10)          // ec:idx:cat:10
RedisKeyManager::indexBrand(5)              // ec:idx:br:5
RedisKeyManager::indexPriceRange('100-500') // ec:idx:price:100-500
RedisKeyManager::indexFeatured()            // ec:idx:feat
RedisKeyManager::indexBestSellers()         // ec:idx:bestsell
```

---

## 🎯 When to Use What

| Task | Use This | Key Method |
|------|----------|------------|
| **Product listings** | Card | `productCard($id)` |
| **Product detail page** | Full | `productFull($id)` |
| **Quick stock check** | Meta | `productMeta($id)` |
| **Variant selector** | Card | `variantCard($id)` |
| **Find by slug** | Lookup | `productBySlug($slug)` |
| **Category filtering** | Index | `indexCategory($id)` |
| **Multi-filter** | Index intersection | See below |
| **Homepage** | Versioned page | `pageHome($version)` |
| **Search results** | Query cache | `searchQuery($q, $page)` |

---

## 💾 Cache Operations

### Get (with fallback)
```php
$key = RedisKeyManager::productCard(123);
$product = RedisCacheService::remember($key, 3600, function() {
    return Product::find(123)->only(['id', 'title', 'price']);
});
```

### Put
```php
$key = RedisKeyManager::productCard(123);
$data = ['id' => 123, 'title' => 'Phone', 'price' => 999];
RedisCacheService::put($key, $data, 3600); // TTL in seconds
```

### Forget
```php
$key = RedisKeyManager::productCard(123);
RedisCacheService::forget($key);
```

### Forget Pattern
```php
// Delete all caches for product 123
$pattern = RedisKeyManager::patternProduct(123);
RedisCacheService::forgetPattern($pattern); // Deletes ec:p:123:*
```

### Batch Get
```php
$keys = [
    RedisKeyManager::productCard(1),
    RedisKeyManager::productCard(2),
    RedisKeyManager::productCard(3),
];
$products = RedisCacheService::mget($keys);
```

### Batch Put
```php
$data = [
    RedisKeyManager::productCard(1) => $product1,
    RedisKeyManager::productCard(2) => $product2,
];
RedisCacheService::mset($data, 3600);
```

---

## 🔥 Advanced Patterns

### Multi-Filter with Indexes
```php
use Illuminate\Support\Facades\Redis;

// Category 5 AND Brand 10 AND Price 100-500
$catKey = RedisKeyManager::indexCategory(5);
$brandKey = RedisKeyManager::indexBrand(10);
$priceKey = RedisKeyManager::indexPriceRange('100-500');

// Redis SET intersection = ultra-fast
$productIds = Redis::sinter($catKey, $brandKey, $priceKey);
```

### Versioned Cache Invalidation
```php
// Get current version
$version = RedisCacheService::getVersion(); // e.g., 1

// Cache homepage with version
$key = RedisKeyManager::pageHome($version); // ec:pg:home:v1
RedisCacheService::put($key, $homepageData, 1800);

// Later: Invalidate ALL versioned caches instantly
RedisCacheService::incrementVersion(); // Now version = 2

// Old caches at v1 become unreachable
// New requests use v2
```

### Distributed Locking
```php
$lockKey = RedisKeyManager::tempLock('rebuild-index');

if (RedisCacheService::lock($lockKey, 300)) {
    try {
        // Only one process executes this
        rebuildProductIndexes();
    } finally {
        RedisCacheService::unlock($lockKey);
    }
}
```

### Lazy Loading Variants
```php
// Store variant IDs only
$key = RedisKeyManager::variantsActive($productId);
$variantIds = RedisCacheService::get($key);

if (!$variantIds) {
    $variantIds = ProductVariant::where('product_id', $productId)
        ->where('status', 'active')
        ->pluck('id')
        ->toArray();
    RedisCacheService::put($key, $variantIds, 1800);
}

// Load full variant only when needed
$selectedVariant = RedisCacheService::remember(
    RedisKeyManager::variantFull($selectedId),
    3600,
    fn() => ProductVariant::find($selectedId)
);
```

---

## 📊 Monitoring

### Cache Stats
```php
$stats = RedisCacheService::getStats();
print_r($stats['redis']);

// Output:
// [
//     'hits' => 12345,
//     'misses' => 234,
//     'hit_rate' => 98.14,
//     'total_keys' => 45678,
//     'used_memory' => '256.34 MB',
//     'ops_per_sec' => 1234,
// ]
```

### Check if Key Exists
```php
$key = RedisKeyManager::productCard(123);
if (RedisCacheService::has($key)) {
    echo "Cached!";
}
```

### Get TTL
```php
$key = RedisKeyManager::productCard(123);
$ttl = RedisCacheService::ttl($key); // Seconds remaining
echo "Expires in {$ttl} seconds";
```

### Count Keys by Pattern
```php
$pattern = RedisKeyManager::patternVariants(); // ec:v:*
$keys = RedisCacheService::keys($pattern, 1000);
echo "Found " . count($keys) . " variant keys";
```

---

## 🛠️ Debugging

### See All Product Keys for ID
```php
$pattern = RedisKeyManager::patternProduct(123); // ec:p:123:*
$keys = RedisCacheService::keys($pattern);
print_r($keys);

// Output:
// [
//     'ec:p:123:full',
//     'ec:p:123:card',
//     'ec:p:123:meta',
//     'ec:p:123:vars',
// ]
```

### Parse a Key
```php
$key = 'ec:p:123:card';
$parsed = RedisKeyManager::parse($key);
print_r($parsed);

// Output:
// [
//     'namespace' => 'ec',
//     'module' => 'p',
//     'type' => '123',
//     'id' => 'card',
//     'suffix' => null,
// ]
```

### Validate Key
```php
$key = 'ec:p:123:card';
if (RedisKeyManager::isValid($key)) {
    echo "Valid key!";
}
```

---

## ⚙️ Configuration

### Enable/Disable Modules
```php
// config/redis_cache.php
'enabled' => [
    'master' => true,        // Master switch
    'products' => true,      // Product caching
    'variants' => true,      // Variant caching
    'categories' => true,    // Category caching
    'search' => true,        // Search caching
    'filters' => true,       // Filter caching
    'indexes' => true,       // Index caching
],
```

### Adjust TTLs
```php
// config/redis_cache.php
'ttl' => [
    'product_card' => 7200,      // 2 hours
    'variant_card' => 7200,      // 2 hours
    'variants_stock' => 900,     // 15 minutes
    'search_results' => 1800,    // 30 minutes
],
```

### Or Use .env
```env
CACHE_TTL_PRODUCT_CARD=7200
CACHE_TTL_VARIANT_CARD=7200
CACHE_TTL_VARIANTS_STOCK=900
```

---

## 🚨 Common Pitfalls

### ❌ Don't Hardcode Keys
```php
// BAD
$data = Redis::get('product_123');

// GOOD
$key = RedisKeyManager::productCard(123);
$data = RedisCacheService::get($key);
```

### ❌ Don't Cache Too Much
```php
// BAD - Caching complete product with all relations
$product = Product::with('variants', 'images', 'reviews')->find($id);
RedisCacheService::put($key, $product, 3600);

// GOOD - Cache lightweight card for listings
$productCard = Product::find($id)->only(['id', 'title', 'price']);
RedisCacheService::put(RedisKeyManager::productCard($id), $productCard, 7200);
```

### ❌ Don't Forget TTL
```php
// BAD - No TTL = stays forever
Redis::set($key, $value);

// GOOD - Always set TTL
RedisCacheService::put($key, $value, 3600);
```

### ❌ Don't Use Keys() in Production
```php
// BAD - Blocks Redis on large datasets
$keys = Redis::keys('ec:p:*');

// GOOD - Use SCAN instead
$keys = RedisCacheService::keys('ec:p:*', 1000);
```

---

## 📋 Checklist for New Features

When adding Redis caching to a new feature:

- [ ] Use `RedisKeyManager` to generate keys
- [ ] Choose appropriate cache level (card/full/meta)
- [ ] Set appropriate TTL based on data volatility
- [ ] Add invalidation logic when data changes
- [ ] Use batch operations for multiple keys
- [ ] Consider using indexes for filtering
- [ ] Test cache hit/miss scenarios
- [ ] Monitor memory usage
- [ ] Document key patterns

---

## 🎓 Remember

1. **Always use RedisKeyManager** - never hardcode
2. **Choose right cache level** - card for lists, full for details
3. **Set appropriate TTLs** - balance freshness vs efficiency
4. **Use indexes for filtering** - SET operations are lightning fast
5. **Version critical pages** - instant invalidation
6. **Monitor regularly** - check hit rates and memory

---

## 📚 Full Documentation

See `REDIS_ARCHITECTURE.md` for complete documentation with examples and deep dives.

---

**Quick Help Commands:**

```bash
# Check Redis stats
php artisan tinker
>>> RedisCacheService::getStats()

# Clear all caches
>>> RedisCacheService::flush()

# Count keys
>>> RedisCacheService::dbSize()

# List product keys
>>> RedisCacheService::keys(RedisKeyManager::patternProducts(), 100)
```
