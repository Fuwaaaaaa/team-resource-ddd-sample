<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Events;

use App\Domain\Authorization\UserRole;

/**
 * Authorization bounded context.
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 *
 * Emitted when an admin changes another user's role via Application\Admin\
 * Commands\ChangeUserRoleHandler. Payload includes the human-supplied reason
 * for audit traceability. The reason is required (validated upstream).
 */
final class UserRoleChanged
{
    public function __construct(
        private readonly int $userId,
        private readonly UserRole $from,
        private readonly UserRole $to,
        private readonly string $reason,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public function from(): UserRole
    {
        return $this->from;
    }

    public function to(): UserRole
    {
        return $this->to;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
