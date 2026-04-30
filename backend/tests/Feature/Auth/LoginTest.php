<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_cookie_then_login_returns_user_payload(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        // Sanctum の EnsureFrontendRequestsAreStateful は Referer/Origin の host を
        // sanctum.stateful と突合して判定する。.env.example が
        // SANCTUM_STATEFUL_DOMAINS=localhost:8080,127.0.0.1:8080 を要求するため、
        // bare 'localhost' ではなくポート付きで送る (production / CI smoke と一致)。
        $headers = ['Origin' => 'http://localhost:8080'];

        $csrfResponse = $this->withHeaders($headers)->get('/sanctum/csrf-cookie');
        $csrfResponse->assertNoContent();

        // 実ブラウザでは axios が XSRF-TOKEN Cookie を読み取り X-XSRF-TOKEN
        // ヘッダに転送する。テストでもその挙動を再現する。
        $xsrf = collect($csrfResponse->headers->getCookies())
            ->firstWhere(fn ($c) => $c->getName() === 'XSRF-TOKEN')
            ?->getValue();

        $response = $this->withHeaders([
            ...$headers,
            'X-XSRF-TOKEN' => $xsrf,
        ])->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonStructure(['id', 'name', 'email']);
        $this->assertSame('admin@example.com', $response->json('email'));
    }

    public function test_invalid_credentials_return_422(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user_when_authenticated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/me')
            ->assertOk()
            ->assertJson(['email' => $user->email]);
    }
}
