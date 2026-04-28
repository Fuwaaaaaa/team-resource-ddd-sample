<?php

declare(strict_types=1);

namespace App\Application\Admin\DTOs;

use App\Models\User;

final class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            role: $user->role->value,
            createdAt: $user->created_at?->toIso8601String() ?? '',
            updatedAt: $user->updated_at?->toIso8601String() ?? '',
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
