<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SkillModel>
 */
class SkillFactory extends Factory
{
    protected $model = SkillModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'category' => fake()->randomElement([
                'programming_language',
                'framework',
                'infrastructure',
                'database',
                'design',
                'management',
                'other',
            ]),
        ];
    }
}
