<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectModel>
 */
class ProjectFactory extends Factory
{
    protected $model = ProjectModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'status' => 'active',
        ];
    }
}
