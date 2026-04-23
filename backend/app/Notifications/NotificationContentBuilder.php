<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestApproved;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestRejected;
use App\Domain\AllocationChangeRequest\Events\AllocationChangeRequestSubmitted;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;

/**
 * ドメインイベントから通知コンテンツ (配信チャネル非依存) を構築する。
 *
 * in-app / email / slack の各チャネルリスナーがここを呼び、
 * 共通の title/body/severity/payload を得たうえで自前の配信を行う。
 */
final class NotificationContentBuilder
{
    public static function fromEvent(object $event): ?NotificationContent
    {
        return match (true) {
            $event instanceof AllocationCreated => new NotificationContent(
                type: 'AllocationCreated',
                title: '新しいアロケーション',
                body: sprintf(
                    'Member %s がプロジェクト %s に %d%% で割当てられました。',
                    self::shortId($event->memberId()->toString()),
                    self::shortId($event->projectId()->toString()),
                    $event->percentage()->value(),
                ),
                payload: [
                    'allocationId' => $event->allocationId()->toString(),
                    'memberId' => $event->memberId()->toString(),
                    'projectId' => $event->projectId()->toString(),
                ],
                severity: NotificationSeverity::Info,
            ),
            $event instanceof AllocationRevoked => new NotificationContent(
                type: 'AllocationRevoked',
                title: 'アロケーションが解除されました',
                body: sprintf('Allocation %s', self::shortId($event->allocationId()->toString())),
                payload: ['allocationId' => $event->allocationId()->toString()],
                severity: NotificationSeverity::Info,
            ),
            $event instanceof AbsenceRegistered => new NotificationContent(
                type: 'AbsenceRegistered',
                title: '不在登録',
                body: sprintf(
                    'Member %s が %s 〜 %s を不在 (%s) として登録しました。',
                    self::shortId($event->memberId()->toString()),
                    $event->period()->startDate()->format('Y-m-d'),
                    $event->period()->endDate()->format('Y-m-d'),
                    $event->type()->value,
                ),
                payload: [
                    'absenceId' => $event->absenceId()->toString(),
                    'memberId' => $event->memberId()->toString(),
                ],
                severity: NotificationSeverity::Info,
            ),
            $event instanceof ProjectCompleted => new NotificationContent(
                type: 'ProjectCompleted',
                title: 'プロジェクト完了',
                body: sprintf('Project %s を完了しました', self::shortId($event->projectId()->toString())),
                payload: ['projectId' => $event->projectId()->toString()],
                severity: NotificationSeverity::Info,
            ),
            $event instanceof ProjectCanceled => new NotificationContent(
                type: 'ProjectCanceled',
                title: 'プロジェクト中止',
                body: sprintf('Project %s が中止されました', self::shortId($event->projectId()->toString())),
                payload: ['projectId' => $event->projectId()->toString()],
                severity: NotificationSeverity::Warning,
            ),
            $event instanceof AllocationChangeRequestSubmitted => new NotificationContent(
                type: 'AllocationChangeRequestSubmitted',
                title: 'アロケーション変更申請',
                body: sprintf(
                    '%s 種別の変更申請が提出されました (ID: %s)',
                    $event->type()->value,
                    self::shortId($event->requestId()->toString()),
                ),
                payload: [
                    'requestId' => $event->requestId()->toString(),
                    'type' => $event->type()->value,
                    'requestedBy' => $event->requestedBy(),
                ],
                severity: NotificationSeverity::Warning,
            ),
            $event instanceof AllocationChangeRequestApproved => new NotificationContent(
                type: 'AllocationChangeRequestApproved',
                title: '変更申請が承認されました',
                body: sprintf('Request %s が承認されました', self::shortId($event->requestId()->toString())),
                payload: [
                    'requestId' => $event->requestId()->toString(),
                    'decidedBy' => $event->decidedBy(),
                    'resultingAllocationId' => $event->resultingAllocationId(),
                ],
                severity: NotificationSeverity::Info,
            ),
            $event instanceof AllocationChangeRequestRejected => new NotificationContent(
                type: 'AllocationChangeRequestRejected',
                title: '変更申請が却下されました',
                body: sprintf('Request %s が却下されました', self::shortId($event->requestId()->toString())),
                payload: [
                    'requestId' => $event->requestId()->toString(),
                    'decidedBy' => $event->decidedBy(),
                    'note' => $event->decisionNote(),
                ],
                severity: NotificationSeverity::Warning,
            ),
            default => null,
        };
    }

    private static function shortId(string $uuid): string
    {
        return substr($uuid, 0, 8);
    }
}
