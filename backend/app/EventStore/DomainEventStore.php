<?php

declare(strict_types=1);

namespace App\EventStore;

use App\Infrastructure\Persistence\Eloquent\Models\DomainEventModel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ドメインイベントを append-only ストリームとして永続化するストア。
 *
 * stream_version は per-stream 単調増加。同時書込が衝突した場合は
 * (stream_type, stream_id, stream_version) の unique 制約で後者が失敗 (楽観ロック)。
 *
 * 並行追記時の挙動:
 *   - 楽観ロック方式 (unique 制約 + retry on duplicate)
 *   - 以前は `lockForUpdate()->max(...)` を使っていたが、PostgreSQL は
 *     aggregate (`max`) と `FOR UPDATE` の組み合わせを SQLSTATE 0A000 で拒否する
 *     ("FOR UPDATE is not allowed with aggregate functions")。
 *   - 楽観ロックなら DB ポータブル + aggregate 制約と無関係 + 単純。
 *
 * Application 層がさらにトランザクションで包む場合、本クラスの retry は内側の
 * SAVEPOINT で行われる (PostgreSQL の nested transaction セマンティクス)。
 */
final class DomainEventStore
{
    /** ストリーム単一行への並行 INSERT が衝突した場合のリトライ上限 */
    private const MAX_APPEND_RETRIES = 5;

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
        $attempt = 0;
        while (true) {
            try {
                return DB::transaction(function () use ($streamType, $streamId, $eventType, $eventData, $metadata) {
                    // FOR UPDATE は使わず、現在の max を読む。並行追記者がいた場合は
                    // 後段の INSERT が unique 制約で 23505 (PostgreSQL) /
                    // SQLSTATE 23000 (SQLite) を投げ、外側で retry する。
                    $nextVersion = (int) (DomainEventModel::query()
                        ->where('stream_type', $streamType)
                        ->where('stream_id', $streamId)
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
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e) && $attempt < self::MAX_APPEND_RETRIES) {
                    $attempt++;
                    continue;
                }
                throw $e;
            }
        }
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

    /**
     * Postgres は SQLSTATE 23505 (unique_violation)、SQLite は 23000 + "UNIQUE constraint failed"。
     * Laravel の QueryException は SQLSTATE を getCode() に詰める一方、
     * 本来は driver 例外を unwrap する方が確実なため両方確認する。
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        if ($sqlState === '23505' || $sqlState === '23000') {
            return true;
        }
        $message = $e->getMessage();
        return str_contains($message, 'duplicate key value violates unique constraint')
            || str_contains($message, 'UNIQUE constraint failed');
    }
}
