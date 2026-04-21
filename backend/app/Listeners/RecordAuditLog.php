<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\Availability\Events\AbsenceCanceled;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Member\Events\MemberCreated;
use App\Domain\Member\Events\MemberSkillUpdated;
use App\Domain\Project\Events\ProjectRequirementChanged;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 全ドメインイベントを audit_logs に永続化する集約リスナー。
 * Event::listen() で bootstrap/providers.php の AppServiceProvider から登録する。
 */
final class RecordAuditLog
{
    public function handle(object $event): void
    {
        $record = $this->buildRecord($event);
        if ($record === null) {
            return; // unknown event type - ignore
        }

        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => Auth::id(),
            'event_type' => $record['event_type'],
            'aggregate_type' => $record['aggregate_type'],
            'aggregate_id' => $record['aggregate_id'],
            'payload' => $record['payload'],
            'created_at' => now(),
        ]);
    }

    /** @return array{event_type:string, aggregate_type:string, aggregate_id:string, payload:array<string, mixed>}|null */
    private function buildRecord(object $event): ?array
    {
        return match (true) {
            $event instanceof AllocationCreated => [
                'event_type' => 'AllocationCreated',
                'aggregate_type' => 'allocation',
                'aggregate_id' => $event->allocationId()->toString(),
                'payload' => [
                    'memberId' => $event->memberId()->toString(),
                    'projectId' => $event->projectId()->toString(),
                    'skillId' => $event->skillId()->toString(),
                    'percentage' => $event->percentage()->value(),
                ],
            ],
            $event instanceof AllocationRevoked => [
                'event_type' => 'AllocationRevoked',
                'aggregate_type' => 'allocation',
                'aggregate_id' => $event->allocationId()->toString(),
                'payload' => [],
            ],
            $event instanceof MemberCreated => [
                'event_type' => 'MemberCreated',
                'aggregate_type' => 'member',
                'aggregate_id' => $event->memberId()->toString(),
                'payload' => [],
            ],
            $event instanceof MemberSkillUpdated => [
                'event_type' => 'MemberSkillUpdated',
                'aggregate_type' => 'member',
                'aggregate_id' => $event->memberId()->toString(),
                'payload' => [
                    'skillId' => $event->skillId()->toString(),
                    'proficiency' => $event->proficiency()->level(),
                ],
            ],
            $event instanceof ProjectRequirementChanged => [
                'event_type' => 'ProjectRequirementChanged',
                'aggregate_type' => 'project',
                'aggregate_id' => $event->projectId()->toString(),
                'payload' => [
                    'skillId' => $event->skillId()->toString(),
                ],
            ],
            $event instanceof AbsenceRegistered => [
                'event_type' => 'AbsenceRegistered',
                'aggregate_type' => 'absence',
                'aggregate_id' => $event->absenceId()->toString(),
                'payload' => [
                    'memberId' => $event->memberId()->toString(),
                    'startDate' => $event->period()->startDate()->format('Y-m-d'),
                    'endDate' => $event->period()->endDate()->format('Y-m-d'),
                    'type' => $event->type()->value,
                ],
            ],
            $event instanceof AbsenceCanceled => [
                'event_type' => 'AbsenceCanceled',
                'aggregate_type' => 'absence',
                'aggregate_id' => $event->absenceId()->toString(),
                'payload' => [
                    'memberId' => $event->memberId()->toString(),
                ],
            ],
            default => null,
        };
    }
}
