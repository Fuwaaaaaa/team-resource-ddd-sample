<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Post-deploy smoke test for the admin route group. One assertion per endpoint.
 */
final class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_endpoints_smoke(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'manager']);

        $this->actingAs($admin)->getJson('/api/admin/users')->assertOk();

        $this->actingAs($admin)
            ->postJson('/api/admin/users', [
                'name' => 'Smoke',
                'email' => 'smoke@example.com',
                'role' => 'viewer',
            ])
            ->assertCreated();

        $this->actingAs($admin)
            ->patchJson("/api/admin/users/{$target->id}/role", [
                'role' => 'viewer',
                'reason' => 'smoke',
                'expectedUpdatedAt' => $target->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/admin/users/{$other->id}/reset-password")
            ->assertOk();
    }
}
