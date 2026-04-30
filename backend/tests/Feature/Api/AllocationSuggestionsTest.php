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
        $this->assertNull($response->json('hint'));
    }

    public function test_response_includes_score_breakdown_and_conflict_flag(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $member = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 4,
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=3&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $candidate = $response->json('data.0');
        $this->assertArrayHasKey('scoreBreakdown', $candidate);
        // JSON encoding collapses trailing .0 — compare numerically
        $this->assertEqualsWithDelta(100.0, $candidate['scoreBreakdown']['capacity'], 0.0001);
        $this->assertEqualsWithDelta(10.0, $candidate['scoreBreakdown']['proficiency'], 0.0001); // (4-3)*10
        $this->assertEqualsWithDelta(0.0, $candidate['scoreBreakdown']['experience'], 0.0001);
        $this->assertFalse($candidate['nextWeekConflict']);
        $this->assertSame([], $candidate['recentAssignments']);
    }

    public function test_recent_assignments_populated_for_same_skill_within_window(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $pastProject = ProjectModel::factory()->create();
        $member = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 4,
        ]);
        // 30 日前に終わった同スキル案件 → recentAssignments に入る
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $pastProject->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 40,
            'period_start' => '2026-03-01',
            'period_end' => '2026-05-01',
            'status' => 'revoked',
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=3&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $candidate = $response->json('data.0');
        $this->assertCount(1, $candidate['recentAssignments']);
        $assignment = $candidate['recentAssignments'][0];
        $this->assertSame($pastProject->id, $assignment['projectId']);
        $this->assertSame(40, $assignment['allocationPercentage']);
        $this->assertSame('revoked', $assignment['status']);
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
        $this->assertSame('all_members_at_capacity', $response->json('hint'));
    }

    public function test_hint_no_members_with_skill_when_skill_unused(): void
    {
        $skill = SkillModel::factory()->create();
        $otherSkill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $member = MemberModel::factory()->create();
        // 別スキルしか持たないメンバー
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $otherSkill->id,
            'proficiency' => 5,
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=3&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $this->assertEmpty($response->json('data'));
        $this->assertSame('no_members_with_skill', $response->json('hint'));
    }

    public function test_hint_min_proficiency_too_high_when_skill_present_but_under_required(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $member = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $skill->id,
            'proficiency' => 2, // 要求 4 より低い
        ]);

        $response = $this->getJson(sprintf(
            '/api/allocations/suggestions?projectId=%s&skillId=%s&minimumProficiency=4&periodStart=2026-06-01',
            $project->id,
            $skill->id,
        ))->assertOk();

        $this->assertEmpty($response->json('data'));
        $this->assertSame('min_proficiency_too_high', $response->json('hint'));
    }

    public function test_validation_rejects_missing_params(): void
    {
        $this->getJson('/api/allocations/suggestions?projectId=x')
            ->assertStatus(422);
    }
}
