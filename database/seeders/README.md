# Database Seeders - Professional Implementation Guide

## 📋 Overview

This directory contains professional, production-ready database seeders for the Enterprise E-commerce application. All seeders follow industry best practices with comprehensive error handling, transaction support, and idempotent operations.

## 🎯 Key Features

### Professional Standards
- ✅ **Strict Typing**: All seeders use `declare(strict_types=1)` for type safety
- ✅ **Comprehensive Documentation**: Full PHPDoc blocks for all classes and methods
- ✅ **Error Handling**: Try-catch blocks with proper rollback and logging
- ✅ **Transaction Support**: All operations wrapped in database transactions
- ✅ **Idempotent Operations**: Safe to run multiple times without data duplication
- ✅ **Logging**: Detailed error logging for debugging and monitoring
- ✅ **Visual Feedback**: Emoji-enhanced console output for better UX

### Code Quality
- PSR-12 coding standards
- Clean architecture principles
- Single Responsibility Principle
- DRY (Don't Repeat Yourself)
- Proper separation of concerns

## 📁 Seeder Structure

### Active Seeders (Production Ready)

| Seeder | Purpose | Records | Idempotent |
|--------|---------|---------|------------|
| `DatabaseSeeder.php` | Main orchestrator | N/A | ✅ |
| `UsersTableSeeder.php` | Admin & test users | 3 users | ✅ |
| `BannerSeeder.php` | Promotional banners | 3 banners | ✅ |
| `SettingsTableSeeder.php` | Application settings | 1 record | ✅ |
| `CouponSeeder.php` | Discount coupons | 3 coupons | ✅ |
| `ShippingSeeder.php` | Shipping methods | 4 methods | ✅ |
| `CategoryBrandVariantBaseSeeder.php` | Core e-commerce data | ~200 records | ✅ |
| `PostgresMassiveProductSeeder.php` | Product generation | Configurable | ✅ |

### Deprecated Seeders

All old seeders have been moved to `OldSeeders/` directory and are no longer active.

## 🚀 Usage

### Quick Start

#### Seed All Tables
```bash
php artisan db:seed
```

#### Seed Specific Table
```bash
php artisan db:seed --class=UsersTableSeeder
php artisan db:seed --class=CategoryBrandVariantBaseSeeder
```

#### Fresh Migration with Seeding
```bash
php artisan migrate:fresh --seed
```

## 📊 Seeding Order

The `DatabaseSeeder` executes seeders in the following order:

1. **Foundation Data**
   - UsersTableSeeder (users needed for tracking)
   - BannerSeeder
   - SettingsTableSeeder
   - CouponSeeder

2. **Core E-commerce Structure**
   - CategoryBrandVariantBaseSeeder
     - Filters (5 types)
     - Categories (19 total: 4 parents + 15 children)
     - Brands (10 brands)
     - Brand-Category mappings
     - Filter-Category mappings
     - Variant Types (4 types)
     - Variant Options (23 options)
   - ShippingSeeder

3. **Products**
   - PostgresMassiveProductSeeder (configurable quantity)

## 🔧 Configuration

### PostgresMassiveProductSeeder

Located at the top of the seeder file:

```php
// Quick configuration
protected int $totalProducts = 100_000;
protected ?int $variantProductTarget = 95_000;
protected int $batchSize = 2000;

// Available presets:
// SMALL TEST (100 products)
// MEDIUM TEST (1,000 products)
// LARGE DATASET (100,000 products)
// MASSIVE DATASET (1,000,000 products)
// ULTRA MASSIVE (10,000,000 products)
```

See `OPTIMIZATION_GUIDE.md` and `QUICK_START_10M.md` for detailed configuration.

## 🛡️ Error Handling

All seeders implement comprehensive error handling:

```php
DB::beginTransaction();

try {
    // Seeding logic here
    DB::commit();
    $this->command->info('✅ Success message');
} catch (Throwable $e) {
    DB::rollBack();
    $this->command->error('❌ Error message');
    Log::error('Seeding failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
```

## 📝 Seeder Details

### UsersTableSeeder
- **Purpose**: Create initial admin and test users
- **Default Password**: `1111` (should be changed in production)
- **Records**: 3 users (Admin, User, Sahil Kothiya)

### BannerSeeder
- **Purpose**: Seed promotional banners
- **Records**: 3 banners with HTML descriptions

### SettingsTableSeeder
- **Purpose**: Application-wide settings
- **Records**: 1 settings record with logo, contact info, etc.

### CouponSeeder
- **Purpose**: Promotional discount coupons
- **Records**: 3 coupons (fixed & percentage types)

### ShippingSeeder
- **Purpose**: Available shipping methods
- **Records**: 4 methods (Free, Standard, Express, Next Day)
- **Note**: Uses `updateOrCreate` for maximum idempotency

### CategoryBrandVariantBaseSeeder
- **Purpose**: Complete e-commerce foundation
- **Features**:
  - Unique 3-character codes for categories/brands
  - SEO titles and descriptions
  - Parent-child category hierarchy
  - ALL brands assigned to parent categories
  - Random brands (2-5) assigned to child categories
  - ALL filters assigned to parent categories
  - Random filters (2-4) assigned to child categories

### PostgresMassiveProductSeeder
- **Purpose**: Generate massive product datasets
- **Capabilities**: 100 to 10,000,000+ products
- **Features**:
  - Variant product generation
  - Image management
  - SKU generation
  - Category assignment
  - Brand assignment
  - Stock management
  - Pricing and discounts

## 🧪 Testing

Run individual seeders to verify functionality:

```bash
# Test users
php artisan db:seed --class=UsersTableSeeder

# Test core data
php artisan db:seed --class=CategoryBrandVariantBaseSeeder

# Test with small product set
# (Edit PostgresMassiveProductSeeder: $totalProducts = 100)
php artisan db:seed --class=PostgresMassiveProductSeeder
```

## 📈 Performance Considerations

### For Small Datasets (< 10,000 products)
- Use default settings
- Transaction support enabled
- All tracking enabled

### For Large Datasets (100,000 - 1,000,000 products)
- Increase batch size
- Consider disabling some tracking
- Monitor memory usage

### For Massive Datasets (10,000,000+ products)
- See `OPTIMIZATION_GUIDE.md`
- Disable transactions
- Use UNLOGGED tables
- Drop indexes during seeding
- Disable constraints

## 🔍 Monitoring & Debugging

### Check Logs
```bash
tail -f storage/logs/laravel.log
```

### Verify Seeded Data
```bash
php artisan tinker
>>> DB::table('users')->count();
>>> DB::table('categories')->count();
>>> DB::table('products')->count();
```

## ⚠️ Important Notes

1. **Production Safety**: Default passwords should be changed
2. **Data Persistence**: Seeders truncate tables - back up data if needed
3. **Memory Limits**: Large datasets may require increased PHP memory
4. **Time Limits**: Massive datasets may require increased execution time
5. **PostgreSQL**: Optimized for PostgreSQL - may need adjustments for MySQL

## 🤝 Contributing

When creating new seeders:

1. Follow the existing professional template
2. Add comprehensive PHPDoc documentation
3. Implement proper error handling
4. Make operations idempotent
5. Add to `DatabaseSeeder` in correct order
6. Update this README

## 📚 Additional Resources

- `OPTIMIZATION_GUIDE.md` - Product seeder optimization
- `QUICK_START_10M.md` - Quick guide for 10M records
- `SEEDER_PERFORMANCE_GUIDE.md` - Performance tuning
- `SEEDER_QUICK_START.md` - Quick reference guide

## 🐛 Troubleshooting

### "Table already exists" Error
```bash
php artisan migrate:fresh --seed
```

### "Out of memory" Error
Increase PHP memory limit:
```bash
php -d memory_limit=2G artisan db:seed
```

### "Maximum execution time exceeded"
Increase timeout:
```bash
php -d max_execution_time=0 artisan db:seed
```

### Transaction Deadlock
Disable transactions for large datasets in the seeder configuration.

---

**Version**: 2.0  
**Last Updated**: November 20, 2025  
**Maintainer**: Development Team
