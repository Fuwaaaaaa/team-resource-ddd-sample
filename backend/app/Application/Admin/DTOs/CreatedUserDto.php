<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

final class CreatedUserDto
{
    public function __construct(
        public readonly UserDto $user,
        public readonly string $generatedPassword,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'user' => $this->user->toArray(),
            'generatedPassword' => $this->generatedPassword,
        ];
    }
}
