<?php

declare(strict_types=1);

namespace App\Listeners;

use App\EventStore\DomainEventStore;
use App\EventStore\EventDescriptorResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;

/**
 * 全ドメインイベントを domain_events ストアに append する。
 *
 * メタデータ:
 *   - correlation_id: Context の request_id (AssignRequestId ミドルウェアが付ける)
 *   - user_id: 認証済みユーザーの ID (未認証なら null)
 *
 * 失敗時は例外を上に投げる (audit_logs と異なり、イベントストアはシステム的真実の源泉のため
 * 取りこぼしを隠蔽しない)。
 */
final class PersistDomainEvent
{
    public function __construct(
        private DomainEventStore $store,
        private EventDescriptorResolver $resolver,
    ) {}

    public function handle(object $event): void
    {
        $descriptor = $this->resolver->resolve($event);
        if ($descriptor === null) {
            return;
        }

        $metadata = [
            'correlation_id' => Context::get('request_id'),
            'user_id' => Auth::id(),
        ];

        $this->store->append(
            streamType: $descriptor->streamType,
            streamId: $descriptor->streamId,
            eventType: $descriptor->eventType,
            eventData: $descriptor->eventData,
            metadata: $metadata,
        );
    }
}
