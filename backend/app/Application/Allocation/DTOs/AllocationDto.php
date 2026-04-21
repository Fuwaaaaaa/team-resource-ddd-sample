<?php

declare(strict_types=1);

namespace App\Application\Allocation\DTOs;

use App\Domain\Allocation\ResourceAllocation;

final class AllocationDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $memberId,
        public readonly string $projectId,
        public readonly string $skillId,
        public readonly int $allocationPercentage,
        public readonly string $periodStart,
        public readonly string $periodEnd,
        public readonly string $status,
    ) {
    }

    public static function fromDomain(ResourceAllocation $a): self
    {
        return new self(
            id: $a->id()->toString(),
            memberId: $a->memberId()->toString(),
            projectId: $a->projectId()->toString(),
            skillId: $a->skillId()->toString(),
            allocationPercentage: $a->percentage()->value(),
            periodStart: $a->period()->startDate()->format('Y-m-d'),
            periodEnd: $a->period()->endDate()->format('Y-m-d'),
            status: $a->status()->toString(),
        );
    }
}
