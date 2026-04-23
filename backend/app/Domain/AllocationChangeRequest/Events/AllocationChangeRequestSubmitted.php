<?php

declare(strict_types=1);

namespace App\Domain\AllocationChangeRequest\Events;

use App\Domain\AllocationChangeRequest\AllocationChangeRequestId;
use App\Domain\AllocationChangeRequest\ChangeRequestType;

final class AllocationChangeRequestSubmitted
{
    public function __construct(
        private readonly AllocationChangeRequestId $requestId,
        private readonly ChangeRequestType $type,
        private readonly int $requestedBy,
    ) {}

    public function requestId(): AllocationChangeRequestId
    {
        return $this->requestId;
    }

    public function type(): ChangeRequestType
    {
        return $this->type;
    }

    public function requestedBy(): int
    {
        return $this->requestedBy;
    }
}
