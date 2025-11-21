<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settings Table Seeder
 *
 * Seeds application-wide settings and configuration.
 * This seeder is idempotent and can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class SettingsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->info('🔄 Seeding settings...');

        DB::beginTransaction();

        try {
            $this->truncateTable();
            $this->seedSettings();

            DB::commit();
            $this->command->info('✅ Settings seeded successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed settings: ' . $e->getMessage());
            Log::error('Settings seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Truncate settings table safely
     *
     * @return void
     */
    private function truncateTable(): void
    {
        DB::table('settings')->truncate();
        DB::statement("SELECT setval(pg_get_serial_sequence('settings', 'id'), 1, false)");
    }

    /**
     * Seed settings record
     *
     * @return void
     */
    private function seedSettings(): void
    {
        $settings = [
            'description' => 'Your trusted online shopping destination for quality products at competitive prices. '
                . 'We offer a wide range of products from electronics to fashion, ensuring customer satisfaction '
                . 'with every purchase. Fast shipping, secure payments, and excellent customer service guaranteed.',
            'short_des' => 'Quality products, competitive prices, and exceptional customer service. '
                . 'Your one-stop shop for all your needs.',
            'logo' => '/storage/photos/1/logo.webp',
            'photo' => '/storage/photos/1/blog3.webp',
            'address' => 'NO. 342 - London Oxford Street, 012 United Kingdom',
            'phone' => '+060 (700) 891-123',
            'email' => 'ecommerce@gmail.com',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('settings')->insert($settings);
        $this->command->info('   • Inserted application settings');
    }
}
