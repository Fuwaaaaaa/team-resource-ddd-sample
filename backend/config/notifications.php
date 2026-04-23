<?php

/**
 * 通知チャネル設定。
 *
 * - in-app は常に有効 (既存のダッシュボードベルアイコン)。
 * - email: MAIL_MAILER が log 以外を指していれば実送、log ならストリームに吐くだけ。
 * - slack: webhook URL が空なら無効。POST は非同期ジョブ化していないので fail は throw。
 *
 * min_severity は 'info' | 'warning' | 'critical'。指定より低いイベントはスキップ。
 */
return [
    'email' => [
        'enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', false),
        'min_severity' => env('NOTIFICATIONS_EMAIL_MIN_SEVERITY', 'warning'),
    ],
    'slack' => [
        'webhook_url' => env('NOTIFICATIONS_SLACK_WEBHOOK', ''),
        'min_severity' => env('NOTIFICATIONS_SLACK_MIN_SEVERITY', 'warning'),
    ],
];
