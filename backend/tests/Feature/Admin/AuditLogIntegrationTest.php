<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_aggregate_type_user_filter_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs?aggregateType=user')
            ->assertOk();
    }

    public function test_audit_logs_appear_after_user_create_and_role_change(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']); // ensure non-last admin

        // Create
        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'audit-int@example.com',
                'role' => 'manager',
            ])
            ->assertCreated();

        $created = User::where('email', 'audit-int@example.com')->firstOrFail();

        // Role change
        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$created->id}/role", [
                'role' => 'viewer',
                'reason' => 'demotion',
                'expectedUpdatedAt' => $created->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertOk();

        $logs = $this->actingAs($admin)
            ->getJson('/api/audit-logs?aggregateType=user')
            ->assertOk()
            ->json('data');

        $eventTypes = array_column($logs, 'event_type');
        $this->assertContains('UserCreated', $eventTypes);
        $this->assertContains('UserRoleChanged', $eventTypes);
    }

    public function test_request_metadata_captured_on_create(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']); // last-admin guard

        $this->actingAs($admin)
            ->withServerVariables([
                'REMOTE_ADDR' => '203.0.113.42',
                'HTTP_USER_AGENT' => 'AuditLogTest/1.0',
            ])
            ->postJson('/api/admin/users', [
                'name' => 'IPCapture',
                'email' => 'ip-capture@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $log = AuditLog::query()
            ->where('event_type', 'UserCreated')
            ->orderByDesc('created_at')
            ->firstOrFail();

        $this->assertSame('203.0.113.42', $log->ip_address);
        $this->assertSame('AuditLogTest/1.0', $log->user_agent);
    }

    public function test_filters_by_date_range(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Insert 3 logs at known timestamps
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => '2026-04-01 10:00:00',
        ]);
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => '2026-04-15 10:00:00',
        ]);
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => '2026-04-30 10:00:00',
        ]);

        $logs = $this->actingAs($admin)
            ->getJson('/api/audit-logs?from=2026-04-10&to=2026-04-20')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $logs);
        $this->assertSame('2026-04-15T10:00:00.000000Z', $logs[0]['created_at']);
    }

    public function test_filters_by_user_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'manager']);

        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $other->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => now(),
        ]);

        $logs = $this->actingAs($admin)
            ->getJson("/api/audit-logs?userId={$other->id}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $logs);
        $this->assertSame($other->id, $logs[0]['user_id']);
    }

    public function test_aggregate_label_resolved_for_member_and_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $memberId = (string) Str::uuid7();
        $projectId = (string) Str::uuid7();

        \DB::table('members')->insert([
            'id' => $memberId,
            'name' => 'Alice',
            'standard_working_hours' => 40.0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \DB::table('projects')->insert([
            'id' => $projectId,
            'name' => 'Bravo Project',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'MemberCreated',
            'aggregate_type' => 'member',
            'aggregate_id' => $memberId,
            'payload' => [],
            'created_at' => now(),
        ]);
        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'ProjectActivated',
            'aggregate_type' => 'project',
            'aggregate_id' => $projectId,
            'payload' => [],
            'created_at' => now(),
        ]);

        $logs = $this->actingAs($admin)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->json('data');

        $byEvent = collect($logs)->keyBy('event_type');
        $this->assertSame('Alice', $byEvent['MemberCreated']['aggregate_label']);
        $this->assertSame('Bravo Project', $byEvent['ProjectActivated']['aggregate_label']);
    }

    public function test_aggregate_label_null_for_unresolvable_types(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'event_type' => 'AllocationCreated',
            'aggregate_type' => 'allocation',
            'aggregate_id' => (string) Str::uuid7(),
            'payload' => [],
            'created_at' => now(),
        ]);

        $logs = $this->actingAs($admin)
            ->getJson('/api/audit-logs')
            ->assertOk()
            ->json('data');

        $this->assertNull($logs[0]['aggregate_label']);
    }

    public function test_invalid_to_before_from_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs?from=2026-04-30&to=2026-04-01')
            ->assertStatus(422);
    }
}
