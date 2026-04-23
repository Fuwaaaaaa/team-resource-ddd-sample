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

final class DashboardCapacityForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_empty_state_returns_zero_buckets_with_default_months(): void
    {
        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01')
            ->assertOk();

        $this->assertSame('2026-05-01', $response->json('data.referenceDate'));
        $this->assertSame(6, $response->json('data.monthsAhead'));
        $this->assertCount(6, $response->json('data.buckets'));
        // Each bucket has no skills since no members / projects exist
        foreach ($response->json('data.buckets') as $bucket) {
            $this->assertSame([], $bucket['skills']);
        }
    }

    public function test_aggregates_demand_and_supply_per_month(): void
    {
        $skill = SkillModel::factory()->create(['name' => 'PHP']);

        // Project active全体期間にかかる — 2026-05〜2026-07 まで3名要求
        $project = ProjectModel::factory()->create(['status' => 'active']);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 3,
        ]);

        // メンバー1名: このスキル熟練度4、5月のみ50%アサイン → 5月は残50%、6-7月は残100%
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

        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=3')
            ->assertOk();

        $buckets = $response->json('data.buckets');
        $this->assertCount(3, $buckets);
        $this->assertSame('2026-05', $buckets[0]['month']);
        $this->assertSame('2026-06', $buckets[1]['month']);
        $this->assertSame('2026-07', $buckets[2]['month']);

        // 5月: 需要3名, 供給=0.5名換算 → gap=-2.5 → critical
        $may = $buckets[0]['skills'][0];
        $this->assertSame($skill->id, $may['skillId']);
        $this->assertSame('PHP', $may['skillName']);
        $this->assertSame(3, $may['demandHeadcount']);
        $this->assertEqualsWithDelta(0.5, $may['supplyHeadcountEquivalent'], 0.01);
        $this->assertEqualsWithDelta(-2.5, $may['gap'], 0.01);
        $this->assertSame('critical', $may['severity']);

        // 6月: アサイン終了したので供給1.0、需要3 → gap=-2 → critical
        $jun = $buckets[1]['skills'][0];
        $this->assertEqualsWithDelta(1.0, $jun['supplyHeadcountEquivalent'], 0.01);
        $this->assertEqualsWithDelta(-2.0, $jun['gap'], 0.01);
    }

    public function test_revoked_allocations_do_not_reduce_supply(): void
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
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
        // revoked は供給を消費しない
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 80,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'revoked',
        ]);

        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=1')
            ->assertOk();

        $may = $response->json('data.buckets.0.skills.0');
        $this->assertEqualsWithDelta(1.0, $may['supplyHeadcountEquivalent'], 0.01);
    }

    public function test_completed_projects_excluded_from_demand(): void
    {
        $skill = SkillModel::factory()->create();

        $completed = ProjectModel::factory()->create(['status' => 'completed']);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $completed->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 99,
        ]);

        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=1')
            ->assertOk();

        $this->assertSame([], $response->json('data.buckets.0.skills'));
    }

    public function test_months_out_of_range_is_rejected(): void
    {
        $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);

        $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=13')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_date_is_required(): void
    {
        $this->getJson('/api/dashboard/capacity-forecast')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }
}
