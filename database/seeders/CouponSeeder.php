<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Coupon Seeder
 *
 * Seeds promotional coupons for discounts.
 * This seeder is idempotent and can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class CouponSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->info('🔄 Seeding coupons...');

        DB::beginTransaction();

        try {
            $this->truncateTable();
            $this->seedCoupons();

            DB::commit();
            $this->command->info('✅ Coupons seeded successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed coupons: ' . $e->getMessage());
            Log::error('Coupon seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Truncate coupons table safely
     *
     * @return void
     */
    private function truncateTable(): void
    {
        DB::table('coupons')->truncate();
        DB::statement("SELECT setval(pg_get_serial_sequence('coupons', 'id'), 1, false)");
    }

    /**
     * Seed coupon records
     *
     * @return void
     */
    private function seedCoupons(): void
    {
        $coupons = [
            [
                'code' => 'SAVE300',
                'type' => 'fixed',
                'value' => '300.00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'PERCENT10',
                'type' => 'percent',
                'value' => '10.00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'WELCOME20',
                'type' => 'percent',
                'value' => '20.00',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('coupons')->insert($coupons);
        $this->command->info('   • Inserted ' . count($coupons) . ' coupons');
    }
}
