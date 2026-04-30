<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\UserInviteMail;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class UsersControllerCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_user_returns_invite_metadata_not_password(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Bob Brown',
                'email' => 'bob@example.com',
                'role' => 'manager',
            ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'inviteSentTo',
                'inviteExpiresAt',
                'inviteUrl',
            ])
            ->assertJsonPath('user.role', 'manager')
            ->assertJsonPath('inviteSentTo', 'bob@example.com')
            ->assertJsonMissing(['generatedPassword' => null]);

        $this->assertNull($response->json('generatedPassword'));

        // Email URL は `/invite/<64 char hex>` で終わる
        $this->assertMatchesRegularExpression(
            '#/invite/[0-9a-f]{64}$#',
            (string) $response->json('inviteUrl'),
        );

        Mail::assertSent(UserInviteMail::class, function (UserInviteMail $mail) {
            return $mail->hasTo('bob@example.com')
                && str_contains($mail->inviteUrl, '/invite/');
        });
    }

    public function test_initial_password_is_unguessable_so_user_cannot_login_until_accept(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Locked',
                'email' => 'locked@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $created = User::where('email', 'locked@example.com')->firstOrFail();

        // 招待 token が発行されている (24h 有効)
        $this->assertNotNull($created->invite_token);
        $this->assertSame(64, strlen((string) $created->invite_token));
        $this->assertNotNull($created->invite_token_expires_at);

        // 推測不能な password がランダムで入っているため、 accept 完了まで誰もログインできない。
        // 直接 password の値を assert することはできないが (hash 済)、
        // 既知の弱い password ('password' / 空) で Hash::check が通らないことを確認。
        $this->assertFalse(Hash::check('password', $created->password));
        $this->assertFalse(Hash::check('', $created->password));
    }

    public function test_emits_user_created_event_without_password_in_payload(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Eve',
                'email' => 'eve@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $log = AuditLog::query()
            ->where('event_type', 'UserCreated')
            ->latest('created_at')
            ->firstOrFail();

        $this->assertSame('user', $log->aggregate_type);
        $this->assertArrayHasKey('email', $log->payload);
        $this->assertArrayHasKey('role', $log->payload);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('generatedPassword', $log->payload);
        $this->assertArrayNotHasKey('invite_token', $log->payload);
    }

    public function test_email_unique_validation(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'taken@example.com',
                'role' => 'viewer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        Mail::assertNothingSent();
    }

    public function test_invalid_role_returns_422(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'role-test@example.com',
                'role' => 'superuser',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_invalid_email_format_returns_422(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'not-an-email',
                'role' => 'viewer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_name_required(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => '',
                'email' => 'noname@example.com',
                'role' => 'viewer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_manager_gets_403(): void
    {
        Mail::fake();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => '403@example.com',
                'role' => 'viewer',
            ])->assertForbidden();
    }

    public function test_response_includes_no_store_cache_header(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'X',
                'email' => 'cache@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
