<?php

declare(strict_types=1);

namespace App\EventStore;

use App\Infrastructure\Persistence\Eloquent\Models\DomainEventModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ドメインイベントを append-only ストリームとして永続化するストア。
 *
 * stream_version は per-stream 単調増加。同時書込が衝突した場合は unique 制約で
 * 例外を投げる (楽観ロック)。Application 層ではトランザクションで包むことで
 * 書込順序 = DB への登録順序 が保証される。
 */
final class DomainEventStore
{
    /**
     * @param  array<string, mixed>  $eventData
     * @param  array<string, mixed>  $metadata  correlation_id / causation_id / user_id 等
     */
    public function append(
        string $streamType,
        string $streamId,
        string $eventType,
        array $eventData,
        array $metadata,
    ): DomainEventModel {
        return DB::transaction(function () use ($streamType, $streamId, $eventType, $eventData, $metadata) {
            $nextVersion = (int) (DomainEventModel::query()
                ->where('stream_type', $streamType)
                ->where('stream_id', $streamId)
                ->lockForUpdate()
                ->max('stream_version') ?? 0) + 1;

            return DomainEventModel::create([
                'id' => (string) Str::uuid7(),
                'stream_type' => $streamType,
                'stream_id' => $streamId,
                'stream_version' => $nextVersion,
                'event_type' => $eventType,
                'event_data' => $eventData,
                'metadata' => $metadata,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * 特定ストリームのイベントを version 昇順で取得する。
     *
     * @return DomainEventModel[]
     */
    public function streamOf(string $streamType, string $streamId): array
    {
        return DomainEventModel::query()
            ->where('stream_type', $streamType)
            ->where('stream_id', $streamId)
            ->orderBy('stream_version')
            ->get()
            ->all();
    }
}
