<?php

declare(strict_types=1);

namespace App\EventStore;

/**
 * ドメインイベントのストリーム識別 + ペイロード DTO。
 *
 * ドメイン層の POPO イベント (Laravel 非依存) から
 * stream_type / stream_id / event_type / event_data を抽出した形を保持する。
 * 生成は {@see EventSchemaRegistry::describe()} が単一の情報源として担当し、
 * domain_events ストア (PersistDomainEvent 経由) と audit_logs (RecordAuditLog 経由)
 * の両方が同じ descriptor を参照する。
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
