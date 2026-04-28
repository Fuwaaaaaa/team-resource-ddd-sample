<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UsersControllerResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_resets_other_user_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'generatedPassword', 'requiresRelogin']);

        $generated = (string) $response->json('generatedPassword');
        $this->assertSame(16, strlen($generated));
        $this->assertFalse((bool) $response->json('requiresRelogin'));

        $target->refresh();
        $this->assertTrue(Hash::check($generated, $target->password));
    }

    public function test_emits_password_reset_event_with_empty_payload(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $log = AuditLog::query()
            ->where('event_type', 'UserPasswordReset')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('user', $log->aggregate_type);
        // Payload must contain ONLY userId — no password, no hash.
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('generatedPassword', $log->payload);
        $this->assertArrayHasKey('userId', $log->payload);
        $this->assertSame($target->id, $log->payload['userId']);
    }

    public function test_deletes_target_user_sanctum_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);
        $target->createToken('test-token');
        $this->assertSame(1, $target->tokens()->count());

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $this->assertSame(0, $target->fresh()->tokens()->count());
    }

    public function test_deletes_target_user_database_sessions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);

        DB::table('sessions')->insert([
            'id' => 'sess-1',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'payload' => '',
            'last_activity' => time(),
        ]);
        $this->assertSame(1, DB::table('sessions')->where('user_id', $target->id)->count());

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->id)->count());
    }

    public function test_self_reset_returns_requires_relogin_true(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$admin->id}/reset-password")
            ->assertOk()
            ->assertJsonPath('requiresRelogin', true);
    }

    public function test_unknown_user_returns_404(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users/9999999/reset-password')
            ->assertNotFound();
    }

    public function test_manager_gets_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($manager)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertForbidden();
    }

    public function test_response_includes_no_store_cache_header(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
