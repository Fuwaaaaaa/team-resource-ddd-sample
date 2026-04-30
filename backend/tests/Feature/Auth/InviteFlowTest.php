<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Domain\Authorization\UserAggregateId;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_user_info_for_valid_token(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'role' => 'manager',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
        ]);

        $this->getJson("/api/invite/{$token}")
            ->assertOk()
            ->assertJson([
                'name' => 'Invitee',
                'email' => 'invitee@example.com',
                'role' => 'manager',
            ]);
    }

    public function test_show_404_for_invalid_token(): void
    {
        $this->getJson('/api/invite/'.$this->makeToken())
            ->assertStatus(404)
            ->assertJson(['error' => 'invite_invalid_or_expired']);
    }

    public function test_show_404_for_expired_token(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'expired@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->subHour(), // 過去
        ]);

        $this->getJson("/api/invite/{$token}")
            ->assertStatus(404);
    }

    public function test_accept_sets_password_and_clears_token(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'accepter@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'email' => 'accepter@example.com']);

        $fresh = $user->fresh();
        $this->assertNull($fresh->invite_token);
        $this->assertNull($fresh->invite_token_expires_at);
        $this->assertTrue(Hash::check('mySecure!Password123', $fresh->password));
    }

    public function test_accept_404_for_invalid_token(): void
    {
        $this->postJson('/api/invite/'.$this->makeToken().'/accept', [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])->assertStatus(404);
    }

    public function test_accept_404_for_expired_token(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'late@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])->assertStatus(404);
    }

    public function test_accept_rejects_short_password(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'shortpw@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_accept_rejects_mismatched_confirmation(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'mismatch@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'differentPassword!Xyz',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_accept_consumes_token_so_second_use_fails(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'once@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'firstPassword!1234',
            'password_confirmation' => 'firstPassword!1234',
        ])->assertOk();

        // 2 回目は token が消費済なので 404
        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'secondPassword!1234',
            'password_confirmation' => 'secondPassword!1234',
        ])->assertStatus(404);
    }

    public function test_show_410_when_user_already_disabled(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'disabled-show@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subMinute(),
        ]);

        $this->getJson("/api/invite/{$token}")
            ->assertStatus(410)
            ->assertJson(['error' => 'invite_disabled']);

        // Token はまだそこにある (consumed されていない) — disable された側を unblock するのは
        // admin の re-enable + 必要なら invite 再発行で運用する。
        $fresh = $user->fresh();
        $this->assertSame($token, $fresh->invite_token);
    }

    public function test_accept_410_when_user_already_disabled(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'disabled-accept@example.com',
            'password' => Hash::make('original-password-1234'),
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'newPassword!StrongOne',
            'password_confirmation' => 'newPassword!StrongOne',
        ])
            ->assertStatus(410)
            ->assertJson(['error' => 'invite_disabled']);

        // Password は書き換わっていない / token も生きたまま (disable のままなので login も不可)。
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('original-password-1234', $fresh->password));
        $this->assertSame($token, $fresh->invite_token);
    }

    public function test_disabled_invite_attempt_writes_audit_log(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'audited@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subMinute(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'evilHijacker!1234',
            'password_confirmation' => 'evilHijacker!1234',
        ])->assertStatus(410);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'InviteRejectedDisabledUser',
            'aggregate_type' => 'user',
            'aggregate_id' => UserAggregateId::fromUserId($user->id),
        ]);

        $log = AuditLog::query()
            ->where('event_type', 'InviteRejectedDisabledUser')
            ->latest('created_at')
            ->first();
        $this->assertNotNull($log);
        $this->assertNull($log->user_id, 'public endpoint なので actor は null');
        $this->assertSame('accept', $log->payload['phase']);
        $this->assertSame('user_disabled', $log->payload['reason']);
        $this->assertSame('audited@example.com', $log->payload['email']);
    }

    public function test_invite_endpoints_are_public_no_auth_required(): void
    {
        // sanctum 認証 cookie 無しでもアクセスできることの担保 (404 になるが 401 にはならない)
        $this->getJson('/api/invite/'.$this->makeToken())
            ->assertStatus(404)
            ->assertJsonMissing(['message' => 'Unauthenticated.']);
    }

    private function makeToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 hex chars
    }
}
