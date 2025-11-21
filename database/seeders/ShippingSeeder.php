<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shipping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Shipping Seeder
 *
 * Seeds shipping methods and pricing.
 * Uses updateOrCreate for idempotency - can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class ShippingSeeder extends Seeder
{
    /**
     * Shipping methods configuration
     */
    private const SHIPPING_METHODS = [
        [
            'type' => 'Free Shipping',
            'price' => 0.00,
            'status' => 'active',
        ],
        [
            'type' => 'Standard Delivery',
            'price' => 5.99,
            'status' => 'active',
        ],
        [
            'type' => 'Express Delivery',
            'price' => 12.99,
            'status' => 'active',
        ],
        [
            'type' => 'Next Day Delivery',
            'price' => 19.99,
            'status' => 'active',
        ],
    ];

    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->info('🔄 Seeding shipping methods...');

        DB::beginTransaction();

        try {
            $this->seedShippingMethods();

            DB::commit();
            $this->command->info('✅ Shipping methods seeded successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed shipping methods: ' . $e->getMessage());
            Log::error('Shipping seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Seed shipping method records
     *
     * @return void
     */
    private function seedShippingMethods(): void
    {
        $count = 0;

        foreach (self::SHIPPING_METHODS as $method) {
            Shipping::updateOrCreate(
                ['type' => $method['type']],
                [
                    'price' => $method['price'],
                    'status' => $method['status'],
                ]
            );
            $count++;
        }

        $this->command->info('   • Inserted/Updated ' . $count . ' shipping methods');
    }
}
