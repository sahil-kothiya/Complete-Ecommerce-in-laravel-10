# 🚀 Quick Start: Seeding 10 Million Products

## One-Command Setup

Edit `PostgresMassiveProductSeeder.php` and uncomment these lines:

```php
// ULTRA MASSIVE (10,000,000 products) - RECOMMENDED FOR 10M
protected int $totalProducts = 10_000_000;
protected ?int $variantProductTarget = 9_500_000;
protected int $batchSize = 20_000;
protected int $streamFlushInterval = 5000;
protected int $maxTrackInsertedIds = 0;
protected bool $useTransactions = false;
protected bool $usePostgresOptimizations = true;
protected int $progressLogFrequency = 50;
```

## Run Seeder

```bash
php artisan db:seed --class=PostgresMassiveProductSeeder
```

## Expected Results

- **Time**: 8-12 hours (on SSD with 8GB+ RAM)
- **Total Rows**: ~300M across 6 tables
- **Rate**: 200-300 products/second

## After Completion

```sql
-- Restore PostgreSQL settings (automatic if usePostgresOptimizations=true)
-- Run this if needed:
ANALYZE products;
ANALYZE product_variants;
VACUUM ANALYZE;
```

## Disk Space Required

- **Minimum**: 500 GB free space
- **Recommended**: 1 TB

## Monitoring

```sql
-- Check progress
SELECT COUNT(*) FROM products;
SELECT COUNT(*) FROM product_variants;
```

## If Something Goes Wrong

1. **Out of Memory**: Reduce `batchSize` to 10000
2. **Too Slow**: Check if using SSD, verify PostgreSQL optimizations applied
3. **Disk Full**: Free up space or reduce `totalProducts`

## Performance Comparison

| Setting | Speed | Safety |
|---------|-------|--------|
| Transactions ON | Slow (3x slower) | ✅ Safe |
| Transactions OFF | Fast | ⚠️ Cannot rollback |
| PG Optimizations | 2x faster | ✅ Safe* |
| UNLOGGED tables | 50% faster | ❌ Data lost on crash |

*Safe for initial seeding

---

**See `OPTIMIZATION_GUIDE.md` for detailed configuration and troubleshooting.**
