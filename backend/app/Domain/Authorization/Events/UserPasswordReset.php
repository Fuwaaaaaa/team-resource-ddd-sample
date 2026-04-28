<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Events;

/**
 * Authorization bounded context.
 * NOTE: User is an authentication identity, NOT a domain aggregate.
 *
 * Emitted when an admin resets another user's password (or their own) via
 * Application\Admin\Commands\ResetUserPasswordHandler. Payload is intentionally
 * empty — only the act of resetting is recorded, never the password itself
 * (security T6).
 */
final class UserPasswordReset
{
    public function __construct(
        private readonly int $userId,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }
}
