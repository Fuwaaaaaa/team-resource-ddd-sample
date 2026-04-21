<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberModel>
 */
class MemberFactory extends Factory
{
    protected $model = MemberModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'standard_working_hours' => fake()->randomFloat(1, 4, 10),
        ];
    }
}
