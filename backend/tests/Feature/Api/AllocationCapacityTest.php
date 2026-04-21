<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AllocationCapacityTest extends TestCase
{
    use RefreshDatabase;

    private MemberModel $member;
    private ProjectModel $project;
    private SkillModel $skill;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->member = MemberModel::factory()->create();
        $this->project = ProjectModel::factory()->create();
        $this->skill = SkillModel::factory()->create();
    }

    public function test_create_within_100_percent_returns_201(): void
    {
        $this->createAllocation(60)->assertCreated();
    }

    public function test_create_over_100_percent_returns_422_with_capacity_exceeded(): void
    {
        $this->createAllocation(60)->assertCreated();

        $response = $this->createAllocation(50);

        $response->assertStatus(422)
            ->assertJson(['error' => 'allocation_capacity_exceeded']);
    }

    public function test_revoke_frees_capacity(): void
    {
        $first = $this->createAllocation(60)->assertCreated();
        $allocationId = $first->json('data.id');

        $this->postJson("/api/allocations/{$allocationId}/revoke")
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked');

        // 80% should now fit since the 60% is revoked
        $this->createAllocation(80)->assertCreated();
    }

    public function test_create_rejects_period_end_not_after_start(): void
    {
        $response = $this->postJson('/api/allocations', [
            'memberId' => $this->member->id,
            'projectId' => $this->project->id,
            'skillId' => $this->skill->id,
            'allocationPercentage' => 50,
            'periodStart' => '2026-04-01',
            'periodEnd' => '2026-04-01',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['periodEnd']);
    }

    private function createAllocation(int $percentage): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/allocations', [
            'memberId' => $this->member->id,
            'projectId' => $this->project->id,
            'skillId' => $this->skill->id,
            'allocationPercentage' => $percentage,
            'periodStart' => '2026-04-01',
            'periodEnd' => '2026-09-30',
        ]);
    }
}
