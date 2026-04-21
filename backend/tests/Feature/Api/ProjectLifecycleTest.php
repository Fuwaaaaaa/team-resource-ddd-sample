<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ProjectLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_new_project_defaults_to_active(): void
    {
        $response = $this->postJson('/api/projects', ['name' => 'Alpha'])->assertCreated();
        $this->assertSame('active', $response->json('data.status'));
    }

    public function test_change_status_active_to_completed(): void
    {
        $created = $this->postJson('/api/projects', ['name' => 'Alpha'])->assertCreated();
        $id = $created->json('data.id');

        $response = $this->postJson("/api/projects/{$id}/status", ['status' => 'completed'])
            ->assertOk();
        $this->assertSame('completed', $response->json('data.status'));

        $this->assertSame(1, AuditLog::where('event_type', 'ProjectCompleted')->count());
    }

    public function test_completion_revokes_active_allocations(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();
        $created = $this->postJson('/api/projects', ['name' => 'Beta'])->assertCreated();
        $projectId = $created->json('data.id');

        $allocationId = (string) Str::uuid7();
        AllocationModel::create([
            'id' => $allocationId,
            'member_id' => $member->id,
            'project_id' => $projectId,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $this->postJson("/api/projects/{$projectId}/status", ['status' => 'completed'])->assertOk();

        $this->assertSame('revoked', AllocationModel::find($allocationId)->status);
    }

    public function test_invalid_transition_returns_500(): void
    {
        $created = $this->postJson('/api/projects', ['name' => 'Alpha'])->assertCreated();
        $id = $created->json('data.id');

        $this->postJson("/api/projects/{$id}/status", ['status' => 'completed'])->assertOk();

        // completed は terminal → active への遷移は不可（InvalidProjectStatusTransition → 500）
        $this->postJson("/api/projects/{$id}/status", ['status' => 'active'])->assertStatus(500);
    }

    public function test_allocation_to_completed_project_rejected(): void
    {
        $member = MemberModel::factory()->create();
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'completed']);

        $this->postJson('/api/allocations', [
            'memberId' => $member->id,
            'projectId' => $project->id,
            'skillId' => $skill->id,
            'allocationPercentage' => 30,
            'periodStart' => '2026-05-01',
            'periodEnd' => '2026-05-31',
        ])->assertStatus(500); // DomainException
    }

    public function test_status_validation_rejects_bogus_value(): void
    {
        $created = $this->postJson('/api/projects', ['name' => 'Alpha'])->assertCreated();
        $id = $created->json('data.id');

        $this->postJson("/api/projects/{$id}/status", ['status' => 'frozen'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_viewer_cannot_change_status(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $created = $this->postJson('/api/projects', ['name' => 'Alpha'])->assertCreated();
        $id = $created->json('data.id');

        $this->actingAs($viewer);
        $this->postJson("/api/projects/{$id}/status", ['status' => 'completed'])->assertForbidden();
    }
}
