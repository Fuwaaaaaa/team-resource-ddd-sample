<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',
            'remember_token' => Str::random(10),
            // Default to admin so that feature tests exercising write endpoints pass
            // without every caller needing to override it. Migration sets 'admin' as
            // column default on Postgres, but SQLite (used in CI feature tests) needs
            // explicit initialization to avoid role-based 403s.
            'role' => 'admin',
        ];
    }
}
