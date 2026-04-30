<?php

declare(strict_types=1);

namespace App\EventStore;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestApproved;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestRejected;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestSubmitted;
use App\Domain\Authorization\Events\UserCreated;
use App\Domain\Authorization\Events\UserDisabled;
use App\Domain\Authorization\Events\UserEnabled;
use App\Domain\Authorization\Events\UserPasswordReset;
use App\Domain\Authorization\Events\UserRoleChanged;
use App\Domain\Authorization\UserAggregateId;
use App\Domain\Availability\Events\AbsenceCanceled;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Member\Events\MemberCreated;
use App\Domain\Member\Events\MemberSkillUpdated;
use App\Domain\Project\Events\ProjectActivated;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;
use App\Domain\Project\Events\ProjectRequirementChanged;
use App\Listeners\PersistDomainEvent;
use App\Listeners\RecordAuditLog;

/**
 * すべてのドメインイベントを {@see EventDescriptor} (streamType / streamId / eventType / eventData)
 * に変換する単一のスキーマ表。
 *
 * 利用経路:
 *   - {@see EventDescriptorResolver}（→ {@see PersistDomainEvent} 経由で domain_events ストアに append）
 *   - {@see RecordAuditLog}（audit_logs テーブルに記録。フィールド名を
 *     aggregate_type / aggregate_id / event_type / payload にマップする）
 *
 * 新しいドメインイベントを追加するときは、ここに 1 ケースを足すだけで両経路の記録が揃う。
 *
 * 戻り値 null = スキーマ未登録 (両経路ともスキップ)。
 */
final class EventSchemaRegistry
{
    public static function describe(object $event): ?EventDescriptor
    {
        return match (true) {
            $event instanceof AllocationCreated => new EventDescriptor(
                streamType: 'allocation',
                streamId: $event->allocationId()->toString(),
                eventType: 'AllocationCreated',
                eventData: [
                    'memberId' => $event->memberId()->toString(),
                    'projectId' => $event->projectId()->toString(),
                    'skillId' => $event->skillId()->toString(),
                    'percentage' => $event->percentage()->value(),
                ],
            ),
            $event instanceof AllocationRevoked => new EventDescriptor(
                streamType: 'allocation',
                streamId: $event->allocationId()->toString(),
                eventType: 'AllocationRevoked',
                eventData: [],
            ),
            $event instanceof MemberCreated => new EventDescriptor(
                streamType: 'member',
                streamId: $event->memberId()->toString(),
                eventType: 'MemberCreated',
                eventData: [],
            ),
            $event instanceof MemberSkillUpdated => new EventDescriptor(
                streamType: 'member',
                streamId: $event->memberId()->toString(),
                eventType: 'MemberSkillUpdated',
                eventData: [
                    'skillId' => $event->skillId()->toString(),
                    'proficiency' => $event->proficiency()->level(),
                ],
            ),
            $event instanceof ProjectRequirementChanged => new EventDescriptor(
                streamType: 'project',
                streamId: $event->projectId()->toString(),
                eventType: 'ProjectRequirementChanged',
                eventData: [
                    'skillId' => $event->skillId()->toString(),
                ],
            ),
            $event instanceof ProjectActivated => new EventDescriptor(
                streamType: 'project',
                streamId: $event->projectId()->toString(),
                eventType: 'ProjectActivated',
                eventData: [],
            ),
            $event instanceof ProjectCompleted => new EventDescriptor(
                streamType: 'project',
                streamId: $event->projectId()->toString(),
                eventType: 'ProjectCompleted',
                eventData: [],
            ),
            $event instanceof ProjectCanceled => new EventDescriptor(
                streamType: 'project',
                streamId: $event->projectId()->toString(),
                eventType: 'ProjectCanceled',
                eventData: [],
            ),
            $event instanceof AbsenceRegistered => new EventDescriptor(
                streamType: 'absence',
                streamId: $event->absenceId()->toString(),
                eventType: 'AbsenceRegistered',
                eventData: [
                    'memberId' => $event->memberId()->toString(),
                    'startDate' => $event->period()->startDate()->format('Y-m-d'),
                    'endDate' => $event->period()->endDate()->format('Y-m-d'),
                    'type' => $event->type()->value,
                ],
            ),
            $event instanceof AbsenceCanceled => new EventDescriptor(
                streamType: 'absence',
                streamId: $event->absenceId()->toString(),
                eventType: 'AbsenceCanceled',
                eventData: [
                    'memberId' => $event->memberId()->toString(),
                ],
            ),
            $event instanceof AllocationChangeRequestSubmitted => new EventDescriptor(
                streamType: 'allocation_change_request',
                streamId: $event->requestId()->toString(),
                eventType: 'AllocationChangeRequestSubmitted',
                eventData: [
                    'type' => $event->type()->value,
                    'requestedBy' => $event->requestedBy(),
                ],
            ),
            $event instanceof AllocationChangeRequestApproved => new EventDescriptor(
                streamType: 'allocation_change_request',
                streamId: $event->requestId()->toString(),
                eventType: 'AllocationChangeRequestApproved',
                eventData: [
                    'decidedBy' => $event->decidedBy(),
                    'resultingAllocationId' => $event->resultingAllocationId(),
                ],
            ),
            $event instanceof AllocationChangeRequestRejected => new EventDescriptor(
                streamType: 'allocation_change_request',
                streamId: $event->requestId()->toString(),
                eventType: 'AllocationChangeRequestRejected',
                eventData: [
                    'decidedBy' => $event->decidedBy(),
                    'note' => $event->decisionNote(),
                ],
            ),
            $event instanceof UserCreated => new EventDescriptor(
                streamType: 'user',
                streamId: UserAggregateId::fromUserId($event->userId()),
                eventType: 'UserCreated',
                eventData: [
                    'userId' => $event->userId(),
                    'email' => $event->email(),
                    'role' => $event->role()->value,
                ],
            ),
            $event instanceof UserRoleChanged => new EventDescriptor(
                streamType: 'user',
                streamId: UserAggregateId::fromUserId($event->userId()),
                eventType: 'UserRoleChanged',
                eventData: [
                    'userId' => $event->userId(),
                    'from' => $event->from()->value,
                    'to' => $event->to()->value,
                    'reason' => $event->reason(),
                ],
            ),
            $event instanceof UserPasswordReset => new EventDescriptor(
                streamType: 'user',
                streamId: UserAggregateId::fromUserId($event->userId()),
                eventType: 'UserPasswordReset',
                eventData: [
                    'userId' => $event->userId(),
                ],
            ),
            $event instanceof UserDisabled => new EventDescriptor(
                streamType: 'user',
                streamId: UserAggregateId::fromUserId($event->userId()),
                eventType: 'UserDisabled',
                eventData: [
                    'userId' => $event->userId(),
                    'disabledByUserId' => $event->disabledByUserId(),
                ],
            ),
            $event instanceof UserEnabled => new EventDescriptor(
                streamType: 'user',
                streamId: UserAggregateId::fromUserId($event->userId()),
                eventType: 'UserEnabled',
                eventData: [
                    'userId' => $event->userId(),
                    'enabledByUserId' => $event->enabledByUserId(),
                ],
            ),
            default => null,
        };
    }
}
