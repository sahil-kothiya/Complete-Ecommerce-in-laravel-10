<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Banner Seeder
 *
 * Seeds promotional banners for the homepage and other pages.
 * This seeder is idempotent and can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class BannerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->info('🔄 Seeding banners...');

        DB::beginTransaction();

        try {
            $this->truncateTable();
            $this->seedBanners();

            DB::commit();
            $this->command->info('✅ Banners seeded successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed banners: ' . $e->getMessage());
            Log::error('Banner seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Truncate banners table safely
     *
     * @return void
     */
    private function truncateTable(): void
    {
        DB::table('banners')->truncate();
        DB::statement("SELECT setval(pg_get_serial_sequence('banners', 'id'), 1, false)");
    }

    /**
     * Seed banner records
     *
     * @return void
     */
    private function seedBanners(): void
    {
        $banners = [
            [
                'title' => 'Summer Sale',
                'slug' => 'summer-sale',
                'photo' => '/storage/photos/1/Banner/banner-01.webp',
                'description' => '<h2><span style="font-weight: bold; color: rgb(99, 99, 99);">Up to 10% OFF</span></h2>',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Mega Discount',
                'slug' => 'mega-discount',
                'photo' => '/storage/photos/1/Banner/banner-07.webp',
                'description' => '<p>Up to 90% OFF</p>',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Special Offer',
                'slug' => 'special-offer',
                'photo' => '/storage/photos/1/Banner/banner-06.webp',
                'description' => '<h2><span style="color: rgb(156, 0, 255); font-size: 2rem; font-weight: bold;">Up to 40% OFF</span></h2>',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('banners')->insert($banners);
        $this->command->info('   • Inserted ' . count($banners) . ' banners');
    }
}
