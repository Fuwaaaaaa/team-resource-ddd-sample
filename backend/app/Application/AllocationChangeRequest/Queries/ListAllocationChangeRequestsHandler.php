<?php

declare(strict_types=1);

namespace App\Application\AllocationChangeRequest\Queries;

use App\Application\AllocationChangeRequest\DTOs\AllocationChangeRequestDto;
use App\Domain\AllocationChangeRequest\AllocationChangeRequestRepositoryInterface;
use App\Domain\AllocationChangeRequest\ChangeRequestStatus;

final class ListAllocationChangeRequestsHandler
{
    public function __construct(
        private AllocationChangeRequestRepositoryInterface $repository,
    ) {}

    /** @return AllocationChangeRequestDto[] */
    public function handle(ListAllocationChangeRequestsQuery $query): array
    {
        $status = $query->status !== null ? ChangeRequestStatus::fromString($query->status) : null;
        $rows = $this->repository->findList($status, $query->requestedBy);

        return array_map(fn ($r) => AllocationChangeRequestDto::fromDomain($r), $rows);
    }
}
