<?php

namespace Database\Factories;

use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => $this->faker->name,
            'email'             => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password'          => bcrypt('password'), // or use Hash::make if you prefer
            'remember_token'    => Str::random(10),
            'role'              => 'user',
            'status'            => 'active',
            'created_at'        => now()->subDays(rand(0, 30)),
            'updated_at'        => now(),
        ];
    }
}
