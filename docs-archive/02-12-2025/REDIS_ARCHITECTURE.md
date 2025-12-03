# Redis Architecture Documentation
## Unified, Scalable Structure for 10M+ Products with Variants

**Last Updated:** December 2, 2025  
**Version:** 2.0  
**Tech Stack:** PostgreSQL + Redis + Laravel 10

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Key Hierarchy](#key-hierarchy)
3. [Module Reference](#module-reference)
4. [Usage Examples](#usage-examples)
5. [TTL Configuration](#ttl-configuration)
6. [Performance Optimization](#performance-optimization)
7. [Migration Guide](#migration-guide)
8. [Best Practices](#best-practices)

---

## 🎯 Overview

### Design Principles

This Redis architecture is built on four core principles:

1. **Memory Efficiency**: Ultra-short namespace (`ec:` instead of `ecommerce:v1:`) saves significant memory at 10M+ scale
2. **Predictable Structure**: Consistent hierarchy makes debugging, monitoring, and bulk operations simple
3. **Granular Caching**: Separate `card`/`full`/`meta` variants let you cache only what you need
4. **Instant Invalidation**: Version-based keys enable atomic cache invalidation without scanning

### Key Statistics

- **Namespace**: `ec:` (2 characters - saves ~15 bytes per key vs `ecommerce:v1:`)
- **At 10M products**: Saves ~150MB just in key names
- **Modules**: 14 distinct modules covering all application needs
- **Cache Layers**: 3-tier caching (full, card, meta) for optimal load times

---

## 🗂️ Key Hierarchy

```
ec:                                  // Root namespace (2 chars)
├─ p:                               // Products
│  ├─ {id}:full                     // Full product data with relationships
│  ├─ {id}:card                     // Lightweight card for listings
│  ├─ {id}:meta                     // Metadata only (no images/variants)
│  ├─ {id}:vars                     // All variants for product
│  ├─ {id}:imgs                     // Product images
│  ├─ {id}:reviews                  // Product reviews
│  ├─ {id}:rating                   // Rating aggregate
│  ├─ slug:{slug}                   // Product ID by slug lookup
│  └─ sku:{sku}                     // Product ID by SKU lookup
│
├─ v:                               // Variants
│  ├─ {id}:full                     // Full variant data
│  ├─ {id}:card                     // Lightweight variant card
│  ├─ {id}:imgs                     // Variant-specific images
│  ├─ {id}:opts                     // Variant options (color, size, etc.)
│  ├─ p:{pid}:all                   // All variants for product {pid}
│  ├─ p:{pid}:active                // Active variants only
│  ├─ p:{pid}:stock                 // In-stock variants only
│  └─ sku:{sku}                     // Variant ID by SKU lookup
│
├─ cat:                             // Categories
│  ├─ {id}:full                     // Full category data
│  ├─ {id}:tree                     // Category hierarchy
│  ├─ {id}:prods                    // Product IDs in category (SET)
│  ├─ {id}:count                    // Product count
│  ├─ {id}:filters                  // Available filters
│  ├─ tree                          // Full category tree
│  └─ slug:{slug}                   // Category ID by slug
│
├─ br:                              // Brands
│  ├─ {id}:full                     // Full brand data
│  ├─ {id}:prods                    // Product IDs for brand (SET)
│  ├─ {id}:count                    // Product count
│  ├─ all                           // All brands list
│  └─ slug:{slug}                   // Brand ID by slug
│
├─ flt:                             // Filters
│  ├─ meta:{cat_slug}               // Filter metadata for category
│  ├─ res:{hash}                    // Cached filter results
│  ├─ opts:{cat}:{type}             // Filter options
│  ├─ price:{cat}                   // Price ranges
│  ├─ price_idx:{cat}               // Price index
│  ├─ cnt:{cat}:{hash}              // Result count
│  └─ avail:{cat_id}:{type}         // Available filter values
│
├─ srch:                            // Search
│  ├─ q:{hash}:p{n}                 // Query results page N
│  ├─ sug:{term_hash}               // Search suggestions
│  ├─ pop                           // Popular searches (ZSET)
│  ├─ recent:{user_id}              // User's recent searches
│  └─ cnt:{hash}                    // Result count
│
├─ idx:                             // Indexes (Pre-computed Sets)
│  ├─ cat:{id}                      // Product IDs in category (SET)
│  ├─ br:{id}                       // Product IDs for brand (SET)
│  ├─ price:{range}                 // Product IDs in price range (SET)
│  ├─ rating:{stars}                // Product IDs with rating (SET)
│  ├─ disc:{pct}                    // Product IDs with discount % (SET)
│  ├─ feat                          // Featured product IDs (SET)
│  ├─ new                           // New product IDs (SET)
│  ├─ sale                          // On-sale product IDs (SET)
│  └─ bestsell                      // Best-sellers (ZSET with scores)
│
├─ pg:                              // Page Caches
│  ├─ home:v{ver}                   // Homepage (versioned)
│  ├─ cat:{id}:p{n}                 // Category page N
│  ├─ prod:{id}                     // Product detail page
│  ├─ brand:{id}:p{n}               // Brand page N
│  └─ search:{hash}:p{n}            // Search results page N
│
├─ cmp:                             // Components
│  ├─ nav                           // Navigation menu
│  ├─ footer                        // Footer content
│  ├─ banner                        // Active banners
│  ├─ feat                          // Featured products section
│  ├─ deal                          // Deals section
│  ├─ new                           // New arrivals section
│  └─ trend                         // Trending products
│
├─ usr:                             // User Data
│  ├─ {id}:cart                     // User cart
│  ├─ {id}:wish                     // User wishlist
│  ├─ {id}:recent                   // Recently viewed products
│  ├─ {id}:profile                  // User profile cache
│  ├─ {id}:orders                   // User orders
│  └─ sess:{session_id}             // Session data
│
├─ set:                             // Settings
│  ├─ app                           // Application settings
│  ├─ seo                           // SEO settings
│  ├─ smtp                          // SMTP settings
│  ├─ payment                       // Payment settings
│  └─ general                       // General settings
│
├─ agg:                             // Aggregations/Statistics
│  ├─ stats                         // Global statistics
│  ├─ count:prods                   // Total product count
│  ├─ count:cats                    // Total category count
│  ├─ top:{type}                    // Top items (ZSET)
│  └─ stats:{period}                // Period-specific stats
│
├─ tmp:                             // Temporary (Short TTL)
│  ├─ lock:{key}                    // Distributed locks
│  ├─ union_{type}:{hash}           // Temporary set unions
│  ├─ job:{id}                      // Job processing data
│  ├─ rate:{ip}:{endpoint_hash}     // Rate limiting
│  └─ flt:{hash}                    // Temporary filter cache
│
└─ meta:                            // Metadata
   ├─ ver                           // Global cache version
   ├─ health                        // System health check
   ├─ warmup:{timestamp}            // Last warmup info
   ├─ sync:{entity}                 // Last sync timestamp
   ├─ hit:{tier}                    // Cache hit metrics
   └─ miss                          // Cache miss counter
```

---

## 📚 Module Reference

### 1. Products (`ec:p:*`)

#### Full Product Data
```php
use App\Services\RedisKeyManager;

$key = RedisKeyManager::productFull(123);
// Returns: ec:p:123:full
// Contains: Complete product data with all relationships
// TTL: 3600s (1 hour)
```

#### Product Card (Lightweight)
```php
$key = RedisKeyManager::productCard(123);
// Returns: ec:p:123:card
// Contains: ID, title, slug, price, discount, image, stock, rating
// TTL: 7200s (2 hours)
// Use: Product listings, category pages, search results
```

#### Product Metadata
```php
$key = RedisKeyManager::productMeta(123);
// Returns: ec:p:123:meta
// Contains: Basic info only - no images, no variants, no reviews
// TTL: 10800s (3 hours)
// Use: Quick lookups, SKU checks, availability checks
```

#### Lookup by Slug/SKU
```php
$key = RedisKeyManager::productBySlug('samsung-galaxy-s24');
// Returns: ec:p:slug:samsung-galaxy-s24
// Contains: Product ID
// TTL: 21600s (6 hours)

$key = RedisKeyManager::productBySku('PROD-S24-BL-256-8');
// Returns: ec:p:sku:PROD-S24-BL-256-8
// Contains: Product ID
```

---

### 2. Variants (`ec:v:*`)

#### Full Variant Data
```php
$key = RedisKeyManager::variantFull(456);
// Returns: ec:v:456:full
// Contains: Complete variant data with options, images, stock
// TTL: 3600s (1 hour)
```

#### Variant Card
```php
$key = RedisKeyManager::variantCard(456);
// Returns: ec:v:456:card
// Contains: ID, SKU, price, discount, stock, display_name
// TTL: 7200s (2 hours)
// Use: Variant selectors, quick variant displays
```

#### Variants for Product
```php
$key = RedisKeyManager::variantsForProduct(123);
// Returns: ec:v:p:123:all
// Data Type: SET of variant IDs
// TTL: 3600s (1 hour)

$key = RedisKeyManager::variantsActive(123);
// Returns: ec:v:p:123:active
// Data Type: SET of active variant IDs
// TTL: 1800s (30 minutes)

$key = RedisKeyManager::variantsInStock(123);
// Returns: ec:v:p:123:stock
// Data Type: SET of in-stock variant IDs
// TTL: 900s (15 minutes) - very dynamic
```

---

### 3. Categories (`ec:cat:*`)

```php
$key = RedisKeyManager::categoryFull(10);
// Returns: ec:cat:10:full
// TTL: 21600s (6 hours)

$key = RedisKeyManager::categoryProducts(10);
// Returns: ec:cat:10:prods
// Data Type: SET of product IDs
// TTL: 7200s (2 hours)

$key = RedisKeyManager::categoryTreeAll();
// Returns: ec:cat:tree
// Contains: Complete category hierarchy
// TTL: 43200s (12 hours)
```

---

### 4. Filters (`ec:flt:*`)

```php
$key = RedisKeyManager::filterMeta('electronics');
// Returns: ec:flt:meta:electronics
// Contains: Available filters, ranges, options
// TTL: 3600s (1 hour)

$hash = md5(json_encode($filters));
$key = RedisKeyManager::filterResults($hash);
// Returns: ec:flt:res:{hash}
// Contains: Filtered product IDs, pagination
// TTL: 1800s (30 minutes)

$key = RedisKeyManager::filterPriceRanges('smartphones');
// Returns: ec:flt:price:smartphones
// Contains: Min/max prices, common ranges
// TTL: 3600s (1 hour)
```

---

### 5. Search (`ec:srch:*`)

```php
$key = RedisKeyManager::searchQuery('samsung galaxy', 1);
// Returns: ec:srch:q:{hash}:p1
// Contains: Product IDs, metadata
// TTL: 1800s (30 minutes)

$key = RedisKeyManager::searchSuggestions('sam');
// Returns: ec:srch:sug:{hash}
// Contains: Array of suggested terms
// TTL: 3600s (1 hour)

$key = RedisKeyManager::searchPopular();
// Returns: ec:srch:pop
// Data Type: ZSET (scores = search count)
// TTL: 21600s (6 hours)
```

---

### 6. Indexes (`ec:idx:*`)

Pre-computed sets for ultra-fast filtering:

```php
$key = RedisKeyManager::indexCategory(5);
// Returns: ec:idx:cat:5
// Data Type: SET of product IDs in category 5
// TTL: 7200s (2 hours)

$key = RedisKeyManager::indexBrand(10);
// Returns: ec:idx:br:10
// Data Type: SET of product IDs for brand 10

$key = RedisKeyManager::indexPriceRange('100-500');
// Returns: ec:idx:price:100-500
// Data Type: SET of product IDs in that range

$key = RedisKeyManager::indexFeatured();
// Returns: ec:idx:feat
// Data Type: SET of featured product IDs

$key = RedisKeyManager::indexBestSellers();
// Returns: ec:idx:bestsell
// Data Type: ZSET (scores = sales count)
```

---

### 7. Page Caches (`ec:pg:*`)

```php
$version = RedisCacheService::getVersion();
$key = RedisKeyManager::pageHome($version);
// Returns: ec:pg:home:v1
// TTL: 1800s (30 minutes)
// Instant invalidation: increment version

$key = RedisKeyManager::pageCategory(10, 2);
// Returns: ec:pg:cat:10:p2
// Category 10, page 2

$key = RedisKeyManager::pageProduct(123);
// Returns: ec:pg:prod:123
```

---

### 8. Components (`ec:cmp:*`)

```php
$key = RedisKeyManager::componentNav();
// Returns: ec:cmp:nav
// TTL: 21600s (6 hours)

$key = RedisKeyManager::componentBanners();
// Returns: ec:cmp:banner
// TTL: 21600s (6 hours)

$key = RedisKeyManager::componentFeatured();
// Returns: ec:cmp:feat
// Contains: Featured products section data
```

---

## 💡 Usage Examples

### Example 1: Caching a Product Card

```php
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;

// Generate key
$productId = 123;
$key = RedisKeyManager::productCard($productId);

// Try cache first
$productCard = RedisCacheService::get($key);

if (!$productCard) {
    // Cache miss - fetch from database
    $productCard = Product::find($productId)->only([
        'id', 'title', 'slug', 'base_price', 'base_discount', 
        'is_featured', 'stock', 'average_rating'
    ]);
    
    // Cache for 2 hours
    RedisCacheService::put($key, $productCard, 7200);
}

return $productCard;
```

### Example 2: Caching Variants for Product

```php
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;

$productId = 123;

// Get all variant IDs from set
$variantsKey = RedisKeyManager::variantsForProduct($productId);
$variantIds = RedisCacheService::get($variantsKey);

if (!$variantIds) {
    $variantIds = ProductVariant::where('product_id', $productId)
        ->pluck('id')
        ->toArray();
    
    // Store as set for 1 hour
    RedisCacheService::put($variantsKey, $variantIds, 3600);
}

// Load individual variant cards
$variantCards = [];
foreach ($variantIds as $variantId) {
    $key = RedisKeyManager::variantCard($variantId);
    $card = RedisCacheService::remember($key, 7200, function() use ($variantId) {
        return ProductVariant::find($variantId)->only([
            'id', 'sku', 'price', 'discount', 'stock', 'display_name'
        ]);
    });
    $variantCards[] = $card;
}
```

### Example 3: Using Index Sets for Filtering

```php
use App\Services\RedisKeyManager;
use Illuminate\Support\Facades\Redis;

// Get products in category AND brand AND price range
$catKey = RedisKeyManager::indexCategory(5);
$brandKey = RedisKeyManager::indexBrand(10);
$priceKey = RedisKeyManager::indexPriceRange('100-500');

// Redis SET intersection = ultra-fast filtering
$productIds = Redis::sinter($catKey, $brandKey, $priceKey);

// If empty, indexes need building
if (empty($productIds)) {
    // Trigger index rebuild job
    dispatch(new RebuildProductIndexesJob());
}
```

### Example 4: Versioned Homepage Cache

```php
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;

// Get current cache version
$version = RedisCacheService::getVersion();
$key = RedisKeyManager::pageHome($version);

$homepage = RedisCacheService::remember($key, 1800, function() {
    return [
        'banners' => Banner::active()->get(),
        'featured' => Product::featured()->limit(12)->get(),
        'new_arrivals' => Product::latest()->limit(8)->get(),
        'bestsellers' => Product::bestSelling()->limit(8)->get(),
    ];
});

// To invalidate entire homepage:
// RedisCacheService::incrementVersion();
// Old cache at v1 becomes unreachable, new requests use v2
```

### Example 5: Distributed Locking

```php
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;

$lockKey = RedisKeyManager::tempLock("rebuild-indexes");

if (RedisCacheService::lock($lockKey, 300)) {
    try {
        // Only one process will execute this
        rebuildAllIndexes();
    } finally {
        RedisCacheService::unlock($lockKey);
    }
} else {
    Log::info("Index rebuild already in progress");
}
```

---

## ⏱️ TTL Configuration

### TTL Strategy by Data Type

| Data Type | TTL | Reasoning |
|-----------|-----|-----------|
| **Product Card** | 2 hours | Lightweight, frequently accessed |
| **Product Full** | 1 hour | Heavy data, may change |
| **Product Meta** | 3 hours | Rarely changes |
| **Variant Card** | 2 hours | Frequently displayed |
| **Variant Stock** | 15 min | Highly dynamic |
| **Categories** | 6 hours | Rarely change |
| **Brands** | 6 hours | Rarely change |
| **Filter Results** | 30 min | User-specific, frequently changing |
| **Search Results** | 30 min | Query-specific |
| **Indexes** | 2 hours | Pre-computed, rebuilt regularly |
| **Homepage** | 30 min | High traffic, needs freshness |
| **Settings** | 24 hours | Almost never change |

### Configuring TTLs

Edit `config/redis_cache.php`:

```php
'ttl' => [
    'variant_card' => env('CACHE_TTL_VARIANT_CARD', 7200),
    'variant_full' => env('CACHE_TTL_VARIANT_FULL', 3600),
    'variants_stock' => env('CACHE_TTL_VARIANTS_STOCK', 900),
    // ... more configurations
],
```

Or set in `.env`:

```env
CACHE_TTL_VARIANT_CARD=7200
CACHE_TTL_VARIANT_FULL=3600
CACHE_TTL_VARIANTS_STOCK=900
```

---

## ⚡ Performance Optimization

### 1. Memory Optimization

#### Short Keys Save Memory
```
Old: ecommerce:v1:entities:product:card:123 (41 chars)
New: ec:p:123:card (13 chars)
Savings: 28 chars = 28 bytes per key

At 10M products × 3 cache types = 30M keys
Total savings: 30M × 28 = 840MB just in key names!
```

#### Use Appropriate Cache Levels
```php
// ❌ Don't cache full product for listings
$product = Product::with('variants', 'images', 'reviews')->find($id);

// ✅ Cache lightweight card instead
$key = RedisKeyManager::productCard($id);
$product = RedisCacheService::get($key);
```

### 2. Batch Operations

```php
// ❌ Individual gets (slow)
foreach ($productIds as $id) {
    $key = RedisKeyManager::productCard($id);
    $products[] = RedisCacheService::get($key);
}

// ✅ Batch get with mget (fast)
$keys = array_map(fn($id) => RedisKeyManager::productCard($id), $productIds);
$products = RedisCacheService::mget($keys);
```

### 3. Index-Based Filtering

```php
// ❌ Database query for each filter
$products = Product::where('cat_id', 5)
    ->where('brand_id', 10)
    ->whereBetween('price', [100, 500])
    ->get();

// ✅ Redis SET intersection (microseconds)
$catKey = RedisKeyManager::indexCategory(5);
$brandKey = RedisKeyManager::indexBrand(10);
$priceKey = RedisKeyManager::indexPriceRange('100-500');
$productIds = Redis::sinter($catKey, $brandKey, $priceKey);
```

### 4. Lazy Loading

```php
// ❌ Load all variants upfront
$product->load('variants.images.options');

// ✅ Cache variant IDs, load on demand
$variantIds = RedisCacheService::get(
    RedisKeyManager::variantsActive($productId)
);

// Load only when user selects a variant
if ($selectedVariantId) {
    $variant = RedisCacheService::remember(
        RedisKeyManager::variantFull($selectedVariantId),
        3600,
        fn() => ProductVariant::find($selectedVariantId)
    );
}
```

---

## 🔄 Migration Guide

### Step 1: Identify Old Keys

```php
use App\Services\RedisKeyManager;

$oldPatterns = RedisKeyManager::getOldPatterns();
// Returns:
// [
//     'ecommerce:v1:*',
//     'ecom:*',
//     'uf_*',
//     'cache:*',
//     // etc.
// ]
```

### Step 2: Create Migration Script

```php
// app/Console/Commands/MigrateRedisKeys.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RedisCacheService;
use App\Services\RedisKeyManager;

class MigrateRedisKeys extends Command
{
    protected $signature = 'redis:migrate-keys {--dry-run}';
    protected $description = 'Migrate old Redis keys to new structure';

    public function handle()
    {
        $oldPatterns = RedisKeyManager::getOldPatterns();
        $dryRun = $this->option('dry-run');
        
        foreach ($oldPatterns as $pattern) {
            $keys = RedisCacheService::keys($pattern, 10000);
            $this->info("Found " . count($keys) . " keys matching {$pattern}");
            
            if (!$dryRun) {
                foreach ($keys as $key) {
                    $this->migrateKey($key);
                }
            }
        }
        
        $this->info("Migration complete!");
    }
    
    private function migrateKey(string $oldKey)
    {
        // Get data from old key
        $data = RedisCacheService::get($oldKey);
        if (!$data) return;
        
        // Map to new key structure
        $newKey = $this->mapOldToNew($oldKey);
        if (!$newKey) return;
        
        // Store in new key
        $ttl = RedisCacheService::ttl($oldKey) ?? 3600;
        RedisCacheService::put($newKey, $data, $ttl);
        
        $this->line("Migrated: {$oldKey} → {$newKey}");
    }
    
    private function mapOldToNew(string $oldKey): ?string
    {
        // Example mapping logic
        if (preg_match('/ecommerce:v1:entities:product:(\d+)/', $oldKey, $matches)) {
            return RedisKeyManager::productFull((int)$matches[1]);
        }
        
        // Add more mappings as needed
        return null;
    }
}
```

### Step 3: Run Migration

```bash
# Dry run first
php artisan redis:migrate-keys --dry-run

# Actual migration
php artisan redis:migrate-keys

# Verify
php artisan tinker
>>> Redis::keys('ec:*') // Should see new keys
```

### Step 4: Clean Old Keys

```php
// After verifying new keys work
use App\Services\RedisKeyManager;
use App\Services\RedisCacheService;

$oldPatterns = RedisKeyManager::getOldPatterns();
foreach ($oldPatterns as $pattern) {
    $deleted = RedisCacheService::forgetPattern($pattern);
    echo "Deleted {$deleted} keys matching {$pattern}\n";
}
```

---

## ✅ Best Practices

### 1. Always Use RedisKeyManager

```php
// ❌ Never hardcode keys
Redis::get('product_123');
Redis::get('ecom:product:123');

// ✅ Always use RedisKeyManager
$key = RedisKeyManager::productCard(123);
Redis::get($key);
```

### 2. Choose Right Cache Level

```php
// For product listings
$key = RedisKeyManager::productCard($id); // Lightweight

// For product detail page
$key = RedisKeyManager::productFull($id); // Complete

// For quick checks
$key = RedisKeyManager::productMeta($id); // Minimal
```

### 3. Use Patterns for Bulk Operations

```php
// Invalidate all product caches
$pattern = RedisKeyManager::patternProduct(123);
// Returns: ec:p:123:*
RedisCacheService::forgetPattern($pattern);

// Invalidate all variant caches
$pattern = RedisKeyManager::patternVariants();
// Returns: ec:v:*
RedisCacheService::forgetPattern($pattern);
```

### 4. Version Critical Pages

```php
// Homepage uses versioning
$version = RedisCacheService::getVersion();
$key = RedisKeyManager::pageHome($version);

// To invalidate, just increment version
RedisCacheService::incrementVersion();
// All old caches become unreachable instantly
```

### 5. Use Sets for Relationships

```php
// Store variant IDs as SET
$key = RedisKeyManager::variantsForProduct($productId);
Redis::sadd($key, ...$variantIds);
Redis::expire($key, 3600);

// Fast membership check
Redis::sismember($key, $variantId);

// Fast intersection (filtering)
Redis::sinter($key1, $key2, $key3);
```

### 6. Monitor Memory Usage

```php
use App\Services\RedisCacheService;

$stats = RedisCacheService::getStats();
echo "Used Memory: " . $stats['redis']['used_memory'] . "\n";
echo "Total Keys: " . $stats['redis']['total_keys'] . "\n";
echo "Hit Rate: " . $stats['redis']['hit_rate'] . "%\n";
```

### 7. Set Appropriate TTLs

| Volatility | Recommended TTL | Examples |
|------------|-----------------|----------|
| Very High | 5-15 minutes | Stock levels, active sessions |
| High | 30-60 minutes | Search results, filter results |
| Medium | 1-3 hours | Product cards, variant data |
| Low | 6-12 hours | Categories, brands, settings |
| Very Low | 24+ hours | Global settings, static content |

---

## 🎓 Summary

### Key Takeaways

1. **Use `ec:` namespace** - saves memory at scale
2. **Use RedisKeyManager** - never hardcode keys
3. **Choose right cache level** - card/full/meta
4. **Leverage Redis data types** - SETs for relationships, ZSETs for rankings
5. **Version critical pages** - instant invalidation
6. **Monitor and optimize** - track hit rates, memory usage

### Quick Reference

```php
// Products
RedisKeyManager::productCard($id)
RedisKeyManager::productFull($id)
RedisKeyManager::productMeta($id)

// Variants
RedisKeyManager::variantCard($id)
RedisKeyManager::variantsForProduct($productId)
RedisKeyManager::variantsActive($productId)

// Lookups
RedisKeyManager::productBySlug($slug)
RedisKeyManager::variantBySku($sku)

// Indexes
RedisKeyManager::indexCategory($id)
RedisKeyManager::indexBrand($id)
RedisKeyManager::indexFeatured()

// Pages
RedisKeyManager::pageHome($version)
RedisKeyManager::pageProduct($id)

// Patterns
RedisKeyManager::patternProduct($id)
RedisKeyManager::patternVariants()
```

---

**Questions? Issues?**  
This architecture is designed to scale to 10M+ products. Follow the patterns, and your Redis will be fast and efficient.
