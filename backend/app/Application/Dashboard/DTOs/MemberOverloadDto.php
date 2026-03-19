<?php

declare(strict_types=1);

namespace App\Application\Dashboard\DTOs;

final class MemberOverloadDto
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $memberName,
        public readonly float $standardHoursPerDay,
        public readonly int $totalAllocatedPercentage,
        public readonly float $allocatedHoursPerDay,
        public readonly float $overloadHours,
        public readonly bool $isOverloaded,
    ) {
    }
}
