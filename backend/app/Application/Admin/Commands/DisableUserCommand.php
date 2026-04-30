<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

final class DisableUserCommand
{
    public function __construct(
        public readonly int $targetUserId,
        public readonly int $actorUserId,
    ) {}
}
