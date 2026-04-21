<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Infrastructure\Persistence\Eloquent\Models\AbsenceModel;
use App\Infrastructure\Persistence\Eloquent\Models\MemberModel;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AbsenceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_store_creates_absence_with_uuid_v7(): void
    {
        $member = MemberModel::factory()->create();

        $response = $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-05',
            'type' => 'vacation',
            'note' => '連休',
        ])->assertCreated();

        $id = $response->json('data.id');
        $this->assertNotEmpty($id);
        $this->assertSame('7', $id[14], 'UUIDv7 のバージョンニブル');
        $this->assertSame('vacation', $response->json('data.type'));
        $this->assertSame(5, $response->json('data.daysInclusive'));
        $this->assertFalse($response->json('data.canceled'));
    }

    public function test_store_emits_audit_log_event(): void
    {
        $member = MemberModel::factory()->create();

        $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'sick',
        ])->assertCreated();

        $this->assertSame(1, AuditLog::where('event_type', 'AbsenceRegistered')->count());
    }

    public function test_cancel_marks_absence_and_emits_canceled_event(): void
    {
        $member = MemberModel::factory()->create();
        $created = $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-05',
            'type' => 'vacation',
        ])->assertCreated();
        $absenceId = $created->json('data.id');

        $this->postJson("/api/absences/{$absenceId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.canceled', true);

        $this->assertSame(1, AuditLog::where('event_type', 'AbsenceCanceled')->count());
    }

    public function test_by_member_returns_only_that_members_absences(): void
    {
        $m1 = MemberModel::factory()->create();
        $m2 = MemberModel::factory()->create();

        $this->postJson('/api/absences', [
            'memberId' => $m1->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'vacation',
        ])->assertCreated();
        $this->postJson('/api/absences', [
            'memberId' => $m2->id,
            'startDate' => '2026-05-02',
            'endDate' => '2026-05-02',
            'type' => 'sick',
        ])->assertCreated();

        $response = $this->getJson("/api/members/{$m1->id}/absences")->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($m1->id, $data[0]['memberId']);
    }

    public function test_store_rejects_end_before_start(): void
    {
        $member = MemberModel::factory()->create();

        $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-05',
            'endDate' => '2026-05-01',
            'type' => 'vacation',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endDate']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $member = MemberModel::factory()->create();

        $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'bogus',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_unknown_member(): void
    {
        $this->postJson('/api/absences', [
            'memberId' => '01912345-0000-7000-8000-000000000000',
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'vacation',
        ])->assertStatus(500); // InvalidArgumentException surfaces as 500
    }

    public function test_viewer_cannot_create_absence(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer);

        $member = MemberModel::factory()->create();

        $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'vacation',
        ])->assertForbidden();
    }

    public function test_member_delete_cascades_absences(): void
    {
        $member = MemberModel::factory()->create();
        $this->postJson('/api/absences', [
            'memberId' => $member->id,
            'startDate' => '2026-05-01',
            'endDate' => '2026-05-01',
            'type' => 'vacation',
        ])->assertCreated();

        $this->assertSame(1, AbsenceModel::where('member_id', $member->id)->count());

        $this->deleteJson("/api/members/{$member->id}")->assertNoContent();

        $this->assertSame(0, AbsenceModel::where('member_id', $member->id)->count());
    }
}
