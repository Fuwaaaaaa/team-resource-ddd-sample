<?php

declare(strict_types=1);

namespace Tests\Feature\EventStore;

use App\EventStore\DomainEventStore;
use App\Infrastructure\Persistence\Eloquent\Models\AllocationModel;
use App\Infrastructure\Persistence\Eloquent\Models\DomainEventModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Infrastructure\Persistence\Eloquent\Models\ProjectModel;
use App\Infrastructure\Persistence\Eloquent\Models\SkillModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DomainEventStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_append_and_read_back_stream(): void
    {
        $store = app(DomainEventStore::class);
        $streamId = (string) Str::uuid7();

        $store->append('project', $streamId, 'ProjectActivated', [], ['correlation_id' => 'c1', 'user_id' => null]);
        $store->append('project', $streamId, 'ProjectCompleted', ['note' => 'done'], ['correlation_id' => 'c2', 'user_id' => 42]);

        $events = $store->streamOf('project', $streamId);
        $this->assertCount(2, $events);
        $this->assertSame(1, $events[0]->stream_version);
        $this->assertSame('ProjectActivated', $events[0]->event_type);
        $this->assertSame(2, $events[1]->stream_version);
        $this->assertSame('ProjectCompleted', $events[1]->event_type);
        $this->assertSame(['note' => 'done'], $events[1]->event_data);
        $this->assertSame(42, $events[1]->metadata['user_id']);
    }

    public function test_versions_are_per_stream_not_global(): void
    {
        $store = app(DomainEventStore::class);
        $s1 = (string) Str::uuid7();
        $s2 = (string) Str::uuid7();

        $store->append('project', $s1, 'ProjectActivated', [], []);
        $store->append('project', $s2, 'ProjectActivated', [], []);
        $store->append('project', $s1, 'ProjectCompleted', [], []);

        $this->assertSame(1, $store->streamOf('project', $s2)[0]->stream_version);
        $this->assertSame([1, 2], array_map(fn ($e) => $e->stream_version, $store->streamOf('project', $s1)));
    }

    public function test_domain_event_persisted_on_allocation_creation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $skill = SkillModel::factory()->create();

        // X-Request-Id ヘッダで correlation_id を固定 (AssignRequestId ミドルウェアがそのまま使う)
        $response = $this->withHeader('X-Request-Id', 'req-abc-123')->postJson('/api/allocations', [
            'memberId' => $member->id,
            'projectId' => $project->id,
            'skillId' => $skill->id,
            'allocationPercentage' => 40,
            'periodStart' => '2026-05-01',
            'periodEnd' => '2026-05-31',
        ])->assertCreated();

        $allocationId = $response->json('data.id');

        $row = DomainEventModel::query()
            ->where('stream_type', 'allocation')
            ->where('stream_id', $allocationId)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('AllocationCreated', $row->event_type);
        $this->assertSame(1, $row->stream_version);
        // correlation_id が Context から入っていること
        $this->assertSame('req-abc-123', $row->metadata['correlation_id']);
        $this->assertSame($user->id, $row->metadata['user_id']);
        // ペイロードに必要な情報が揃うこと
        $this->assertSame($member->id, $row->event_data['memberId']);
        $this->assertSame(40, $row->event_data['percentage']);
    }

    public function test_allocation_revoke_is_version_2_on_same_stream(): void
    {
        $this->actingAs(User::factory()->create());

        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $skill = SkillModel::factory()->create();

        $response = $this->postJson('/api/allocations', [
            'memberId' => $member->id,
            'projectId' => $project->id,
            'skillId' => $skill->id,
            'allocationPercentage' => 30,
            'periodStart' => '2026-05-01',
            'periodEnd' => '2026-05-31',
        ])->assertCreated();
        $allocationId = $response->json('data.id');

        $this->postJson("/api/allocations/{$allocationId}/revoke", [])->assertOk();

        $events = app(DomainEventStore::class)->streamOf('allocation', $allocationId);
        $this->assertCount(2, $events);
        $this->assertSame(['AllocationCreated', 'AllocationRevoked'], [
            $events[0]->event_type,
            $events[1]->event_type,
        ]);
        $this->assertSame([1, 2], [$events[0]->stream_version, $events[1]->stream_version]);
    }

    public function test_events_stream_command_outputs_table(): void
    {
        $store = app(DomainEventStore::class);
        $streamId = (string) Str::uuid7();
        $store->append('project', $streamId, 'ProjectActivated', [], []);

        $this->artisan('events:stream', ['stream_type' => 'project', 'stream_id' => $streamId])
            ->assertSuccessful()
            ->expectsOutputToContain('ProjectActivated');
    }

    public function test_events_stream_command_warns_on_empty(): void
    {
        $this->artisan('events:stream', [
            'stream_type' => 'project',
            'stream_id' => '00000000-0000-0000-0000-000000000000',
        ])->assertSuccessful()
            ->expectsOutputToContain('No events for stream');
    }

    public function test_allocation_model_is_not_required_for_event_store_read(): void
    {
        // allocation テーブルから直接消しても event stream は独立して残る (append-only)
        $this->actingAs(User::factory()->create());

        $member = MemberModel::factory()->create();
        $project = ProjectModel::factory()->create(['status' => 'active']);
        $skill = SkillModel::factory()->create();

        $response = $this->postJson('/api/allocations', [
            'memberId' => $member->id,
            'projectId' => $project->id,
            'skillId' => $skill->id,
            'allocationPercentage' => 30,
            'periodStart' => '2026-05-01',
            'periodEnd' => '2026-05-31',
        ])->assertCreated();
        $allocationId = $response->json('data.id');

        AllocationModel::where('id', $allocationId)->delete();

        $events = app(DomainEventStore::class)->streamOf('allocation', $allocationId);
        $this->assertCount(1, $events);
        $this->assertSame('AllocationCreated', $events[0]->event_type);
    }
}
