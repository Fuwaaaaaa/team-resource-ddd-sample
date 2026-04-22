<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProjectKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_returns_100_fulfillment_when_all_seats_filled(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 1,
        ]);

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
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 60,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/projects/{$project->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        $this->assertSame(100.0, $response->json('data.fulfillmentRate'));
        $this->assertSame(1, $response->json('data.activeAllocationCount'));
        $this->assertGreaterThan(0, $response->json('data.personMonthsAllocated'));
    }

    public function test_partial_fulfillment_counts_qualified_capped(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 3,
        ]);

        // 1 名だけ配置
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
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/projects/{$project->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        // required=3, qualified=1 → 33.3%
        $this->assertEqualsWithDelta(33.3, $response->json('data.fulfillmentRate'), 0.1);
        $this->assertSame(3, $response->json('data.totalRequiredHeadcount'));
        $this->assertSame(-2, $response->json('data.requiredSkillsBreakdown.0.gap'));
    }

    public function test_upcoming_ends_within_30_days(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $m1 = MemberModel::factory()->create();
        $m2 = MemberModel::factory()->create();

        // 終了 10 日後 → upcoming
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m1->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 30,
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-25',
            'status' => 'active',
        ]);
        // 終了 60 日後 → upcoming ではない
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m2->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 30,
            'period_start' => '2026-04-01',
            'period_end' => '2026-07-15',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/projects/{$project->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        $upcoming = $response->json('data.upcomingEnds');
        $this->assertCount(1, $upcoming);
        $this->assertSame($m1->id, $upcoming[0]['memberId']);
        $this->assertSame(10, $upcoming[0]['daysRemaining']);
    }

    public function test_404_for_missing_project(): void
    {
        $this->getJson('/api/projects/01912345-0000-7000-8000-000000000000/kpi')
            ->assertStatus(500); // InvalidArgumentException
    }

    public function test_reference_date_defaults_to_today(): void
    {
        $project = ProjectModel::factory()->create();
        $response = $this->getJson("/api/projects/{$project->id}/kpi")->assertOk();
        $this->assertSame(date('Y-m-d'), $response->json('data.referenceDate'));
    }
}
