<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

final class ChangeUserRoleCommand
{
    public function __construct(
        public readonly int $targetUserId,
        public readonly int $actorUserId,
        public readonly string $newRole,
        public readonly string $reason,
        public readonly string $expectedUpdatedAt,
    ) {}
}
