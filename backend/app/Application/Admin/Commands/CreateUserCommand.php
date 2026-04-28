<?php

declare(strict_types=1);

namespace App\Application\Admin\Commands;

final class CreateUserCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $role,
    ) {}
}
