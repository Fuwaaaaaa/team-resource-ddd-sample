<?php

declare(strict_types=1);

namespace App\EventStore;

/**
 * ドメインイベント → EventDescriptor の変換。{@see EventSchemaRegistry} に薄く委譲する。
 *
 * 既存呼出 (PersistDomainEvent) との互換のため class はそのまま残しているが、
 * スキーマの単一情報源は {@see EventSchemaRegistry::describe()}。新イベント追加は
 * registry 側に 1 ケース足すこと。
 */
final class EventDescriptorResolver
{
    public function resolve(object $event): ?EventDescriptor
    {
        return EventSchemaRegistry::describe($event);
    }
}
