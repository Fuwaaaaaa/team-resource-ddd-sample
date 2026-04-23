<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Queries;

final class ListAllocationChangeRequestsQuery
{
    public function __construct(
        public readonly ?string $status = null, // 'pending'|'approved'|'rejected'|null (all)
        public readonly ?int $requestedBy = null,
    ) {}
}
