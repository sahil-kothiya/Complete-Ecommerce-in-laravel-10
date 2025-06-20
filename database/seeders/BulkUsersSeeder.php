<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Faker\Factory as Faker;
use Carbon\Carbon;

class BulkUsersSeeder extends Seeder
{
    public function run(): void
    {
        ini_set('max_execution_time', 0); // unlimited
        ini_set('memory_limit', '4G');

        $totalRecords = 1_000_000; // 1 Million
        // $totalRecords = 10000;
        $batchSize = 5000;
        $batches = $totalRecords / $batchSize;
        $now = Carbon::now();
        $hashedPassword = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'; // bcrypt('password')

        $this->command->info("🚀 Seeding $totalRecords users in $batches batches of $batchSize...");

        for ($i = 0; $i < $batches; $i++) {
            $users = [];

            // Use local faker instance to avoid exhaustion of unique email pool
            $localFaker = Faker::create();

            for ($j = 0; $j < $batchSize; $j++) {
                $users[] = [
                    'name'              => $localFaker->name,
                    'email'             => 'user_' . ($i * $batchSize + $j) . '@example.com',
                    'email_verified_at' => $now,
                    'password'          => $hashedPassword,
                    'remember_token'    => Str::random(10),
                    'role'              => 'user',
                    'status'            => 'active',
                    'created_at'        => $now->copy()->subDays(rand(0, 30)),
                    'updated_at'        => $now,
                ];
            }

            DB::table('users')->insert($users);

            if (($i + 1) % 10 === 0) {
                $this->command->info("✅ Completed batch " . ($i + 1) . " of $batches");
            }
        }

        $this->command->info("🎉 Done seeding 1 million users.");
    }
}
