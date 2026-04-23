<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest\Events;

use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;

final class AllocationChangeRequestRejected
{
    public function __construct(
        private readonly AllocationChangeRequestId $requestId,
        private readonly int $decidedBy,
        private readonly ?string $decisionNote,
    ) {}

    public function requestId(): AllocationChangeRequestId
    {
        return $this->requestId;
    }

    public function decidedBy(): int
    {
        return $this->decidedBy;
    }

    public function decisionNote(): ?string
    {
        return $this->decisionNote;
    }
}
