<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Models\Notification;
use App\Models\User;
use App\Notifications\NotificationContentBuilder;
use Illuminate\Support\Str;

/**
 * 主要なドメインイベントを in-app 通知として admin / manager の各ユーザーへ
 * ファンアウトする。Email / Slack チャネルは別リスナーが処理する。
 *
 * コンテンツ生成は NotificationContentBuilder で一元化し、各チャネルは
 * title/body/severity を共有する。
 */
final class CreateNotification
{
    public function handle(object $event): void
    {
        $content = NotificationContentBuilder::fromEvent($event);
        if ($content === null) {
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
                'type' => $content->type,
                'title' => $content->title,
                'body' => $content->body,
                'payload' => $content->payload,
                'read_at' => null,
                'created_at' => $now,
            ]);
        }
    }
}
