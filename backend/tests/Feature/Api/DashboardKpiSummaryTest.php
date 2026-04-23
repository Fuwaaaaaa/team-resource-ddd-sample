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

final class DashboardKpiSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_empty_state_returns_100_fulfillment_and_zeros(): void
    {
        $response = $this->getJson('/api/dashboard/kpi-summary?date=2026-05-15')
            ->assertOk();

        $this->assertSame(100.0, $response->json('data.averageFulfillmentRate'));
        $this->assertSame(0, $response->json('data.activeProjectCount'));
        $this->assertSame(0, $response->json('data.overloadedMemberCount'));
        $this->assertSame(0, $response->json('data.upcomingEndsThisWeek'));
        $this->assertSame(0, $response->json('data.skillGapsTotal'));
        $this->assertSame('2026-05-15', $response->json('data.referenceDate'));
    }

    public function test_aggregates_fulfillment_across_eligible_projects(): void
    {
        $skill = SkillModel::factory()->create();

        // P1: 完全充足 (required=1, qualified=1 → 100%)
        $p1 = ProjectModel::factory()->create(['status' => 'active']);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $p1->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 1,
        ]);
        $m1 = MemberModel::factory()->create();
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m1->id,
            'skill_id' => $skill->id,
            'proficiency' => 4,
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m1->id,
            'project_id' => $p1->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        // P2: 半分充足 (required=2, qualified=1 → 50%)
        $p2 = ProjectModel::factory()->create(['status' => 'active']);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $p2->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 2,
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $m1->id,
            'project_id' => $p2->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 30,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        // P3: completed は集計対象外
        $p3 = ProjectModel::factory()->create(['status' => 'completed']);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $p3->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 10, // もし集計に入ったら大きな gap として拾われる
        ]);

        $response = $this->getJson('/api/dashboard/kpi-summary?date=2026-05-15')
            ->assertOk();

        // (100 + 50) / 2 = 75.0
        $this->assertEqualsWithDelta(75.0, $response->json('data.averageFulfillmentRate'), 0.1);
        $this->assertSame(2, $response->json('data.activeProjectCount')); // completed を除く
        $this->assertSame(1, $response->json('data.skillGapsTotal'));     // P2 のギャップ 1 のみ
    }

    public function test_overloaded_member_count(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $member = MemberModel::factory()->create();

        // 80 + 50 = 130% → overloaded
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 80,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
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

        $response = $this->getJson('/api/dashboard/kpi-summary?date=2026-05-15')
            ->assertOk();

        $this->assertSame(1, $response->json('data.overloadedMemberCount'));
    }

    public function test_upcoming_ends_within_7_days_only(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $member = MemberModel::factory()->create();

        // 5 日後終了 → カウント
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 40,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-20',
            'status' => 'active',
        ]);
        // 20 日後終了 → 7 日範囲外
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 30,
            'period_start' => '2026-05-01',
            'period_end' => '2026-06-05',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/dashboard/kpi-summary?date=2026-05-15')
            ->assertOk();

        $this->assertSame(1, $response->json('data.upcomingEndsThisWeek'));
    }

    public function test_date_is_required(): void
    {
        $this->getJson('/api/dashboard/kpi-summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_rejects_invalid_date_format(): void
    {
        $this->getJson('/api/dashboard/kpi-summary?date=2026/05/15')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }
}
