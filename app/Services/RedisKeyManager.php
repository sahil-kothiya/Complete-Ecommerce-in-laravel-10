<?php

namespace App\Services;

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * REDIS KEY MANAGER - UNIFIED ARCHITECTURE FOR 10M+ PRODUCTS
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Single source of truth for ALL Redis keys across the entire application.
 * Optimized for: PostgreSQL + Redis + Laravel 10 + 10M+ products with variants
 *
 * KEY HIERARCHY:
 * ──────────────
 * ec:                              // Root namespace (2 chars to save memory)
 *   ├─ p:                         // Products
 *   │  ├─ {id}:full               // Full product data
 *   │  ├─ {id}:card               // Lightweight card data
 *   │  ├─ {id}:meta               // Metadata only
 *   │  ├─ {id}:vars               // All variants for product
 *   │  ├─ {id}:imgs               // All images for product
 *   │  ├─ slug:{slug}             // Product ID by slug
 *   │  └─ sku:{sku}               // Product ID by SKU
 *   │
 *   ├─ v:                         // Variants
 *   │  ├─ {id}:full               // Full variant data
 *   │  ├─ {id}:card               // Lightweight variant card
 *   │  ├─ {id}:imgs               // Variant images
 *   │  ├─ p:{pid}:all             // All variants for product {pid}
 *   │  ├─ p:{pid}:active          // Active variants for product
 *   │  ├─ p:{pid}:stock           // In-stock variants
 *   │  └─ sku:{sku}               // Variant ID by SKU
 *   │
 *   ├─ cat:                       // Categories
 *   │  ├─ {id}:full               // Full category data
 *   │  ├─ {id}:tree               // Category tree/hierarchy
 *   │  ├─ {id}:prods              // Product IDs in category
 *   │  ├─ {id}:count              // Product count in category
 *   │  ├─ tree                    // Full category tree
 *   │  └─ slug:{slug}             // Category ID by slug
 *   │
 *   ├─ br:                        // Brands
 *   │  ├─ {id}:full               // Full brand data
 *   │  ├─ {id}:prods              // Product IDs for brand
 *   │  ├─ all                     // All brands list
 *   │  └─ slug:{slug}             // Brand ID by slug
 *   │
 *   ├─ flt:                       // Filters
 *   │  ├─ meta:{cat_slug}         // Filter metadata for category
 *   │  ├─ res:{hash}              // Filter results by hash
 *   │  ├─ opts:{cat}:{type}       // Filter options (brands, sizes, etc.)
 *   │  ├─ price:{cat}             // Price ranges for category
 *   │  └─ cnt:{cat}:{filters}     // Count for filter combination
 *   │
 *   ├─ srch:                      // Search
 *   │  ├─ q:{hash}:p{n}           // Search query results page N
 *   │  ├─ sug:{term}              // Search suggestions
 *   │  ├─ pop                     // Popular searches
 *   │  └─ recent:{user}           // Recent searches for user
 *   │
 *   ├─ idx:                       // Indexes (pre-computed sets)
 *   │  ├─ cat:{id}                // Product IDs in category
 *   │  ├─ br:{id}                 // Product IDs for brand
 *   │  ├─ price:{range}           // Product IDs in price range
 *   │  ├─ rating:{stars}          // Product IDs with rating
 *   │  ├─ disc:{pct}              // Product IDs with discount %
 *   │  ├─ feat                    // Featured product IDs
 *   │  └─ new                     // New product IDs
 *   │
 *   ├─ pg:                        // Pages (full page cache)
 *   │  ├─ home:v{ver}             // Homepage (versioned)
 *   │  ├─ cat:{id}:p{n}           // Category page N
 *   │  ├─ prod:{id}               // Product detail page
 *   │  └─ search:{hash}:p{n}      // Search results page N
 *   │
 *   ├─ cmp:                       // Components (page sections)
 *   │  ├─ nav                     // Navigation menu
 *   │  ├─ footer                  // Footer content
 *   │  ├─ banner                  // Active banners
 *   │  ├─ feat                    // Featured products section
 *   │  └─ deal                    // Deals section
 *   │
 *   ├─ usr:                       // User data
 *   │  ├─ {id}:cart               // User cart
 *   │  ├─ {id}:wish               // User wishlist
 *   │  ├─ {id}:recent             // Recently viewed products
 *   │  ├─ {id}:profile            // User profile cache
 *   │  └─ sess:{sid}              // Session data
 *   │
 *   ├─ set:                       // Settings
 *   │  ├─ app                     // App settings
 *   │  ├─ seo                     // SEO settings
 *   │  ├─ smtp                    // SMTP settings
 *   │  └─ payment                 // Payment settings
 *   │
 *   ├─ agg:                       // Aggregations (stats/counts)
 *   │  ├─ stats                   // Global statistics
 *   │  ├─ count:prods             // Total product count
 *   │  ├─ count:cats              // Total category count
 *   │  └─ top:{type}              // Top items (sellers, viewed, etc.)
 *   │
 *   ├─ tmp:                       // Temporary (short TTL)
 *   │  ├─ lock:{key}              // Distributed locks
 *   │  ├─ union:{hash}            // Temporary set unions
 *   │  ├─ job:{id}                // Job processing data
 *   │  └─ rate:{ip}:{endpoint}    // Rate limiting
 *   │
 *   └─ meta:                      // Metadata
 *      ├─ ver                     // Cache version (for invalidation)
 *      ├─ health                  // System health check
 *      └─ warmup:{timestamp}      // Last cache warmup info
 *
 * NAMING CONVENTIONS:
 * ───────────────────
 * - Ultra-short prefixes (2-4 chars) to minimize memory usage
 * - Consistent hierarchy: namespace:module:type:identifier:suffix
 * - Use colons (:) as separators (Redis best practice)
 * - IDs and slugs for fast lookups
 * - Versioning for atomic invalidation
 * - Hash-based keys for complex filters/queries
 *
 * PERFORMANCE OPTIMIZATIONS:
 * ──────────────────────────
 * - Short keys = less memory per key (critical at 10M+ scale)
 * - Predictable patterns = easier scanning and cleanup
 * - Separate card/full variants = load only what you need
 * - Indexed sets = O(1) lookups for filters
 * - Versioned pages = instant invalidation without scanning
 */
class RedisKeyManager
{
    // ═══════════════════════════════════════════════════════════════════════
    // NAMESPACE & MODULE CONSTANTS
    // ═══════════════════════════════════════════════════════════════════════

    // Root namespace (2 chars to save memory at 10M+ scale)
    private const NAMESPACE = 'ec';

    // Module prefixes (2-4 chars max)
    private const MOD_PRODUCT = 'p';

    private const MOD_VARIANT = 'v';

    private const MOD_CATEGORY = 'cat';

    private const MOD_BRAND = 'br';

    private const MOD_FILTER = 'flt';

    private const MOD_SEARCH = 'srch';

    private const MOD_INDEX = 'idx';

    private const MOD_PAGE = 'pg';

    private const MOD_COMPONENT = 'cmp';

    private const MOD_USER = 'usr';

    private const MOD_SETTINGS = 'set';

    private const MOD_AGGREGATE = 'agg';

    private const MOD_TEMP = 'tmp';

    private const MOD_META = 'meta';

    // ═══════════════════════════════════════════════════════════════════════
    // CORE KEY BUILDER
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Build a standardized Redis key
     *
     * @param  string  $module Module prefix
     * @param  string|array  $parts Key parts (type, ID, suffix, etc.)
     * @return string Formatted Redis key
     */
    private static function key(string $module, ...$parts): string
    {
        $segments = [self::NAMESPACE, $module];

        foreach ($parts as $part) {
            if ($part === null || $part === '') {
                continue;
            }
            $segments[] = is_array($part) ? implode(':', $part) : $part;
        }

        return implode(':', $segments);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRODUCT KEYS - ec:p:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Full product data with all relationships */
    public static function productFull(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'full');
    }

    /** Lightweight product card (for listings) */
    public static function productCard(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'card');
    }

    /** Product metadata only (no images/variants) */
    public static function productMeta(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'meta');
    }

    /** All variants for a product */
    public static function productVariants(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'vars');
    }

    /** All images for a product */
    public static function productImages(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'imgs');
    }

    /** Product ID by slug lookup */
    public static function productBySlug(string $slug): string
    {
        return self::key(self::MOD_PRODUCT, 'slug', $slug);
    }

    /** Product ID by SKU lookup */
    public static function productBySku(string $sku): string
    {
        return self::key(self::MOD_PRODUCT, 'sku', $sku);
    }

    /** Product reviews */
    public static function productReviews(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'reviews');
    }

    /** Product rating aggregate */
    public static function productRating(int $id): string
    {
        return self::key(self::MOD_PRODUCT, $id, 'rating');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VARIANT KEYS - ec:v:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Full variant data */
    public static function variantFull(int $id): string
    {
        return self::key(self::MOD_VARIANT, $id, 'full');
    }

    /** Lightweight variant card */
    public static function variantCard(int $id): string
    {
        return self::key(self::MOD_VARIANT, $id, 'card');
    }

    /** Variant images */
    public static function variantImages(int $id): string
    {
        return self::key(self::MOD_VARIANT, $id, 'imgs');
    }

    /** All variants for product (SET of variant IDs) */
    public static function variantsForProduct(int $productId): string
    {
        return self::key(self::MOD_VARIANT, "p:{$productId}", 'all');
    }

    /** Active variants for product (SET of variant IDs) */
    public static function variantsActive(int $productId): string
    {
        return self::key(self::MOD_VARIANT, "p:{$productId}", 'active');
    }

    /** In-stock variants for product (SET of variant IDs) */
    public static function variantsInStock(int $productId): string
    {
        return self::key(self::MOD_VARIANT, "p:{$productId}", 'stock');
    }

    /** Variant ID by SKU lookup */
    public static function variantBySku(string $sku): string
    {
        return self::key(self::MOD_VARIANT, 'sku', $sku);
    }

    /** Variant options (color, size, etc.) */
    public static function variantOptions(int $id): string
    {
        return self::key(self::MOD_VARIANT, $id, 'opts');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CATEGORY KEYS - ec:cat:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Full category data */
    public static function categoryFull(int $id): string
    {
        return self::key(self::MOD_CATEGORY, $id, 'full');
    }

    /** Category tree/hierarchy */
    public static function categoryTree(int $id): string
    {
        return self::key(self::MOD_CATEGORY, $id, 'tree');
    }

    /** Product IDs in category (SET) */
    public static function categoryProducts(int $id): string
    {
        return self::key(self::MOD_CATEGORY, $id, 'prods');
    }

    /** Product count in category */
    public static function categoryCount(int $id): string
    {
        return self::key(self::MOD_CATEGORY, $id, 'count');
    }

    /** Full category tree */
    public static function categoryTreeAll(): string
    {
        return self::key(self::MOD_CATEGORY, 'tree');
    }

    /** Category ID by slug */
    public static function categoryBySlug(string $slug): string
    {
        return self::key(self::MOD_CATEGORY, 'slug', $slug);
    }

    /** Category filters metadata */
    public static function categoryFilters(int $id): string
    {
        return self::key(self::MOD_CATEGORY, $id, 'filters');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BRAND KEYS - ec:br:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Full brand data */
    public static function brandFull(int $id): string
    {
        return self::key(self::MOD_BRAND, $id, 'full');
    }

    /** Product IDs for brand (SET) */
    public static function brandProducts(int $id): string
    {
        return self::key(self::MOD_BRAND, $id, 'prods');
    }

    /** All brands list */
    public static function brandsAll(): string
    {
        return self::key(self::MOD_BRAND, 'all');
    }

    /** Brand ID by slug */
    public static function brandBySlug(string $slug): string
    {
        return self::key(self::MOD_BRAND, 'slug', $slug);
    }

    /** Brand product count */
    public static function brandCount(int $id): string
    {
        return self::key(self::MOD_BRAND, $id, 'count');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // FILTER KEYS - ec:flt:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Filter metadata for category */
    public static function filterMeta(string $categorySlug = 'all'): string
    {
        return self::key(self::MOD_FILTER, 'meta', $categorySlug);
    }

    /** Filter results by hash */
    public static function filterResults(string $hash): string
    {
        return self::key(self::MOD_FILTER, 'res', $hash);
    }

    /** Filter options for category and type */
    public static function filterOptions(string $category, string $type): string
    {
        return self::key(self::MOD_FILTER, 'opts', $category, $type);
    }

    /** Price ranges for category */
    public static function filterPriceRanges(string $category = 'all'): string
    {
        return self::key(self::MOD_FILTER, 'price', $category);
    }

    /** Count for filter combination */
    public static function filterCount(string $category, string $filterHash): string
    {
        return self::key(self::MOD_FILTER, 'cnt', $category, $filterHash);
    }

    /** Available filter values for category */
    public static function filterAvailable(int $categoryId, string $filterType): string
    {
        return self::key(self::MOD_FILTER, 'avail', $categoryId, $filterType);
    }

    /** Price index for filtering */
    public static function filterPriceIndex(string $categorySlug = 'all'): string
    {
        return self::key(self::MOD_FILTER, 'price_idx', $categorySlug);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SEARCH KEYS - ec:srch:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Search query results for specific page */
    public static function searchQuery(string $query, int $page = 1): string
    {
        $hash = md5(strtolower(trim($query)));

        return self::key(self::MOD_SEARCH, 'q', $hash, "p{$page}");
    }

    /** Search suggestions */
    public static function searchSuggestions(string $term): string
    {
        return self::key(self::MOD_SEARCH, 'sug', substr(md5($term), 0, 8));
    }

    /** Popular searches */
    public static function searchPopular(): string
    {
        return self::key(self::MOD_SEARCH, 'pop');
    }

    /** Recent searches for user */
    public static function searchRecent(int $userId): string
    {
        return self::key(self::MOD_SEARCH, 'recent', $userId);
    }

    /** Search result count */
    public static function searchCount(string $query): string
    {
        $hash = md5(strtolower(trim($query)));

        return self::key(self::MOD_SEARCH, 'cnt', $hash);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX KEYS - ec:idx:* (Pre-computed product ID sets)
    // ═══════════════════════════════════════════════════════════════════════

    /** Product IDs in category (SET) */
    public static function indexCategory(int $categoryId): string
    {
        return self::key(self::MOD_INDEX, 'cat', $categoryId);
    }

    /** Product IDs for brand (SET) */
    public static function indexBrand(int $brandId): string
    {
        return self::key(self::MOD_INDEX, 'br', $brandId);
    }

    /** Product IDs in price range (SET) */
    public static function indexPriceRange(string $range): string
    {
        return self::key(self::MOD_INDEX, 'price', $range);
    }

    /** Product IDs with rating (SET) */
    public static function indexRating(int $stars): string
    {
        return self::key(self::MOD_INDEX, 'rating', $stars);
    }

    /** Product IDs with discount % (SET) */
    public static function indexDiscount(int $percentage): string
    {
        return self::key(self::MOD_INDEX, 'disc', $percentage);
    }

    /** Featured product IDs (SET) */
    public static function indexFeatured(): string
    {
        return self::key(self::MOD_INDEX, 'feat');
    }

    /** New product IDs (SET) */
    public static function indexNew(): string
    {
        return self::key(self::MOD_INDEX, 'new');
    }

    /** On-sale product IDs (SET) */
    public static function indexOnSale(): string
    {
        return self::key(self::MOD_INDEX, 'sale');
    }

    /** Best-selling product IDs (ZSET with scores) */
    public static function indexBestSellers(): string
    {
        return self::key(self::MOD_INDEX, 'bestsell');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PAGE CACHE KEYS - ec:pg:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Homepage cache (versioned for instant invalidation) */
    public static function pageHome(int $version = 1): string
    {
        return self::key(self::MOD_PAGE, 'home', "v{$version}");
    }

    /** Category page cache */
    public static function pageCategory(int $categoryId, int $page = 1): string
    {
        return self::key(self::MOD_PAGE, 'cat', $categoryId, "p{$page}");
    }

    /** Product detail page cache */
    public static function pageProduct(int $productId): string
    {
        return self::key(self::MOD_PAGE, 'prod', $productId);
    }

    /** Search results page cache */
    public static function pageSearch(string $query, int $page = 1): string
    {
        $hash = substr(md5($query), 0, 12);

        return self::key(self::MOD_PAGE, 'search', $hash, "p{$page}");
    }

    /** Brand page cache */
    public static function pageBrand(int $brandId, int $page = 1): string
    {
        return self::key(self::MOD_PAGE, 'brand', $brandId, "p{$page}");
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPONENT CACHE KEYS - ec:cmp:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Navigation menu */
    public static function componentNav(): string
    {
        return self::key(self::MOD_COMPONENT, 'nav');
    }

    /** Footer content */
    public static function componentFooter(): string
    {
        return self::key(self::MOD_COMPONENT, 'footer');
    }

    /** Active banners */
    public static function componentBanners(): string
    {
        return self::key(self::MOD_COMPONENT, 'banner');
    }

    /** Featured products section */
    public static function componentFeatured(): string
    {
        return self::key(self::MOD_COMPONENT, 'feat');
    }

    /** Deals section */
    public static function componentDeals(): string
    {
        return self::key(self::MOD_COMPONENT, 'deal');
    }

    /** New arrivals section */
    public static function componentNewArrivals(): string
    {
        return self::key(self::MOD_COMPONENT, 'new');
    }

    /** Trending products section */
    public static function componentTrending(): string
    {
        return self::key(self::MOD_COMPONENT, 'trend');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // USER KEYS - ec:usr:*
    // ═══════════════════════════════════════════════════════════════════════

    /** User cart */
    public static function userCart(int $userId): string
    {
        return self::key(self::MOD_USER, $userId, 'cart');
    }

    /** User wishlist */
    public static function userWishlist(int $userId): string
    {
        return self::key(self::MOD_USER, $userId, 'wish');
    }

    /** Recently viewed products */
    public static function userRecent(int $userId): string
    {
        return self::key(self::MOD_USER, $userId, 'recent');
    }

    /** User profile cache */
    public static function userProfile(int $userId): string
    {
        return self::key(self::MOD_USER, $userId, 'profile');
    }

    /** Session data */
    public static function userSession(string $sessionId): string
    {
        return self::key(self::MOD_USER, 'sess', $sessionId);
    }

    /** User orders cache */
    public static function userOrders(int $userId): string
    {
        return self::key(self::MOD_USER, $userId, 'orders');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // SETTINGS KEYS - ec:set:*
    // ═══════════════════════════════════════════════════════════════════════

    /** App settings */
    public static function settingsApp(): string
    {
        return self::key(self::MOD_SETTINGS, 'app');
    }

    /** SEO settings */
    public static function settingsSeo(): string
    {
        return self::key(self::MOD_SETTINGS, 'seo');
    }

    /** SMTP settings */
    public static function settingsSmtp(): string
    {
        return self::key(self::MOD_SETTINGS, 'smtp');
    }

    /** Payment settings */
    public static function settingsPayment(): string
    {
        return self::key(self::MOD_SETTINGS, 'payment');
    }

    /** General settings */
    public static function settingsGeneral(): string
    {
        return self::key(self::MOD_SETTINGS, 'general');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AGGREGATE/STATS KEYS - ec:agg:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Global statistics */
    public static function aggregateStats(): string
    {
        return self::key(self::MOD_AGGREGATE, 'stats');
    }

    /** Total product count */
    public static function aggregateProductCount(): string
    {
        return self::key(self::MOD_AGGREGATE, 'count', 'prods');
    }

    /** Total category count */
    public static function aggregateCategoryCount(): string
    {
        return self::key(self::MOD_AGGREGATE, 'count', 'cats');
    }

    /** Top items by type (sellers, viewed, etc.) */
    public static function aggregateTop(string $type = 'sellers'): string
    {
        return self::key(self::MOD_AGGREGATE, 'top', $type);
    }

    /** Daily/weekly/monthly stats */
    public static function aggregateStatsPeriod(string $period): string
    {
        return self::key(self::MOD_AGGREGATE, 'stats', $period);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEMPORARY KEYS - ec:tmp:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Distributed lock */
    public static function tempLock(string $key): string
    {
        return self::key(self::MOD_TEMP, 'lock', $key);
    }

    /** Temporary set union */
    public static function tempUnion(string $type, array $ids): string
    {
        $hash = md5(implode(',', $ids));

        return self::key(self::MOD_TEMP, "union_{$type}", $hash);
    }

    /** Job processing data */
    public static function tempJob(string $jobId): string
    {
        return self::key(self::MOD_TEMP, 'job', $jobId);
    }

    /** Rate limiting */
    public static function tempRateLimit(string $ip, string $endpoint): string
    {
        return self::key(self::MOD_TEMP, 'rate', $ip, md5($endpoint));
    }

    /** Temporary cache for filters */
    public static function tempFilter(string $hash): string
    {
        return self::key(self::MOD_TEMP, 'flt', $hash);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // METADATA KEYS - ec:meta:*
    // ═══════════════════════════════════════════════════════════════════════

    /** Cache version (for instant invalidation) */
    public static function metaVersion(): string
    {
        return self::key(self::MOD_META, 'ver');
    }

    /** System health check */
    public static function metaHealth(): string
    {
        return self::key(self::MOD_META, 'health');
    }

    /** Last cache warmup info */
    public static function metaWarmup(string $timestamp = ''): string
    {
        return self::key(self::MOD_META, 'warmup', $timestamp);
    }

    /** Last sync timestamp for entity type */
    public static function metaLastSync(string $entityType): string
    {
        return self::key(self::MOD_META, 'sync', $entityType);
    }

    /** Metrics hit tracking */
    public static function metricsHit(string $tier): string
    {
        return self::key(self::MOD_META, 'hit', $tier);
    }

    /** Metrics miss tracking */
    public static function metricsMiss(): string
    {
        return self::key(self::MOD_META, 'miss');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PATTERN GENERATORS (for bulk operations)
    // ═══════════════════════════════════════════════════════════════════════

    /** Get pattern for all keys in a module */
    public static function pattern(string $module, ?string $type = null): string
    {
        if ($type) {
            return self::NAMESPACE.":{$module}:{$type}:*";
        }

        return self::NAMESPACE.":{$module}:*";
    }

    /** Get pattern for all product keys */
    public static function patternProducts(): string
    {
        return self::pattern(self::MOD_PRODUCT);
    }

    /** Get pattern for all variant keys */
    public static function patternVariants(): string
    {
        return self::pattern(self::MOD_VARIANT);
    }

    /** Get pattern for all category keys */
    public static function patternCategories(): string
    {
        return self::pattern(self::MOD_CATEGORY);
    }

    /** Get pattern for all page caches */
    public static function patternPages(): string
    {
        return self::pattern(self::MOD_PAGE);
    }

    /** Get pattern for specific product's related keys */
    public static function patternProduct(int $id): string
    {
        return self::NAMESPACE.':'.self::MOD_PRODUCT.":{$id}:*";
    }

    /** Get pattern for specific category's related keys */
    public static function patternCategory(int $id): string
    {
        return self::NAMESPACE.':'.self::MOD_CATEGORY.":{$id}:*";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UTILITY METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /** Get namespace */
    public static function namespace(): string
    {
        return self::NAMESPACE;
    }

    /** Validate if key follows our standard */
    public static function isValid(string $key): bool
    {
        return str_starts_with($key, self::NAMESPACE.':');
    }

    /** Parse key into components */
    public static function parse(string $key): ?array
    {
        if (! self::isValid($key)) {
            return null;
        }

        $parts = explode(':', $key);

        return [
            'namespace' => $parts[0] ?? null,
            'module' => $parts[1] ?? null,
            'type' => $parts[2] ?? null,
            'id' => $parts[3] ?? null,
            'suffix' => $parts[4] ?? null,
        ];
    }

    /** Get old pattern keys for migration */
    public static function getOldPatterns(): array
    {
        return [
            'ecommerce:v1:*',           // Old verbose namespace
            'ecom:*',                   // Old namespace
            'uf_*',                     // Ultra-fast filter keys
            'cache:*',                  // Unnamespaced cache
            'homepage:*',               // Old homepage cache
            'product_*',                // Old product keys
            'category_*',               // Old category keys
            'filter:*',                 // Old filter keys
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // BACKWARD COMPATIBILITY (Deprecated - use specific methods instead)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @deprecated Use specific methods like productCard(), variantFull(), etc.
     */
    public static function make(string $module, string $type, $identifier = null, ?string $suffix = null): string
    {
        $parts = [$type];

        if ($identifier !== null) {
            $parts[] = is_array($identifier) ? implode(':', $identifier) : $identifier;
        }

        if ($suffix !== null) {
            $parts[] = $suffix;
        }

        return self::key($module, ...$parts);
    }

    // Backward compatibility methods (map old calls to new structure)
    public static function cacheProduct(int $productId, string $variant = 'full'): string
    {
        return self::productCard($productId);
    }

    public static function cacheCategory(int $categoryId): string
    {
        return self::categoryFull($categoryId);
    }

    public static function cacheSettings(string $type = 'global'): string
    {
        return match ($type) {
            'seo' => self::settingsSeo(),
            'smtp' => self::settingsSmtp(),
            'payment' => self::settingsPayment(),
            default => self::settingsApp(),
        };
    }

    public static function cacheHomepage(string $section = 'full'): string
    {
        return self::pageHome(RedisCacheService::getVersion());
    }
}
