<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AllocationSuggestionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_returns_ordered_candidates(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $m1 = MemberModel::factory()->create();
        $m2 = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m1->id,
            'skill_id' => $skill->id,
            'proficiency' => 5,
        ]);
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m2->id,
            'skill_id' => $skill->id,
            'proficiency' => 3,
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=3&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(2, count($data));
        $this->assertSame($m1->id, $data[0]['memberId']); // 熟練度 5 が上位
        $this->assertContains('熟練度 L5 (要求 L3)', $data[0]['reasons']);
    }

    public function test_excludes_fully_booked_members(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $otherProject = ProjectModel::factory()->create();
        $member = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 4,
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $otherProject->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 100,
            'period_start' => '2026-05-01',
            'period_end' => '2026-12-31',
            'status' => 'active',
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=3&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $this->assertEmpty($response->json('data'));
    }

    public function test_validation_rejects_missing_params(): void
    {
        $this->getJson('/api/allocations/suggestions?projectId=x')
            ->assertStatus(422);
    }
}
