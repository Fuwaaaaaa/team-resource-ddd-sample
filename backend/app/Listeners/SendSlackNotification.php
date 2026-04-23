<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Notifications\NotificationContentBuilder;
use App\Notifications\NotificationSeverity;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ドメインイベントを Slack incoming webhook に配信する。
 *
 * 設定:
 *   - config('notifications.slack.webhook_url') が空でない
 *   - 各イベントの severity が min_severity 以上
 *
 * webhook 側の失敗はログのみ (throw しない) — ドメイン処理を止めないため。
 */
final class SendSlackNotification
{
    public function __construct(
        private Config $config,
        private Http $http,
    ) {}

    public function handle(object $event): void
    {
        $url = (string) $this->config->get('notifications.slack.webhook_url', '');
        if ($url === '') {
            return;
        }

        $content = NotificationContentBuilder::fromEvent($event);
        if ($content === null) {
            return;
        }

        $threshold = NotificationSeverity::fromString(
            (string) $this->config->get('notifications.slack.min_severity', 'warning'),
        );
        if (! $content->severity->atLeast($threshold)) {
            return;
        }

        $color = match ($content->severity) {
            NotificationSeverity::Info => '#3b82f6',
            NotificationSeverity::Warning => '#f59e0b',
            NotificationSeverity::Critical => '#dc2626',
        };

        $payload = [
            'text' => sprintf('[%s] %s', $content->severity->value, $content->title),
            'attachments' => [[
                'color' => $color,
                'title' => $content->title,
                'text' => $content->body,
                'footer' => 'Team Resource · '.$content->type,
                'ts' => time(),
            ]],
        ];

        try {
            $this->http->post($url, $payload)->throw();
        } catch (Throwable $e) {
            Log::warning('Slack notification dispatch failed', [
                'error' => $e->getMessage(),
                'type' => $content->type,
            ]);
        }
    }
}
