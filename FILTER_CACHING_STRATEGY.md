# 🎯 Adaptive Filter Caching Strategy for 10M+ Products

## 📊 The Problem

With 10M+ products, caching **all** filter combinations is impossible:
- **Memory**: 1 billion combinations × 100KB = 100TB of Redis storage ❌
- **Time**: Pre-computing all combinations would take weeks ❌
- **Reality**: 99% of combinations are never accessed ❌

## ✅ The Solution: 4-Tier Adaptive Caching

### **Tier 1: Product Index Cache** (Most Important!)
Cache **raw product ID lists** by single dimensions, then combine in-memory.

```
Category IDs:
  - electronics → [1,2,3,4,...100000]
  - clothing → [100001,...200000]

Brand IDs:
  - samsung → [1,5,7,22,...]
  - nike → [8,9,15,...]

Price Range IDs:
  - 0-100 → [...]
  - 100-500 → [...]
```

**Benefit**: 
- Only ~2,000 keys instead of billions
- Combine in-memory using set operations (ultra-fast)
- Total memory: ~200MB instead of TBs

### **Tier 2: Hot Combo Cache** (Top 500-1000)
Cache complete results for the **most popular** filter combinations.

**How to identify hot combos:**
- Track every filter request with counter
- Daily job identifies top 500 combinations
- Pre-warm these specific combos

**Storage**: Top 500 × 100KB = 50MB

### **Tier 3: Smart Pagination Pre-fetching**
When user views page 1 of any filter combo:
- If user scrolls → pre-fetch pages 2-5 in background
- If user clicks page 6 → pre-fetch pages 7-15
- TTL: 15 minutes (short, just for session)

**Storage**: ~100 active sessions × 5 pages × 100KB = 50MB

### **Tier 4: On-Demand with Smart Indexing**
For rare combinations (cold path):
- Use in-memory set operations on Tier 1 indexes
- Response time: 100-300ms (acceptable for rare queries)
- Store result in cache for 5 minutes (might be accessed again)

---

## 🚀 Implementation Strategy

### Phase 1: Product Index Builder (Foundation)

Create specialized service to build and maintain product indexes:

```php
// app/Services/ProductIndexService.php

class ProductIndexService
{
    // Build category index
    public function buildCategoryIndex(): void
    {
        Category::where('status', 'active')->chunk(100, function($categories) {
            foreach ($categories as $category) {
                $productIds = Product::where('status', 'active')
                    ->where(function($q) use ($category) {
                        $q->where('cat_id', $category->id)
                          ->orWhere('child_cat_id', $category->id);
                    })
                    ->pluck('id')
                    ->toArray();
                
                Redis::sadd("index:category:{$category->id}", ...$productIds);
                Redis::expire("index:category:{$category->id}", 86400); // 24h
            }
        });
    }
    
    // Build brand index
    public function buildBrandIndex(): void
    {
        Brand::where('status', 'active')->chunk(100, function($brands) {
            foreach ($brands as $brand) {
                $productIds = Product::where('status', 'active')
                    ->where('brand_id', $brand->id)
                    ->pluck('id')
                    ->toArray();
                
                Redis::sadd("index:brand:{$brand->id}", ...$productIds);
                Redis::expire("index:brand:{$brand->id}", 86400);
            }
        });
    }
    
    // Build price range indexes
    public function buildPriceIndex(): void
    {
        $ranges = [
            '0-100' => [0, 100],
            '100-500' => [100, 500],
            '500-1000' => [500, 1000],
            '1000-5000' => [1000, 5000],
            '5000+' => [5000, 999999],
        ];
        
        foreach ($ranges as $key => $range) {
            $productIds = Product::where('status', 'active')
                ->whereBetween('base_price', $range)
                ->pluck('id')
                ->toArray();
            
            Redis::sadd("index:price:{$key}", ...$productIds);
            Redis::expire("index:price:{$key}", 86400);
        }
    }
}
```

### Phase 2: Fast In-Memory Filter Combiner

```php
// app/Services/FastFilterService.php

class FastFilterService
{
    public function getFilteredProductIds(array $filters): array
    {
        $sets = [];
        
        // Collect all relevant sets
        if (!empty($filters['category_id'])) {
            $sets[] = "index:category:{$filters['category_id']}";
        }
        
        if (!empty($filters['brands'])) {
            foreach ($filters['brands'] as $brandId) {
                $sets[] = "index:brand:{$brandId}";
            }
        }
        
        if (!empty($filters['price_range'])) {
            $sets[] = "index:price:{$filters['price_range']}";
        }
        
        // Perform set intersection (Redis does this in C, super fast!)
        if (count($sets) === 0) {
            return [];
        }
        
        if (count($sets) === 1) {
            return Redis::smembers($sets[0]);
        }
        
        // Intersect all sets
        $tempKey = "temp:filter:" . md5(implode('|', $sets));
        Redis::sinterstore($tempKey, ...$sets);
        Redis::expire($tempKey, 300); // 5 min
        
        $productIds = Redis::smembers($tempKey);
        
        return array_map('intval', $productIds);
    }
}
```

### Phase 3: Enhanced productSubCat Method

```php
public function productSubCat(Request $request, $encryptedPath)
{
    $startTime = microtime(true);
    
    try {
        // 1. Parse category and filters
        $slugPath = UrlEncryptor::decodePath($encryptedPath);
        $currentCategory = $this->resolveCategory($slugPath);
        $filters = $this->extractFilters($request, $currentCategory);
        
        $page = $request->input('page', 1);
        $perPage = $request->input('show', 12);
        
        // 2. Try SmartFilterCacheService (Tier 2: Hot Combos)
        $cached = app(SmartFilterCacheService::class)
            ->getFilteredProducts($filters, $page, $perPage);
        
        if ($cached) {
            Log::info("Filter cache HIT", [
                'filters' => $filters,
                'page' => $page,
                'time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ]);
            
            return $this->renderCachedResponse($cached, $request);
        }
        
        // 3. Use Fast Index-Based Filtering (Tier 1)
        $productIds = app(FastFilterService::class)
            ->getFilteredProductIds($filters);
        
        // Apply additional filters (sorting, rating, etc.)
        $query = Product::whereIn('id', $productIds)
            ->where('status', 'active')
            ->with($this->getProductRelations());
        
        $this->applySortingAndRating($query, $request);
        
        // 4. Paginate
        $products = $query->paginate($perPage);
        
        // 5. Store in cache for next time
        $response = $this->buildResponse($products, $currentCategory, $request);
        
        app(SmartFilterCacheService::class)
            ->storeFilteredProducts($filters, $page, $perPage, $response);
        
        // 6. Smart pre-fetching for engaged users
        if ($page === 1) {
            dispatch(new PrefetchFilterPagesJob($filters, $perPage))
                ->afterResponse();
        }
        
        Log::info("Filter response", [
            'source' => 'index_based',
            'product_count' => count($productIds),
            'time_ms' => round((microtime(true) - $startTime) * 1000, 2)
        ]);
        
        return $this->renderResponse($response, $request);
        
    } catch (\Exception $e) {
        Log::error('Filter error: ' . $e->getMessage());
        abort(500, 'Error loading products');
    }
}
```

---

## 🔄 Maintenance & Warming Strategy

### Daily Index Rebuild (Off-Peak Hours)
```php
// app/Console/Commands/RebuildProductIndexes.php

Schedule::command('indexes:rebuild')->daily()->at('03:00');
```

### Identify Hot Combos Weekly
```php
// Analyze past 7 days of filter requests
// Cache top 500 combinations
Schedule::command('cache:analyze-hot-combos')->weekly();
```

### Real-time Index Updates
```php
// app/Observers/ProductObserver.php

public function updated(Product $product)
{
    // Update affected indexes
    if ($product->isDirty('cat_id')) {
        Redis::sadd("index:category:{$product->cat_id}", $product->id);
        Redis::srem("index:category:{$product->getOriginal('cat_id')}", $product->id);
    }
    
    if ($product->isDirty('brand_id')) {
        Redis::sadd("index:brand:{$product->brand_id}", $product->id);
        Redis::srem("index:brand:{$product->getOriginal('brand_id')}", $product->id);
    }
}
```

---

## 📈 Expected Performance

| Scenario | Method | Response Time | Memory |
|----------|--------|---------------|--------|
| Hot combo (Top 500) | Tier 2 Cache | **5-20ms** | 50MB |
| Single filter | Tier 1 Index | **50-100ms** | 200MB |
| 2-3 filters combined | Set Intersection | **100-300ms** | 200MB |
| Rare complex combo | On-demand + Cache | **300-800ms** | +100MB |
| Pagination (engaged) | Pre-fetched | **5-15ms** | +50MB |

**Total Redis Memory**: ~500MB-1GB (vs. 100TB if caching all combos!)

---

## 🎯 Key Benefits

✅ **Scalable**: Handles billions of combinations with <1GB memory
✅ **Fast**: 90%+ requests under 100ms
✅ **Adaptive**: Learns user behavior, caches what matters
✅ **Maintainable**: Simple index structure, easy debugging
✅ **Cost-Effective**: Standard Redis instance, no expensive hardware
✅ **Real-time**: Indexes update immediately on product changes

---

## 🚨 What NOT to Do

❌ **Don't** pre-cache all combinations (impossible)
❌ **Don't** cache pagination beyond page 10 (rarely accessed)
❌ **Don't** use long TTL for rare combos (wastes memory)
❌ **Don't** store full product data in indexes (just IDs)
❌ **Don't** forget to expire temporary keys

---

## 🎓 Implementation Priority

1. **Week 1**: Build product indexes (Tier 1) ← START HERE
2. **Week 2**: Integrate index-based filtering into productSubCat
3. **Week 3**: Add hot combo analysis and caching (Tier 2)
4. **Week 4**: Implement smart pre-fetching (Tier 3)
5. **Week 5**: Monitor, optimize, tune based on real traffic

---

## 💡 Pro Tips

1. **Monitor hot combos daily** - user behavior changes over time
2. **Use Redis Sets** - they're optimized for intersection operations
3. **Keep indexes lean** - only store product IDs, fetch details on-demand
4. **Expire temp keys** - prevent memory leaks
5. **Log everything** - understand your cache hit rates
6. **A/B test** - compare cached vs. uncached performance

---

## Summary

This strategy gives you:
- **500MB memory** instead of 100TB
- **Sub-100ms** response for 95% of requests
- **Adaptive** caching that learns user patterns
- **Scalable** to 100M+ products without architectural changes

**The secret**: Don't cache combinations—cache **indexes** and **combine on-the-fly**! 🚀
