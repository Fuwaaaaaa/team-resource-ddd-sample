<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class UsersControllerCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_user_returns_generated_password(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Bob Brown',
                'email' => 'bob@example.com',
                'role' => 'manager',
            ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'role'], 'generatedPassword'])
            ->assertJsonPath('user.role', 'manager');

        $this->assertSame(16, strlen((string) $response->json('generatedPassword')));
    }

    public function test_password_persisted_hashed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Bob',
                'email' => 'bob-hash@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $generated = (string) $response->json('generatedPassword');
        $created = User::where('email', 'bob-hash@example.com')->firstOrFail();
        $this->assertNotSame($generated, $created->password); // not stored in plaintext
        $this->assertTrue(Hash::check($generated, $created->password));
    }

    public function test_emits_user_created_event_without_password_in_payload(): void
    {
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
    }

    public function test_email_unique_validation(): void
    {
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
    }

    public function test_invalid_role_returns_422(): void
    {
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
