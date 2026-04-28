<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UsersControllerListTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'createdAt', 'updatedAt']],
                'meta' => ['total', 'page', 'perPage', 'lastPage'],
            ]);
    }

    public function test_pagination_respects_per_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->count(15)->create();

        $response = $this->actingAs($admin)->getJson('/api/admin/users?perPage=5')->assertOk();
        $this->assertCount(5, $response->json('data'));
        $this->assertSame(5, $response->json('meta.perPage'));
    }

    public function test_search_filters_by_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['name' => 'Alice Smith']);
        User::factory()->create(['name' => 'Bob Brown']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?search=Alice')->assertOk();
        $names = array_column($response->json('data'), 'name');
        $this->assertContains('Alice Smith', $names);
        $this->assertNotContains('Bob Brown', $names);
    }

    public function test_search_filters_by_email_prefix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        User::factory()->create(['email' => 'unique-prefix@example.com']);

        $response = $this->actingAs($admin)->getJson('/api/admin/users?search=unique-prefix')->assertOk();
        $emails = array_column($response->json('data'), 'email');
        $this->assertContains('unique-prefix@example.com', $emails);
    }

    public function test_manager_gets_403(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $this->actingAs($manager)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_viewer_gets_403(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_per_page_max_is_validated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->getJson('/api/admin/users?perPage=999')
            ->assertStatus(422);
    }
}
