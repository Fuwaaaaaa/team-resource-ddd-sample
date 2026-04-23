<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\EventStore\DomainEventStore;
use Illuminate\Console\Command;

/**
 * 指定ストリームのイベントを時系列で表示する。
 *
 *   php artisan events:stream project 01980000-1234-...
 *   php artisan events:stream allocation 01980000-5678-... --json
 */
class ShowEventStreamCommand extends Command
{
    protected $signature = 'events:stream {stream_type} {stream_id} {--json : JSON 形式で出力}';

    protected $description = '指定ストリームのドメインイベントを version 昇順で表示';

    public function handle(DomainEventStore $store): int
    {
        $streamType = (string) $this->argument('stream_type');
        $streamId = (string) $this->argument('stream_id');

        $events = $store->streamOf($streamType, $streamId);

        if ($events === []) {
            $this->warn("No events for stream {$streamType}/{$streamId}.");

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $payload = array_map(fn ($e) => [
                'version' => $e->stream_version,
                'type' => $e->event_type,
                'data' => $e->event_data,
                'metadata' => $e->metadata,
                'occurred_at' => $e->occurred_at?->toIso8601String(),
            ], $events);
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $rows = array_map(fn ($e) => [
            'v' => $e->stream_version,
            'type' => $e->event_type,
            'occurred_at' => $e->occurred_at?->toIso8601String() ?? '',
            'correlation_id' => substr((string) ($e->metadata['correlation_id'] ?? ''), 0, 8),
            'user_id' => (string) ($e->metadata['user_id'] ?? ''),
            'data' => json_encode($e->event_data, JSON_UNESCAPED_UNICODE),
        ], $events);
        $this->table(['v', 'type', 'occurred_at', 'corr_id', 'user', 'data'], $rows);

        return self::SUCCESS;
    }
}
