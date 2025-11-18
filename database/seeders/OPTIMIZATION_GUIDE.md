# PostgreSQL Massive Product Seeder - Optimization Guide for 10M Records

## 🚀 Quick Start for 10 Million Records

### Step 1: Configure the Seeder

Uncomment the **ULTRA MASSIVE** preset in `PostgresMassiveProductSeeder.php`:

```php
// ULTRA MASSIVE (10,000,000 products) - RECOMMENDED FOR 10M
protected int $totalProducts = 10_000_000;
protected ?int $variantProductTarget = 9_500_000;
protected int $batchSize = 20_000;
protected int $streamFlushInterval = 5000;
protected int $maxTrackInsertedIds = 0; // Disable ID tracking
protected bool $useTransactions = false;
protected bool $usePostgresOptimizations = true;
protected int $progressLogFrequency = 50;
```

### Step 2: Pre-Seeding Database Optimizations

Before running the seeder, optimize PostgreSQL settings:

```sql
-- Edit postgresql.conf or run these commands in a superuser session:

-- Increase checkpoint intervals
ALTER SYSTEM SET checkpoint_timeout = '1h';
ALTER SYSTEM SET max_wal_size = '10GB';

-- Increase work memory
ALTER SYSTEM SET maintenance_work_mem = '2GB';
ALTER SYSTEM SET work_mem = '256MB';

-- Disable synchronous commit (faster but less safe)
ALTER SYSTEM SET synchronous_commit = OFF;

-- Reduce autovacuum during bulk insert
ALTER SYSTEM SET autovacuum = OFF;

-- Reload configuration
SELECT pg_reload_conf();
```

### Step 3: (Optional) Drop Non-Essential Indexes

For maximum speed, drop indexes before seeding and recreate them after:

```sql
-- List all indexes
SELECT indexname, tablename 
FROM pg_indexes 
WHERE tablename IN (
    'products', 
    'product_variants', 
    'product_images', 
    'variant_images',
    'product_variant_option_assignments',
    'product_variant_type_selections'
)
AND indexname NOT LIKE '%pkey%';

-- Drop indexes (keep primary keys)
-- Example:
-- DROP INDEX CONCURRENTLY idx_products_category;
-- DROP INDEX CONCURRENTLY idx_products_brand;
-- DROP INDEX CONCURRENTLY idx_variants_product_id;
-- (Run for each index found above)
```

### Step 4: Run the Seeder

```bash
php artisan db:seed --class=PostgresMassiveProductSeeder
```

### Step 5: Post-Seeding Restoration

After seeding completes, restore settings and recreate indexes:

```sql
-- Restore PostgreSQL settings
ALTER SYSTEM RESET checkpoint_timeout;
ALTER SYSTEM RESET max_wal_size;
ALTER SYSTEM RESET maintenance_work_mem;
ALTER SYSTEM RESET work_mem;
ALTER SYSTEM RESET synchronous_commit;
ALTER SYSTEM RESET autovacuum;
SELECT pg_reload_conf();

-- Recreate indexes (use CONCURRENTLY for zero downtime)
-- Example:
-- CREATE INDEX CONCURRENTLY idx_products_category ON products(cat_id);
-- CREATE INDEX CONCURRENTLY idx_products_brand ON products(brand_id);
-- CREATE INDEX CONCURRENTLY idx_variants_product_id ON product_variants(product_id);

-- Update table statistics
ANALYZE products;
ANALYZE product_variants;
ANALYZE product_images;
ANALYZE variant_images;
ANALYZE product_variant_option_assignments;
ANALYZE product_variant_type_selections;

-- Run VACUUM to reclaim space
VACUUM ANALYZE products;
VACUUM ANALYZE product_variants;
```

---

## ⚡ Performance Optimizations Explained

### 1. **Batch Size: 20,000**
- **Why**: Larger batches reduce INSERT overhead
- **Trade-off**: More memory per batch
- **Optimal Range**: 10,000 - 50,000 for 10M records

### 2. **Disable Transactions**
- **Why**: PostgreSQL transaction overhead is expensive for bulk inserts
- **Trade-off**: Cannot rollback if error occurs
- **Recommendation**: Use only for initial seeding, not production updates

### 3. **PostgreSQL-Specific Optimizations**

#### Synchronous Commit OFF
```sql
SET synchronous_commit = OFF;
```
- **Speed Gain**: 2-5x faster
- **Risk**: Recent transactions may be lost on crash (within 1-2 seconds)
- **Safe for**: Initial data seeding

#### Increase WAL Size
```sql
SET max_wal_size = '10GB';
```
- **Why**: Reduces checkpoints during bulk insert
- **Effect**: Fewer disk writes

#### Disable Autovacuum
```sql
SET autovacuum = OFF;
```
- **Why**: Autovacuum competes for I/O during inserts
- **Important**: Run VACUUM ANALYZE after seeding

### 4. **UNLOGGED Tables** (Advanced)

For extreme speed, enable:
```php
protected bool $useUnloggedTables = true;
```

**What it does**: Converts tables to UNLOGGED mode temporarily
- **Speed Gain**: 10-50% faster
- **Risk**: All data lost if PostgreSQL crashes
- **Use Case**: Test environments only

### 5. **Memory Management**
- Seeder auto-adjusts to 2GB for 1M+ products
- Garbage collection runs after each batch
- ID tracking disabled for 100k+ products

---

## 📊 Expected Performance

### Hardware Assumptions
- **CPU**: 4+ cores
- **RAM**: 8GB+ available
- **Disk**: SSD (NVMe preferred)
- **PostgreSQL**: Version 12+

### Estimated Timing

| Records | Config | Est. Time | Rate |
|---------|--------|-----------|------|
| 100,000 | Default | 5-10 min | ~200/sec |
| 1,000,000 | Optimized | 30-60 min | ~300/sec |
| 10,000,000 | Ultra | 8-12 hours | ~250/sec |

**Note**: Timing includes products + variants + images (average 4 variants per product)

### Actual Row Counts for 10M Products
- **Products**: 10,000,000
- **Variants**: ~40,000,000 (avg 4 per product)
- **Product Images**: ~30,000,000 (3 per product)
- **Variant Images**: ~120,000,000 (3 per variant)
- **Assignments**: ~80,000,000 (2 variant types avg)
- **Type Selections**: ~20,000,000

**Total Rows**: ~300,000,000 rows across 6 tables

---

## 🔍 Monitoring Progress

### Real-time Progress
```bash
# Watch seeding progress in real-time
php artisan db:seed --class=PostgresMassiveProductSeeder

# Sample output:
⚡ Batch #50  │ Progress:  10.00% │ Seeded:  1,000,000 │ Rate:   278/s │ ETA: 08:58:23
   └─ Variant: 950,000 │ Non-Variant: 50,000
```

### Database Monitoring
```sql
-- Check table sizes
SELECT 
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size,
    n_live_tup AS row_count
FROM pg_stat_user_tables
WHERE tablename LIKE '%product%'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;

-- Monitor current activity
SELECT 
    pid,
    application_name,
    state,
    query_start,
    LEFT(query, 50) as query
FROM pg_stat_activity
WHERE datname = current_database()
AND application_name LIKE '%artisan%';
```

---

## 🛠️ Troubleshooting

### Issue: Out of Memory
**Solution**:
- Reduce batch size: `protected int $batchSize = 5000;`
- Increase stream flush: `protected int $streamFlushInterval = 1000;`
- Check available RAM: `free -h`

### Issue: Disk Space Full
**Solution**:
```sql
-- Check disk usage
SELECT pg_size_pretty(pg_database_size(current_database()));

-- Estimate needed space: ~500GB for 10M products with variants
```

### Issue: Too Slow
**Checklist**:
- [ ] Transactions disabled?
- [ ] PostgreSQL optimizations applied?
- [ ] Indexes dropped?
- [ ] Using SSD storage?
- [ ] Sufficient RAM (8GB+)?

### Issue: PostgreSQL Crashes
**Recovery**:
```bash
# If using UNLOGGED tables and crash occurs
# All data in UNLOGGED tables is lost - restart seeding

# If using regular tables
# PostgreSQL will auto-recover on restart
sudo systemctl restart postgresql
```

---

## 🎯 Advanced: COPY Command Alternative

For **absolute maximum speed**, consider PostgreSQL's `COPY` command:

### Method:
1. Generate CSV files instead of direct inserts
2. Use `COPY` to bulk load

```php
// Generate CSV (example)
$file = fopen('/tmp/products.csv', 'w');
foreach ($productRows as $row) {
    fputcsv($file, $row);
}
fclose($file);

// Load via COPY (5-10x faster)
DB::statement("COPY products FROM '/tmp/products.csv' WITH CSV");
```

**Speed**: Can insert 10M products in 1-2 hours vs 8-12 hours

---

## 📋 Configuration Checklist for 10M

Before starting, verify:

- [x] `totalProducts = 10_000_000`
- [x] `batchSize = 20_000`
- [x] `useTransactions = false`
- [x] `usePostgresOptimizations = true`
- [x] `maxTrackInsertedIds = 0`
- [x] `progressLogFrequency = 50`
- [x] Sufficient disk space (500GB+)
- [x] PostgreSQL config optimized
- [x] Indexes dropped (optional but recommended)
- [x] Backup existing data

---

## 🎓 Best Practices

### DO:
✅ Test with small dataset first (100k)
✅ Monitor disk space continuously
✅ Run during off-peak hours
✅ Backup database before seeding
✅ Document your specific indexes for recreation

### DON'T:
❌ Use transactions for 10M+ records
❌ Enable query logging
❌ Run during production hours
❌ Forget to restore PostgreSQL settings
❌ Skip ANALYZE after seeding

---

## 📞 Support

For issues or optimization questions:
1. Check PostgreSQL logs: `/var/log/postgresql/`
2. Review seeder output for errors
3. Verify hardware meets minimum requirements

**Estimated Total Time for 10M Products**: 8-12 hours optimized, 24+ hours unoptimized
