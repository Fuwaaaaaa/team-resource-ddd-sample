<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Events;

/**
 * Authorization bounded context.
 *
 * 退職者対応 / アカウント停止のため admin が user を無効化したときに発火する。
 * 無効化された user は login が即時拒否され、 全 Sanctum token / session が
 * 失効する。 row 自体は audit_logs の user_id 参照保全のため残す。
 */
final class UserDisabled
{
    public function __construct(
        private readonly int $userId,
        private readonly int $disabledByUserId,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public function disabledByUserId(): int
    {
        return $this->disabledByUserId;
    }
}
