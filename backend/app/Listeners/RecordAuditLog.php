<?php

declare(strict_types=1);

namespace App\Listeners;

use App\EventStore\EventSchemaRegistry;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * 全ドメインイベントを audit_logs に永続化する集約リスナー。
 * Event::listen() で AppServiceProvider から登録する。
 *
 * イベント → 永続化形 (event_type / aggregate_type / aggregate_id / payload) の対応は
 * {@see EventSchemaRegistry::describe()} を SoT として共有しており、本クラスは
 * EventDescriptor のフィールド名を audit_logs カラム名にマップして書き込むだけ。
 */
final class RecordAuditLog
{
    public function handle(object $event): void
    {
        $descriptor = EventSchemaRegistry::describe($event);
        if ($descriptor === null) {
            return; // unknown event type - ignore
        }

        AuditLog::create([
            'id' => (string) Str::uuid7(),
            'user_id' => Auth::id(),
            'event_type' => $descriptor->eventType,
            'aggregate_type' => $descriptor->streamType,
            'aggregate_id' => $descriptor->streamId,
            'payload' => $descriptor->eventData,
            'created_at' => now(),
        ]);
    }
}
