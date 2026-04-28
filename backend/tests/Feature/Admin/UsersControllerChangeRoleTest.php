<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UsersControllerChangeRoleTest extends TestCase
{
    use RefreshDatabase;

    private function expectedAt(User $user): string
    {
        return $user->updated_at->format('Y-m-d H:i:s');
    }

    public function test_admin_changes_role_and_emits_event(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // need >=2 admins so the demote is allowed
        User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'viewer',
                'reason' => '降格テスト',
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertOk()
            ->assertJsonPath('user.role', 'viewer');

        $log = AuditLog::query()
            ->where('event_type', 'UserRoleChanged')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('user', $log->aggregate_type);
        $this->assertSame('manager', $log->payload['from']);
        $this->assertSame('viewer', $log->payload['to']);
        $this->assertSame('降格テスト', $log->payload['reason']);
    }

    public function test_unknown_user_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patchJson('/api/admin/users/9999999/role', [
                'role' => 'viewer',
                'reason' => 'x',
                'expectedUpdatedAt' => '2026-01-01 00:00:00',
            ])
            ->assertNotFound();
    }

    public function test_invalid_role_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'superuser',
                'reason' => 'x',
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_reason_required_returns_422(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'viewer',
                'reason' => '',
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_reason_max_200(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'viewer',
                'reason' => str_repeat('a', 201),
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_changing_own_role_returns_422_cannot_change_self(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$admin->id}/role", [
                'role' => 'manager',
                'reason' => '自分降格',
                'expectedUpdatedAt' => $this->expectedAt($admin),
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_change_self');
    }

    /**
     * Logical "last admin lock" test using the handler directly.
     * From HTTP (role:admin middleware) the actor is always an admin, so when
     * count==1 the lone admin IS the actor → self-check fires first. The lock
     * itself defends against parallel races between 2 admins demoting each other;
     * we exercise it via the handler with explicit IDs.
     */
    public function test_last_admin_lock_via_handler(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // No other admins exist.

        /** @var \App\Application\Admin\Commands\ChangeUserRoleHandler $handler */
        $handler = app(\App\Application\Admin\Commands\ChangeUserRoleHandler::class);

        // Pretend a different admin (id=999) is demoting the only real admin.
        $this->expectException(\App\Application\Admin\Exceptions\LastAdminLockException::class);
        $handler->handle(new \App\Application\Admin\Commands\ChangeUserRoleCommand(
            targetUserId: $admin->id,
            actorUserId: 999, // different from target — bypasses self-check
            newRole: 'manager',
            reason: 'demote-last',
            expectedUpdatedAt: $this->expectedAt($admin),
        ));
    }

    public function test_409_on_occ_mismatch(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'viewer',
                'reason' => 'change',
                'expectedUpdatedAt' => '2000-01-01 00:00:00', // stale
            ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'occ_mismatch');
    }

    public function test_idempotent_when_role_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'manager', // same as current
                'reason' => 'no-op',
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertOk()
            ->assertJsonPath('user.role', 'manager');

        // No new audit log row for an idempotent no-op
        $this->assertSame(0, AuditLog::query()->where('event_type', 'UserRoleChanged')->count());
    }

    public function test_manager_gets_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($manager)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'admin',
                'reason' => 'sneaky',
                'expectedUpdatedAt' => $this->expectedAt($target),
            ])
            ->assertForbidden();
    }
}
