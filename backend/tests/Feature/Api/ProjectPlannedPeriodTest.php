<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\RequiredSkillModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * projects.planned_start_date / planned_end_date の往復、およびキャパシティ
 * フォレキャストの月次需要フィルタ (期間外バケットから除外) を検証。
 */
final class ProjectPlannedPeriodTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    // ---------- CRUD ----------

    public function test_create_project_with_planned_period(): void
    {
        $response = $this->postJson('/api/projects', [
            'name' => 'Alpha',
            'plannedStartDate' => '2026-05-01',
            'plannedEndDate' => '2026-07-31',
        ])->assertCreated();

        $this->assertSame('2026-05-01', $response->json('data.plannedStartDate'));
        $this->assertSame('2026-07-31', $response->json('data.plannedEndDate'));
    }

    public function test_create_without_period_leaves_null(): void
    {
        $response = $this->postJson('/api/projects', ['name' => 'Beta'])->assertCreated();

        $this->assertNull($response->json('data.plannedStartDate'));
        $this->assertNull($response->json('data.plannedEndDate'));
    }

    public function test_partial_period_is_rejected(): void
    {
        // start のみ → required_with で弾かれる
        $this->postJson('/api/projects', [
            'name' => 'Gamma',
            'plannedStartDate' => '2026-05-01',
        ])->assertStatus(422)->assertJsonValidationErrors(['plannedEndDate']);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $this->postJson('/api/projects', [
            'name' => 'Delta',
            'plannedStartDate' => '2026-05-01',
            'plannedEndDate' => '2026-04-30',
        ])->assertStatus(422)->assertJsonValidationErrors(['plannedEndDate']);
    }

    public function test_update_can_set_and_clear_period(): void
    {
        $created = $this->postJson('/api/projects', ['name' => 'Eta'])->assertCreated();
        $id = $created->json('data.id');

        // 設定
        $this->patchJson("/api/projects/{$id}", [
            'name' => 'Eta',
            'plannedStartDate' => '2026-06-01',
            'plannedEndDate' => '2026-09-30',
        ])->assertOk()
            ->assertJsonPath('data.plannedStartDate', '2026-06-01')
            ->assertJsonPath('data.plannedEndDate', '2026-09-30');

        // クリア (両方 null で送る)
        $this->patchJson("/api/projects/{$id}", [
            'name' => 'Eta',
            'plannedStartDate' => null,
            'plannedEndDate' => null,
        ])->assertOk()
            ->assertJsonPath('data.plannedStartDate', null)
            ->assertJsonPath('data.plannedEndDate', null);
    }

    // ---------- CapacityForecast 統合 ----------

    public function test_capacity_forecast_excludes_projects_outside_month(): void
    {
        $skill = SkillModel::factory()->create(['name' => 'Go']);

        // 2026-05 にだけ存在するプロジェクト (6 月以降は demand ゼロ)
        $p = ProjectModel::factory()->create([
            'status' => 'active',
            'planned_start_date' => '2026-05-01',
            'planned_end_date' => '2026-05-31',
        ]);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 2,
        ]);

        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=3')
            ->assertOk();

        $buckets = $response->json('data.buckets');
        $this->assertCount(3, $buckets);
        // 5 月は需要あり
        $this->assertSame(2, $buckets[0]['skills'][0]['demandHeadcount']);
        // 6 月 / 7 月は期間外なので skills 空
        $this->assertSame([], $buckets[1]['skills']);
        $this->assertSame([], $buckets[2]['skills']);
    }

    public function test_capacity_forecast_includes_projects_without_period_in_all_buckets(): void
    {
        $skill = SkillModel::factory()->create(['name' => 'Rust']);

        // 期間未設定 → 既存の後方互換動作 (全バケットに demand)
        $p = ProjectModel::factory()->create([
            'status' => 'active',
            'planned_start_date' => null,
            'planned_end_date' => null,
        ]);
        RequiredSkillModel::create([
            'id' => (string) Str::uuid7(),
            'project_id' => $p->id,
            'skill_id' => $skill->id,
            'required_proficiency' => 3,
            'headcount' => 1,
        ]);

        $response = $this->getJson('/api/dashboard/capacity-forecast?date=2026-05-01&months=3')
            ->assertOk();

        foreach ($response->json('data.buckets') as $bucket) {
            $this->assertSame(1, $bucket['skills'][0]['demandHeadcount']);
        }
    }
}
