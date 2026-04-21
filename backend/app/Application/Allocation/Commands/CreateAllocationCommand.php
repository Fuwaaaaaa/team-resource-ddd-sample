<?php

declare(strict_types=1);

namespace App\Application\Allocation\Commands;

final class CreateAllocationCommand
{
    public function __construct(
        public readonly string $memberId,
        public readonly string $projectId,
        public readonly string $skillId,
        public readonly int $allocationPercentage,
        public readonly string $periodStart,
        public readonly string $periodEnd,
    ) {
    }
}
