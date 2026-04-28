<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

final class PasswordResetResultDto
{
    public function __construct(
        public readonly UserDto $user,
        public readonly string $generatedPassword,
        public readonly bool $requiresRelogin,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'generatedPassword' => $this->generatedPassword,
            'requiresRelogin' => $this->requiresRelogin,
        ];
    }
}
