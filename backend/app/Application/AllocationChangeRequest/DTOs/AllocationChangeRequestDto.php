<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\DTOs;

use App\Domain\AllocationChangeRequest\AllocationChangeRequest;

final class AllocationChangeRequestDto
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $payload,
        public readonly int $requestedBy,
        public readonly ?string $reason,
        public readonly string $status,
        public readonly string $requestedAt,
        public readonly ?int $decidedBy,
        public readonly ?string $decidedAt,
        public readonly ?string $decisionNote,
        public readonly ?string $resultingAllocationId,
    ) {}

    public static function fromDomain(AllocationChangeRequest $r): self
    {
        return new self(
            id: $r->id()->toString(),
            type: $r->type()->value,
            payload: $r->payload()->toArray(),
            requestedBy: $r->requestedBy(),
            reason: $r->reason(),
            status: $r->status()->value,
            requestedAt: $r->requestedAt()->format(DATE_ATOM),
            decidedBy: $r->decidedBy(),
            decidedAt: $r->decidedAt()?->format(DATE_ATOM),
            decisionNote: $r->decisionNote(),
            resultingAllocationId: $r->resultingAllocationId(),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'payload' => $this->payload,
            'requestedBy' => $this->requestedBy,
            'reason' => $this->reason,
            'status' => $this->status,
            'requestedAt' => $this->requestedAt,
            'decidedBy' => $this->decidedBy,
            'decidedAt' => $this->decidedAt,
            'decisionNote' => $this->decisionNote,
            'resultingAllocationId' => $this->resultingAllocationId,
        ];
    }
}
