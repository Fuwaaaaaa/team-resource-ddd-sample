<?php

declare(strict_types=1);

namespace App\Application\Member\Commands;

final class UpdateMemberCommand
{
    public function __construct(
        public readonly string $memberId,
        public readonly ?string $name = null,
        public readonly ?float $standardWorkingHours = null,
    ) {
    }
}
