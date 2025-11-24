# Scripts to Delete - Debugging/Temporary Files

## Scripts folder cleanup analysis

### ✅ KEEP (Useful Scripts):
- `check-all-cached.php` - Verify cache status
- `check-cached-products.php` - Check product caching
- `inspect-redis-cache.php` - Redis inspection

### ❌ DELETE (Debug/Temporary - No longer needed):

1. **Image Investigation Scripts** (debugging completed):
   - `check-db-images.php`
   - `check-problem-products.php`
   - `check-product-99064.php`
   - `check-product-images-query.php`
   - `find-no-image-products.php`
   - `fix-no-image-products.php`
   - `identify-fallback-products.php`
   - `inspect-images.php`
   - `test-images-relation.php`
   - `test-problematic-products.php`
   - `test-specific-products.php`

2. **Transform/Warmup Testing** (debugging completed):
   - `recreate-transform.php`
   - `test-transform-logic.php`
   - `test-warmup-query.php`
   - `inspect-transform-method.php`

3. **Homepage Investigation** (debugging completed):
   - `dump-homepage-product.php`
   - `inspect-homepage-products.php`
   - `quick-check-products.php`
   - `check-homepage-query.php`

4. **Redis Log Analysis** (one-time debugging):
   - `analyze-redis-log.php`
   - `extract-redis-data.php`
   - `deep-investigate-products.php`
   - `write-test-log.php`

5. **Verification Scripts** (one-time use):
   - `verify-frontend-cache.php`
   - `verify-get-time.php`
   - `verify-store-time.php`

6. **Misc**:
   - `controller-path.php`
   - `clear-redis.php` (dangerous if run accidentally)

### Commands to delete (run in PowerShell):

```powershell
cd D:\wamp64\www\Enterprice-Ecommerce\scripts

# Delete image investigation scripts
Remove-Item check-db-images.php, check-problem-products.php, check-product-99064.php, check-product-images-query.php, find-no-image-products.php, fix-no-image-products.php, identify-fallback-products.php, inspect-images.php, test-images-relation.php, test-problematic-products.php, test-specific-products.php -Force

# Delete transform/warmup testing
Remove-Item recreate-transform.php, test-transform-logic.php, test-warmup-query.php, inspect-transform-method.php -Force

# Delete homepage investigation
Remove-Item dump-homepage-product.php, inspect-homepage-products.php, quick-check-products.php, check-homepage-query.php -Force

# Delete Redis log analysis
Remove-Item analyze-redis-log.php, extract-redis-data.php, deep-investigate-products.php, write-test-log.php -Force

# Delete verification scripts
Remove-Item verify-frontend-cache.php, verify-get-time.php, verify-store-time.php -Force

# Delete misc
Remove-Item controller-path.php, clear-redis.php -Force

Write-Host "Cleanup complete! Removed 31 debug/temp scripts." -ForegroundColor Green
```

### After cleanup, you'll have only these useful scripts:
- `check-all-cached.php` - Check cache status
- `check-cached-products.php` - Verify product caching
- `inspect-redis-cache.php` - Inspect Redis keys

Much cleaner! 🎉
