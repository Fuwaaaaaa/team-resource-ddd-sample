<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Commands;

final class SubmitAllocationChangeRequestCommand
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $type,
        public readonly array $payload,
        public readonly int $requestedBy,
        public readonly ?string $reason,
    ) {}
}
