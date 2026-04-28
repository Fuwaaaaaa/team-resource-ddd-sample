<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuditLogIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_aggregateType_user_filter_accepted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->getJson('/api/audit-logs?aggregateType=user')
            ->assertOk();
    }

    public function test_audit_logs_appear_after_user_create_and_role_change(): void
    {
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
}
