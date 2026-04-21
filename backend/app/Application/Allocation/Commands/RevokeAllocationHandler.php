<?php

declare(strict_types=1);

namespace App\Application\Allocation\Commands;

use App\Application\Allocation\DTOs\AllocationDto;
use App\Domain\Allocation\AllocationId;
use App\Domain\Allocation\ResourceAllocationRepositoryInterface;
use App\Infrastructure\Events\DomainEventDispatcher;
use RuntimeException;

final class RevokeAllocationHandler
{
    public function __construct(
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(string $allocationId): AllocationDto
    {
        $allocation = $this->allocationRepository->findById(new AllocationId($allocationId));
        if ($allocation === null) {
            throw new RuntimeException('Allocation not found: '.$allocationId);
        }

        $allocation->revoke();
        $this->allocationRepository->save($allocation);

        $this->eventDispatcher->dispatchAll($allocation->pullDomainEvents());

        return AllocationDto::fromDomain($allocation);
    }
}
