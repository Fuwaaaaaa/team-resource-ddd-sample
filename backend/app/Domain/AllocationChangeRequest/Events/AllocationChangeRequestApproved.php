<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest\Events;

use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;

final class AllocationChangeRequestApproved
{
    public function __construct(
        private readonly AllocationChangeRequestId $requestId,
        private readonly int $decidedBy,
        private readonly ?string $resultingAllocationId,
    ) {}

    public function requestId(): AllocationChangeRequestId
    {
        return $this->requestId;
    }

    public function decidedBy(): int
    {
        return $this->decidedBy;
    }

    public function resultingAllocationId(): ?string
    {
        return $this->resultingAllocationId;
    }
}
