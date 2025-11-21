<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Database Seeder - Main Entry Point
 *
 * Orchestrates the seeding of all database tables in the correct order.
 * This is the primary seeder that should be run for fresh installations.
 *
 * Usage:
 *   php artisan db:seed
 *   php artisan migrate:fresh --seed
 *
 * @package Database\Seeders
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Executes all seeders in dependency order:
     * 1. Users (required for tracking created_by fields)
     * 2. Settings & Static Data (banners, coupons, shipping)
     * 3. Core E-commerce Data (categories, brands, variants)
     * 4. Products (massive product generation)
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->newLine();
        $this->command->info('╔════════════════════════════════════════════════════════════════╗');
        $this->command->info('║              ENTERPRISE E-COMMERCE DATABASE SEEDER            ║');
        $this->command->info('╚════════════════════════════════════════════════════════════════╝');
        $this->command->newLine();

        $startTime = microtime(true);

        try {
            // Step 1: Foundation Data
            $this->command->info('📋 Step 1: Seeding Foundation Data...');
            $this->call(UsersTableSeeder::class);
            $this->call(BannerSeeder::class);
            $this->call(SettingsTableSeeder::class);
            $this->call(CouponSeeder::class);
            $this->command->newLine();

            // Step 2: Core E-commerce Structure
            $this->command->info('📋 Step 2: Seeding Core E-commerce Data...');
            $this->call(CategoryBrandVariantBaseSeeder::class);
            $this->call(ShippingSeeder::class);
            $this->command->newLine();

            // Step 3: Products (can be intensive for large datasets)
            $this->command->info('📋 Step 3: Seeding Products...');
            $this->call(PostgresMassiveProductSeeder::class);
            $this->command->newLine();

            $elapsed = microtime(true) - $startTime;

            $this->command->info('╔════════════════════════════════════════════════════════════════╗');
            $this->command->info('║            ✅ DATABASE SEEDING COMPLETED SUCCESSFULLY          ║');
            $this->command->info('║                                                                ║');
            $this->command->info(sprintf('║   Total Time: %-48s ║', $this->formatDuration($elapsed)));
            $this->command->info('╚════════════════════════════════════════════════════════════════╝');
            $this->command->newLine();
        } catch (Throwable $e) {
            $this->command->error('❌ Database seeding failed: ' . $e->getMessage());
            Log::error('Database seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Format duration in human-readable format
     *
     * @param float $seconds
     * @return string
     */
    private function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%.2f seconds', $seconds);
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        if ($minutes < 60) {
            return sprintf('%d min %.2f sec', $minutes, $remainingSeconds);
        }

        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%d hr %d min %.2f sec', $hours, $remainingMinutes, $remainingSeconds);
    }
}
