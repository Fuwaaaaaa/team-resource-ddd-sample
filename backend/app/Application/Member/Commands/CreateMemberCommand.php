<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

final class CreateMemberCommand
{
    public function __construct(
        public readonly string $name,
        public readonly float $standardWorkingHours,
    ) {}
}
