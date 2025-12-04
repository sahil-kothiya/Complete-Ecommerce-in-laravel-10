# Discount Filter Fix

## ISSUE
API showing products without 50% discount when `discounts=50` filter was applied. No warning message displayed when no products matched the filter.

**Symptom**: API returned 666,598 products with discounts ranging from 0-17% instead of 50%+.

## ROOT CAUSE
1. **Missing Discount Index Key Pattern**: `ProductIndexService::buildDiscountIndex()` used old key pattern `index:discount:{$key}` instead of `RedisKeyManager::indexDiscount($minDiscount)`.
   
2. **Empty Index Skipped**: `FastFilterService` checked `Redis::exists()` before adding discount filter to intersection. Empty indexes (0 products) returned `false`, causing filter to be silently ignored.

3. **No Warning Logic**: When discount filter was skipped, API returned category products without any warning message.

## SOLUTION

### 1. Fixed Discount Index Key Pattern
**File**: `app/Services/ProductIndexService.php`

```php
// BEFORE (line 383)
$indexKey = "index:discount:{$key}";

// AFTER
$indexKey = RedisKeyManager::indexDiscount($minDiscount);
```

**Rebuilt Indexes**:
```bash
php artisan tinker --execute="print_r(app('App\Services\ProductIndexService')->buildDiscountIndex());"
```

**Result**:
- ✅ 10%+: 7,479,000 products
- ✅ 25%+: 1 product
- ✅ 50%+: 0 products (empty index created)
- ✅ 75%+: 0 products (empty index created)

### 2. Fixed Empty Index Handling
**File**: `app/Services/FastFilterService.php`

```php
// BEFORE (lines 75-78)
if (! empty($filters['min_discount'])) {
    $discountKey = RedisKeyManager::indexDiscount($filters['min_discount']);
    if (Redis::exists($discountKey)) {  // ❌ Skips empty indexes
        $sets[] = $discountKey;
    }
}

// AFTER
if (! empty($filters['min_discount'])) {
    $discountKey = RedisKeyManager::indexDiscount($filters['min_discount']);
    // Always add discount filter to force 0 results if index is empty
    // Empty index means no products match this discount level
    $sets[] = $discountKey;
}
```

**Logic**: Redis SINTERSTORE with an empty set will always return 0 results, which correctly triggers the warning message flow.

### 3. Warning Message Already Implemented
**File**: `app/Http/Controllers/UltraFastFilterController.php` (lines 173-175)

```php
if ($result['count'] === 0 || ! $result['key']) {
    Log::warning('No products found for filters', ['filters' => $filters, 'redis_result' => $result]);
    return $this->buildSimilarProductsResponse($category, $filters, $perPage);
}
```

**Warning Message** (lines 829-837):
```php
if (! empty($filters['discounts'])) {
    $appliedFilters[] = 'discount: '.min($filters['discounts']).'%+';
}

if ($category) {
    return "No exact matches for {$filterText} in {$category->title}. Showing similar products from this category.";
}
```

## VERIFICATION

### Test 1: Discount Filter Returns 0
```bash
php scripts/02-12-2025/test-api-discount-filter.php
```
**Result**: ✅ Category 4 + Discount 50% = 0 products

### Test 2: API Shows Warning
**Before Fix**:
- URL: `/api/filters/...?discounts=50`
- Response: `{ "total": 666598, "source": "redis_indexes", "similar": false }`
- Products: Showed items with 0-17% discounts

**After Fix**:
- URL: `/api/filters/...?discounts=50`
- Response: `{ "total": 12, "source": "similar_fallback", "similar": true, "message": "No exact matches for discount: 50%+ in Electronics. Showing similar products from this category." }`
- Products: Shows fallback category products with warning

## FILES MODIFIED
1. `app/Services/ProductIndexService.php` - Fixed discount index key pattern
2. `app/Services/FastFilterService.php` - Removed empty index check
3. `scripts/02-12-2025/test-discount-filter.php` - Debug script
4. `scripts/02-12-2025/test-api-discount-filter.php` - Verification script

## REDIS INDEXES
```
ec:idx:discount:10  → 7,479,000 members
ec:idx:discount:25  → 1 member
ec:idx:discount:50  → 0 members (exists but empty)
ec:idx:discount:75  → 0 members (exists but empty)
```

## IMPACT
- ✅ Discount filter now works correctly
- ✅ Empty filters trigger warning message
- ✅ API shows similar products instead of wrong results
- ✅ User experience improved with clear messaging

## TESTING
Clear cache before testing:
```bash
php artisan cache:clear
```

Test URL:
```
GET /api/filters/{encrypted_path}?brands=hm,hp&discounts=50&sortBy=latest&show=12&page=1
```

Expected response:
```json
{
  "total": 12,
  "source": "similar_fallback",
  "similar": true,
  "message": "No exact matches for brand: hm, hp, discount: 50%+ in Electronics. Showing similar products from this category.",
  "products": [...]
}
```
