<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Events;

use App\Domain\Authorization\UserRole;

/**
 * Authorization bounded context.
 *
 * NOTE: User is an authentication identity, NOT a domain aggregate (unlike
 * Member/Project/Allocation). Authorization is an Application Concern, and
 * this event is emitted from Application\Admin\Commands\CreateUserHandler.
 *
 * Payload schema MUST NOT contain the password (security T6).
 */
final class UserCreated
{
    public function __construct(
        private readonly int $userId,
        private readonly string $email,
        private readonly UserRole $role,
    ) {}

    public function userId(): int
    {
        return $this->userId;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function role(): UserRole
    {
        return $this->role;
    }
}
