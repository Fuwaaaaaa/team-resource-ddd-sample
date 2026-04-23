<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\KpiSnapshotModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DashboardKpiTrendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_returns_snapshots_in_range_sorted_ascending(): void
    {
        // 3 日分のスナップショット + 範囲外 1 件
        $this->seedSnapshot('2026-04-18', 80.0);
        $this->seedSnapshot('2026-04-20', 75.0);
        $this->seedSnapshot('2026-04-22', 90.0);
        $this->seedSnapshot('2026-03-01', 50.0); // 範囲外

        $response = $this->getJson('/api/dashboard/kpi-trend?date=2026-04-22&days=7')
            ->assertOk();

        $this->assertSame('2026-04-22', $response->json('data.referenceDate'));
        $this->assertSame(7, $response->json('data.days'));

        $points = $response->json('data.points');
        $this->assertCount(3, $points);
        $this->assertSame('2026-04-18', $points[0]['date']);
        $this->assertSame('2026-04-20', $points[1]['date']);
        $this->assertSame('2026-04-22', $points[2]['date']);
        $this->assertSame(80.0, $points[0]['averageFulfillmentRate']);
    }

    public function test_empty_when_no_snapshots(): void
    {
        $response = $this->getJson('/api/dashboard/kpi-trend?date=2026-04-22&days=30')
            ->assertOk();

        $this->assertSame([], $response->json('data.points'));
    }

    public function test_default_days_is_30(): void
    {
        $this->seedSnapshot('2026-04-22', 50.0);

        $response = $this->getJson('/api/dashboard/kpi-trend?date=2026-04-22')
            ->assertOk();

        $this->assertSame(30, $response->json('data.days'));
    }

    public function test_rejects_invalid_days(): void
    {
        $this->getJson('/api/dashboard/kpi-trend?date=2026-04-22&days=15')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['days']);
    }

    public function test_date_is_required(): void
    {
        $this->getJson('/api/dashboard/kpi-trend')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_capture_command_upserts_snapshot_for_given_date(): void
    {
        $this->artisan('kpi:snapshot-capture', ['--date' => '2026-04-22'])
            ->assertSuccessful();

        $this->assertDatabaseCount('kpi_snapshots', 1);
        $this->assertSame('2026-04-22', KpiSnapshotModel::first()->snapshot_date->format('Y-m-d'));

        // 同日 2 回目: 置き換わるだけで行数は 1 のまま
        $this->artisan('kpi:snapshot-capture', ['--date' => '2026-04-22'])
            ->assertSuccessful();
        $this->assertDatabaseCount('kpi_snapshots', 1);
    }

    private function seedSnapshot(string $date, float $fulfillment): void
    {
        KpiSnapshotModel::create([
            'id' => (string) Str::uuid7(),
            'snapshot_date' => $date,
            'average_fulfillment_rate' => $fulfillment,
            'active_project_count' => 3,
            'overloaded_member_count' => 0,
            'upcoming_ends_this_week' => 1,
            'skill_gaps_total' => 2,
        ]);
    }
}
