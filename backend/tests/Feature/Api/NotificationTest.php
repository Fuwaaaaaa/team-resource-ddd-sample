<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_index_returns_empty_on_fresh_db(): void
    {
        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.unreadCount', 0);
    }

    public function test_allocation_created_fans_out_to_admins(): void
    {
        // 自分 + 追加の admin を用意 (既に setUp で admin 1 名)
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'manager']);
        User::factory()->create(['role' => 'viewer']); // viewer には配信しない

        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create();
        $skill = SkillModel::factory()->create();

        $this->postJson('/api/allocations', [
            'memberId' => $member->id,
            'projectId' => $project->id,
            'skillId' => $skill->id,
            'allocationPercentage' => 40,
            'periodStart' => '2026-06-01',
            'periodEnd' => '2026-06-30',
        ])->assertCreated();

        // admin(setUp) + admin + manager = 3 名、viewer を除く
        $this->assertSame(3, Notification::query()->where('type', 'AllocationCreated')->count());
    }

    public function test_inbox_only_shows_own_notifications(): void
    {
        $other = User::factory()->create();
        Notification::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $other->id,
            'type' => 'AllocationCreated',
            'title' => 'Other',
            'body' => '',
            'payload' => null,
            'created_at' => now(),
        ]);

        $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_mark_read_updates_single_notification(): void
    {
        /** @var User $user */
        $user = auth()->user();
        $n = Notification::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'type' => 'AllocationCreated',
            'title' => 'Test',
            'body' => 'body',
            'payload' => null,
            'created_at' => now(),
        ]);

        $this->postJson("/api/notifications/{$n->id}/read")->assertOk();

        $this->assertNotNull(Notification::find($n->id)->read_at);
    }

    public function test_mark_all_read(): void
    {
        /** @var User $user */
        $user = auth()->user();
        foreach (range(1, 3) as $i) {
            Notification::create([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'type' => 'AllocationCreated',
                'title' => "N{$i}",
                'body' => '',
                'payload' => null,
                'created_at' => now(),
            ]);
        }

        $this->postJson('/api/notifications/read-all')->assertOk();

        $this->assertSame(0, Notification::where('user_id', $user->id)->whereNull('read_at')->count());
    }

    public function test_unread_filter(): void
    {
        /** @var User $user */
        $user = auth()->user();
        Notification::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'type' => 'AllocationCreated',
            'title' => 'Read',
            'body' => '',
            'payload' => null,
            'read_at' => now(),
            'created_at' => now(),
        ]);
        Notification::create([
            'id' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'type' => 'AllocationCreated',
            'title' => 'Unread',
            'body' => '',
            'payload' => null,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/notifications?unread=1')->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('Unread', $response->json('data.0.title'));
    }
}
