<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Allocation\Events\AllocationCreated;
use App\Domain\Allocation\Events\AllocationRevoked;
use App\Domain\Availability\Events\AbsenceRegistered;
use App\Domain\Project\Events\ProjectCanceled;
use App\Domain\Project\Events\ProjectCompleted;
use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * 主要なドメインイベントを in-app 通知として admin / manager の
 * 各ユーザーへファンアウトする。
 *
 * 読了状態はユーザー単位で管理するため、各受信者につき 1 行作成する。
 * メールや Slack 等への拡張は将来ここから分岐する。
 */
final class CreateNotification
{
    public function handle(object $event): void
    {
        $record = $this->buildRecord($event);
        if ($record === null) {
            return;
        }

        // admin / manager 全員に配信。viewer は通知対象外。
        $recipients = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get();

        $now = now();
        foreach ($recipients as $user) {
            Notification::create([
                'id' => (string) Str::uuid7(),
                'user_id' => $user->id,
                'type' => $record['type'],
                'title' => $record['title'],
                'body' => $record['body'],
                'payload' => $record['payload'],
                'read_at' => null,
                'created_at' => $now,
            ]);
        }
    }

    /** @return array{type:string,title:string,body:string,payload:array<string,mixed>}|null */
    private function buildRecord(object $event): ?array
    {
        return match (true) {
            $event instanceof AllocationCreated => [
                'type' => 'AllocationCreated',
                'title' => '新しいアロケーション',
                'body' => sprintf(
                    'Member %s がプロジェクト %s に %d%% で割当てられました。',
                    $this->shortId($event->memberId()->toString()),
                    $this->shortId($event->projectId()->toString()),
                    $event->percentage()->value(),
                ),
                'payload' => [
                    'allocationId' => $event->allocationId()->toString(),
                    'memberId' => $event->memberId()->toString(),
                    'projectId' => $event->projectId()->toString(),
                ],
            ],
            $event instanceof AllocationRevoked => [
                'type' => 'AllocationRevoked',
                'title' => 'アロケーションが解除されました',
                'body' => sprintf('Allocation %s', $this->shortId($event->allocationId()->toString())),
                'payload' => [
                    'allocationId' => $event->allocationId()->toString(),
                ],
            ],
            $event instanceof AbsenceRegistered => [
                'type' => 'AbsenceRegistered',
                'title' => '不在登録',
                'body' => sprintf(
                    'Member %s が %s 〜 %s を不在 (%s) として登録しました。',
                    $this->shortId($event->memberId()->toString()),
                    $event->period()->startDate()->format('Y-m-d'),
                    $event->period()->endDate()->format('Y-m-d'),
                    $event->type()->value,
                ),
                'payload' => [
                    'absenceId' => $event->absenceId()->toString(),
                    'memberId' => $event->memberId()->toString(),
                ],
            ],
            $event instanceof ProjectCompleted => [
                'type' => 'ProjectCompleted',
                'title' => 'プロジェクト完了',
                'body' => sprintf('Project %s を完了しました', $this->shortId($event->projectId()->toString())),
                'payload' => ['projectId' => $event->projectId()->toString()],
            ],
            $event instanceof ProjectCanceled => [
                'type' => 'ProjectCanceled',
                'title' => 'プロジェクト中止',
                'body' => sprintf('Project %s が中止されました', $this->shortId($event->projectId()->toString())),
                'payload' => ['projectId' => $event->projectId()->toString()],
            ],
            default => null,
        };
    }

    private function shortId(string $uuid): string
    {
        return substr($uuid, 0, 8);
    }
}
