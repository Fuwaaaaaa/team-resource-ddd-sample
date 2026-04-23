<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\UserRole;
use App\Mail\NotificationMail;
use App\Models\User;
use App\Notifications\NotificationContentBuilder;
use App\Notifications\NotificationSeverity;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Mail\Mailer;

/**
 * ドメインイベントを email で admin/manager に配信する。
 *
 * 設定:
 *   - config('notifications.email.enabled') == true
 *   - 各イベントの severity が min_severity 以上
 */
final class SendEmailNotification
{
    public function __construct(
        private Config $config,
        private Mailer $mailer,
    ) {}

    public function handle(object $event): void
    {
        if (! $this->config->get('notifications.email.enabled')) {
            return;
        }

        $content = NotificationContentBuilder::fromEvent($event);
        if ($content === null) {
            return;
        }

        $threshold = NotificationSeverity::fromString(
            (string) $this->config->get('notifications.email.min_severity', 'warning'),
        );
        if (! $content->severity->atLeast($threshold)) {
            return;
        }

        $recipients = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->whereNotNull('email')
            ->get();

        foreach ($recipients as $user) {
            $this->mailer->to($user->email)->send(new NotificationMail($content));
        }
    }
}
