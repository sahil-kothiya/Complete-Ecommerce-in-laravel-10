<?php
namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = Carbon::now();

        $data = [
            [
                'name'       => 'Admin',
                'email'      => 'admin@gmail.com',
                'password'   => Hash::make('1111'),
                'role'       => 'admin',
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name'       => 'User',
                'email'      => 'user@gmail.com',
                'password'   => Hash::make('1111'),
                'role'       => 'user',
                'status'     => 'active',
                'created_at' => $now->copy()->subDays(2),
                'updated_at' => $now->copy()->subDays(2),
            ],
        ];

        DB::table('users')->insert($data);
    }
}
