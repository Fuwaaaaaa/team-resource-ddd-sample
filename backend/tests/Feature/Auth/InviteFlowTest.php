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

    public function test_accept_410_when_user_is_disabled(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'disabled-invitee@example.com',
            'password' => Hash::make('originalPassword!123'),
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subDay(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])
            ->assertStatus(410)
            ->assertJson(['error' => 'invite_user_disabled']);

        // token は消費されず、 password も書き換わらない (disable が復活していない)。
        $fresh = $user->fresh();
        $this->assertNotNull($fresh->invite_token);
        $this->assertNotNull($fresh->disabled_at);
        $this->assertTrue(Hash::check('originalPassword!123', $fresh->password));

        // 監査ログが記録されている。
        $log = AuditLog::query()
            ->where('event_type', 'InviteRejectedUserDisabled')
            ->where('aggregate_id', UserAggregateId::fromUserId($user->id))
            ->firstOrFail();
        $this->assertSame('user', $log->aggregate_type);
        $this->assertSame('user_disabled', $log->payload['reason']);
        $this->assertSame($user->id, $log->payload['userId']);
    }

    public function test_accept_410_is_repeatable_for_disabled_user(): void
    {
        $token = $this->makeToken();
        $user = User::factory()->create([
            'email' => 'disabled-twice@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subDay(),
        ]);

        // token を消費しないので、 2 回目も 404 ではなく 410 のままになる。
        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])->assertStatus(410);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])->assertStatus(410);

        // 拒否は毎回監査ログに残る (= disable 済 account への繰り返しアクセスのシグナル)。
        // 2 回の 410 に対し 2 行が記録される。
        $this->assertSame(2, AuditLog::query()
            ->where('event_type', 'InviteRejectedUserDisabled')
            ->where('aggregate_id', UserAggregateId::fromUserId($user->id))
            ->count());
    }

    public function test_show_410_when_user_is_disabled(): void
    {
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'disabled-show@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subDay(),
        ]);

        $this->getJson("/api/invite/{$token}")
            ->assertStatus(410)
            ->assertJson(['error' => 'invite_user_disabled']);
    }

    public function test_show_for_disabled_user_writes_no_audit_log(): void
    {
        // show は副作用無しの閲覧なので、 disabled でも監査ログは残さない (accept とは非対称)。
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'disabled-show-noaudit@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subDay(),
        ]);

        $this->getJson("/api/invite/{$token}")->assertStatus(410);

        $this->assertSame(0, AuditLog::query()
            ->where('event_type', 'InviteRejectedUserDisabled')
            ->count());
    }

    public function test_accept_404_when_token_expired_even_if_disabled(): void
    {
        // findValidByToken が isDisabled より先に走るので、 期限切れ + disabled は 410 でなく 404。
        // 404 経路なので監査ログも残らない。
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'expired-and-disabled@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->subHour(), // 期限切れ
            'disabled_at' => now()->subDay(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'mySecure!Password123',
            'password_confirmation' => 'mySecure!Password123',
        ])->assertStatus(404);

        $this->assertSame(0, AuditLog::query()
            ->where('event_type', 'InviteRejectedUserDisabled')
            ->count());
    }

    public function test_accept_422_on_invalid_password_for_disabled_user(): void
    {
        // validate() が disabled ガードより先に走るので、 短い password は 410 でなく 422。
        // バリデーション段で弾かれるため監査ログも残らない。
        $token = $this->makeToken();
        User::factory()->create([
            'email' => 'disabled-shortpw@example.com',
            'invite_token' => $token,
            'invite_token_expires_at' => now()->addHours(24),
            'disabled_at' => now()->subDay(),
        ]);

        $this->postJson("/api/invite/{$token}/accept", [
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->assertSame(0, AuditLog::query()
            ->where('event_type', 'InviteRejectedUserDisabled')
            ->count());
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
