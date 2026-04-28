<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use Illuminate\Support\Str;

/**
 * audit_logs.aggregate_id / domain_events.stream_id は uuid 列だが、
 * User は authentication identity でありDomainAggregate ではないため、
 * その primary key は bigint (= 既存方針)。
 *
 * このギャップを埋めるため、user_id (int) から決定的 uuid (v5) を生成する。
 * 同じ user_id は常に同じ uuid に解決されるため、aggregate_id でクエリできる。
 *
 * Namespace は 'url' を使い (= AppServiceProvider に登録済の uuid5 macro)、
 * name は 'user:{id}' で固定。
 */
final class UserAggregateId
{
    public static function fromUserId(int $userId): string
    {
        return (string) Str::uuid5(Str::uuid5Namespace('url'), 'user:'.$userId);
    }
}
