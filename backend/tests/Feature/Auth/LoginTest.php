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

        // Sanctum の EnsureFrontendRequestsAreStateful は Referer/Origin を
        // sanctum.stateful (localhost 等) と突合して判定するため、
        // テストでは stateful 一覧にマッチする固定値を明示する
        $headers = ['Origin' => 'http://localhost'];

        $this->withHeaders($headers)->get('/sanctum/csrf-cookie')->assertNoContent();

        $response = $this->withHeaders($headers)->postJson('/api/login', [
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
