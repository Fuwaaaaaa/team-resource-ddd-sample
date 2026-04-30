<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Events;

/**
 * Authorization bounded context.
 *
 * 一旦無効化された user を admin が再有効化したときに発火する。
 * disabled_at が null に戻り login が再び可能になる。
 */
final class UserEnabled
{
    public function __construct(
        private readonly int $userId,
        private readonly int $enabledByUserId,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public function enabledByUserId(): int
    {
        return $this->enabledByUserId;
    }
}
