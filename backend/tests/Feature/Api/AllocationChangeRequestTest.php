<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationChangeRequestModel;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AllocationChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    // ---------- 認可マトリクス (submit) ----------

    public function test_viewer_cannot_submit_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
        $this->postJson('/api/allocation-requests', $this->createPayload())
            ->assertForbidden();
    }

    public function test_manager_can_submit_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->postJson('/api/allocation-requests', $this->createPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.type', 'create_allocation');
    }

    public function test_admin_can_submit_request(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->postJson('/api/allocation-requests', $this->createPayload())
            ->assertCreated();
    }

    // ---------- バリデーション ----------

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $payload = $this->createPayload();
        $payload['type'] = 'delete_project';
        $this->postJson('/api/allocation-requests', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_payload_requires_all_fields(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->postJson('/api/allocation-requests', [
            'type' => 'create_allocation',
            'payload' => ['memberId' => (string) Str::uuid7()], // 不完全
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payload.projectId', 'payload.skillId', 'payload.allocationPercentage']);
    }

    // ---------- 認可マトリクス (approve/reject) ----------

    public function test_manager_cannot_approve(): void
    {
        $request = $this->submitAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->actingAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->postJson("/api/allocation-requests/{$request->id}/approve", [])
            ->assertForbidden();
    }

    public function test_viewer_cannot_approve(): void
    {
        $request = $this->submitAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->actingAs(User::factory()->create(['role' => UserRole::Viewer]));
        $this->postJson("/api/allocation-requests/{$request->id}/approve", [])
            ->assertForbidden();
    }

    public function test_admin_can_approve_and_creates_allocation(): void
    {
        $request = $this->submitAs(User::factory()->create(['role' => UserRole::Manager]));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $response = $this->postJson("/api/allocation-requests/{$request->id}/approve", ['note' => 'LGTM'])
            ->assertOk();

        $response->assertJsonPath('data.status', 'approved');
        $response->assertJsonPath('data.decidedBy', $admin->id);
        $response->assertJsonPath('data.decisionNote', 'LGTM');

        $resultingId = $response->json('data.resultingAllocationId');
        $this->assertNotNull($resultingId);

        // 実 Allocation が作成されていること
        $this->assertDatabaseHas('resource_allocations', [
            'id' => $resultingId,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_reject(): void
    {
        $request = $this->submitAs(User::factory()->create(['role' => UserRole::Manager]));
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);

        $response = $this->postJson("/api/allocation-requests/{$request->id}/reject", ['note' => 'ΟΟ 理由'])
            ->assertOk();

        $response->assertJsonPath('data.status', 'rejected');
        $response->assertJsonPath('data.decisionNote', 'ΟΟ 理由');
        $this->assertDatabaseCount('resource_allocations', 0); // 副作用なし
    }

    // ---------- 不正な状態遷移 ----------

    public function test_approving_already_decided_request_is_rejected(): void
    {
        $request = $this->submitAs(User::factory()->create(['role' => UserRole::Manager]));
        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));

        $this->postJson("/api/allocation-requests/{$request->id}/approve", [])->assertOk();
        // 2 回目は 500 (DomainException)
        $this->postJson("/api/allocation-requests/{$request->id}/approve", [])
            ->assertStatus(500);
    }

    // ---------- List ----------

    public function test_admin_sees_all_requests(): void
    {
        $m1 = User::factory()->create(['role' => UserRole::Manager]);
        $m2 = User::factory()->create(['role' => UserRole::Manager]);
        $this->submitAs($m1);
        $this->submitAs($m2);

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->getJson('/api/allocation-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_manager_only_sees_own_requests(): void
    {
        $m1 = User::factory()->create(['role' => UserRole::Manager]);
        $m2 = User::factory()->create(['role' => UserRole::Manager]);
        $this->submitAs($m1);
        $this->submitAs($m2);

        $this->actingAs($m1);
        $this->getJson('/api/allocation-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.requestedBy', $m1->id);
    }

    public function test_list_filters_by_status(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $r1 = $this->submitAs($manager);
        $r2 = $this->submitAs($manager);

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin);
        $this->postJson("/api/allocation-requests/{$r1->id}/approve", [])->assertOk();

        $this->getJson('/api/allocation-requests?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $r2->id);
    }

    // ---------- Revoke type ----------

    public function test_revoke_request_is_approved_and_revokes_existing_allocation(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager);

        // 既存 allocation を直接作成 (manager は直接作成も可)
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $member = MemberModel::factory()->create();
        $alloc = AllocationModel::create([
            'id' => (string) Str::uuid7(),
            'member_id' => $member->id,
            'project_id' => $project->id,
            'skill_id' => $skill->id,
            'allocation_percentage' => 50,
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'active',
        ]);

        $submit = $this->postJson('/api/allocation-requests', [
            'type' => 'revoke_allocation',
            'payload' => ['allocationId' => $alloc->id],
            'reason' => 'not needed',
        ])->assertCreated();
        $requestId = $submit->json('data.id');

        $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
        $this->postJson("/api/allocation-requests/{$requestId}/approve", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('resource_allocations', [
            'id' => $alloc->id,
            'status' => 'revoked',
        ]);
    }

    // ---------- 副作用: 監査ログ & 通知 ----------

    public function test_submission_records_audit_log_and_notifies(): void
    {
        // 通知受信者として admin を 1 人用意
        User::factory()->create(['role' => UserRole::Admin]);

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager);
        $this->postJson('/api/allocation-requests', $this->createPayload())->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'AllocationChangeRequestSubmitted',
            'aggregate_type' => 'allocation_change_request',
        ]);
        // admin + manager (上記 2 人) へ通知配信
        $this->assertSame(
            2,
            Notification::where('type', 'AllocationChangeRequestSubmitted')->count(),
        );
        // AuditLog が少なくとも 1 件存在
        $this->assertGreaterThanOrEqual(1, AuditLog::where('event_type', 'AllocationChangeRequestSubmitted')->count());
    }

    // ===== helpers =====

    /** @return array<string, mixed> */
    private function createPayload(): array
    {
        $skill = SkillModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $member = MemberModel::factory()->create();

        return [
            'type' => 'create_allocation',
            'payload' => [
                'memberId' => $member->id,
                'projectId' => $project->id,
                'skillId' => $skill->id,
                'allocationPercentage' => 40,
                'periodStart' => '2026-05-01',
                'periodEnd' => '2026-05-31',
            ],
            'reason' => 'テスト',
        ];
    }

    private function submitAs(User $user): AllocationChangeRequestModel
    {
        $this->actingAs($user);
        $response = $this->postJson('/api/allocation-requests', $this->createPayload())->assertCreated();

        return AllocationChangeRequestModel::findOrFail($response->json('data.id'));
    }
}
