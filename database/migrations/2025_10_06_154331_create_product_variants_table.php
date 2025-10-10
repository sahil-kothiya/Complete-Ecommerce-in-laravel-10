<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->string('sku')->unique(); // Required, auto-gen if needed (e.g., slug + option codes)
            $table->decimal('price', 10, 2); // Variant-specific price
            $table->decimal('discount', 10, 2)->nullable(); // Variant-specific
            $table->integer('stock')->default(0); // Variant-specific
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            // High-scale indexes
            $table->index(['product_id', 'status'], 'idx_product_variants_product_status');
            $table->index('sku'); // Global unique lookup
            $table->index('price'); // Price sorting/filters
            $table->index(['product_id', 'stock'], 'idx_product_variants_product_stock'); // Low-stock alerts
            $table->index(['status', 'created_at'], 'idx_product_variants_status_created'); // Pagination
        });

        // PG partials for active/low-stock
        // Create partial indexes only when not present
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = 'idx_active_variants') THEN
                CREATE INDEX idx_active_variants ON product_variants (product_id, created_at DESC) WHERE status = 'active';
            END IF;
            IF NOT EXISTS (SELECT 1 FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE c.relname = 'idx_low_stock_active') THEN
                CREATE INDEX idx_low_stock_active ON product_variants (product_id) WHERE status = 'active' AND stock <= 10;
            END IF;
        END $$;");

        DB::statement("CREATE EXTENSION IF NOT EXISTS pg_trgm;");
        DB::statement("CREATE INDEX IF NOT EXISTS product_variants_sku_trgm_idx ON product_variants USING GIN (sku gin_trgm_ops);");
        DB::statement("CREATE INDEX IF NOT EXISTS product_variants_sku_gin_idx ON product_variants USING GIN (to_tsvector('english', sku));");

        // CHECK constraints for data integrity (PG-specific). Add only if not exists
        DB::statement("DO $$ BEGIN
            IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_price_positive') THEN
                ALTER TABLE product_variants ADD CONSTRAINT chk_price_positive CHECK (price IS NULL OR price > 0);
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_discount_valid') THEN
                ALTER TABLE product_variants ADD CONSTRAINT chk_discount_valid CHECK (discount IS NULL OR (discount >= 0 AND discount <= 100));
            END IF;
            IF NOT EXISTS (SELECT 1 FROM information_schema.table_constraints WHERE constraint_name = 'chk_stock_non_negative') THEN
                ALTER TABLE product_variants ADD CONSTRAINT chk_stock_non_negative CHECK (stock IS NULL OR stock >= 0);
            END IF;
        END $$;");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
