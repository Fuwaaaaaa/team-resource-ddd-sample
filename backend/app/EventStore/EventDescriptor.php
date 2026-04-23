<?php

declare(strict_types=1);

namespace App\EventStore;

/**
 * ドメインイベントのストリーム識別 + ペイロードを 1 箇所で解決する。
 *
 * NotificationContentBuilder と同じパターンで、ドメイン層の POPO イベント
 * (Laravel 非依存) から stream_type / stream_id / event_type / event_data を抽出する。
 *
 * 戻り値 null → イベントストア対象外 (既存 audit_logs にしか記録されない)。
 */
final class EventDescriptor
{
    public function __construct(
        public readonly string $streamType,
        public readonly string $streamId,
        public readonly string $eventType,
        /** @var array<string, mixed> */
        public readonly array $eventData,
    ) {}
}
