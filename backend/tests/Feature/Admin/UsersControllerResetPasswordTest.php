<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\UserInviteMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * 招待リンク再発行フロー (TODO-22) 移行後:
 *   POST /api/admin/users/{id}/reset-password は 16 文字 password を返さず、
 *   24h invite token を発行 + UserInviteMail 送信 + 既存セッション全失効を行う。
 */
final class UsersControllerResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_admin_reset_returns_invite_url_and_expiry_not_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager', 'email' => 'target@example.com']);

        $response = $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email', 'role'], 'inviteUrl', 'inviteExpiresAt', 'requiresRelogin'])
            ->assertJsonMissing(['generatedPassword' => null]);

        // 旧フローの 16 文字 password は返却されない
        $this->assertNull($response->json('generatedPassword'));
        $this->assertNull($response->json('password'));

        // invite URL は /invite/<64 hex>
        $this->assertMatchesRegularExpression(
            '#/invite/[0-9a-f]{64}$#',
            (string) $response->json('inviteUrl'),
        );

        $this->assertFalse((bool) $response->json('requiresRelogin'));
    }

    public function test_admin_reset_persists_invite_token_and_24h_expiry(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $target->refresh();
        $this->assertNotNull($target->invite_token);
        $this->assertSame(64, strlen((string) $target->invite_token));
        $this->assertNotNull($target->invite_token_expires_at);

        // 24h ± 5 秒で発行されている
        $expiresIn = $target->invite_token_expires_at->getTimestamp() - now()->getTimestamp();
        $this->assertGreaterThan(24 * 3600 - 5, $expiresIn);
        $this->assertLessThanOrEqual(24 * 3600 + 5, $expiresIn);
    }

    public function test_admin_reset_invalidates_old_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // 既知の password で作成
        $target = User::factory()->create([
            'role' => 'viewer',
            'password' => Hash::make('OldPasswordKnown123!'),
        ]);
        $oldHash = $target->password;

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        $target->refresh();
        // password hash は元の値から差し替わっている
        $this->assertNotSame($oldHash, $target->password);
        // 旧 password ではログインできなくなっている
        $this->assertFalse(Hash::check('OldPasswordKnown123!', $target->password));
    }

    public function test_admin_reset_sends_invite_mail_to_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'viewer', 'email' => 'reset-target@example.com']);

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertOk();

        Mail::assertSent(UserInviteMail::class, function (UserInviteMail $mail) {
            return $mail->hasTo('reset-target@example.com')
                && str_contains($mail->inviteUrl, '/invite/');
        });
    }

    public function test_emits_password_reset_event_with_user_id_only(): void
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
        // payload には password / token を絶対に含めない
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('generatedPassword', $log->payload);
        $this->assertArrayNotHasKey('invite_token', $log->payload);
        $this->assertArrayNotHasKey('inviteUrl', $log->payload);
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

        Mail::assertNothingSent();
    }

    public function test_manager_gets_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $target = User::factory()->create(['role' => 'viewer']);

        $this->actingAs($manager)
            ->postJson("/api/admin/users/{$target->id}/reset-password")
            ->assertForbidden();

        Mail::assertNothingSent();
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
