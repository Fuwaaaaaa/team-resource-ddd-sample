<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AllocationModel>
 */
class AllocationFactory extends Factory
{
    protected $model = AllocationModel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'member_id' => MemberFactory::new(),
            'project_id' => ProjectFactory::new(),
            'skill_id' => SkillFactory::new(),
            'allocation_percentage' => fake()->numberBetween(10, 80),
            'period_start' => '2026-04-01',
            'period_end' => '2026-09-30',
            'status' => 'active',
        ];
    }

    public function revoked(): self
    {
        return $this->state(fn () => ['status' => 'revoked']);
    }

    public function forMember(MemberModel $member): self
    {
        return $this->state(fn () => ['member_id' => $member->id]);
    }

    public function forProject(ProjectModel $project): self
    {
        return $this->state(fn () => ['project_id' => $project->id]);
    }

    public function forSkill(SkillModel $skill): self
    {
        return $this->state(fn () => ['skill_id' => $skill->id]);
    }

    public function withPercentage(int $pct): self
    {
        return $this->state(fn () => ['allocation_percentage' => $pct]);
    }
}
