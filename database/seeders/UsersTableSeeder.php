<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Users Table Seeder
 *
 * Seeds initial users including admin and test users.
 * This seeder is idempotent and can be run multiple times safely.
 *
 * @package Database\Seeders
 */
class UsersTableSeeder extends Seeder
{
    /**
     * Default password for all seeded users
     */
    private const DEFAULT_PASSWORD = '1111';

    /**
     * Run the database seeds.
     *
     * @return void
     * @throws Throwable
     */
    public function run(): void
    {
        $this->command->info('🔄 Seeding users...');

        DB::beginTransaction();

        try {
            $this->truncateTable();
            $this->seedUsers();

            DB::commit();
            $this->command->info('✅ Users seeded successfully!');
        } catch (Throwable $e) {
            DB::rollBack();
            $this->command->error('❌ Failed to seed users: ' . $e->getMessage());
            Log::error('Users seeding failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Truncate users table safely
     *
     * @return void
     */
    private function truncateTable(): void
    {
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        DB::table('users')->truncate();
        DB::statement("SELECT setval(pg_get_serial_sequence('users', 'id'), 1, false)");
    }

    /**
     * Seed user records
     *
     * @return void
     */
    private function seedUsers(): void
    {
        $now = Carbon::now();
        $hashedPassword = Hash::make(self::DEFAULT_PASSWORD);

        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@gmail.com',
                'password' => $hashedPassword,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'User',
                'email' => 'user@gmail.com',
                'password' => $hashedPassword,
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => $now->copy()->subDays(2),
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
            [
                'name' => 'Sahil Kothiya',
                'email' => 'sahil@mailinator.com',
                'password' => $hashedPassword,
                'role' => 'user',
                'status' => 'active',
                'email_verified_at' => $now->copy()->subDays(5),
                'created_at' => $now->copy()->subDays(5),
                'updated_at' => $now->copy()->subDays(5),
            ],
        ];

        DB::table('users')->insert($users);
        $this->command->info('   • Inserted ' . count($users) . ' users');
    }
}
