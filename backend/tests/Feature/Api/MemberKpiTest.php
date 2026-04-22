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

final class MemberKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_utilization_is_sum_of_active_allocation_percentages(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();
        $p1 = ProjectModel::factory()->create();
        $p2 = ProjectModel::factory()->create();

        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p1->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 60,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p2->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 30,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/members/{$member->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        $this->assertSame(90, $response->json('data.currentUtilization'));
        $this->assertSame(10, $response->json('data.remainingCapacity'));
        $this->assertFalse($response->json('data.isOverloaded'));
        $this->assertSame(2, $response->json('data.activeAllocationCount'));
    }

    public function test_overloaded_clamps_remaining_capacity_to_zero(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();
        $p = ProjectModel::factory()->create();

        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 80,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/members/{$member->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        $this->assertSame(130, $response->json('data.currentUtilization'));
        $this->assertSame(0, $response->json('data.remainingCapacity'));
        $this->assertTrue($response->json('data.isOverloaded'));
    }

    public function test_upcoming_ends_within_30_days(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();
        $p = ProjectModel::factory()->create();

        // 終了 10 日後 → upcoming
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 40,
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-25',
            'status' => 'active',
        ]);
        // 終了 60 日後 → upcoming ではない
        AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 40,
            'period_start' => '2026-04-01',
            'period_end' => '2026-07-15',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/members/{$member->id}/kpi?referenceDate=2026-05-15")
            ->assertOk();

        $upcoming = $response->json('data.upcomingEnds');
        $this->assertCount(1, $upcoming);
        $this->assertSame(10, $upcoming[0]['daysRemaining']);
    }

    public function test_skills_are_sorted_by_proficiency_descending(): void
    {
        $member = MemberModel::factory()->create();
        $s1 = SkillModel::factory()->create(['name' => 'Go']);
        $s2 = SkillModel::factory()->create(['name' => 'Python']);
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $s1->id,
            'proficiency' => 3,
        ]);
        MemberSkillModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'skill_id' => $s2->id,
            'proficiency' => 5,
        ]);

        $response = $this->getJson("/api/members/{$member->id}/kpi")
            ->assertOk();

        $skills = $response->json('data.skills');
        $this->assertCount(2, $skills);
        $this->assertSame(5, $skills[0]['proficiency']);
        $this->assertSame('Python', $skills[0]['skillName']);
        $this->assertSame(3, $skills[1]['proficiency']);
    }

    public function test_404_for_missing_member(): void
    {
        $this->getJson('/api/members/01912345-0000-7000-8000-000000000000/kpi')
            ->assertStatus(500); // InvalidArgumentException (Project KPI と同じ挙動に揃える)
    }

    public function test_reference_date_defaults_to_today(): void
    {
        $member = MemberModel::factory()->create();

        $response = $this->getJson("/api/members/{$member->id}/kpi")->assertOk();

        $this->assertSame(date('Y-m-d'), $response->json('data.referenceDate'));
        $this->assertSame(0, $response->json('data.currentUtilization'));
        $this->assertSame(100, $response->json('data.remainingCapacity'));
    }
}
